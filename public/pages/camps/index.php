<?php
declare(strict_types=1);

use App\Database\CampRepository;
use App\Database\EvaluatorRepository;

$userId = (int)($_SESSION['user_id'] ?? 0);
$userEmail = $_SESSION['user_email'] ?? '';

// Auto-accept pending invitations for this user's email
if ($userEmail !== '') {
    EvaluatorRepository::autoAcceptByEmail(strtolower($userEmail), $userId);
}

$camps = CampRepository::findAll($userId);
$evaluatorCamps = EvaluatorRepository::findCampsForEvaluator($userId);
$sessions = EvaluatorRepository::getSessionsForUser($userId);

$statusLabels = [
    'draft'     => ['Brouillon', 'bg-secondary'],
    'active'    => ['Actif', 'bg-success'],
    'completed' => ['Terminé', 'bg-primary'],
    'archived'  => ['Archivé', 'bg-dark border border-secondary'],
];

$sessionStatusLabels = [
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

<main class="container py-4 flex-grow-1" style="max-width: 1100px;">

    <?php if (isset($_GET['deleted'])): ?>
        <div class="alert alert-success alert-dismissible fade show bg-dark text-success border-success" role="alert">
            Camp supprimé avec succès.
            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="alert" aria-label="Fermer"></button>
        </div>
    <?php endif; ?>

    <!-- ============================================================ -->
    <!-- SÉANCES À ÉVALUER (priorisé en haut pour les évaluateurs)   -->
    <!-- ============================================================ -->
    <?php if (!empty($sessions)): ?>
        <div class="d-flex align-items-center justify-content-between mb-3">
            <h2 class="h5 fw-semibold mb-0">Mes séances</h2>
        </div>

        <div class="row g-3 mb-5">
            <?php foreach ($sessions as $sess):
                $today = date('Y-m-d');
                $sDate = $sess['session_date'] ?? null;
                $isToday = $sDate === $today;
                $isPast = $sDate && $sDate < $today;
                $borderClass = $isToday ? 'border-warning' : 'border-secondary';
                $ss = $sessionStatusLabels[$sess['session_status']] ?? ['?', 'bg-secondary'];
            ?>
                <div class="col-md-6 col-lg-4">
                    <div class="card bg-dark <?= $borderClass ?> h-100">
                        <div class="card-body d-flex flex-column">
                            <div class="d-flex align-items-start justify-content-between mb-2">
                                <div>
                                    <h6 class="card-title text-light mb-1"><?= htmlspecialchars($sess['session_name']) ?></h6>
                                    <small class="text-muted"><?= htmlspecialchars($sess['camp_name']) ?></small>
                                </div>
                                <span class="badge <?= $ss[1] ?> ms-2"><?= $ss[0] ?></span>
                            </div>

                            <div class="text-muted small mb-2">
                                <?php if ($sess['sport']): ?>
                                    <span class="me-2"><?= htmlspecialchars($sess['sport']) ?></span>
                                <?php endif; ?>
                                <?php if ($sDate): ?>
                                    <span><?= $isToday ? 'Aujourd\'hui' : date('j M Y', strtotime($sDate)) ?></span>
                                <?php else: ?>
                                    <span>Date non définie</span>
                                <?php endif; ?>
                            </div>

                            <div class="mt-auto d-flex gap-2">
                                <a href="/camps/<?= $sess['camp_id'] ?>/evaluate?session=<?= $sess['session_id'] ?>" class="btn btn-sm btn-gold flex-grow-1">Évaluer</a>
                                <a href="/camps/<?= $sess['camp_id'] ?>" class="btn btn-sm btn-outline-light">Camp</a>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <!-- ============================================================ -->
    <!-- MES CAMPS (propriétaire)                                     -->
    <!-- ============================================================ -->
    <div class="d-flex align-items-center justify-content-between mb-3">
        <h2 class="h5 fw-semibold mb-0">Mes camps</h2>
        <a href="/camps/create" class="btn btn-success btn-sm">+ Nouveau camp</a>
    </div>

    <?php if (empty($camps)): ?>
        <div class="alert alert-dark border-secondary text-light mb-5">
            Aucun camp pour le moment. Créez votre premier camp!
        </div>
    <?php else: ?>
        <div class="table-responsive mb-5">
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
                    <?php $s = $statusLabels[$camp['status']] ?? ['?', 'bg-secondary']; ?>
                    <tr class="border-secondary">
                        <td><span class="badge <?= $s[1] ?>"><?= htmlspecialchars($s[0]) ?></span></td>
                        <td>
                            <a href="/camps/<?= $camp['id'] ?>" class="text-decoration-none text-light fw-semibold">
                                <?= htmlspecialchars($camp['name']) ?>
                            </a>
                        </td>
                        <td><?= htmlspecialchars($camp['sport']) ?></td>
                        <td><?= htmlspecialchars($camp['season']) ?></td>
                        <td class="small"><?= (int)$camp['rating_min'] ?>-<?= (int)$camp['rating_max'] ?></td>
                        <td class="small"><?= date('Y-m-d', strtotime($camp['created_at'])) ?></td>
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

    <!-- ============================================================ -->
    <!-- CAMPS OÙ JE SUIS ÉVALUATEUR                                 -->
    <!-- ============================================================ -->
    <?php if (!empty($evaluatorCamps)): ?>
        <div class="d-flex align-items-center justify-content-between mb-3">
            <h2 class="h5 fw-semibold mb-0 text-info">Camps où je suis évaluateur</h2>
        </div>
        <div class="row g-3 mb-4">
            <?php foreach ($evaluatorCamps as $ec):
                $s = $statusLabels[$ec['status']] ?? ['?', 'bg-secondary'];
            ?>
                <div class="col-md-6 col-lg-4">
                    <div class="card bg-dark border-secondary h-100">
                        <div class="card-body d-flex flex-column">
                            <div class="d-flex align-items-start justify-content-between mb-2">
                                <h6 class="card-title text-light mb-0"><?= htmlspecialchars($ec['name']) ?></h6>
                                <span class="badge <?= $s[1] ?> ms-2"><?= htmlspecialchars($s[0]) ?></span>
                            </div>
                            <div class="text-muted small mb-3">
                                <?php if ($ec['sport']): ?>
                                    <span class="me-2"><?= htmlspecialchars($ec['sport']) ?></span>
                                <?php endif; ?>
                                <?php if ($ec['season']): ?>
                                    <span>Saison <?= htmlspecialchars($ec['season']) ?></span>
                                <?php endif; ?>
                            </div>
                            <div class="mt-auto d-flex gap-2">
                                <a href="/camps/<?= $ec['id'] ?>/evaluate" class="btn btn-sm btn-gold flex-grow-1">Évaluer</a>
                                <a href="/camps/<?= $ec['id'] ?>/results" class="btn btn-sm btn-outline-light">Résultats</a>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

</main>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
</body>
</html>
