<?php
declare(strict_types=1);

use Slim\App;
use Slim\Routing\RouteCollectorProxy;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use App\Middleware\RequireAuthMiddleware;

return function (App $app) {

    $app->group('/camps', function (RouteCollectorProxy $camps) {

        // Helper pour inclure une page avec camp_id
        $includePage = function (string $page) {
            return function (Request $r, Response $res, array $args) use ($page) {
                $_GET['camp_id'] = $args['id'] ?? '';
                ob_start();
                include __DIR__ . '/../public/pages/camps/' . $page;
                $html = ob_get_clean();
                $res->getBody()->write($html);
                return $res->withHeader('Content-Type', 'text/html; charset=utf-8');
            };
        };

        // Liste des camps
        $camps->get('', function (Request $r, Response $res) {
            ob_start();
            include __DIR__ . '/../public/pages/camps/index.php';
            $html = ob_get_clean();
            $res->getBody()->write($html);
            return $res->withHeader('Content-Type', 'text/html; charset=utf-8');
        });

        // Formulaire de création
        $camps->map(['GET', 'POST'], '/create', function (Request $r, Response $res) {
            ob_start();
            include __DIR__ . '/../public/pages/camps/create.php';
            $html = ob_get_clean();
            $res->getBody()->write($html);
            return $res->withHeader('Content-Type', 'text/html; charset=utf-8');
        });

        // Vue d'ensemble d'un camp (hub)
        $camps->get('/{id:[0-9]+}', $includePage('view.php'));

        // Édition d'un camp
        $camps->map(['GET', 'POST'], '/{id:[0-9]+}/edit', $includePage('edit.php'));

        // Suppression d'un camp
        $camps->post('/{id:[0-9]+}/delete', function (Request $r, Response $res, array $args) {
            $campId = (int)$args['id'];
            $userId = (int)($_SESSION['user_id'] ?? 0);

            $camp = \App\Database\CampRepository::findById($campId);
            if ($camp && (int)$camp['created_by'] === $userId) {
                \App\Database\CampRepository::delete($campId);
            }

            return $res->withHeader('Location', '/camps?deleted=1')->withStatus(302);
        });

        // --- Séances ---
        $camps->map(['GET', 'POST'], '/{id:[0-9]+}/sessions', $includePage('sessions.php'));

        // --- Joueurs ---
        $camps->map(['GET', 'POST'], '/{id:[0-9]+}/players', $includePage('players.php'));

        // --- Compétences ---
        $camps->map(['GET', 'POST'], '/{id:[0-9]+}/skills', $includePage('skills.php'));

        // --- Groupes ---
        $camps->map(['GET', 'POST'], '/{id:[0-9]+}/groups', $includePage('groups.php'));

        // --- Évaluateurs ---
        $camps->map(['GET', 'POST'], '/{id:[0-9]+}/evaluators', $includePage('evaluators.php'));
        $camps->map(['GET', 'POST'], '/{id:[0-9]+}/access-codes', $includePage('access-codes.php'));

        // --- Évaluation ---
        $camps->get('/{id:[0-9]+}/evaluate', $includePage('evaluate.php'));
        $camps->get('/{id:[0-9]+}/test-physique', $includePage('test-physique.php'));
        $camps->get('/{id:[0-9]+}/test-physique-results', $includePage('test-physique-results.php'));

        // --- Résultats ---
        $camps->get('/{id:[0-9]+}/results', $includePage('results.php'));

    })->add(new RequireAuthMiddleware());
};
