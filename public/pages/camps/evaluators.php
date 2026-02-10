<?php
declare(strict_types=1);

use App\Database\CampRepository;
use App\Database\EvaluatorRepository;

$campId = (int)($_GET['camp_id'] ?? 0);
$camp = $campId > 0 ? CampRepository::findById($campId) : null;

if (!$camp || (int)$camp['created_by'] !== (int)$_SESSION['user_id']) {
    header('Location: /camps');
    exit;
}

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'invite') {
        $email = trim($_POST['email'] ?? '');
        if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL)) {
            EvaluatorRepository::invite($campId, strtolower($email));
            $message = 'Invitation envoyée à ' . htmlspecialchars($email);
        } else {
            $error = 'Adresse courriel invalide.';
        }
    } elseif ($action === 'revoke') {
        $evId = (int)($_POST['evaluator_id'] ?? 0);
        $ev = EvaluatorRepository::findById($evId);
        if ($ev && (int)$ev['camp_id'] === $campId) {
            EvaluatorRepository::revoke($evId);
            $message = 'Accès révoqué.';
        }
    } elseif ($action === 'activate') {
        $evId = (int)($_POST['evaluator_id'] ?? 0);
        $ev = EvaluatorRepository::findById($evId);
        if ($ev && (int)$ev['camp_id'] === $campId) {
            EvaluatorRepository::activate($evId);
            $message = 'Évaluateur activé.';
        }
    } elseif ($action === 'delete') {
        $evId = (int)($_POST['evaluator_id'] ?? 0);
        $ev = EvaluatorRepository::findById($evId);
        if ($ev && (int)$ev['camp_id'] === $campId) {
            EvaluatorRepository::delete($evId);
            $message = 'Évaluateur supprimé.';
        }
    }
}

$evaluators = EvaluatorRepository::findByCamp($campId);

$statusLabels = [
    'invited' => ['Invité', 'bg-warning text-dark'],
    'active'  => ['Actif', 'bg-success'],
    'revoked' => ['Révoqué', 'bg-danger'],
];
?>
<!doctype html>
<html lang="fr">
<?php include __DIR__ . '/../../includes/head.php'; ?>
<body class="d-flex flex-column min-vh-100">
<?php include __DIR__ . '/../../includes/navbar.php'; ?>

<main class="container py-4 flex-grow-1" style="max-width: 900px;">

    <div class="mb-2">
        <a href="/camps/<?= $campId ?>" class="text-muted small text-decoration-none">&larr; <?= htmlspecialchars($camp['name']) ?></a>
    </div>

    <h1 class="display-6 fw-semibold mb-4">Évaluateurs</h1>

    <?php if ($message): ?>
        <div class="alert alert-success alert-dismissible fade show bg-dark text-success border-success" role="alert">
            <?= $message ?>
            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="alert alert-danger alert-dismissible fade show bg-dark text-danger border-danger" role="alert">
            <?= $error ?>
            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- Invite form -->
    <form method="POST" class="card bg-dark border-secondary mb-4">
        <div class="card-body">
            <h6 class="text-muted mb-3">Inviter un évaluateur</h6>
            <input type="hidden" name="action" value="invite">
            <div class="row g-2 align-items-end">
                <div class="col-md-8">
                    <input type="email" name="email" class="form-control form-control-sm bg-dark text-white border-secondary"
                           placeholder="adresse@courriel.com" required>
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-primary btn-sm w-100">Inviter</button>
                </div>
            </div>
            <small class="text-muted mt-2 d-block">L'évaluateur sera activé automatiquement quand il se connectera avec ce courriel.</small>
        </div>
    </form>

    <!-- Evaluators list -->
    <?php if (empty($evaluators)): ?>
        <div class="alert alert-dark border-secondary text-light">
            Aucun évaluateur invité. Vous êtes le seul évaluateur de ce camp.
        </div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table table-dark table-hover align-middle">
                <thead>
                <tr class="text-secondary border-secondary">
                    <th>Courriel</th>
                    <th>Statut</th>
                    <th>Invité le</th>
                    <th>Accepté le</th>
                    <th class="text-end">Actions</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($evaluators as $ev): ?>
                    <?php $sl = $statusLabels[$ev['status']] ?? ['?', 'bg-secondary']; ?>
                    <tr class="border-secondary">
                        <td><?= htmlspecialchars($ev['email']) ?></td>
                        <td><span class="badge <?= $sl[1] ?>"><?= $sl[0] ?></span></td>
                        <td class="text-muted small"><?= $ev['invited_at'] ? date('Y-m-d', strtotime($ev['invited_at'])) : '-' ?></td>
                        <td class="text-muted small"><?= $ev['accepted_at'] ? date('Y-m-d', strtotime($ev['accepted_at'])) : '-' ?></td>
                        <td class="text-end">
                            <div class="btn-group">
                                <?php if ($ev['status'] === 'active'): ?>
                                    <form method="POST" class="d-inline">
                                        <input type="hidden" name="action" value="revoke">
                                        <input type="hidden" name="evaluator_id" value="<?= $ev['id'] ?>">
                                        <button type="submit" class="btn btn-outline-warning btn-sm">Révoquer</button>
                                    </form>
                                <?php elseif ($ev['status'] === 'revoked'): ?>
                                    <form method="POST" class="d-inline">
                                        <input type="hidden" name="action" value="activate">
                                        <input type="hidden" name="evaluator_id" value="<?= $ev['id'] ?>">
                                        <button type="submit" class="btn btn-outline-success btn-sm">Réactiver</button>
                                    </form>
                                <?php endif; ?>
                                <form method="POST" class="d-inline">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="evaluator_id" value="<?= $ev['id'] ?>">
                                    <button type="submit" class="btn btn-outline-danger btn-sm"
                                            onclick="return confirm('Supprimer cet évaluateur ?')">Suppr.</button>
                                </form>
                            </div>
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
