<?php
declare(strict_types=1);

use App\Database\CampRepository;
use App\Database\PlayerRepository;

$campId = (int)($_GET['camp_id'] ?? 0);
$camp = $campId > 0 ? CampRepository::findById($campId) : null;

if (!$camp || (int)$camp['created_by'] !== (int)$_SESSION['user_id']) {
    header('Location: /camps');
    exit;
}

// POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        $firstName = trim($_POST['first_name'] ?? '');
        $lastName = trim($_POST['last_name'] ?? '');
        if ($firstName !== '' && $lastName !== '') {
            $playerId = PlayerRepository::create([
                'first_name'    => $firstName,
                'last_name'     => $lastName,
                'jersey_number' => trim($_POST['jersey_number'] ?? ''),
                'position'      => trim($_POST['position'] ?? ''),
                'date_of_birth' => $_POST['date_of_birth'] ?? '',
            ]);
            PlayerRepository::addToCamp($playerId, $campId);
        }
    } elseif ($action === 'cut') {
        $cpId = (int)($_POST['camp_player_id'] ?? 0);
        $cp = PlayerRepository::getCampPlayer($cpId);
        if ($cp && (int)$cp['camp_id'] === $campId) {
            PlayerRepository::cutFromCamp($cpId);
        }
    } elseif ($action === 'reinstate') {
        $cpId = (int)($_POST['camp_player_id'] ?? 0);
        $cp = PlayerRepository::getCampPlayer($cpId);
        if ($cp && (int)$cp['camp_id'] === $campId) {
            PlayerRepository::reinstateInCamp($cpId);
        }
    } elseif ($action === 'remove') {
        $cpId = (int)($_POST['camp_player_id'] ?? 0);
        $cp = PlayerRepository::getCampPlayer($cpId);
        if ($cp && (int)$cp['camp_id'] === $campId) {
            PlayerRepository::removeFromCamp($cpId);
        }
    } elseif ($action === 'generate') {
        $count = min(60, max(1, (int)($_POST['count'] ?? 30)));
        PlayerRepository::generateTestPlayers($campId, $count);
    }

    header("Location: /camps/{$campId}/players");
    exit;
}

$players = PlayerRepository::findByCamp($campId);
$activePlayers = array_filter($players, fn($p) => $p['status'] === 'active');
$cutPlayers = array_filter($players, fn($p) => $p['status'] === 'cut');

$positions = ['Attaquant','Défenseur','Gardien','Centre','Ailier gauche','Ailier droit'];
?>
<!doctype html>
<html lang="fr">
<?php include __DIR__ . '/../../includes/head.php'; ?>
<body class="d-flex flex-column min-vh-100">
<?php include __DIR__ . '/../../includes/navbar.php'; ?>

<main class="container py-4 flex-grow-1" style="max-width: 1100px;">

    <div class="mb-2">
        <a href="/camps/<?= $campId ?>" class="text-muted small text-decoration-none">← <?= htmlspecialchars($camp['name']) ?></a>
    </div>

    <div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-2">
        <h1 class="display-6 fw-semibold mb-0">Joueurs <span class="text-muted fs-5">(<?= count($activePlayers) ?> actifs)</span></h1>
        <div class="d-flex gap-2">
            <button class="btn btn-outline-light btn-sm" data-bs-toggle="collapse" data-bs-target="#addForm">+ Ajouter</button>
            <form method="POST" class="d-inline">
                <input type="hidden" name="action" value="generate">
                <input type="hidden" name="count" value="30">
                <button type="submit" class="btn btn-outline-warning btn-sm"
                        onclick="return confirm('Générer 30 joueurs fictifs ?')">Générer 30 joueurs test</button>
            </form>
        </div>
    </div>

    <!-- Formulaire d'ajout (collapsible) -->
    <div class="collapse mb-4" id="addForm">
        <form method="POST" class="card bg-dark border-secondary">
            <div class="card-body">
                <input type="hidden" name="action" value="add">
                <div class="row g-2 align-items-end">
                    <div class="col-md-3">
                        <label class="form-label small">Prénom *</label>
                        <input type="text" name="first_name" class="form-control form-control-sm bg-dark text-white border-secondary" required>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small">Nom *</label>
                        <input type="text" name="last_name" class="form-control form-control-sm bg-dark text-white border-secondary" required>
                    </div>
                    <div class="col-md-1">
                        <label class="form-label small">#</label>
                        <input type="text" name="jersey_number" class="form-control form-control-sm bg-dark text-white border-secondary" maxlength="10">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label small">Position</label>
                        <select name="position" class="form-select form-select-sm bg-dark text-white border-secondary">
                            <option value="">-</option>
                            <?php foreach ($positions as $pos): ?>
                                <option value="<?= htmlspecialchars($pos) ?>"><?= htmlspecialchars($pos) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label small">Date naiss.</label>
                        <input type="date" name="date_of_birth" class="form-control form-control-sm bg-dark text-white border-secondary">
                    </div>
                    <div class="col-md-1">
                        <button type="submit" class="btn btn-primary btn-sm w-100">OK</button>
                    </div>
                </div>
            </div>
        </form>
    </div>

    <!-- Table des joueurs actifs -->
    <?php if (empty($activePlayers)): ?>
        <div class="alert alert-dark border-secondary text-light">
            Aucun joueur actif. Ajoutez-en ou générez des cas d'essai.
        </div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table table-dark table-hover table-sm align-middle">
                <thead>
                <tr class="text-secondary border-secondary">
                    <th>#</th>
                    <th>Nom</th>
                    <th>Position</th>
                    <th>Groupe</th>
                    <th class="text-end">Actions</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($activePlayers as $p): ?>
                    <tr class="border-secondary">
                        <td class="fw-bold"><?= htmlspecialchars($p['jersey_number'] ?? '-') ?></td>
                        <td><?= htmlspecialchars($p['last_name'] . ', ' . $p['first_name']) ?></td>
                        <td class="text-muted small"><?= htmlspecialchars($p['position'] ?? '-') ?></td>
                        <td>
                            <?php if (!empty($p['group_name'])): ?>
                                <span class="badge" style="background:<?= htmlspecialchars($p['group_color'] ?? '#666') ?>"><?= htmlspecialchars($p['group_name']) ?></span>
                            <?php else: ?>
                                <span class="text-muted small">-</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-end">
                            <form method="POST" class="d-inline">
                                <input type="hidden" name="camp_player_id" value="<?= $p['camp_player_id'] ?>">
                                <button type="submit" name="action" value="cut" class="btn btn-outline-warning btn-sm"
                                        onclick="return confirm('Couper ce joueur ?')">Couper</button>
                                <button type="submit" name="action" value="remove" class="btn btn-outline-danger btn-sm"
                                        onclick="return confirm('Retirer définitivement ce joueur du camp ?')">Retirer</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>

    <!-- Joueurs coupés -->
    <?php if (!empty($cutPlayers)): ?>
        <h5 class="text-muted mt-4 mb-3">Joueurs coupés (<?= count($cutPlayers) ?>)</h5>
        <div class="table-responsive">
            <table class="table table-dark table-sm align-middle" style="opacity: 0.7;">
                <tbody>
                <?php foreach ($cutPlayers as $p): ?>
                    <tr class="border-secondary">
                        <td class="fw-bold"><?= htmlspecialchars($p['jersey_number'] ?? '-') ?></td>
                        <td><?= htmlspecialchars($p['last_name'] . ', ' . $p['first_name']) ?></td>
                        <td class="text-muted small"><?= htmlspecialchars($p['position'] ?? '-') ?></td>
                        <td class="text-end">
                            <form method="POST" class="d-inline">
                                <input type="hidden" name="camp_player_id" value="<?= $p['camp_player_id'] ?>">
                                <button type="submit" name="action" value="reinstate" class="btn btn-outline-success btn-sm">Réintégrer</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>

</main>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
</body>
</html>
