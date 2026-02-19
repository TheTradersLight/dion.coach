<?php
declare(strict_types=1);

use App\Database\CampRepository;
use App\Database\AccessCodeRepository;
use App\Database\Database;

$campId = (int)($_GET['camp_id'] ?? 0);
$camp = $campId > 0 ? CampRepository::findById($campId) : null;

if (!$camp || (int)$camp['created_by'] !== (int)($_SESSION['user_id'])) {
    header('Location: /camps');
    exit;
}

$message = '';
$error = '';
$newCode = null;
$newCodeRole = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'create') {
        $role = $_POST['role'] ?? 'station';
        $testTypeId = $_POST['test_type_id'] !== '' ? (int)($_POST['test_type_id'] ?? 0) : null;
        $userId = (int)($_POST['user_id'] ?? 0);
        $days = (int)($_POST['expires_days'] ?? 14);

        if (!in_array($role, ['station', 'admin'], true)) {
            $error = 'Rôle invalide.';
        } elseif ($role === 'station' && (!$testTypeId || $testTypeId <= 0)) {
            $error = 'Choisir un test pour un code station.';
        } elseif ($userId <= 0) {
            $error = 'Choisir un évaluateur.';
        } else {
            $expiresAt = $days > 0 ? date('Y-m-d H:i:s', time() + ($days * 86400)) : null;
            $createdBy = (int)$_SESSION['user_id'];
            $result = AccessCodeRepository::createCode($campId, $testTypeId, $userId, $role, $expiresAt, $createdBy);
            $newCode = $result['code'] ?? null;
            $newCodeRole = $role;
            $message = 'Code créé.';
        }
    } elseif ($action === 'revoke') {
        $codeId = (int)($_POST['code_id'] ?? 0);
        if ($codeId > 0) {
            AccessCodeRepository::revokeCode($campId, $codeId);
            $message = 'Code révoqué.';
        }
    }
}

$testTypes = Database::fetchAll(
    "SELECT id, name FROM test_types WHERE camp_id IS NULL OR camp_id = ? ORDER BY sort_order ASC, name ASC",
    [$campId]
);

// Active evaluators + owner
$evaluators = Database::fetchAll(
    "SELECT u.id, u.email
     FROM users u
     WHERE u.id = ?
     UNION
     SELECT u.id, u.email
     FROM camp_evaluators ce
     JOIN users u ON ce.user_id = u.id
     WHERE ce.camp_id = ? AND ce.status = 'active' AND ce.user_id IS NOT NULL
     ORDER BY email ASC",
    [(int)$camp['created_by'], $campId]
);

$codes = AccessCodeRepository::listCodes($campId);

$baseUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https' : 'http') .
    '://' . ($_SERVER['HTTP_HOST'] ?? 'dion.coach');
?>
<!doctype html>
<html lang="fr">
<?php include __DIR__ . '/../../includes/head.php'; ?>
<body class="d-flex flex-column min-vh-100">
<?php include __DIR__ . '/../../includes/navbar.php'; ?>

<main class="container py-4 flex-grow-1" style="max-width: 1000px;">

    <div class="mb-2">
        <a href="/camps/<?= $campId ?>" class="text-muted small text-decoration-none">&larr; <?= htmlspecialchars($camp['name']) ?></a>
    </div>

    <h1 class="display-6 fw-semibold mb-4">Codes d'accès</h1>

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

    <!-- Create code -->
    <form method="POST" class="card bg-dark border-secondary mb-4">
        <div class="card-body">
            <h6 class="text-muted mb-3">Créer un code</h6>
            <input type="hidden" name="action" value="create">
            <div class="row g-2">
                <div class="col-md-3">
                    <label class="form-label text-muted small">Rôle</label>
                    <select name="role" class="form-select form-select-sm bg-dark text-white border-secondary">
                        <option value="station">Station</option>
                        <option value="admin">Admin (résultats)</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label text-muted small">Test / Station</label>
                    <select name="test_type_id" class="form-select form-select-sm bg-dark text-white border-secondary">
                        <option value="">-- choisir --</option>
                        <?php foreach ($testTypes as $t): ?>
                            <option value="<?= (int)$t['id'] ?>"><?= htmlspecialchars($t['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label text-muted small">Évaluateur</label>
                    <select name="user_id" class="form-select form-select-sm bg-dark text-white border-secondary">
                        <option value="">-- choisir --</option>
                        <?php foreach ($evaluators as $u): ?>
                            <option value="<?= (int)$u['id'] ?>"><?= htmlspecialchars($u['email']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label text-muted small">Expire (jours)</label>
                    <input type="number" name="expires_days" value="14" min="0"
                           class="form-control form-control-sm bg-dark text-white border-secondary">
                </div>
            </div>
            <div class="mt-3">
                <button type="submit" class="btn btn-primary btn-sm">Générer le code</button>
                <small class="text-muted ms-2">Pour un code admin, laisser le test vide.</small>
            </div>
        </div>
    </form>

    <?php if ($newCode): ?>
        <div class="card bg-dark border-secondary mb-4">
            <div class="card-body">
                <h6 class="text-muted mb-2">Code généré</h6>
                <div class="d-flex align-items-center gap-3 mb-3">
                    <span class="badge bg-success fs-6"><?= htmlspecialchars($newCode) ?></span>
                    <span class="text-muted small">À partager immédiatement (non stocké en clair).</span>
                </div>
                <div class="d-flex align-items-center justify-content-between">
                    <label class="form-label text-muted small mb-1">Contenu de courriel</label>
                    <button type="button" class="btn btn-outline-light btn-sm" onclick="copyEmailTemplate()">Copier</button>
                </div>
                <textarea id="emailTemplate" class="form-control form-control-sm bg-dark text-white border-secondary" rows="4" readonly><?=
"Bonjour,\n\nVoici votre lien d'accès aux tests physiques:\n" .
($newCodeRole === 'admin'
    ? $baseUrl . "/camps/" . $campId . "/test-physique-results?code=" . $newCode
    : $baseUrl . "/camps/" . $campId . "/test-physique?code=" . $newCode) . "\n\n" .
"Code: " . $newCode . "\n\n" .
"Merci." ?></textarea>
            </div>
        </div>
    <?php endif; ?>

    <!-- Codes list -->
    <div class="card bg-dark border-secondary">
        <div class="card-body">
            <h6 class="text-muted mb-3">Codes existants</h6>
            <?php if (empty($codes)): ?>
                <div class="text-muted">Aucun code.</div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-dark table-hover align-middle">
                        <thead>
                        <tr class="text-secondary border-secondary">
                            <th>Rôle</th>
                            <th>Test</th>
                            <th>Évaluateur</th>
                            <th>Expire</th>
                            <th>Créé</th>
                            <th>Statut</th>
                            <th class="text-end">Actions</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($codes as $c): ?>
                            <tr class="border-secondary">
                                <td><?= htmlspecialchars($c['role']) ?></td>
                                <td><?= htmlspecialchars($c['test_type_name'] ?? '-') ?></td>
                                <td><?= htmlspecialchars($c['user_email'] ?? '-') ?></td>
                                <td class="text-muted small"><?= $c['expires_at'] ? date('Y-m-d', strtotime($c['expires_at'])) : '-' ?></td>
                                <td class="text-muted small"><?= $c['created_at'] ? date('Y-m-d', strtotime($c['created_at'])) : '-' ?></td>
                                <td>
                                    <?php if ($c['status'] === 'active'): ?>
                                        <span class="badge bg-success">Actif</span>
                                    <?php else: ?>
                                        <span class="badge bg-danger">Révoqué</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end">
                                    <?php if ($c['status'] === 'active'): ?>
                                        <form method="POST" class="d-inline">
                                            <input type="hidden" name="action" value="revoke">
                                            <input type="hidden" name="code_id" value="<?= (int)$c['id'] ?>">
                                            <button type="submit" class="btn btn-outline-warning btn-sm">Révoquer</button>
                                        </form>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>

</main>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
<script>
function copyEmailTemplate() {
    const el = document.getElementById('emailTemplate');
    if (!el) return;
    el.select();
    el.setSelectionRange(0, 99999);
    try {
        document.execCommand('copy');
    } catch (e) {}
}
</script>
</body>
</html>
