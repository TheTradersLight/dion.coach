<?php
declare(strict_types=1);

use Slim\App;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

return function (App $app) {

    // Page d'accueil
    $app->get('/', function (Request $r, Response $res) {
        require_once __DIR__ . '/auth/getAuth.php';
        $user = getAuth()->getUser();
        ob_start();
        include __DIR__ . '/../public/pages/home.php';
        $html = ob_get_clean();
        $res->getBody()->write($html);
        return $res->withHeader('Content-Type', 'text/html; charset=utf-8');
    });

    // News
    $app->get('/nouvelles', function (Request $r, Response $res) {
        require_once __DIR__ . '/auth/getAuth.php';
        $user = getAuth()->getUser();
        ob_start();
        include __DIR__ . '/../public/pages/nouvelles.php';
        $html = ob_get_clean();
        $res->getBody()->write($html);
        return $res->withHeader('Content-Type', 'text/html; charset=utf-8');
    });

    $app->get('/a-propos', function (Request $r, Response $res) {
        require_once __DIR__ . '/auth/getAuth.php';
        $user = getAuth()->getUser();
        ob_start();
        include __DIR__ . '/../public/pages/apropos.php';
        $html = ob_get_clean();
        $res->getBody()->write($html);
        return $res->withHeader('Content-Type', 'text/html; charset=utf-8');
    });

    $app->get('/contact', function (Request $r, Response $res) {
        require_once __DIR__ . '/auth/getAuth.php';
        $user = getAuth()->getUser();

        ob_start();
        include __DIR__ . '/../public/pages/contact.php';
        $html = ob_get_clean();
        $res->getBody()->write($html);
        return $res->withHeader('Content-Type', 'text/html; charset=utf-8');
    });
    $app->get('/login', function (Request $r, Response $res) {
        require_once __DIR__ . '/auth/getAuth.php';

        $url = getAuth()->login(
            null,
            null,
            ['scope' => 'openid profile email'],
            'code',
            true  // 👈 ceci retourne l'URL au lieu de faire un header() en interne
        );

        return $res
            ->withHeader('Location', $url)
            ->withStatus(302);
    });

    $app->get('/callback', function (Request $r, Response $res) {
        require_once __DIR__ . '/auth/getAuth.php';
        getAuth()->exchange();
        return $res->withHeader('Location', '/dashboard')->withStatus(302);
    });

    $app->get('/dashboard', function (Request $r, Response $res) {
        require_once __DIR__ . '/auth/getAuth.php';
        $user = getAuth()->getUser();

        if (!$user) {
            return $res->withHeader('Location', '/login')->withStatus(302);
        }
        $GLOBALS['user'] = $user;
        ob_start();
        include __DIR__ . '/../public/pages/dashboard.php';
        $html = ob_get_clean();
        $res->getBody()->write($html);
        return $res->withHeader('Content-Type', 'text/html; charset=utf-8');
    });

    $app->get('/logout', function (Request $r, Response $res) {
        require_once __DIR__ . '/auth/getAuth.php';

        $auth0 = getAuth();

        // 1. Supprimer session côté application (cookie)
        $auth0->clear();

        // 2. Rediriger vers Auth0 pour déconnexion OAuth + retour à la page d'accueil
        $logoutUrl = sprintf(
            'https://%s/v2/logout?client_id=%s&returnTo=%s',
            $auth0->configuration()->getDomain(),
            $auth0->configuration()->getClientId(),
            urlencode('https://dion.coach/') // 👈 page d’accueil
        );

        return $res->withHeader('Location', $logoutUrl)->withStatus(302);
    });
};
