<?php
declare(strict_types=1);

use App\Database\NewsRepository;

$slug = $_GET['slug'] ?? '';
if ($slug === '') {
    header('Location: /nouvelles');
    exit;
}

$item = NewsRepository::findPublishedBySlug($slug);
if (!$item) {
    header('Location: /nouvelles');
    exit;
}

$date = !empty($item['published_at']) ? date('j M Y', strtotime($item['published_at'])) : '';
?>
<!doctype html>
<html lang="fr">
<?php include __DIR__ . '/../../includes/head.php'; ?>
<body class="d-flex flex-column min-vh-100">
<?php include __DIR__ . '/../../includes/navbar.php'; ?>

<main class="container py-4 flex-grow-1" style="max-width: 980px;">

    <div class="mb-3">
        <a class="btn btn-sm btn-outline-light" href="/nouvelles">← Retour aux nouvelles</a>
    </div>

    <div class="mb-3">
        <div class="d-flex align-items-start justify-content-between gap-2">
            <h1 class="news-title"><?= htmlspecialchars($item['title']) ?></h1>
            <?php if (!empty($item['is_pinned'])): ?>
                <span class="badge text-bg-warning">Épinglé</span>
            <?php endif; ?>
        </div>

        <?php if ($date !== ''): ?>
            <div class="text-secondary small"><?= htmlspecialchars($date) ?></div>
        <?php endif; ?>
    </div>

    <div class="card bg-dark text-light border-secondary shadow-sm">
        <div class="card-body news-body">
            <div class="row">
                <?php if (!empty($item['image_path'])): ?>
                    <div class="col-md-4 mb-3 mb-md-0">
                        <img src="/media/<?= htmlspecialchars($item['image_path']) ?>"
                             class="img-fluid rounded border border-secondary"
                             alt="<?= htmlspecialchars($item['title']) ?>">
                    </div>
                    <div class="col-md-8">
                        <?= $item['body_html'] ?>
                    </div>
                <?php else: ?>
                    <div class="col-12">
                        <?= $item['body_html'] ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

</main>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
</body>
</html>
