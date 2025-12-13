<?php
session_start();

require_once __DIR__ . '/../src/database/Database.php';
require_once __DIR__ . '/../src/database/UserRepository.php';
require_once __DIR__ . '/../src/auth/AuthService.php';

use App\Database\UserRepository;
use App\Auth\AuthService;

// ---------------------------------------------------------------------
// 1) VÉRIFICATION : avons-nous des données OAuth en attente ?
// ---------------------------------------------------------------------
if (!isset($_SESSION['pending_oauth'])) {
    header('Location: /login.php');
    exit;
}

$data = $_SESSION['pending_oauth'];
$provider       = $data['provider'];
$providerUserId = $data['providerUserId'];
$email          = $data['email'] ?? '';
$name           = $data['name'] ?? '';
$avatarUrl      = $data['avatarUrl'] ?? null;

// ---------------------------------------------------------------------
// 2) TRAITEMENT DU FORMULAIRE : POST = créer le user + provider
// ---------------------------------------------------------------------
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Récupérer les champs soumis
    $email = trim($_POST['email'] ?? '');
    $name  = trim($_POST['name'] ?? '');

    // Validation simple
    if ($name === '') {
        $errors[] = "Le nom est obligatoire.";
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "L'adresse courriel est invalide.";
    }

    // Vérifier si l'email est déjà utilisé par un autre user
    $existing = UserRepository::findByEmail($email);
    if ($existing !== null) {
        $errors[] = "Un compte existe déjà avec cette adresse courriel.";
    }

    // Si aucune erreur → création
    if (empty($errors)) {

        // 2.1) Créer le user dans "users"
        $roleId = UserRepository::getDefaultRoleId();
        $userId = UserRepository::createUser($name, $email, $roleId);

        // 2.2) Créer l'entrée user_providers
        UserRepository::linkProvider(
            $userId,
            $provider,
            $providerUserId,
            $email,
            $name,
            $avatarUrl
        );

        // 2.3) Mise à jour du last_login
        UserRepository::touchLogin(
            $userId,
            $provider,
            $providerUserId,
            $avatarUrl
        );

        // 2.4) On met en session le user nouvellement créé
        $_SESSION['user_id'] = $userId;
        $_SESSION['user_name'] = $name;
        $_SESSION['user_email'] = $email;

        // 2.5) On supprime les données OAuth temporaires
        unset($_SESSION['pending_oauth']);

        // 2.6) Redirect final
        header('Location: /pages/dashboard.php');
        exit;
    }
}

// ---------------------------------------------------------------------
// 3) AFFICHAGE : formulaire d'inscription
// ---------------------------------------------------------------------
include __DIR__ . '/../includes/head.php';
?>
<body>
<?php include __DIR__ . '/../includes/navbar.php'; ?>

<main class="container py-4">

    <h1 class="mb-4">Créer votre compte</h1>

    <p class="text-muted">
        Vous êtes authentifié via
        <strong><?= htmlspecialchars(ucfirst($provider)) ?></strong>.
        Merci de compléter votre profil pour finaliser votre inscription.
    </p>

    <?php if (!empty($errors)): ?>
        <div class="alert alert-danger">
            <ul class="mb-0">
                <?php foreach ($errors as $err): ?>
                    <li><?= htmlspecialchars($err) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <div class="card shadow-sm">
        <div class="card-body">

            <form method="POST">

                <!-- Provider cachés -->
                <input type="hidden" name="provider" value="<?= htmlspecialchars($provider) ?>">
                <input type="hidden" name="providerUserId" value="<?= htmlspecialchars($providerUserId) ?>">
                <input type="hidden" name="avatarUrl" value="<?= htmlspecialchars($avatarUrl) ?>">

                <div class="mb-3">
                    <label class="form-label">Adresse courriel</label>
                    <input type="email"
                           name="email"
                           value="<?= htmlspecialchars($email) ?>"
                           class="form-control"
                           required>
                </div>

                <div class="mb-3">
                    <label class="form-label">Nom complet</label>
                    <input type="text"
                           name="name"
                           value="<?= htmlspecialchars($name) ?>"
                           class="form-control"
                           required>
                </div>

                <!-- Tu peux ajouter d'autres champs ici -->

                <button type="submit" class="btn btn-primary">
                    Finaliser l’inscription
                </button>

            </form>

        </div>
    </div>

</main>

<?php include __DIR__ . '/../includes/footer.php'; ?>
</body>
</html>
