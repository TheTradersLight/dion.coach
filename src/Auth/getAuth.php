<?php
declare(strict_types=1);

use Auth0\SDK\Auth0;

require_once __DIR__ . '/../../vendor/autoload.php';


/**
 * Retourne une instance Auth0 préconfigurée
 *
 * @return Auth0
 */
function getAuth(): Auth0
{
    $clientSecret = getenv('AUTH0_CLIENT_SECRET');
    $cookieSecret = getenv('AUTH0_COOKIE_SECRET');
    if (!$clientSecret || !$cookieSecret) {
        throw new \RuntimeException(
            'AUTH0_CLIENT_SECRET and AUTH0_COOKIE_SECRET env vars must be set'
        );
    }
    return new Auth0([
        'domain'       => getenv('AUTH0_DOMAIN') ?: 'ionultimate.auth0.com',
        'clientId'     => getenv('AUTH0_CLIENT_ID') ?: 'bWZ4d4P0QPtyeernlkXz1hI8BSILJn05',
        'clientSecret' => $clientSecret,
        'redirectUri'  => getenv('AUTH0_REDIRECT_URI') ?: 'https://dion.coach/callback',
        'cookieSecret' => $cookieSecret,
    ]);
}
