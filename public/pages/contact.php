<!doctype html>
<html lang="fr">
<?php


// On récupère l'objet utilisateur passé depuis la route (via getAuth()->getUser())
$user = getAuth()->getUser();

// Traitement du formulaire (appel de SendMail)


use App\SendMail;

$messageEnvoye = false;
$erreur = '';




if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {

        $recaptchaResponse = $_POST['g-recaptcha-response'] ?? '';
        $secretKey = '6LfNNSosAAAAAFF81nlITPJB3E22TctCQK5IPdJm';

        $response = file_get_contents(
            "https://www.google.com/recaptcha/api/siteverify?secret={$secretKey}&response={$recaptchaResponse}"
        );
        $data = json_decode($response, true);

        if (!$data['success']) {
            throw new \Exception('Échec de la vérification reCAPTCHA.');
        }

        SendMail::envoyer(
            $_POST['name'] ?? '',
            $_POST['email'] ?? '',
            $_POST['message'] ?? ''
        );
        $messageEnvoye = true;
    } catch (\Exception $e) {
        $erreur = $e->getMessage();
    }
}
?>
<?php include __DIR__ . '/../includes/head.php'; ?>
<body>
<?php include __DIR__ . '/../includes/navbar.php'; ?>

<!-- HERO -->
<main class="hero">
    <div class="container">
        <h1 class="display-6 fw-semibold">Contact</h1>

        <?php if ($messageEnvoye): ?>
            <div class="alert alert-success mt-4">✅ Merci, votre message a été envoyé.</div>
        <?php elseif (!empty($erreur)): ?>
            <div class="alert alert-danger mt-4">❌ Erreur : <?= htmlspecialchars($erreur) ?></div>
        <?php endif; ?>

        <form class="mt-4" method="POST" action="/contact">
            <div class="mb-3">
                <label for="name" class="form-label">Nom</label>
                <input type="text" class="form-control" name="name" id="name" required>
            </div>
            <div class="mb-3">
                <label for="email" class="form-label">Courriel</label>
                <input type="email" class="form-control" name="email" id="email" required>
            </div>
            <div class="mb-3">
                <label for="message" class="form-label">Message</label>
                <textarea class="form-control" name="message" id="message" rows="5" required></textarea>
            </div>
            <div class="mb-3">
                <div class="g-recaptcha" data-sitekey="6LfNNSosAAAAAIY6LV2fPJ5i58QFoq_CO3Iyqqq0"></div>
            </div>

            <button type="submit" class="btn btn-primary">Envoyer</button>
        </form>
    </div>
</main>

<?php //include __DIR__ . '/../includes/footer.php'; ?>
<script src="https://www.google.com/recaptcha/api.js" async defer></script>

</body>
</html>
