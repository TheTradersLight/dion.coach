<?php include __DIR__ . '/../includes/head.php'; ?>
<body>
<?php include __DIR__ . '/../includes/navbar.php'; ?>

<main class="container">





<h1>Vérification du courriel requise</h1>

<p>
    Pour des raisons de sécurité, nous devons confirmer votre adresse courriel avant de finaliser
    la création de votre compte.
</p>

<h2>Que devez-vous faire ?</h2>

<ol>
    <li>
        Consultez votre boîte courriel et ouvrez le message de vérification envoyé par notre
        fournisseur d’authentification.
    </li>
    <li>
        Cliquez sur le lien de vérification contenu dans ce courriel.
    </li>
    <li>
        Une fois la vérification complétée, reconnectez-vous à votre compte.
    </li>
</ol>

<p>
    Aucune information n’a été enregistrée dans notre base de données tant que votre adresse courriel
    n’est pas vérifiée.
</p>

<h2>Vous ne voyez pas le courriel ?</h2>

<ul>
    <li>Vérifiez votre dossier <strong>courrier indésirable</strong> (spam)</li>
    <li>Patientez quelques minutes, l’envoi peut parfois être retardé</li>
    <li>Assurez-vous d’avoir utilisé la bonne adresse courriel</li>
</ul>

<p>
    Si le problème persiste, vous pouvez simplement vous reconnecter pour relancer le processus
    de vérification.
</p>

<p style="margin-top: 2rem;">
    <a href="/login" class="btn btn-primary">
        Se reconnecter
    </a>
</p>

<hr>

<p style="font-size: 0.9em; color: #999;">
    Cette étape permet de protéger votre compte et d’éviter toute utilisation non autorisée.
</p>
</main>

<?php include __DIR__ . '/../includes/footer.php'; ?>
</body>
</html>