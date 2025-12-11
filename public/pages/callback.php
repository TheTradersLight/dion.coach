<?php
require_once __DIR__ . '/vendor/autoload.php';


$auth0->exchange();

header('Location: /dashboard.php');
exit;
