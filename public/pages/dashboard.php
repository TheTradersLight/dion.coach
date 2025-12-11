<?php
// On récupère l'objet utilisateur passé depuis la route (via getAuth()->getUser())
$user = getAuth()->getUser();
?>

<?php include __DIR__ . '/../includes/head.php'; ?>
<?php include __DIR__ . '/../includes/navbar.php'; ?>

<main class="container">
    <h1>Bienvenue, <?= htmlspecialchars($user['name']) ?> 👋</h1>

    <p>Vous êtes connecté avec :</p>
    <ul>
        <li><strong>Email :</strong> <?= htmlspecialchars($user['email']) ?></li>
        <li><strong>Provider :</strong> <?= htmlspecialchars($user['sub']) ?></li>
    </ul>

    <?php if (!empty($user['picture'])): ?>
        <img src="<?= htmlspecialchars($user['picture']) ?>" alt="Photo de profil" style="max-width: 120px; border-radius: 50%;">
    <?php endif; ?>

    <p><a href="/logout">Se déconnecter</a></p>
</main>

<?php include __DIR__ . '/../includes/footer.php'; ?>
