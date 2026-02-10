<?php
declare(strict_types=1);

use Slim\App;
use Slim\Routing\RouteCollectorProxy;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use App\Middleware\RequireAuthMiddleware;
use App\Middleware\RequireAdminMiddleware;
use Google\Cloud\Storage\StorageClient;

return function (App $app) {

    // =====================================================================
    // ROUTES PUBLIQUES
    // =====================================================================

    // Page d'accueil
    $app->get('/', function (Request $r, Response $res) {
        ob_start();
        include __DIR__ . '/../public/pages/home.php';
        $html = ob_get_clean();
        $res->getBody()->write($html);
        return $res->withHeader('Content-Type', 'text/html; charset=utf-8');
    });

    // Nouvelles — listing
    $app->get('/nouvelles', function (Request $r, Response $res) {
        ob_start();
        include __DIR__ . '/../public/pages/nouvelles.php';
        $html = ob_get_clean();
        $res->getBody()->write($html);
        return $res->withHeader('Content-Type', 'text/html; charset=utf-8');
    });

    // Nouvelles — vue article
    $app->get('/nouvelles/{slug}', function (Request $r, Response $res, array $args) {
        $_GET['slug'] = $args['slug'] ?? '';
        ob_start();
        include __DIR__ . '/../public/pages/nouvelles/view.php';
        $html = ob_get_clean();
        $res->getBody()->write($html);
        return $res->withHeader('Content-Type', 'text/html; charset=utf-8');
    });

    $app->get('/a-propos', function (Request $r, Response $res) {
        ob_start();
        include __DIR__ . '/../public/pages/apropos.php';
        $html = ob_get_clean();
        $res->getBody()->write($html);
        return $res->withHeader('Content-Type', 'text/html; charset=utf-8');
    });

    $app->map(['GET', 'POST'], '/contact', function (Request $r, Response $res) {
        ob_start();
        include __DIR__ . '/../public/pages/contact.php';
        $html = ob_get_clean();
        $res->getBody()->write($html);
        return $res->withHeader('Content-Type', 'text/html; charset=utf-8');
    });

    $app->get('/login', function (Request $r, Response $res) {
        require_once __DIR__ . '/Auth/getAuth.php';

        $url = getAuth()->login(
            null,
            null,
            ['scope' => 'openid profile email'],
            'code',
            true
        );

        return $res
            ->withHeader('Location', $url)
            ->withStatus(302);
    });

    $app->map(['GET', 'POST'], '/callback', function (Request $r, Response $res) {
        require_once __DIR__ . '/Auth/getAuth.php';
        $auth0 = getAuth();
        ob_start();
        include __DIR__ . '/../public/pages/callback.php';
        $html = ob_get_clean();

        $res->getBody()->write($html);
        return $res->withHeader('Content-Type', 'text/html; charset=utf-8');
    });

    $app->map(['GET', 'POST'], '/register', function (Request $r, Response $res) {
        ob_start();
        include __DIR__ . '/../public/pages/register.php';
        $html = ob_get_clean();

        $res->getBody()->write($html);
        return $res->withHeader('Content-Type', 'text/html; charset=utf-8');
    });

    $app->map(['GET', 'POST'], '/verify-email-required', function (Request $r, Response $res) {
        ob_start();
        include __DIR__ . '/../public/pages/verify-email-required.php';
        $html = ob_get_clean();

        $res->getBody()->write($html);
        return $res->withHeader('Content-Type', 'text/html; charset=utf-8');
    });

    $app->get('/privacy-policy', function ($req, $res) {
        ob_start();
        include __DIR__ . '/../public/pages/privacy.php';
        $html = ob_get_clean();
        $res->getBody()->write($html);
        return $res->withHeader('Content-Type', 'text/html');
    });

    $app->get('/data-deletion', function ($req, $res) {
        ob_start();
        include __DIR__ . '/../public/pages/data-deletion.php';
        $html = ob_get_clean();
        $res->getBody()->write($html);
        return $res->withHeader('Content-Type', 'text/html');
    });

    $app->get('/terms', function ($req, $res) {
        ob_start();
        include __DIR__ . '/../public/pages/terms.php';
        $html = ob_get_clean();
        $res->getBody()->write($html);
        return $res->withHeader('Content-Type', 'text/html');
    });

    // =====================================================================
    // ROUTE MEDIA — Images GCS (signed URL)
    // =====================================================================

    $app->get('/media/{path:.*}', function (Request $r, Response $res, array $args) {
        $path = $args['path'] ?? '';
        if ($path === '') {
            $res->getBody()->write('Missing path');
            return $res->withStatus(400);
        }

        $bucketName = getenv('GCS_BUCKET') ?: '';
        if ($bucketName === '') {
            $res->getBody()->write('Missing env: GCS_BUCKET');
            return $res->withStatus(500);
        }

        try {
            $storage = new StorageClient();
            $bucket  = $storage->bucket($bucketName);
            $object  = $bucket->object($path);

            if (!$object->exists()) {
                $res->getBody()->write('Not found');
                return $res->withStatus(404);
            }

            $signedUrl = $object->signedUrl(new \DateTimeImmutable('+15 minutes'), ['version' => 'v4']);

            return $res->withHeader('Location', $signedUrl)->withStatus(302);

        } catch (\Throwable $e) {
            $res->getBody()->write('Media error: ' . $e->getMessage());
            return $res->withStatus(500);
        }
    });

    // =====================================================================
    // ROUTES PROTÉGÉES (RequireAuthMiddleware)
    // =====================================================================

    $app->group('', function (RouteCollectorProxy $group) {

        $group->get('/dashboard', function (Request $r, Response $res) {
            ob_start();
            include __DIR__ . '/../public/pages/dashboard.php';
            $html = ob_get_clean();
            $res->getBody()->write($html);
            return $res->withHeader('Content-Type', 'text/html; charset=utf-8');
        });

        $group->get('/logout', function (Request $r, Response $res) {
            require_once __DIR__ . '/Auth/getAuth.php';
            $auth0 = getAuth();
            $auth0->clear();

            // Détruire la session PHP
            $_SESSION = [];
            if (ini_get('session.use_cookies')) {
                $p = session_get_cookie_params();
                setcookie(session_name(), '', time() - 42000,
                    $p['path'], $p['domain'], $p['secure'], $p['httponly']);
            }
            session_destroy();

            $logoutUrl = sprintf(
                'https://%s/v2/logout?client_id=%s&returnTo=%s',
                $auth0->configuration()->getDomain(),
                $auth0->configuration()->getClientId(),
                urlencode('https://dion.coach/')
            );
            return $res->withHeader('Location', $logoutUrl)->withStatus(302);
        });

    })->add(new RequireAuthMiddleware());

    // =====================================================================
    // ROUTES ADMIN (RequireAuthMiddleware + RequireAdminMiddleware)
    // =====================================================================

    $app->group('/admin', function (RouteCollectorProxy $admin) {

        // Liste des nouvelles (admin)
        $admin->get('/news', function (Request $r, Response $res) {
            ob_start();
            include __DIR__ . '/../public/pages/admin/news_list.php';
            $html = ob_get_clean();
            $res->getBody()->write($html);
            return $res->withHeader('Content-Type', 'text/html; charset=utf-8');
        });

        // Créer / modifier un article
        $admin->map(['GET', 'POST'], '/news/edit', function (Request $r, Response $res) {
            ob_start();
            include __DIR__ . '/../public/pages/admin/news_edit.php';
            $html = ob_get_clean();
            $res->getBody()->write($html);
            return $res->withHeader('Content-Type', 'text/html; charset=utf-8');
        });

        // Supprimer un article (+ image GCS)
        $admin->post('/news/delete', function (Request $r, Response $res) {
            $data = (array)($r->getParsedBody() ?? []);
            $id = (int)($data['id'] ?? 0);

            if ($id > 0) {
                $newsItem = \App\Database\Database::fetch("SELECT image_path FROM news WHERE id = ?", [$id]);

                if (!empty($newsItem['image_path'])) {
                    $bucketName = getenv('GCS_BUCKET') ?: '';
                    if ($bucketName !== '') {
                        try {
                            $storage = new StorageClient();
                            $bucket  = $storage->bucket($bucketName);
                            $object  = $bucket->object($newsItem['image_path']);

                            if ($object->exists()) {
                                $object->delete();
                            }
                        } catch (\Throwable $e) {
                            error_log("GCS Delete Error: " . $e->getMessage());
                        }
                    }
                }

                \App\Database\NewsRepository::delete($id);
            }

            return $res->withHeader('Location', '/admin/news?deleted=1')->withStatus(302);
        });

        // Upload image vers GCS
        $admin->post('/upload-image', function (Request $r, Response $res) {

            $json = function (Response $res, array $data, int $status = 200): Response {
                $payload = json_encode($data, JSON_UNESCAPED_SLASHES);
                $res->getBody()->write($payload);
                return $res->withHeader('Content-Type', 'application/json')->withStatus($status);
            };

            if (!isset($_FILES['image'])) {
                return $json($res, ['ok' => false, 'error' => 'Champ fichier manquant : image'], 400);
            }

            $file = $_FILES['image'];

            if (!empty($file['error'])) {
                return $json($res, ['ok' => false, 'error' => 'Erreur d\'upload', 'code' => (int)$file['error']], 400);
            }

            if (empty($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
                return $json($res, ['ok' => false, 'error' => 'Fichier uploadé invalide'], 400);
            }

            $finfo = new \finfo(FILEINFO_MIME_TYPE);
            $mime = $finfo->file($file['tmp_name']) ?: '';
            $allowed = [
                'image/png'  => 'png',
                'image/jpeg' => 'jpg',
                'image/webp' => 'webp',
                'image/gif'  => 'gif',
            ];
            if (!isset($allowed[$mime])) {
                return $json($res, ['ok' => false, 'error' => 'Type d\'image non supporté', 'mime' => $mime], 415);
            }

            $bucketName = getenv('GCS_BUCKET') ?: '';
            if ($bucketName === '') {
                return $json($res, ['ok' => false, 'error' => 'Missing env: GCS_BUCKET'], 500);
            }

            $prefix = trim((string)(getenv('GCS_PREFIX') ?: 'news'), '/');

            $ext = $allowed[$mime];
            $ymd = (new \DateTimeImmutable('now', new \DateTimeZone('America/Toronto')))->format('Y/m/d');
            $name = 'news_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;

            $objectPath = $prefix . '/' . $ymd . '/' . $name;

            try {
                $storage = new StorageClient();
                $bucket  = $storage->bucket($bucketName);

                $bucket->upload(
                    fopen($file['tmp_name'], 'r'),
                    [
                        'name' => $objectPath,
                        'metadata' => [
                            'contentType' => $mime,
                            'cacheControl' => 'public, max-age=3600',
                        ],
                    ]
                );

                $object = $bucket->object($objectPath);

                $signedUrl = $object->signedUrl(
                    new \DateTimeImmutable('+15 minutes'),
                    ['version' => 'v4']
                );

                return $json($res, [
                    'ok' => true,
                    'path' => $objectPath,
                    'url' => $signedUrl,
                ], 200);

            } catch (\Throwable $e) {
                return $json($res, [
                    'ok' => false,
                    'error' => 'GCS upload failed',
                    'message' => $e->getMessage(),
                ], 500);
            }
        });

    })->add(new RequireAdminMiddleware())->add(new RequireAuthMiddleware());

    // =====================================================================
    // ROUTES CAMPS DE SÉLECTION
    // =====================================================================

    $campsRoutes = require __DIR__ . '/routes-camps.php';
    $campsRoutes($app);
};
