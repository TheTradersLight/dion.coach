<?php
require_once __DIR__ . '/vendor/autoload.php';



$userInfo = $auth0->getUser();

if (!$userInfo) {
    header('Location: /login.php');
    exit;
}
