<?php
// On récupère l'objet utilisateur passé depuis la route (via getAuth()->getUser())
session_start();
$user = getAuth()->getUser();
?>

<?php include __DIR__ . '/../includes/head.php'; ?>
<body>
<?php include __DIR__ . '/../includes/navbar.php'; ?>

<main class="container">
    <h1>Bienvenue, <?= htmlspecialchars($_SESSION['user_name']) ?> 👋</h1>

    <p>Vous êtes connecté avec :</p>
    <ul>
        <li><strong>Email :</strong> <?= htmlspecialchars($_SESSION['user_email']) ?></li>
    </ul>

    <?php /*if (!empty($user['picture'])): ?>
        <img src="<?= htmlspecialchars($user['picture']) ?>" alt="Photo de profil" style="max-width: 120px; border-radius: 50%;">
    <?php endif;*/ ?>

</main>

<?php //include __DIR__ . '/../includes/footer.php'; ?>
</body>
</html>