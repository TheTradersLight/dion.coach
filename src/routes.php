<?php
declare(strict_types=1);

use Slim\App;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

return function (App $app) {

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

    $app->get('/contact', function (Request $r, Response $res) {
        ob_start();
        include __DIR__ . '/../public/pages/contact.php';
        $html = ob_get_clean();
        $res->getBody()->write($html);
        return $res->withHeader('Content-Type', 'text/html; charset=utf-8');
    });
    $app->get('/login', function (Request $r, Response $res) {
        require_once __DIR__ . '/../public/includes/auth/getAuth.php';
        getAuth()->login();
        return $res;
    });

    $app->get('/callback', function (Request $r, Response $res) {
        require_once __DIR__ . '/../public/includes/auth/getAuth.php';
        getAuth()->exchange();
        return $res->withHeader('Location', '/dashboard')->withStatus(302);
    });

    $app->get('/dashboard', function (Request $r, Response $res) {
        require_once __DIR__ . '/../public/includes/auth/getAuth.php';
        $user = getAuth()->getUser();

        if (!$user) {
            return $res->withHeader('Location', '/login')->withStatus(302);
        }
        $GLOBALS['user'] = $user;
        ob_start();
        include __DIR__ . '/../public/public/pages/dashboard.php';
        $html = ob_get_clean();
        $res->getBody()->write($html);
        return $res->withHeader('Content-Type', 'text/html; charset=utf-8');
    });
};
