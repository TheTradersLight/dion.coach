<?php
declare(strict_types=1);

use App\Database\CampRepository;

$campId = (int)($_GET['camp_id'] ?? 0);
$camp = $campId > 0 ? CampRepository::findById($campId) : null;

if (!$camp || (int)$camp['created_by'] !== (int)$_SESSION['user_id']) {
    header('Location: /camps');
    exit;
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    if ($name === '') $errors[] = 'Le nom est requis.';

    if (empty($errors)) {
        CampRepository::update($campId, [
            'name'        => $name,
            'description' => trim($_POST['description'] ?? ''),
            'sport'       => trim($_POST['sport'] ?? ''),
            'season'      => trim($_POST['season'] ?? ''),
            'status'      => $_POST['status'] ?? $camp['status'],
            'eval_mode'   => $_POST['eval_mode'] ?? $camp['eval_mode'],
            'rating_min'  => (int)($_POST['rating_min'] ?? $camp['rating_min']),
            'rating_max'  => (int)($_POST['rating_max'] ?? $camp['rating_max']),
        ]);

        header("Location: /camps/{$campId}");
        exit;
    }
}

$c = $camp; // shortcut for form values
?>
<!doctype html>
<html lang="fr">
<?php include __DIR__ . '/../../includes/head.php'; ?>
<body class="d-flex flex-column min-vh-100">
<?php include __DIR__ . '/../../includes/navbar.php'; ?>

<main class="container py-4 flex-grow-1" style="max-width: 800px;">

    <div class="d-flex align-items-center justify-content-between mb-4">
        <h1 class="display-6 fw-semibold mb-0">Modifier le camp</h1>
        <a href="/camps/<?= $campId ?>" class="btn btn-outline-secondary btn-sm">← Retour</a>
    </div>

    <?php if (!empty($errors)): ?>
        <div class="alert alert-danger">
            <?php foreach ($errors as $e): ?>
                <div><?= htmlspecialchars($e) ?></div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <form method="POST" class="card bg-dark border-secondary">
        <div class="card-body">
            <div class="row g-3">
                <div class="col-12">
                    <label class="form-label">Nom du camp *</label>
                    <input type="text" name="name" class="form-control bg-dark text-white border-secondary"
                           value="<?= htmlspecialchars($_POST['name'] ?? $c['name']) ?>" required>
                </div>

                <div class="col-12">
                    <label class="form-label">Description</label>
                    <textarea name="description" class="form-control bg-dark text-white border-secondary" rows="3"><?= htmlspecialchars($_POST['description'] ?? $c['description'] ?? '') ?></textarea>
                </div>

                <div class="col-md-6">
                    <label class="form-label">Sport</label>
                    <input type="text" name="sport" class="form-control bg-dark text-white border-secondary"
                           value="<?= htmlspecialchars($_POST['sport'] ?? $c['sport']) ?>">
                </div>

                <div class="col-md-6">
                    <label class="form-label">Saison</label>
                    <input type="text" name="season" class="form-control bg-dark text-white border-secondary"
                           value="<?= htmlspecialchars($_POST['season'] ?? $c['season']) ?>">
                </div>

                <div class="col-md-4">
                    <label class="form-label">Statut</label>
                    <?php $st = $_POST['status'] ?? $c['status']; ?>
                    <select name="status" class="form-select bg-dark text-white border-secondary">
                        <option value="draft" <?= $st === 'draft' ? 'selected' : '' ?>>Brouillon</option>
                        <option value="active" <?= $st === 'active' ? 'selected' : '' ?>>Actif</option>
                        <option value="completed" <?= $st === 'completed' ? 'selected' : '' ?>>Terminé</option>
                        <option value="archived" <?= $st === 'archived' ? 'selected' : '' ?>>Archivé</option>
                    </select>
                </div>

                <div class="col-md-4">
                    <label class="form-label">Note minimale</label>
                    <input type="number" name="rating_min" class="form-control bg-dark text-white border-secondary"
                           value="<?= (int)($_POST['rating_min'] ?? $c['rating_min']) ?>" min="0" max="100">
                </div>

                <div class="col-md-4">
                    <label class="form-label">Note maximale</label>
                    <input type="number" name="rating_max" class="form-control bg-dark text-white border-secondary"
                           value="<?= (int)($_POST['rating_max'] ?? $c['rating_max']) ?>" min="1" max="100">
                </div>

                <input type="hidden" name="eval_mode" value="<?= htmlspecialchars($c['eval_mode']) ?>">
            </div>
        </div>
        <div class="card-footer border-secondary d-flex justify-content-between">
            <button type="button" class="btn btn-outline-danger" onclick="confirmDelete()">Supprimer</button>
            <button type="submit" class="btn btn-primary px-4">Sauvegarder</button>
        </div>
    </form>

</main>

<form id="deleteForm" method="POST" action="/camps/<?= $campId ?>/delete" style="display:none;"></form>

<script>
function confirmDelete() {
    if (confirm("Êtes-vous sûr de vouloir supprimer ce camp ? Tous les joueurs, groupes, compétences et évaluations seront supprimés. Cette action est irréversible.")) {
        document.getElementById('deleteForm').submit();
    }
}
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
</body>
</html>
