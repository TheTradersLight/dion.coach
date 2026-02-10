<?php
declare(strict_types=1);

use App\Database\CampRepository;

$campId = (int)($_GET['camp_id'] ?? 0);
$camp = $campId > 0 ? CampRepository::findById($campId) : null;

if (!$camp || (int)$camp['created_by'] !== (int)$_SESSION['user_id']) {
    header('Location: /camps');
    exit;
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

    <h1 class="display-6 fw-semibold mb-4">Résultats</h1>

    <div class="alert alert-dark border-secondary text-light">
        Les résultats seront disponibles une fois que des évaluations auront été saisies.
    </div>

</main>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
</body>
</html>
