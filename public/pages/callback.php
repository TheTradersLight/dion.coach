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

//require_once __DIR__ . '/vendor/autoload.php';

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
$sub    = $authUser['sub'];
$parts  = explode('|', $sub);

$provider        = strtolower($parts[0]);
$providerUserId  = $parts[1] ?? null;

trace("Parsed provider info:", [
    'sub'             => $sub,
    'provider_raw'    => $provider,
    'providerUserId'  => $providerUserId
]);

if ($provider === 'google-oauth2') { $provider = 'google'; }
if ($provider === 'facebook')      { $provider = 'facebook'; }

trace("Normalized provider:", $provider);


// ---------------------------------------------------------------------
// 4) Infos utiles
// ---------------------------------------------------------------------
$email     = $authUser['email']   ?? null;
$name      = $authUser['name']    ?? null;
$avatarUrl = $authUser['picture'] ?? null;

trace("User info extracted:", [
    'email'     => $email,
    'name'      => $name,
    'avatarUrl' => $avatarUrl
]);


// ---------------------------------------------------------------------
// 5) Vérifier si ce provider existe déjà dans la BD
// ---------------------------------------------------------------------
trace("STEP 3: Checking provider link in DB");

$user = UserRepository::findByProvider($provider, $providerUserId);

trace("DB lookup result (user):", $user);

if ($user) {
    trace("STEP 4: Existing provider match → LOGIN USER", $user);

    $_SESSION['user_id']    = $user['id'];
    $_SESSION['user_name']  = $user['name'];
    $_SESSION['user_email'] = $user['email'];

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
// ---------------------------------------------------------------------
trace("STEP 5: No provider found → redirect to register.php");

$_SESSION['pending_oauth'] = [
    'provider'        => $provider,
    'providerUserId'  => $providerUserId,
    'email'           => $email,
    'name'            => $name,
    'avatarUrl'       => $avatarUrl
];

trace("Stored pending_oauth:", $_SESSION['pending_oauth']);

if (DEBUG_CALLBACK) {
    trace("STOP: Debug mode prevents redirect to register.php");
    exit;
}

header('Location: /register');
exit;
