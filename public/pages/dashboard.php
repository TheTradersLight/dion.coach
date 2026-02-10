<?php include __DIR__ . '/../includes/head.php'; ?>
<body>
<?php include __DIR__ . '/../includes/navbar.php'; ?>

<main class="container">
    <h1>Bienvenue, <?= htmlspecialchars($_SESSION['user_name']) ?> </h1>

    <p>Vous êtes connecté avec :</p>
    <ul>
        <li><strong>Email :</strong> <?= htmlspecialchars($_SESSION['user_email']) ?></li>
    </ul>

</main>

<?php include __DIR__ . '/../includes/footer.php'; ?>
</body>
</html>
