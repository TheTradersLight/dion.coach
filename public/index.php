<?php
declare(strict_types=1);

use Slim\Factory\AppFactory;

require __DIR__ . '/../vendor/autoload.php';

$app = AppFactory::create();

// Middlewares
$app->addRoutingMiddleware();
$app->addErrorMiddleware(true, true, true);

// Charger les routes séparées
(require __DIR__ . '/../src/routes.php')($app);

$app->run();
