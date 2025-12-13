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
        <h1 class="display-6 fw-semibold">#OutWORK | #OutSMART | #OutBELIEVE <br><br> #OutPERFORM</h1>
        <p class="lead">Éleve ta performance</p>

        <!-- Chaîne YouTube corrigée -->
        <a class="btn btn-gold btn-lg px-4" href="https://www.youtube.com/@dion_coach_ultimate" target="_blank" rel="noopener">
            ▶️ Voir la chaîne YouTube
        </a>
    </div>
</main>

<?php include __DIR__ . '/../includes/footer.php'; ?>


</body>
</html>
