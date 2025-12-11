<?php
require_once __DIR__ . '/vendor/autoload.php';


$auth0->logout();

// Redirige vers ta home page après déconnexion
header('Location: /');
exit;
