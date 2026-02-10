<?php
declare(strict_types=1);

use App\Database\CampRepository;
use App\Database\SessionRepository;

$campId = (int)($_GET['camp_id'] ?? 0);
$camp = $campId > 0 ? CampRepository::findById($campId) : null;

if (!$camp || (int)$camp['created_by'] !== (int)$_SESSION['user_id']) {
    header('Location: /camps');
    exit;
}

// POST — Créer / Modifier / Supprimer
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'create') {
        SessionRepository::create([
            'camp_id'      => $campId,
            'name'         => trim($_POST['name'] ?? ''),
            'session_date' => $_POST['session_date'] ?? '',
            'status'       => 'planned',
        ]);
    } elseif ($action === 'update') {
        $sid = (int)($_POST['session_id'] ?? 0);
        $sess = SessionRepository::findById($sid);
        if ($sess && (int)$sess['camp_id'] === $campId) {
            SessionRepository::update($sid, [
                'name'         => trim($_POST['name'] ?? ''),
                'session_date' => $_POST['session_date'] ?? '',
                'status'       => $_POST['status'] ?? $sess['status'],
            ]);
        }
    } elseif ($action === 'delete') {
        $sid = (int)($_POST['session_id'] ?? 0);
        $sess = SessionRepository::findById($sid);
        if ($sess && (int)$sess['camp_id'] === $campId) {
            SessionRepository::delete($sid);
        }
    }

    header("Location: /camps/{$campId}/sessions");
    exit;
}

$sessions = SessionRepository::findByCamp($campId);

$statusLabels = [
    'planned'     => ['Planifiée', 'bg-secondary'],
    'in_progress' => ['En cours', 'bg-warning text-dark'],
    'completed'   => ['Terminée', 'bg-success'],
];
?>
<!doctype html>
<html lang="fr">
<?php include __DIR__ . '/../../includes/head.php'; ?>
<body class="d-flex flex-column min-vh-100">
<?php include __DIR__ . '/../../includes/navbar.php'; ?>

<main class="container py-4 flex-grow-1" style="max-width: 900px;">

    <div class="mb-2">
        <a href="/camps/<?= $campId ?>" class="text-muted small text-decoration-none">← <?= htmlspecialchars($camp['name']) ?></a>
    </div>

    <div class="d-flex align-items-center justify-content-between mb-4">
        <h1 class="display-6 fw-semibold mb-0">Séances</h1>
    </div>

    <!-- Formulaire d'ajout -->
    <form method="POST" class="card bg-dark border-secondary mb-4">
        <div class="card-body">
            <h6 class="text-muted mb-3">Ajouter une séance</h6>
            <input type="hidden" name="action" value="create">
            <div class="row g-2 align-items-end">
                <div class="col-md-5">
                    <label class="form-label small">Nom *</label>
                    <input type="text" name="name" class="form-control form-control-sm bg-dark text-white border-secondary"
                           placeholder="Ex: Séance 1 - Évaluation initiale" required>
                </div>
                <div class="col-md-3">
                    <label class="form-label small">Date</label>
                    <input type="date" name="session_date" class="form-control form-control-sm bg-dark text-white border-secondary">
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-primary btn-sm w-100">Ajouter</button>
                </div>
            </div>
        </div>
    </form>

    <!-- Liste des séances -->
    <?php if (empty($sessions)): ?>
        <div class="alert alert-dark border-secondary text-light">
            Aucune séance. Ajoutez-en une ci-dessus.
        </div>
    <?php else: ?>
        <?php foreach ($sessions as $sess): ?>
            <?php $sl = $statusLabels[$sess['status']] ?? ['?', 'bg-secondary']; ?>
            <div class="card bg-dark border-secondary mb-2">
                <div class="card-body py-2">
                    <form method="POST" class="row g-2 align-items-center">
                        <input type="hidden" name="action" value="update">
                        <input type="hidden" name="session_id" value="<?= $sess['id'] ?>">
                        <div class="col-md-1 text-center">
                            <span class="text-muted fw-bold">#<?= (int)$sess['session_order'] ?></span>
                        </div>
                        <div class="col-md-4">
                            <input type="text" name="name" class="form-control form-control-sm bg-dark text-white border-secondary"
                                   value="<?= htmlspecialchars($sess['name']) ?>" required>
                        </div>
                        <div class="col-md-2">
                            <input type="date" name="session_date" class="form-control form-control-sm bg-dark text-white border-secondary"
                                   value="<?= htmlspecialchars($sess['session_date'] ?? '') ?>">
                        </div>
                        <div class="col-md-2">
                            <select name="status" class="form-select form-select-sm bg-dark text-white border-secondary">
                                <option value="planned" <?= $sess['status'] === 'planned' ? 'selected' : '' ?>>Planifiée</option>
                                <option value="in_progress" <?= $sess['status'] === 'in_progress' ? 'selected' : '' ?>>En cours</option>
                                <option value="completed" <?= $sess['status'] === 'completed' ? 'selected' : '' ?>>Terminée</option>
                            </select>
                        </div>
                        <div class="col-md-3 text-end">
                            <button type="submit" class="btn btn-outline-light btn-sm">Sauver</button>
                            <button type="submit" name="action" value="delete" class="btn btn-outline-danger btn-sm"
                                    onclick="return confirm('Supprimer cette séance ?')">Suppr.</button>
                        </div>
                    </form>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>

</main>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
</body>
</html>
