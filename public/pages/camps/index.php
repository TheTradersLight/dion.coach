<?php
declare(strict_types=1);

use App\Database\CampRepository;

$userId = (int)($_SESSION['user_id'] ?? 0);
$camps = CampRepository::findAll($userId);

$statusLabels = [
    'draft'     => ['Brouillon', 'bg-secondary'],
    'active'    => ['Actif', 'bg-success'],
    'completed' => ['Terminé', 'bg-primary'],
    'archived'  => ['Archivé', 'bg-dark border border-secondary'],
];
?>
<!doctype html>
<html lang="fr">
<?php include __DIR__ . '/../../includes/head.php'; ?>
<body class="d-flex flex-column min-vh-100">
<?php include __DIR__ . '/../../includes/navbar.php'; ?>

<main class="container py-4 flex-grow-1" style="max-width: 1100px;">

    <div class="d-flex align-items-center justify-content-between mb-4">
        <h1 class="display-6 fw-semibold mb-0">Camps de sélection</h1>
        <a href="/camps/create" class="btn btn-success">+ Nouveau camp</a>
    </div>

    <?php if (isset($_GET['deleted'])): ?>
        <div class="alert alert-success alert-dismissible fade show bg-dark text-success border-success" role="alert">
            Camp supprimé avec succès.
            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="alert" aria-label="Fermer"></button>
        </div>
    <?php endif; ?>

    <?php if (empty($camps)): ?>
        <div class="alert alert-dark border-secondary text-light">
            Aucun camp pour le moment. Créez votre premier camp!
        </div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table table-dark table-hover align-middle">
                <thead>
                <tr class="text-secondary border-secondary">
                    <th>Statut</th>
                    <th>Nom</th>
                    <th>Sport</th>
                    <th>Saison</th>
                    <th>Échelle</th>
                    <th>Créé le</th>
                    <th class="text-end">Actions</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($camps as $camp): ?>
                    <?php
                    $s = $statusLabels[$camp['status']] ?? ['?', 'bg-secondary'];
                    ?>
                    <tr class="border-secondary">
                        <td><span class="badge <?= $s[1] ?>"><?= htmlspecialchars($s[0]) ?></span></td>
                        <td>
                            <a href="/camps/<?= $camp['id'] ?>" class="text-decoration-none text-light fw-semibold">
                                <?= htmlspecialchars($camp['name']) ?>
                            </a>
                        </td>
                        <td class="text-muted"><?= htmlspecialchars($camp['sport']) ?></td>
                        <td class="text-muted"><?= htmlspecialchars($camp['season']) ?></td>
                        <td class="text-muted small"><?= (int)$camp['rating_min'] ?>-<?= (int)$camp['rating_max'] ?></td>
                        <td class="text-muted small"><?= date('Y-m-d', strtotime($camp['created_at'])) ?></td>
                        <td class="text-end">
                            <div class="btn-group">
                                <a href="/camps/<?= $camp['id'] ?>" class="btn btn-outline-info btn-sm">Ouvrir</a>
                                <a href="/camps/<?= $camp['id'] ?>/edit" class="btn btn-outline-light btn-sm">Modifier</a>
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
