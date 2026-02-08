<?php
declare(strict_types=1);

use Slim\App;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

return function (App $app) {

    // Page d'accueil
    $app->get('/', function (Request $r, Response $res) {
        require_once __DIR__ . '/Auth/getAuth.php';
        $user = getAuth()->getUser();
        ob_start();
        include __DIR__ . '/../public/pages/home.php';
        $html = ob_get_clean();
        $res->getBody()->write($html);
        return $res->withHeader('Content-Type', 'text/html; charset=utf-8');
    });

    // News
    $app->get('/nouvelles', function (Request $r, Response $res) {
        require_once __DIR__ . '/Auth/getAuth.php';
        $user = getAuth()->getUser();
        ob_start();
        include __DIR__ . '/../public/pages/nouvelles.php';
        $html = ob_get_clean();
        $res->getBody()->write($html);
        return $res->withHeader('Content-Type', 'text/html; charset=utf-8');
    });

    $app->get('/a-propos', function (Request $r, Response $res) {
        require_once __DIR__ . '/Auth/getAuth.php';
        $user = getAuth()->getUser();
        ob_start();
        include __DIR__ . '/../public/pages/apropos.php';
        $html = ob_get_clean();
        $res->getBody()->write($html);
        return $res->withHeader('Content-Type', 'text/html; charset=utf-8');
    });

    $app->map(['GET', 'POST'],'/contact', function (Request $r, Response $res) {
        require_once __DIR__ . '/Auth/getAuth.php';
        $user = getAuth()->getUser();

        ob_start();
        include __DIR__ . '/../public/pages/contact.php';
        $html = ob_get_clean();
        $res->getBody()->write($html);
        return $res->withHeader('Content-Type', 'text/html; charset=utf-8');
    });
    $app->get('/login', function (Request $r, Response $res) {
        require_once __DIR__ . '/Auth/getAuth.php';

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

    $app->map(['GET', 'POST'],'/callback', function (Request $r, Response $res) {
        require_once __DIR__ . '/Auth/getAuth.php';
        $auth0 = getAuth();
        ob_start();
        include __DIR__ . '/../public/pages/callback.php';
        $html = ob_get_clean();

        // IMPORTANT :
        // callback.php fait des header(Location...) puis exit;
        // donc normalement on NE devrait PAS atteindre ce point.
        // Mais si jamais callback.php affiche du debug, on l'affiche.

        $res->getBody()->write($html);
        return $res->withHeader('Content-Type', 'text/html; charset=utf-8');
    });
    $app->map(['GET', 'POST'], '/register', function (Request $r, Response $res) {
        //require_once __DIR__ . '/Auth/getAuth.php';
        //$auth0 = getAuth();

        ob_start();
        include __DIR__ . '/../public/pages/register.php';
        $html = ob_get_clean();

        // register.php va souvent faire:
        //   header('Location: /dashboard');
        //   exit;
        //
        // Donc ce code ne sera atteint que si:
        // - il y a des erreurs
        // - ou on affiche juste le formulaire
        // - ou on est en debug

        $res->getBody()->write($html);

        return $res->withHeader('Content-Type', 'text/html; charset=utf-8');
    });
    $app->map(['GET', 'POST'], '/verify-email-required', function (Request $r, Response $res) {
        require_once __DIR__ . '/Auth/getAuth.php';
        $user = getAuth()->getUser();
        ob_start();
        include __DIR__ . '/../public/pages/verify-email-required.php';
        $html = ob_get_clean();

        $res->getBody()->write($html);

        return $res->withHeader('Content-Type', 'text/html; charset=utf-8');
    });
    $app->get('/dashboard', function (Request $r, Response $res) {
        require_once __DIR__ . '/Auth/getAuth.php';
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
        require_once __DIR__ . '/Auth/getAuth.php';

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

    $app->get('/privacy-policy', function ($req, $res) {
        ob_start();
        include __DIR__ . '/../public/pages/privacy.php';
        $html = ob_get_clean();
        $res->getBody()->write($html);
        return $res->withHeader('Content-Type', 'text/html');
    });

    $app->get('/data-deletion', function ($req, $res) {
        ob_start();
        include __DIR__ . '/../public/pages/data-deletion.php';
        $html = ob_get_clean();
        $res->getBody()->write($html);
        return $res->withHeader('Content-Type', 'text/html');
    });

    $app->get('/terms', function ($req, $res) {
        ob_start();
        include __DIR__ . '/../public/pages/terms.php';
        $html = ob_get_clean();
        $res->getBody()->write($html);
        return $res->withHeader('Content-Type', 'text/html');
    });

    // --- Camps de selection (prototype) ---
    $app->get('/camps/evaluate', function (Request $r, Response $res) {
        require_once __DIR__ . '/Auth/getAuth.php';
        $user = getAuth()->getUser();
        if (!$user) {
            return $res->withHeader('Location', '/login')->withStatus(302);
        }
        $GLOBALS['user'] = $user;
        ob_start();
        include __DIR__ . '/../public/pages/camps/evaluate.php';
        $html = ob_get_clean();
        $res->getBody()->write($html);
        return $res->withHeader('Content-Type', 'text/html; charset=utf-8');
    });
};
