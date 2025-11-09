// Placeholder for additional routes.
<?php
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

$app->get('/', function (Request $request, Response $response) {
    $html = file_get_contents(__DIR__ . '/../public/home.html');
    $response->getBody()->write($html);
    return $response;
});
