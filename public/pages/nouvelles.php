<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>dion.coach</title>
    <meta name="description" content="Ultimate, Coaching, Performance — dion.coach">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="/css/theme.css" rel="stylesheet">
</head>
<body>
<?php include __DIR__ . '/../includes/navbar.php'; ?>


<!-- HERO -->
<main class="hero">
    <div class="container">
        <h1 class="display-6 fw-semibold">Nouvelles</h1>
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
