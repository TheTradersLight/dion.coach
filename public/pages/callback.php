<?php
// ---------------------------------------------------------------------
// DEBUG SWITCH
// ---------------------------------------------------------------------
define('DEBUG_CALLBACK', false);   // <-- METTRE false POUR PROD

function trace($msg, $var = null) {
    if (DEBUG_CALLBACK) {
        echo "<pre style='background:#111;color:#0f0;padding:10px;border-radius:6px;margin:5px 0;'>";
        echo htmlspecialchars($msg);
        if ($var !== null) {
            echo "\n\n";
            print_r($var);
        }
        echo "</pre>";
        flush();
    }
}

use App\Database\UserRepository;

session_start();

// ---------------------------------------------------------------------
// 1) Auth0 échange le code OAuth
// ---------------------------------------------------------------------
trace("STEP 1: Calling auth0->exchange()");
$auth0->exchange();

// ---------------------------------------------------------------------
// 2) Infos Auth0
// ---------------------------------------------------------------------
trace("STEP 2: Retrieving Auth0 user");
$authUser = $auth0->getUser();

trace("AUTH0 RAW USER:", $authUser);

if (!$authUser) {
    die("ERREUR: Impossible de récupérer les infos utilisateur via Auth0.");
}

// ---------------------------------------------------------------------
// 3) Parsing provider
// ---------------------------------------------------------------------
$sub   = $authUser['sub'] ?? '';
$parts = explode('|', $sub);

$provider       = strtolower($parts[0] ?? '');
$providerUserId = $parts[1] ?? null;

trace("Parsed provider info:", [
    'sub'            => $sub,
    'provider_raw'   => $provider,
    'providerUserId' => $providerUserId
]);

// Normalisation de quelques providers connus
if ($provider === 'google-oauth2') { $provider = 'google'; }
if ($provider === 'facebook')      { $provider = 'facebook'; }

// Si tu utilises Auth0 Database (email/password), "auth0|xxxx" revient souvent.
// Tu peux choisir de le traiter comme "local".
if ($provider === 'auth0')         { $provider = 'local'; }

trace("Normalized provider:", $provider);

// ---------------------------------------------------------------------
// 4) Infos utiles
// ---------------------------------------------------------------------
$email         = isset($authUser['email']) ? strtolower(trim((string)$authUser['email'])) : null;
$name          = $authUser['name']    ?? null;
$avatarUrl     = $authUser['picture'] ?? null;
$emailVerified = (bool)($authUser['email_verified'] ?? false);

trace("User info extracted:", [
    'email'         => $email,
    'emailVerified' => $emailVerified,
    'name'          => $name,
    'avatarUrl'     => $avatarUrl
]);

// ---------------------------------------------------------------------
// 4.5) BLOQUER tant que l'email n'est pas vérifié (avant toute écriture BD)
// ---------------------------------------------------------------------
if (!$email || !$emailVerified) {
    trace("STEP 2.5: Email missing or not verified -> block DB writes");

    // On garde quand même l'info en session (au cas où tu veux l'afficher)
    $_SESSION['pending_oauth'] = [
        'provider'       => $provider,
        'providerUserId' => $providerUserId,
        'email'          => $email,
        'name'           => $name,
        'avatarUrl'      => $avatarUrl,
        'emailVerified'  => $emailVerified
    ];

    if (DEBUG_CALLBACK) {
        trace("STOP: Debug mode prevents redirect to verify-email-required");
        exit;
    }

    // À toi de créer cette page (message: va vérifier ton email puis reconnecte-toi)
    header('Location: /verify-email-required');
    exit;
}

// ---------------------------------------------------------------------
// 5) Vérifier si ce provider existe déjà dans la BD
// ---------------------------------------------------------------------
trace("STEP 3: Checking provider link in DB");

if (!$provider || !$providerUserId) {
    // Cas rare: sub absent/mal formé
    trace("Provider info missing -> redirect to /login", [
        'provider' => $provider,
        'providerUserId' => $providerUserId
    ]);
    header('Location: /login');
    exit;
}

$user = UserRepository::findByProvider($provider, $providerUserId);

trace("DB lookup result (user):", $user);

if ($user) {
    trace("STEP 4: Existing provider match → LOGIN USER", $user);

    $dbUser = UserRepository::findById((int)$user['id']) ?? $user;

    $_SESSION['user_id']    = $dbUser['id'];
    $_SESSION['user_name']  = $dbUser['name'];   // DB source of truth
    $_SESSION['user_email'] = $dbUser['email'];

    UserRepository::touchLogin($user['id'], $provider, $providerUserId, $avatarUrl);



    if (DEBUG_CALLBACK) {
        trace("STOP: Debug mode prevents redirect to dashboard.php");
        exit;
    }

    header('Location: /dashboard');
    exit;
}

// ---------------------------------------------------------------------
// 6) Sinon → envoyer vers register.php (sans créer en BD)
//    (on ne fait PAS de binding automatique par email ici, par sécurité)
// ---------------------------------------------------------------------
trace("STEP 5: No provider found → redirect to register.php");

$_SESSION['pending_oauth'] = [
    'provider'       => $provider,
    'providerUserId' => $providerUserId,
    'email'          => $email,
    'name'           => $name,
    'avatarUrl'      => $avatarUrl,
    'emailVerified'  => $emailVerified
];

trace("Stored pending_oauth:", $_SESSION['pending_oauth']);

if (DEBUG_CALLBACK) {
    trace("STOP: Debug mode prevents redirect to register.php");
    exit;
}

header('Location: /register');
exit;
