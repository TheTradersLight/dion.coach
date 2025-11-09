<?php
declare(strict_types=1);

use Slim\App;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

return function (App $app) {

    // Home
    $app->get('/', function (Request $r, Response $res) {
        $html = @file_get_contents(__DIR__ . '/../public/home.html');
        if ($html === false) {
            $res->getBody()->write('home.html manquant');
            return $res->withStatus(500)->withHeader('Content-Type','text/plain; charset=utf-8');
        }
        $res->getBody()->write($html);
        return $res->withHeader('Content-Type','text/html; charset=utf-8');
    });

    // Pages simples (placeholders)
    $app->get('/nouvelles', function (Request $r, Response $res) {
        $res->getBody()->write('<div style="padding:3rem;color:#eaeef2;background:#000;font-family:system-ui">
          <h1>Nouvelles</h1><p>Bientôt…</p></div>');
        return $res->withHeader('Content-Type','text/html; charset=utf-8');
    });

    $app->get('/a-propos', function (Request $r, Response $res) {
        $res->getBody()->write('<div style="padding:3rem;color:#eaeef2;background:#000;font-family:system-ui">
          <h1>À propos</h1><p>À venir.</p></div>');
        return $res->withHeader('Content-Type','text/html; charset=utf-8');
    });

    $app->get('/contact', function (Request $r, Response $res) {
        $res->getBody()->write('<div style="padding:3rem;color:#eaeef2;background:#000;font-family:system-ui">
          <h1>Contact</h1><p>Écris-moi: hello@dion.coach</p></div>');
        return $res->withHeader('Content-Type','text/html; charset=utf-8');
    });
};
