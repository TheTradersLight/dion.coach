<?php
declare(strict_types=1);

use App\Database\CampRepository;

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $sport = trim($_POST['sport'] ?? '');
    $season = trim($_POST['season'] ?? '');

    if ($name === '') $errors[] = 'Le nom est requis.';

    if (empty($errors)) {
        $id = CampRepository::create([
            'name'        => $name,
            'description' => trim($_POST['description'] ?? ''),
            'sport'       => $sport,
            'season'      => $season,
            'status'      => $_POST['status'] ?? 'draft',
            'eval_mode'   => $_POST['eval_mode'] ?? 'cumulative',
            'rating_min'  => (int)($_POST['rating_min'] ?? 1),
            'rating_max'  => (int)($_POST['rating_max'] ?? 5),
            'created_by'  => (int)$_SESSION['user_id'],
        ]);

        header("Location: /camps/{$id}");
        exit;
    }
}
?>
<!doctype html>
<html lang="fr">
<?php include __DIR__ . '/../../includes/head.php'; ?>
<body class="d-flex flex-column min-vh-100">
<?php include __DIR__ . '/../../includes/navbar.php'; ?>

<main class="container py-4 flex-grow-1" style="max-width: 800px;">

    <div class="d-flex align-items-center justify-content-between mb-4">
        <h1 class="display-6 fw-semibold mb-0">Nouveau camp</h1>
        <a href="/camps" class="btn btn-outline-secondary btn-sm">← Retour</a>
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
                           value="<?= htmlspecialchars($_POST['name'] ?? '') ?>" required
                           placeholder="Ex: Camp de sélection U15">
                </div>

                <div class="col-12">
                    <label class="form-label">Description</label>
                    <textarea name="description" class="form-control bg-dark text-white border-secondary" rows="3"
                              placeholder="Description optionnelle du camp"><?= htmlspecialchars($_POST['description'] ?? '') ?></textarea>
                </div>

                <div class="col-md-6">
                    <label class="form-label">Sport</label>
                    <input type="text" name="sport" class="form-control bg-dark text-white border-secondary"
                           value="<?= htmlspecialchars($_POST['sport'] ?? '') ?>"
                           placeholder="Ex: Hockey">
                </div>

                <div class="col-md-6">
                    <label class="form-label">Saison</label>
                    <input type="text" name="season" class="form-control bg-dark text-white border-secondary"
                           value="<?= htmlspecialchars($_POST['season'] ?? '') ?>"
                           placeholder="Ex: 2025-2026">
                </div>

                <div class="col-md-4">
                    <label class="form-label">Statut</label>
                    <select name="status" class="form-select bg-dark text-white border-secondary">
                        <option value="draft" <?= ($_POST['status'] ?? 'draft') === 'draft' ? 'selected' : '' ?>>Brouillon</option>
                        <option value="active" <?= ($_POST['status'] ?? '') === 'active' ? 'selected' : '' ?>>Actif</option>
                    </select>
                </div>

                <div class="col-md-4">
                    <label class="form-label">Note minimale</label>
                    <input type="number" name="rating_min" class="form-control bg-dark text-white border-secondary"
                           value="<?= (int)($_POST['rating_min'] ?? 1) ?>" min="0" max="100">
                </div>

                <div class="col-md-4">
                    <label class="form-label">Note maximale</label>
                    <input type="number" name="rating_max" class="form-control bg-dark text-white border-secondary"
                           value="<?= (int)($_POST['rating_max'] ?? 5) ?>" min="1" max="100">
                </div>

                <input type="hidden" name="eval_mode" value="cumulative">
            </div>
        </div>
        <div class="card-footer border-secondary text-end">
            <button type="submit" class="btn btn-primary px-4">Créer le camp</button>
        </div>
    </form>

</main>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
</body>
</html>
