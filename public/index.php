<?php
declare(strict_types=1);

use Slim\Factory\AppFactory;
use App\Database\Database;
use App\Auth\MySQLSessionHandler;

require __DIR__ . '/../vendor/autoload.php';

// 1. Initialisation de la DB + session handler
$pdo = Database::pdo();
$handler = new MySQLSessionHandler($pdo);
session_set_save_handler($handler, true);

// 2. Configuration des cookies sécurisés (7 jours)
session_set_cookie_params([
    'lifetime' => 604800,
    'path' => '/',
    'secure' => true,
    'httponly' => true,
    'samesite' => 'Lax'
]);

// 3. Démarrer la session globalement
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 4. Création de l'application
$app = AppFactory::create();

// Middlewares
$app->addRoutingMiddleware();
$app->addErrorMiddleware(true, true, true);

// Charger les routes séparées
(require __DIR__ . '/../src/routes.php')($app);

$app->run();

// Forcer l'écriture de la session en BD après Slim
session_write_close();
