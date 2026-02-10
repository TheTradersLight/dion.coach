<?php
declare(strict_types=1);

use App\Database\CampRepository;
use App\Database\EvaluatorRepository;
use App\Database\SkillRepository;
use App\Database\Database;

$campId = (int)($_GET['camp_id'] ?? 0);
$camp = $campId > 0 ? CampRepository::findById($campId) : null;
$userId = (int)($_SESSION['user_id'] ?? 0);
$isOwner = $camp && (int)$camp['created_by'] === $userId;
$isEvaluator = $camp && !$isOwner && EvaluatorRepository::isEvaluator($campId, $userId);

if (!$camp || (!$isOwner && !$isEvaluator)) {
    header('Location: /camps');
    exit;
}

// Gather data for results rendering
$players = Database::fetchAll(
    "SELECT cp.id AS camp_player_id, p.first_name, p.last_name, p.jersey_number, p.position, cp.status
     FROM camp_players cp
     JOIN players p ON cp.player_id = p.id
     WHERE cp.camp_id = ? AND cp.status = 'active'
     ORDER BY p.last_name ASC, p.first_name ASC",
    [$campId]
);

$groups = Database::fetchAll(
    "SELECT id, name, color FROM camp_groups WHERE camp_id = ? ORDER BY sort_order ASC",
    [$campId]
);

$groupPlayers = Database::fetchAll(
    "SELECT gp.camp_player_id, gp.group_id
     FROM group_players gp
     JOIN camp_groups cg ON gp.group_id = cg.id
     WHERE cg.camp_id = ?",
    [$campId]
);
$gpMap = [];
foreach ($groupPlayers as $gp) {
    $gpMap[(int)$gp['camp_player_id']] = (int)$gp['group_id'];
}
foreach ($players as &$p) {
    $p['group_id'] = $gpMap[(int)$p['camp_player_id']] ?? null;
}
unset($p);

$skillCategories = SkillRepository::getCategoriesWithSkills($campId);

// Get aggregated results
$results = Database::fetchAll(
    "SELECT e.camp_player_id, e.skill_id,
            AVG(e.rating) AS avg_rating,
            COUNT(DISTINCT e.evaluated_by) AS evaluator_count
     FROM evaluations e
     JOIN camp_sessions cs ON e.session_id = cs.id
     WHERE cs.camp_id = ?
     GROUP BY e.camp_player_id, e.skill_id",
    [$campId]
);

// Build result map: "cpId-skillId" => avg_rating
$resultMap = [];
foreach ($results as $r) {
    $key = $r['camp_player_id'] . '-' . $r['skill_id'];
    $resultMap[$key] = (float)$r['avg_rating'];
}

// Build category skills helper
function getCatSkills(array $cat): array {
    $skills = $cat['skills'] ?? [];
    foreach (($cat['children'] ?? []) as $sub) {
        $skills = array_merge($skills, $sub['skills'] ?? []);
    }
    return $skills;
}

// Build player scores
$playerScores = [];
foreach ($players as $player) {
    $catAverages = [];
    $totalSum = 0;
    $totalCount = 0;

    foreach ($skillCategories as $cat) {
        $catSkills = getCatSkills($cat);
        $catSum = 0;
        $catCount = 0;
        foreach ($catSkills as $skill) {
            $key = $player['camp_player_id'] . '-' . $skill['id'];
            if (isset($resultMap[$key])) {
                $catSum += $resultMap[$key];
                $catCount++;
                $totalSum += $resultMap[$key];
                $totalCount++;
            }
        }
        $catAverages[] = $catCount > 0 ? $catSum / $catCount : null;
    }

    $playerScores[] = [
        'player' => $player,
        'catAverages' => $catAverages,
        'totalAvg' => $totalCount > 0 ? $totalSum / $totalCount : null,
    ];
}

// Sort by total average descending
usort($playerScores, function ($a, $b) {
    if ($a['totalAvg'] === null && $b['totalAvg'] === null) return 0;
    if ($a['totalAvg'] === null) return 1;
    if ($b['totalAvg'] === null) return -1;
    return $b['totalAvg'] <=> $a['totalAvg'];
});

$rMin = (int)$camp['rating_min'];
$rMax = (int)$camp['rating_max'];
$hasResults = !empty($results);
?>
<!doctype html>
<html lang="fr">
<?php include __DIR__ . '/../../includes/head.php'; ?>
<link href="/css/camps.css" rel="stylesheet">
<body class="d-flex flex-column min-vh-100">
<?php include __DIR__ . '/../../includes/navbar.php'; ?>

<main class="container py-4 flex-grow-1" style="max-width: 1200px;">

    <div class="mb-2">
        <a href="/camps/<?= $campId ?>" class="text-muted small text-decoration-none">&larr; <?= htmlspecialchars($camp['name']) ?></a>
    </div>

    <div class="d-flex align-items-center justify-content-between mb-4">
        <h1 class="display-6 fw-semibold mb-0">Résultats</h1>
        <?php if (!empty($groups)): ?>
        <select id="groupFilter" class="form-select form-select-sm" style="width: auto;" onchange="filterGroup(this.value)">
            <option value="">Tous les joueurs</option>
            <?php foreach ($groups as $g): ?>
                <option value="<?= $g['id'] ?>"><?= htmlspecialchars($g['name']) ?></option>
            <?php endforeach; ?>
        </select>
        <?php endif; ?>
    </div>

    <?php if (!$hasResults): ?>
        <div class="alert alert-dark border-secondary text-light">
            Aucune évaluation enregistrée. Les résultats apparaîtront une fois les évaluations synchronisées.
        </div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table table-dark table-sm table-hover" id="resultsTable">
                <thead>
                <tr>
                    <th>Rang</th>
                    <th>#</th>
                    <th>Joueur</th>
                    <th>Pos.</th>
                    <?php foreach ($skillCategories as $cat): ?>
                        <th class="text-center cat-header"><?= htmlspecialchars($cat['name']) ?></th>
                    <?php endforeach; ?>
                    <th class="text-center">Moy.</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($playerScores as $idx => $ps):
                    $p = $ps['player'];
                ?>
                <tr data-group="<?= $p['group_id'] ?? '' ?>">
                    <td class="text-center fw-bold"><?= $ps['totalAvg'] !== null ? $idx + 1 : '-' ?></td>
                    <td><?= htmlspecialchars($p['jersey_number'] ?? '?') ?></td>
                    <td><?= htmlspecialchars($p['first_name'] . ' ' . $p['last_name']) ?></td>
                    <td><small class="text-muted"><?= htmlspecialchars($p['position'] ?? '') ?></small></td>
                    <?php foreach ($ps['catAverages'] as $avg): ?>
                        <?php if ($avg !== null):
                            $pct = $rMax > $rMin ? (($avg - $rMin) / ($rMax - $rMin)) * 100 : 0;
                            $color = $pct >= 70 ? 'text-success' : ($pct >= 40 ? 'text-warning' : 'text-danger');
                        ?>
                            <td class="text-center <?= $color ?>"><?= number_format($avg, 1) ?></td>
                        <?php else: ?>
                            <td class="text-center text-muted">-</td>
                        <?php endif; ?>
                    <?php endforeach; ?>
                    <?php if ($ps['totalAvg'] !== null): ?>
                        <td class="text-center fw-bold text-gold"><?= number_format($ps['totalAvg'], 2) ?></td>
                    <?php else: ?>
                        <td class="text-center text-muted">-</td>
                    <?php endif; ?>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>

</main>

<?php include __DIR__ . '/../../includes/footer.php'; ?>

<script>
function filterGroup(groupId) {
    const rows = document.querySelectorAll('#resultsTable tbody tr');
    rows.forEach(row => {
        if (!groupId || row.dataset.group === groupId) {
            row.style.display = '';
        } else {
            row.style.display = 'none';
        }
    });
}
</script>
</body>
</html>
