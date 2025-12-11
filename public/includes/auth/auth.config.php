<?php

require_once __DIR__ . '/../../../vendor/autoload.php';

$auth0 = new \Auth0\SDK\Auth0([
    'domain' => 'dion.coach',
    'clientId' => 'bWZ4d4P0QPtyeernlkXz1hI8BSILJn05',
    'clientSecret' => 'C0aRexx3wXnbBAfoGhRVh0MikwAWEUPptNuvewcaHGMv_pGJVrHj8FSi9Hme0Wgv',
    'redirectUri' => 'https://dion.coach/callback.php',
    'cookieSecret' => 'a5b03e8a1d4c7df75c80e2fb9b5e46c9d3420c7ab37e6f081f93a2f9f7e6db36',
]);
