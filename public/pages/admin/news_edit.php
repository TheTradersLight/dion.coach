<?php
use App\Database\NewsRepository;
use App\Database\Database;

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$news = null;

// --- LOGIQUE DE TRAITEMENT (POST) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $textFromEditor = $_POST['body_html_quill'] ?? '';
    $imagePath = $_POST['image_path'] ?? '';

    $finalHtml = $textFromEditor;

    $data = [
        'id'               => $id > 0 ? $id : null,
        'slug'             => $_POST['slug'] ?? '',
        'title'            => $_POST['title'] ?? '',
        'excerpt'          => $_POST['excerpt'] ?? '',
        'body_html'        => $finalHtml,
        'image_path'       => $imagePath,
        'meta_description' => $_POST['meta_description'] ?? '',
        'published_at'     => !empty($_POST['published_at']) ? $_POST['published_at'] : date('Y-m-d H:i:s'),
        'is_published'     => isset($_POST['is_published']) ? 1 : 0,
        'is_pinned'        => isset($_POST['is_pinned']) ? 1 : 0
    ];

    NewsRepository::upsert($data);

    header("Location: /admin/news");
    exit;
}

// --- CHARGEMENT DES DONNÉES (GET) ---
if ($id > 0) {
    $news = Database::fetch("SELECT * FROM news WHERE id = ?", [$id]);
}

$title = $news ? "Modifier l'article" : "Créer un article";
?>
<!doctype html>
<html lang="fr">
<?php include __DIR__ . '/../../includes/head.php'; ?>
<body class="d-flex flex-column min-vh-100">
<?php include __DIR__ . '/../../includes/navbar.php'; ?>

    <link href="https://cdn.quilljs.com/1.3.6/quill.snow.css" rel="stylesheet">

    <div class="container mt-5 mb-5 text-white flex-grow-1">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1><?= $title ?></h1>
            <a href="/admin/news" class="btn btn-outline-secondary">Retour à la liste</a>
        </div>

        <form method="POST" id="newsForm">
            <div class="row">
                <div class="col-md-8">
                    <div class="mb-3">
                        <label class="form-label">Titre</label>
                        <input type="text" name="title" id="newsTitle" class="form-control bg-dark text-white" value="<?= htmlspecialchars($news['title'] ?? '') ?>" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Slug</label>
                        <input type="text" name="slug" id="newsSlug" class="form-control bg-dark text-white" value="<?= htmlspecialchars($news['slug'] ?? '') ?>" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Extrait (courte description pour les listes, max 280 car.)</label>
                        <textarea name="excerpt" class="form-control bg-dark text-white" rows="2" maxlength="280"><?= htmlspecialchars($news['excerpt'] ?? '') ?></textarea>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Contenu</label>
                        <div id="editor" style="height: 400px; background: #222; color: white;"><?= $news['body_html'] ?? '' ?></div>
                        <input type="hidden" name="body_html_quill" id="body_html_quill">
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Meta description (SEO, max 160 car.)</label>
                        <input type="text" name="meta_description" class="form-control bg-dark text-white" value="<?= htmlspecialchars($news['meta_description'] ?? '') ?>" maxlength="160">
                    </div>
                </div>

                <div class="col-md-4">
                    <div class="card bg-dark border-secondary mb-3">
                        <div class="card-body">
                            <label class="form-label">Image principale</label>
                            <input type="file" id="imageUploader" class="form-control bg-dark text-white mb-2">
                            <input type="hidden" name="image_path" id="image_path" value="<?= htmlspecialchars($news['image_path'] ?? '') ?>">
                            <div id="imagePreview" class="mt-2 text-center">
                                <?php if(!empty($news['image_path'])): ?>
                                    <img src="/media/<?= htmlspecialchars($news['image_path']) ?>" class="img-fluid rounded border border-secondary shadow-sm">
                                <?php else: ?>
                                    <small class="text-muted">Aucune image</small>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <div class="card bg-dark border-secondary">
                        <div class="card-body">
                            <div class="form-check mb-2">
                                <input class="form-check-input" type="checkbox" name="is_published" id="is_published" <?= ($news['is_published'] ?? 0) ? 'checked' : '' ?>>
                                <label class="form-check-label" for="is_published">Publié</label>
                            </div>
                            <div class="form-check mb-3">
                                <input class="form-check-input" type="checkbox" name="is_pinned" id="is_pinned" <?= ($news['is_pinned'] ?? 0) ? 'checked' : '' ?>>
                                <label class="form-check-label" for="is_pinned">Épinglé en haut</label>
                            </div>
                            <button type="submit" class="btn btn-primary w-100 py-2 shadow-sm">Sauvegarder</button>
                        </div>
                    </div>
                </div>
            </div>
        </form>
    </div>

    <script src="https://cdn.quilljs.com/1.3.6/quill.js"></script>
    <script>
        var quill = new Quill('#editor', {
            theme: 'snow',
            modules: { toolbar: [['bold', 'italic'], [{ 'list': 'ordered'}, { 'list': 'bullet' }], ['link', 'clean']] }
        });

        // Auto-slugification (uniquement à la création)
        document.getElementById('newsTitle').addEventListener('input', function(e) {
            if (<?= $id ?> === 0) {
                const slug = e.target.value.toLowerCase()
                    .normalize("NFD").replace(/[\u0300-\u036f]/g, "")
                    .replace(/[^\w ]+/g,'')
                    .replace(/ +/g,'-');
                document.getElementById('newsSlug').value = slug;
            }
        });

        // Upload GCS
        document.getElementById('imageUploader').addEventListener('change', function(e) {
            if (!e.target.files[0]) return;

            let formData = new FormData();
            formData.append('image', e.target.files[0]);

            document.getElementById('imagePreview').innerHTML = '<div class="spinner-border spinner-border-sm text-primary" role="status"></div>';

            fetch('/admin/upload-image', { method: 'POST', body: formData })
                .then(res => res.json())
                .then(data => {
                    if(data.ok) {
                        document.getElementById('image_path').value = data.path;
                        document.getElementById('imagePreview').innerHTML = `<img src="${data.url}" class="img-fluid rounded border border-primary">`;
                    } else {
                        alert("Erreur d'upload : " + data.error);
                        document.getElementById('imagePreview').innerHTML = '<small class="text-danger">Échec de l\'upload</small>';
                    }
                })
                .catch(err => alert("Erreur serveur lors de l'upload"));
        });

        document.getElementById('newsForm').onsubmit = function() {
            document.getElementById('body_html_quill').value = quill.root.innerHTML;
        };
    </script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
</body>
</html>
