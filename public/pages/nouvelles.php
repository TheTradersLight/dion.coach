<?php
declare(strict_types=1);

use App\Database\NewsRepository;

$page = (int)($_GET['page'] ?? 1);
if ($page < 1) $page = 1;

$limit  = 12;
$offset = ($page - 1) * $limit;

$items = NewsRepository::listPublished($limit, $offset);
$total = NewsRepository::countPublished();

$hasPrev = $page > 1;
$hasNext = ($offset + $limit) < $total;
?>
<!doctype html>
<html lang="fr">
<?php include __DIR__ . '/../includes/head.php'; ?>
<body class="d-flex flex-column min-vh-100">
<?php include __DIR__ . '/../includes/navbar.php'; ?>

<main class="container py-4 flex-grow-1" style="max-width: 980px;">

    <div class="d-flex align-items-center justify-content-between mb-3">
        <div>
            <h1 class="display-6 fw-semibold mb-1">Nouvelles</h1>
            <p class="text-secondary mb-0">Les dernières mises à jour de dion.coach.</p>
        </div>
    </div>

    <?php if (empty($items)): ?>
        <div class="alert alert-dark border-secondary text-light">
            Aucune nouvelle pour le moment.
        </div>
    <?php else: ?>
        <div class="row g-3">
            <?php foreach ($items as $n): ?>
                <?php
                $date = !empty($n['published_at']) ? date('j M Y', strtotime($n['published_at'])) : '';
                ?>
                <div class="col-12">
                    <a href="/nouvelles/<?= htmlspecialchars($n['slug']) ?>"
                       class="text-decoration-none">
                        <div class="card bg-dark text-light border-secondary shadow-sm h-100">
                            <div class="card-body">
                                <div class="d-flex align-items-start justify-content-between gap-2">
                                    <h2 class="h5 fw-semibold mb-2">
                                        <?= htmlspecialchars($n['title']) ?>
                                    </h2>

                                    <?php if (!empty($n['is_pinned'])): ?>
                                        <span class="badge text-bg-warning">Épinglé</span>
                                    <?php endif; ?>
                                </div>

                                <?php if ($date !== ''): ?>
                                    <div class="text-secondary small mb-2"><?= htmlspecialchars($date) ?></div>
                                <?php endif; ?>

                                <?php if (!empty($n['excerpt'])): ?>
                                    <p class="text-secondary mb-0">
                                        <?= htmlspecialchars($n['excerpt']) ?>
                                    </p>
                                <?php else: ?>
                                    <p class="text-secondary mb-0">Lire la suite →</p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </a>
                </div>
            <?php endforeach; ?>
        </div>

        <div class="d-flex justify-content-between align-items-center mt-4">
            <div>
                <?php if ($hasPrev): ?>
                    <a class="btn btn-sm btn-outline-light" href="/nouvelles?page=<?= $page-1 ?>">← Plus récentes</a>
                <?php endif; ?>
            </div>

            <div class="text-secondary small">
                Page <?= (int)$page ?> • <?= (int)$total ?> au total
            </div>

            <div>
                <?php if ($hasNext): ?>
                    <a class="btn btn-sm btn-outline-light" href="/nouvelles?page=<?= $page+1 ?>">Plus anciennes →</a>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>

</main>

<?php include __DIR__ . '/../includes/footer.php'; ?>
</body>
</html>
