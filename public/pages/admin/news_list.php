<?php
use App\Database\NewsRepository;

$news = NewsRepository::findAll();
?>
<!doctype html>
<html lang="fr">
<?php include __DIR__ . '/../../includes/head.php'; ?>
<body class="d-flex flex-column min-vh-100">
<?php include __DIR__ . '/../../includes/navbar.php'; ?>

<main class="container mt-5 mb-5 flex-grow-1">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="text-white">Gestion des nouvelles</h1>
        <a href="/admin/news/edit" class="btn btn-success shadow-sm">+ Créer un article</a>
    </div>

    <?php if (isset($_GET['deleted'])): ?>
        <div class="alert alert-success alert-dismissible fade show bg-dark text-success border-success" role="alert">
            Article supprimé avec succès.
            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="alert" aria-label="Fermer"></button>
        </div>
    <?php endif; ?>

    <div class="card bg-dark text-white border-secondary shadow">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-dark table-hover align-middle mb-0">
                    <thead>
                    <tr class="text-secondary border-secondary">
                        <th class="ps-4" style="width: 100px;">Statut</th>
                        <th>Titre</th>
                        <th>Slug</th>
                        <th>Date de publication</th>
                        <th class="text-end pe-4">Actions</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php if (empty($news)): ?>
                        <tr>
                            <td colspan="5" class="text-center py-5 text-muted">
                                Aucun article. Commencez par en créer un!
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($news as $item): ?>
                            <tr class="border-secondary">
                                <td class="ps-4">
                                    <?php if ($item['is_published']): ?>
                                        <span class="badge bg-success">Publié</span>
                                    <?php else: ?>
                                        <span class="badge bg-warning text-dark">Brouillon</span>
                                    <?php endif; ?>

                                    <?php if (!empty($item['is_pinned'])): ?>
                                        <span class="badge bg-primary ms-1" title="Épinglé">&#128204;</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <?php if(!empty($item['image_path'])): ?>
                                            <img src="/media/<?= htmlspecialchars($item['image_path']) ?>" class="rounded me-2" style="width: 40px; height: 30px; object-fit: cover; border: 1px solid #444;">
                                        <?php endif; ?>
                                        <strong><?= htmlspecialchars($item['title']) ?></strong>
                                    </div>
                                </td>
                                <td class="text-muted small">/nouvelles/<?= htmlspecialchars($item['slug']) ?></td>
                                <td class="small">
                                    <?= $item['published_at'] ? date('Y-m-d H:i', strtotime($item['published_at'])) : '---' ?>
                                </td>
                                <td class="text-end pe-4">
                                    <div class="btn-group">
                                        <a href="/nouvelles/<?= htmlspecialchars($item['slug']) ?>" target="_blank" class="btn btn-outline-info btn-sm" title="Voir">
                                            Voir
                                        </a>
                                        <a href="/admin/news/edit?id=<?= $item['id'] ?>" class="btn btn-outline-light btn-sm" title="Modifier">
                                            Modifier
                                        </a>
                                        <button type="button" class="btn btn-outline-danger btn-sm"
                                                onclick="confirmDelete(<?= $item['id'] ?>, '<?= htmlspecialchars($item['title'], ENT_QUOTES) ?>')"
                                                title="Supprimer">
                                            Supprimer
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</main>

<form id="deleteForm" method="POST" action="/admin/news/delete" style="display:none;">
    <input type="hidden" name="id" id="deleteId">
</form>

<script>
    function confirmDelete(id, title) {
        if (confirm("Êtes-vous sûr de vouloir supprimer définitivement l'article : '" + title + "' ? Cette action est irréversible.")) {
            document.getElementById('deleteId').value = id;
            document.getElementById('deleteForm').submit();
        }
    }
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
</body>
</html>
