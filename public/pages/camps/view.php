<?php
declare(strict_types=1);

use App\Database\CampRepository;
use App\Database\EvaluatorRepository;

$campId = (int)($_GET['camp_id'] ?? 0);
$camp = $campId > 0 ? CampRepository::findById($campId) : null;
$userId = (int)($_SESSION['user_id'] ?? 0);
$isOwner = $camp && (int)$camp['created_by'] === $userId;
$isEvaluator = $camp && !$isOwner && EvaluatorRepository::isEvaluator($campId, $userId);

if (!$camp || (!$isOwner && !$isEvaluator)) {
    header('Location: /camps');
    exit;
}

$stats = CampRepository::countStats($campId);

$statusLabels = [
    'draft'     => ['Brouillon', 'bg-secondary'],
    'active'    => ['Actif', 'bg-success'],
    'completed' => ['Terminé', 'bg-primary'],
    'archived'  => ['Archivé', 'bg-dark border border-secondary'],
];
$s = $statusLabels[$camp['status']] ?? ['?', 'bg-secondary'];
?>
<!doctype html>
<html lang="fr">
<?php include __DIR__ . '/../../includes/head.php'; ?>
<body class="d-flex flex-column min-vh-100">
<?php include __DIR__ . '/../../includes/navbar.php'; ?>

<main class="container py-4 flex-grow-1" style="max-width: 1100px;">

    <div class="d-flex align-items-center justify-content-between mb-2">
        <div>
            <a href="/camps" class="text-muted small text-decoration-none">← Mes camps</a>
            <h1 class="display-6 fw-semibold mb-0 mt-1"><?= htmlspecialchars($camp['name']) ?></h1>
        </div>
        <div class="d-flex gap-2">
            <span class="badge <?= $s[1] ?> fs-6 align-self-center"><?= htmlspecialchars($s[0]) ?></span>
            <?php if ($isOwner): ?>
                <a href="/camps/<?= $campId ?>/edit" class="btn btn-outline-light btn-sm">Modifier</a>
            <?php else: ?>
                <span class="badge bg-info">Évaluateur</span>
            <?php endif; ?>
        </div>
    </div>

    <?php if (!empty($camp['description'])): ?>
        <p class="text-secondary mb-3"><?= htmlspecialchars($camp['description']) ?></p>
    <?php endif; ?>

    <div class="text-muted small mb-4">
        <?php if ($camp['sport'] !== ''): ?>
            <span class="me-3"><?= htmlspecialchars($camp['sport']) ?></span>
        <div class="col-md-4">
            <a href="/camps/<?= $campId ?>/access-codes" class="text-decoration-none">
                <div class="card bg-dark border-secondary h-100">
                    <div class="card-body">
                        <h5 class="card-title text-light">Codes d'accès</h5>
                        <p class="card-text text-muted small">Générer des codes pour l'accès aux tests physiques.</p>
                    </div>
                </div>
            </a>
        </div>
        <?php endif; ?>
        <?php if ($camp['season'] !== ''): ?>
            <span class="me-3">Saison <?= htmlspecialchars($camp['season']) ?></span>
        <?php endif; ?>
        <span>Échelle <?= (int)$camp['rating_min'] ?>-<?= (int)$camp['rating_max'] ?></span>
    </div>

    <!-- Stats -->
    <div class="row g-3 mb-4">
        <div class="col-6 col-md-3">
            <div class="card bg-dark border-secondary text-center">
                <div class="card-body py-3">
                    <div class="fs-3 fw-bold text-light"><?= $stats['players'] ?></div>
                    <div class="text-muted small">Joueurs</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card bg-dark border-secondary text-center">
                <div class="card-body py-3">
                    <div class="fs-3 fw-bold text-light"><?= $stats['groups'] ?></div>
                    <div class="text-muted small">Groupes</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card bg-dark border-secondary text-center">
                <div class="card-body py-3">
                    <div class="fs-3 fw-bold text-light"><?= $stats['skills'] ?></div>
                    <div class="text-muted small">Compétences</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card bg-dark border-secondary text-center">
                <div class="card-body py-3">
                    <div class="fs-3 fw-bold text-light"><?= $stats['sessions'] ?></div>
                    <div class="text-muted small">Séances</div>
                </div>
            </div>
        </div>
        <?php if ($isOwner): ?>
        <div class="col-6 col-md-3">
            <div class="card bg-dark border-secondary text-center">
                <div class="card-body py-3">
                    <div class="fs-3 fw-bold text-light"><?= $stats['evaluators'] ?></div>
                    <div class="text-muted small">Évaluateurs</div>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <!-- Navigation rapide -->
    <div class="row g-3">
        <?php if ($isOwner): ?>
        <div class="col-md-4">
            <a href="/camps/<?= $campId ?>/players" class="text-decoration-none">
                <div class="card bg-dark border-secondary h-100">
                    <div class="card-body">
                        <h5 class="card-title text-light">Joueurs</h5>
                        <p class="card-text text-muted small">Ajouter, gérer et organiser les joueurs du camp.</p>
                    </div>
                </div>
            </a>
        </div>
        <div class="col-md-4">
            <a href="/camps/<?= $campId ?>/groups" class="text-decoration-none">
                <div class="card bg-dark border-secondary h-100">
                    <div class="card-body">
                        <h5 class="card-title text-light">Groupes</h5>
                        <p class="card-text text-muted small">Créer des groupes et assigner les joueurs.</p>
                    </div>
                </div>
            </a>
        </div>
        <div class="col-md-4">
            <a href="/camps/<?= $campId ?>/skills" class="text-decoration-none">
                <div class="card bg-dark border-secondary h-100">
                    <div class="card-body">
                        <h5 class="card-title text-light">Compétences</h5>
                        <p class="card-text text-muted small">Définir la grille d'évaluation (catégories et compétences).</p>
                    </div>
                </div>
            </a>
        </div>
        <div class="col-md-4">
            <a href="/camps/<?= $campId ?>/sessions" class="text-decoration-none">
                <div class="card bg-dark border-secondary h-100">
                    <div class="card-body">
                        <h5 class="card-title text-light">Séances</h5>
                        <p class="card-text text-muted small">Planifier les séances d'évaluation.</p>
                    </div>
                </div>
            </a>
        </div>
        <div class="col-md-4">
            <a href="/camps/<?= $campId ?>/evaluators" class="text-decoration-none">
                <div class="card bg-dark border-secondary h-100">
                    <div class="card-body">
                        <h5 class="card-title text-light">Évaluateurs</h5>
                        <p class="card-text text-muted small">Inviter et gérer les évaluateurs du camp.</p>
                    </div>
                </div>
            </a>
        </div>
        <?php endif; ?>
        <div class="col-md-4">
            <a href="/camps/<?= $campId ?>/evaluate" class="text-decoration-none">
                <div class="card bg-dark border-secondary h-100">
                    <div class="card-body">
                        <h5 class="card-title text-light">Évaluer</h5>
                        <p class="card-text text-muted small">Saisir les notes des joueurs.</p>
                    </div>
                </div>
            </a>
        </div>
        <div class="col-md-4">
            <a href="/camps/<?= $campId ?>/test-physique" class="text-decoration-none">
                <div class="card bg-dark border-secondary h-100">
                    <div class="card-body">
                        <h5 class="card-title text-light">Tests physiques</h5>
                        <p class="card-text text-muted small">Saisir les résultats des tests physiques.</p>
                    </div>
                </div>
            </a>
        </div>
        <div class="col-md-4">
            <a href="/camps/<?= $campId ?>/results" class="text-decoration-none">
                <div class="card bg-dark border-secondary h-100">
                    <div class="card-body">
                        <h5 class="card-title text-light">Résultats</h5>
                        <p class="card-text text-muted small">Consulter les classements et résultats.</p>
                    </div>
                </div>
            </a>
        </div>
    </div>

    <!-- Offline preparation -->
    <div class="mt-4">
        <button class="btn btn-outline-secondary btn-sm" id="btnOffline" onclick="prepareOffline(<?= $campId ?>)">
            Préparer pour hors-ligne
        </button>
        <span id="offlineStatus" class="ms-2 small text-muted"></span>
    </div>

</main>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
<script src="/js/offline-manager.js"></script>
<script>
async function prepareOffline(campId) {
    const btn = document.getElementById('btnOffline');
    const status = document.getElementById('offlineStatus');
    btn.disabled = true;
    status.textContent = 'Préparation en cours...';
    status.className = 'ms-2 small text-info';

    const result = await window.OfflineManager.prepareCampOffline(campId);
    if (result.ok) {
        status.textContent = 'Camp prêt pour utilisation hors-ligne!';
        status.className = 'ms-2 small text-success';
    } else {
        status.textContent = 'Erreur: ' + (result.error || 'Échec de la préparation');
        status.className = 'ms-2 small text-danger';
    }
    btn.disabled = false;
}
</script>
</body>
</html>
