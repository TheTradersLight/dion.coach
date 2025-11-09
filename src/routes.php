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
    $app->get('/', function (Request $r, Response $res) {
        ob_start();
        include __DIR__ . '/../public/pages/nouvelles.php';
        $html = ob_get_clean();
        $res->getBody()->write($html);
        return $res->withHeader('Content-Type', 'text/html; charset=utf-8');
    });

    // Les autres routes
    $app->get('/nouvelles', fn($r, $res) => $res->withBody((new Slim\Psr7\Stream(fopen('php://temp', 'r+'))))
        ->write('<h1>Nouvelles à venir</h1>')->withHeader('Content-Type', 'text/html; charset=utf-8'));

    $app->get('/a-propos', fn($r, $res) => $res->withBody((new Slim\Psr7\Stream(fopen('php://temp', 'r+'))))
        ->write('<h1>À propos à venir</h1>')->withHeader('Content-Type', 'text/html; charset=utf-8'));

    $app->get('/contact', fn($r, $res) => $res->withBody((new Slim\Psr7\Stream(fopen('php://temp', 'r+'))))
        ->write('<h1>Contact: hello@dion.coach</h1>')->withHeader('Content-Type', 'text/html; charset=utf-8'));
};
