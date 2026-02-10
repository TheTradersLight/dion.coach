<?php
declare(strict_types=1);

use App\Database\CampRepository;
use App\Database\GroupRepository;

$campId = (int)($_GET['camp_id'] ?? 0);
$camp = $campId > 0 ? CampRepository::findById($campId) : null;

if (!$camp || (int)$camp['created_by'] !== (int)$_SESSION['user_id']) {
    header('Location: /camps');
    exit;
}

// POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'create') {
        $name = trim($_POST['name'] ?? '');
        if ($name !== '') {
            GroupRepository::create([
                'camp_id' => $campId,
                'name'    => $name,
                'color'   => $_POST['color'] ?? '#6c757d',
            ]);
        }
    } elseif ($action === 'update') {
        $gid = (int)($_POST['group_id'] ?? 0);
        $group = GroupRepository::findById($gid);
        if ($group && (int)$group['camp_id'] === $campId) {
            GroupRepository::update($gid, [
                'name'  => trim($_POST['name'] ?? ''),
                'color' => $_POST['color'] ?? $group['color'],
            ]);
        }
    } elseif ($action === 'delete') {
        $gid = (int)($_POST['group_id'] ?? 0);
        $group = GroupRepository::findById($gid);
        if ($group && (int)$group['camp_id'] === $campId) {
            GroupRepository::delete($gid);
        }
    } elseif ($action === 'assign') {
        $gid = (int)($_POST['group_id'] ?? 0);
        $cpIds = $_POST['camp_player_ids'] ?? [];
        $group = GroupRepository::findById($gid);
        if ($group && (int)$group['camp_id'] === $campId) {
            foreach ($cpIds as $cpId) {
                GroupRepository::assignPlayer($gid, (int)$cpId);
            }
        }
    } elseif ($action === 'unassign') {
        $cpId = (int)($_POST['camp_player_id'] ?? 0);
        GroupRepository::removePlayer($cpId);
    }

    header("Location: /camps/{$campId}/groups");
    exit;
}

$groups = GroupRepository::findByCamp($campId);
$unassigned = GroupRepository::getUnassignedPlayers($campId);

// Load players per group
$groupPlayers = [];
foreach ($groups as $g) {
    $groupPlayers[$g['id']] = GroupRepository::getPlayersInGroup($g['id']);
}
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
        <h1 class="display-6 fw-semibold mb-0">Groupes</h1>
    </div>

    <!-- Créer un groupe -->
    <form method="POST" class="card bg-dark border-secondary mb-4">
        <div class="card-body">
            <h6 class="text-muted mb-3">Créer un groupe</h6>
            <input type="hidden" name="action" value="create">
            <div class="row g-2 align-items-end">
                <div class="col-md-5">
                    <input type="text" name="name" class="form-control form-control-sm bg-dark text-white border-secondary"
                           placeholder="Ex: Groupe A - 9h00" required>
                </div>
                <div class="col-md-2">
                    <label class="form-label small">Couleur</label>
                    <input type="color" name="color" class="form-control form-control-sm form-control-color bg-dark border-secondary" value="#3b82f6">
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-primary btn-sm w-100">Créer</button>
                </div>
            </div>
        </div>
    </form>

    <div class="row g-3">
        <!-- Groups -->
        <?php foreach ($groups as $g): ?>
            <?php $gPlayers = $groupPlayers[$g['id']] ?? []; ?>
            <div class="col-md-6">
                <div class="card bg-dark border-secondary h-100">
                    <div class="card-header border-secondary d-flex align-items-center justify-content-between">
                        <div class="d-flex align-items-center gap-2">
                            <span class="rounded-circle d-inline-block" style="width:12px;height:12px;background:<?= htmlspecialchars($g['color'] ?? '#666') ?>"></span>
                            <strong class="text-light"><?= htmlspecialchars($g['name']) ?></strong>
                            <span class="text-muted small">(<?= count($gPlayers) ?>)</span>
                        </div>
                        <div class="d-flex gap-1">
                            <form method="POST" class="d-flex gap-1">
                                <input type="hidden" name="action" value="update">
                                <input type="hidden" name="group_id" value="<?= $g['id'] ?>">
                                <input type="text" name="name" class="form-control form-control-sm bg-dark text-white border-secondary"
                                       value="<?= htmlspecialchars($g['name']) ?>" style="width:120px;">
                                <input type="color" name="color" class="form-control form-control-sm form-control-color bg-dark border-secondary"
                                       value="<?= htmlspecialchars($g['color'] ?? '#666') ?>" style="width:35px;padding:2px;">
                                <button type="submit" class="btn btn-outline-light btn-sm">OK</button>
                            </form>
                            <form method="POST" class="d-inline">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="group_id" value="<?= $g['id'] ?>">
                                <button type="submit" class="btn btn-outline-danger btn-sm"
                                        onclick="return confirm('Supprimer ce groupe ?')">X</button>
                            </form>
                        </div>
                    </div>
                    <div class="card-body py-2">
                        <?php if (empty($gPlayers)): ?>
                            <p class="text-muted small mb-0">Aucun joueur dans ce groupe.</p>
                        <?php else: ?>
                            <?php foreach ($gPlayers as $p): ?>
                                <div class="d-flex justify-content-between align-items-center border-bottom border-secondary py-1">
                                    <span class="small">#<?= htmlspecialchars($p['jersey_number'] ?? '') ?> <?= htmlspecialchars($p['first_name'] . ' ' . $p['last_name']) ?></span>
                                    <form method="POST" class="d-inline">
                                        <input type="hidden" name="action" value="unassign">
                                        <input type="hidden" name="camp_player_id" value="<?= $p['camp_player_id'] ?>">
                                        <button type="submit" class="btn btn-outline-secondary btn-sm py-0 px-1" title="Retirer du groupe">&times;</button>
                                    </form>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>

                        <!-- Assigner des joueurs non assignés -->
                        <?php if (!empty($unassigned)): ?>
                            <form method="POST" class="mt-2">
                                <input type="hidden" name="action" value="assign">
                                <input type="hidden" name="group_id" value="<?= $g['id'] ?>">
                                <div class="d-flex gap-1">
                                    <select name="camp_player_ids[]" class="form-select form-select-sm bg-dark text-white border-secondary" multiple style="height:60px;">
                                        <?php foreach ($unassigned as $u): ?>
                                            <option value="<?= $u['camp_player_id'] ?>">#<?= htmlspecialchars($u['jersey_number'] ?? '') ?> <?= htmlspecialchars($u['first_name'] . ' ' . $u['last_name']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <button type="submit" class="btn btn-outline-primary btn-sm align-self-end">Assigner</button>
                                </div>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <!-- Joueurs non assignés -->
    <?php if (!empty($unassigned)): ?>
        <div class="mt-4">
            <h6 class="text-muted">Joueurs non assignés (<?= count($unassigned) ?>)</h6>
            <div class="d-flex flex-wrap gap-1">
                <?php foreach ($unassigned as $u): ?>
                    <span class="badge bg-secondary">#<?= htmlspecialchars($u['jersey_number'] ?? '') ?> <?= htmlspecialchars($u['first_name'] . ' ' . $u['last_name']) ?></span>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>

</main>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
</body>
</html>
