<?php
declare(strict_types=1);

use Slim\App;
use Slim\Routing\RouteCollectorProxy;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use App\Middleware\RequireAuthMiddleware;

return function (App $app) {

    // =====================================================================
    // ROUTES PUBLIQUES
    // =====================================================================

    // Page d'accueil
    $app->get('/', function (Request $r, Response $res) {
        ob_start();
        include __DIR__ . '/../public/pages/home.php';
        $html = ob_get_clean();
        $res->getBody()->write($html);
        return $res->withHeader('Content-Type', 'text/html; charset=utf-8');
    });

    // News
    $app->get('/nouvelles', function (Request $r, Response $res) {
        ob_start();
        include __DIR__ . '/../public/pages/nouvelles.php';
        $html = ob_get_clean();
        $res->getBody()->write($html);
        return $res->withHeader('Content-Type', 'text/html; charset=utf-8');
    });

    $app->get('/a-propos', function (Request $r, Response $res) {
        ob_start();
        include __DIR__ . '/../public/pages/apropos.php';
        $html = ob_get_clean();
        $res->getBody()->write($html);
        return $res->withHeader('Content-Type', 'text/html; charset=utf-8');
    });

    $app->map(['GET', 'POST'], '/contact', function (Request $r, Response $res) {
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
            true
        );

        return $res
            ->withHeader('Location', $url)
            ->withStatus(302);
    });

    $app->map(['GET', 'POST'], '/callback', function (Request $r, Response $res) {
        require_once __DIR__ . '/Auth/getAuth.php';
        $auth0 = getAuth();
        ob_start();
        include __DIR__ . '/../public/pages/callback.php';
        $html = ob_get_clean();

        $res->getBody()->write($html);
        return $res->withHeader('Content-Type', 'text/html; charset=utf-8');
    });

    $app->map(['GET', 'POST'], '/register', function (Request $r, Response $res) {
        ob_start();
        include __DIR__ . '/../public/pages/register.php';
        $html = ob_get_clean();

        $res->getBody()->write($html);
        return $res->withHeader('Content-Type', 'text/html; charset=utf-8');
    });

    $app->map(['GET', 'POST'], '/verify-email-required', function (Request $r, Response $res) {
        ob_start();
        include __DIR__ . '/../public/pages/verify-email-required.php';
        $html = ob_get_clean();

        $res->getBody()->write($html);
        return $res->withHeader('Content-Type', 'text/html; charset=utf-8');
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

    // TEMPORARY DEBUG ROUTE - REMOVE AFTER DEBUGGING
    $app->get('/debug-session', function ($req, $res) {
        ob_start();
        include __DIR__ . '/../public/pages/debug-session.php';
        $html = ob_get_clean();
        $res->getBody()->write($html);
        return $res->withHeader('Content-Type', 'text/plain');
    });

    // =====================================================================
    // ROUTES PROTÉGÉES (RequireAuthMiddleware)
    // =====================================================================

    $app->group('', function (RouteCollectorProxy $group) {

        $group->get('/dashboard', function (Request $r, Response $res) {
            ob_start();
            include __DIR__ . '/../public/pages/dashboard.php';
            $html = ob_get_clean();
            $res->getBody()->write($html);
            return $res->withHeader('Content-Type', 'text/html; charset=utf-8');
        });

        $group->get('/camps/evaluate', function (Request $r, Response $res) {
            ob_start();
            include __DIR__ . '/../public/pages/camps/evaluate.php';
            $html = ob_get_clean();
            $res->getBody()->write($html);
            return $res->withHeader('Content-Type', 'text/html; charset=utf-8');
        });

        $group->get('/logout', function (Request $r, Response $res) {
            require_once __DIR__ . '/Auth/getAuth.php';
            $auth0 = getAuth();
            $auth0->clear();

            // Détruire la session PHP
            $_SESSION = [];
            if (ini_get('session.use_cookies')) {
                $p = session_get_cookie_params();
                setcookie(session_name(), '', time() - 42000,
                    $p['path'], $p['domain'], $p['secure'], $p['httponly']);
            }
            session_destroy();

            $logoutUrl = sprintf(
                'https://%s/v2/logout?client_id=%s&returnTo=%s',
                $auth0->configuration()->getDomain(),
                $auth0->configuration()->getClientId(),
                urlencode('https://dion.coach/')
            );
            return $res->withHeader('Location', $logoutUrl)->withStatus(302);
        });

    })->add(new RequireAuthMiddleware());
};
