<!doctype html>
<html lang="fr">
<?php
// On récupère l'objet utilisateur passé depuis la route (via getAuth()->getUser())
$user = getAuth()->getUser();
?>
<?php include __DIR__ . '/../includes/head.php'; ?>
<body>
<?php include __DIR__ . '/../includes/navbar.php'; ?>


<!-- HERO -->
<main class="hero">
    <div class="container">
        <h1 class="display-6 fw-semibold">À propos</h1>
        <p class="lead">À venir</p>


    </div>
</main>

<?php //include __DIR__ . '/../includes/footer.php'; ?>
</body>
</html>
