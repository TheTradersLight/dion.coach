<!doctype html>
<html lang="fr">
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

<footer class="site-footer py-3">
    <div class="container small text-center">
        © <span id="y"></span> dion.coach — Tous droits réservés
    </div>
</footer>

<script>document.getElementById('y').textContent=new Date().getFullYear()</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
