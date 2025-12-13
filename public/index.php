<?php
declare(strict_types=1);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

use Slim\Factory\AppFactory;

require __DIR__ . '/../vendor/autoload.php';
//require_once __DIR__ . '/vendor/autoload.php';
$app = AppFactory::create();

// Middlewares
$app->addRoutingMiddleware();
$app->addErrorMiddleware(true, true, true);

// Charger les routes séparées
(require __DIR__ . '/../src/routes.php')($app);

$app->run();
