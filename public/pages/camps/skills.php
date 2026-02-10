<?php
declare(strict_types=1);

use App\Database\CampRepository;
use App\Database\SkillRepository;

$campId = (int)($_GET['camp_id'] ?? 0);
$camp = $campId > 0 ? CampRepository::findById($campId) : null;

if (!$camp || (int)$camp['created_by'] !== (int)$_SESSION['user_id']) {
    header('Location: /camps');
    exit;
}

// POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add_category') {
        $name = trim($_POST['name'] ?? '');
        if ($name !== '') {
            SkillRepository::createCategory([
                'camp_id'   => $campId,
                'parent_id' => !empty($_POST['parent_id']) ? (int)$_POST['parent_id'] : null,
                'name'      => $name,
            ]);
        }
    } elseif ($action === 'update_category') {
        $catId = (int)($_POST['category_id'] ?? 0);
        $cat = SkillRepository::findCategoryById($catId);
        if ($cat && (int)$cat['camp_id'] === $campId) {
            SkillRepository::updateCategory($catId, ['name' => trim($_POST['name'] ?? '')]);
        }
    } elseif ($action === 'delete_category') {
        $catId = (int)($_POST['category_id'] ?? 0);
        $cat = SkillRepository::findCategoryById($catId);
        if ($cat && (int)$cat['camp_id'] === $campId) {
            SkillRepository::deleteCategory($catId);
        }
    } elseif ($action === 'add_skill') {
        $catId = (int)($_POST['category_id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        $cat = SkillRepository::findCategoryById($catId);
        if ($cat && (int)$cat['camp_id'] === $campId && $name !== '') {
            SkillRepository::createSkill([
                'category_id' => $catId,
                'name'        => $name,
                'description' => trim($_POST['description'] ?? ''),
            ]);
        }
    } elseif ($action === 'update_skill') {
        $skillId = (int)($_POST['skill_id'] ?? 0);
        $skill = SkillRepository::findSkillById($skillId);
        if ($skill) {
            $cat = SkillRepository::findCategoryById((int)$skill['category_id']);
            if ($cat && (int)$cat['camp_id'] === $campId) {
                SkillRepository::updateSkill($skillId, [
                    'name'        => trim($_POST['name'] ?? ''),
                    'description' => trim($_POST['description'] ?? ''),
                ]);
            }
        }
    } elseif ($action === 'delete_skill') {
        $skillId = (int)($_POST['skill_id'] ?? 0);
        $skill = SkillRepository::findSkillById($skillId);
        if ($skill) {
            $cat = SkillRepository::findCategoryById((int)$skill['category_id']);
            if ($cat && (int)$cat['camp_id'] === $campId) {
                SkillRepository::deleteSkill($skillId);
            }
        }
    }

    header("Location: /camps/{$campId}/skills");
    exit;
}

$tree = SkillRepository::getCategoriesWithSkills($campId);
?>
<!doctype html>
<html lang="fr">
<?php include __DIR__ . '/../../includes/head.php'; ?>
<body class="d-flex flex-column min-vh-100">
<?php include __DIR__ . '/../../includes/navbar.php'; ?>

<main class="container py-4 flex-grow-1" style="max-width: 1000px;">

    <div class="mb-2">
        <a href="/camps/<?= $campId ?>" class="text-muted small text-decoration-none">← <?= htmlspecialchars($camp['name']) ?></a>
    </div>

    <div class="d-flex align-items-center justify-content-between mb-4">
        <h1 class="display-6 fw-semibold mb-0">Grille de compétences</h1>
    </div>

    <!-- Ajouter une catégorie de niveau 1 -->
    <form method="POST" class="card bg-dark border-secondary mb-4">
        <div class="card-body">
            <h6 class="text-muted mb-3">Ajouter une catégorie</h6>
            <input type="hidden" name="action" value="add_category">
            <div class="row g-2 align-items-end">
                <div class="col-md-8">
                    <input type="text" name="name" class="form-control form-control-sm bg-dark text-white border-secondary"
                           placeholder="Ex: Physique, Technique, Mental..." required>
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-primary btn-sm w-100">Ajouter</button>
                </div>
            </div>
        </div>
    </form>

    <!-- Arbre des compétences -->
    <?php if (empty($tree)): ?>
        <div class="alert alert-dark border-secondary text-light">
            Aucune compétence définie. Ajoutez des catégories ci-dessus puis des compétences.
        </div>
    <?php else: ?>
        <?php foreach ($tree as $cat): ?>
            <div class="card bg-dark border-secondary mb-3">
                <div class="card-header border-secondary d-flex align-items-center justify-content-between"
                     data-bs-toggle="collapse" data-bs-target="#cat-<?= $cat['id'] ?>" role="button" style="cursor:pointer;">
                    <strong class="text-light"><?= htmlspecialchars($cat['name']) ?></strong>
                    <span class="text-muted small"><?= count($cat['skills']) ?> compétences<?= !empty($cat['children']) ? ' + ' . count($cat['children']) . ' sous-cat.' : '' ?></span>
                </div>
                <div class="collapse show" id="cat-<?= $cat['id'] ?>">
                    <div class="card-body pt-2">
                        <!-- Modifier / Supprimer catégorie -->
                        <div class="d-flex gap-2 mb-3">
                            <form method="POST" class="d-flex gap-1 flex-grow-1">
                                <input type="hidden" name="action" value="update_category">
                                <input type="hidden" name="category_id" value="<?= $cat['id'] ?>">
                                <input type="text" name="name" class="form-control form-control-sm bg-dark text-white border-secondary"
                                       value="<?= htmlspecialchars($cat['name']) ?>" style="max-width: 300px;">
                                <button type="submit" class="btn btn-outline-light btn-sm">Renommer</button>
                            </form>
                            <form method="POST" class="d-inline">
                                <input type="hidden" name="action" value="delete_category">
                                <input type="hidden" name="category_id" value="<?= $cat['id'] ?>">
                                <button type="submit" class="btn btn-outline-danger btn-sm"
                                        onclick="return confirm('Supprimer cette catégorie et toutes ses compétences ?')">Suppr. cat.</button>
                            </form>
                        </div>

                        <!-- Sous-catégories -->
                        <?php foreach ($cat['children'] as $sub): ?>
                            <div class="ms-3 mb-3 border-start border-secondary ps-3">
                                <div class="d-flex gap-2 mb-2">
                                    <form method="POST" class="d-flex gap-1 flex-grow-1">
                                        <input type="hidden" name="action" value="update_category">
                                        <input type="hidden" name="category_id" value="<?= $sub['id'] ?>">
                                        <input type="text" name="name" class="form-control form-control-sm bg-dark text-white border-secondary"
                                               value="<?= htmlspecialchars($sub['name']) ?>" style="max-width: 250px;">
                                        <button type="submit" class="btn btn-outline-light btn-sm">OK</button>
                                    </form>
                                    <form method="POST" class="d-inline">
                                        <input type="hidden" name="action" value="delete_category">
                                        <input type="hidden" name="category_id" value="<?= $sub['id'] ?>">
                                        <button type="submit" class="btn btn-outline-danger btn-sm"
                                                onclick="return confirm('Supprimer ?')">X</button>
                                    </form>
                                </div>
                                <!-- Skills in subcategory -->
                                <?php foreach ($sub['skills'] as $skill): ?>
                                    <div class="d-flex gap-1 mb-1 ms-2">
                                        <form method="POST" class="d-flex gap-1 flex-grow-1">
                                            <input type="hidden" name="action" value="update_skill">
                                            <input type="hidden" name="skill_id" value="<?= $skill['id'] ?>">
                                            <input type="text" name="name" class="form-control form-control-sm bg-dark text-white border-secondary"
                                                   value="<?= htmlspecialchars($skill['name']) ?>" style="max-width: 200px;">
                                            <button type="submit" class="btn btn-outline-secondary btn-sm">OK</button>
                                        </form>
                                        <form method="POST" class="d-inline">
                                            <input type="hidden" name="action" value="delete_skill">
                                            <input type="hidden" name="skill_id" value="<?= $skill['id'] ?>">
                                            <button type="submit" class="btn btn-outline-danger btn-sm" onclick="return confirm('Supprimer ?')">X</button>
                                        </form>
                                    </div>
                                <?php endforeach; ?>
                                <!-- Add skill to subcategory -->
                                <form method="POST" class="d-flex gap-1 ms-2 mt-1">
                                    <input type="hidden" name="action" value="add_skill">
                                    <input type="hidden" name="category_id" value="<?= $sub['id'] ?>">
                                    <input type="text" name="name" class="form-control form-control-sm bg-dark text-white border-secondary"
                                           placeholder="+ Compétence..." style="max-width: 200px;" required>
                                    <button type="submit" class="btn btn-outline-primary btn-sm">+</button>
                                </form>
                            </div>
                        <?php endforeach; ?>

                        <!-- Skills directly under category (not in subcategory) -->
                        <?php foreach ($cat['skills'] as $skill): ?>
                            <div class="d-flex gap-1 mb-1">
                                <form method="POST" class="d-flex gap-1 flex-grow-1">
                                    <input type="hidden" name="action" value="update_skill">
                                    <input type="hidden" name="skill_id" value="<?= $skill['id'] ?>">
                                    <input type="text" name="name" class="form-control form-control-sm bg-dark text-white border-secondary"
                                           value="<?= htmlspecialchars($skill['name']) ?>" style="max-width: 250px;">
                                    <button type="submit" class="btn btn-outline-secondary btn-sm">OK</button>
                                </form>
                                <form method="POST" class="d-inline">
                                    <input type="hidden" name="action" value="delete_skill">
                                    <input type="hidden" name="skill_id" value="<?= $skill['id'] ?>">
                                    <button type="submit" class="btn btn-outline-danger btn-sm" onclick="return confirm('Supprimer ?')">X</button>
                                </form>
                            </div>
                        <?php endforeach; ?>

                        <!-- Add skill to this category -->
                        <form method="POST" class="d-flex gap-1 mt-2">
                            <input type="hidden" name="action" value="add_skill">
                            <input type="hidden" name="category_id" value="<?= $cat['id'] ?>">
                            <input type="text" name="name" class="form-control form-control-sm bg-dark text-white border-secondary"
                                   placeholder="+ Compétence..." style="max-width: 250px;" required>
                            <button type="submit" class="btn btn-outline-primary btn-sm">+</button>
                        </form>

                        <!-- Add subcategory -->
                        <form method="POST" class="d-flex gap-1 mt-2">
                            <input type="hidden" name="action" value="add_category">
                            <input type="hidden" name="parent_id" value="<?= $cat['id'] ?>">
                            <input type="text" name="name" class="form-control form-control-sm bg-dark text-white border-secondary"
                                   placeholder="+ Sous-catégorie..." style="max-width: 250px;" required>
                            <button type="submit" class="btn btn-outline-secondary btn-sm">+</button>
                        </form>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>

</main>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
</body>
</html>
