<?php
declare(strict_types=1);

use Slim\App;
use Slim\Routing\RouteCollectorProxy;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use App\Middleware\RequireAuthMiddleware;
use App\Database\CampRepository;
use App\Database\Database;
use App\Database\EvaluatorRepository;
use App\Database\EvaluationRepository;
use App\Database\SkillRepository;

return function (App $app) {

    $json = function (Response $res, array $data, int $status = 200): Response {
        $payload = json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $res->getBody()->write($payload);
        return $res->withHeader('Content-Type', 'application/json')->withStatus($status);
    };

    $app->group('/api/camps', function (RouteCollectorProxy $api) use ($json) {

        /**
         * Check if current user has access to a camp (owner or active evaluator).
         */
        $checkAccess = function (int $campId, int $userId): ?array {
            $camp = CampRepository::findById($campId);
            if (!$camp) return null;
            $isOwner = (int)$camp['created_by'] === $userId;
            $isEval = !$isOwner && EvaluatorRepository::isEvaluator($campId, $userId);
            if (!$isOwner && !$isEval) return null;
            return $camp;
        };

        // GET /api/camps/{id}/data — Full camp payload (players, skills, sessions, groups)
        $api->get('/{id:[0-9]+}/data', function (Request $r, Response $res, array $args) use ($json, $checkAccess) {
            $campId = (int)$args['id'];
            $userId = (int)($_SESSION['user_id'] ?? 0);
            $camp = $checkAccess($campId, $userId);
            if (!$camp) return $json($res, ['error' => 'Accès refusé'], 403);

            // Camp info
            $campData = [
                'id' => (int)$camp['id'],
                'name' => $camp['name'],
                'sport' => $camp['sport'],
                'rating_min' => (int)$camp['rating_min'],
                'rating_max' => (int)$camp['rating_max'],
                'eval_mode' => $camp['eval_mode'],
            ];

            // Sessions
            $sessions = Database::fetchAll(
                "SELECT id, name, session_date, session_order FROM camp_sessions WHERE camp_id = ? ORDER BY session_order ASC, id ASC",
                [$campId]
            );

            // Groups
            $groups = Database::fetchAll(
                "SELECT id, name, color, sort_order FROM camp_groups WHERE camp_id = ? ORDER BY sort_order ASC, id ASC",
                [$campId]
            );

            // Players with group assignments
            $players = Database::fetchAll(
                "SELECT cp.id AS camp_player_id, p.id AS player_id, p.first_name, p.last_name,
                        p.jersey_number, p.position, cp.status
                 FROM camp_players cp
                 JOIN players p ON cp.player_id = p.id
                 WHERE cp.camp_id = ?
                 ORDER BY p.last_name ASC, p.first_name ASC",
                [$campId]
            );

            // Group memberships
            $groupPlayers = Database::fetchAll(
                "SELECT gp.camp_player_id, gp.group_id
                 FROM group_players gp
                 JOIN camp_groups cg ON gp.group_id = cg.id
                 WHERE cg.camp_id = ?",
                [$campId]
            );
            $gpMap = [];
            foreach ($groupPlayers as $gp) {
                $gpMap[(int)$gp['camp_player_id']] = (int)$gp['group_id'];
            }
            foreach ($players as &$p) {
                $p['group_id'] = $gpMap[(int)$p['camp_player_id']] ?? null;
            }
            unset($p);

            // Skills
            $skillCategories = SkillRepository::getCategoriesWithSkills($campId);

            return $json($res, [
                'camp' => $campData,
                'sessions' => $sessions,
                'groups' => $groups,
                'players' => $players,
                'skillCategories' => $skillCategories,
            ]);
        });

        // GET /api/camps/{id}/evaluations — Current user's evaluations
        $api->get('/{id:[0-9]+}/evaluations', function (Request $r, Response $res, array $args) use ($json, $checkAccess) {
            $campId = (int)$args['id'];
            $userId = (int)($_SESSION['user_id'] ?? 0);
            $camp = $checkAccess($campId, $userId);
            if (!$camp) return $json($res, ['error' => 'Accès refusé'], 403);

            $evals = EvaluationRepository::findByUserForCamp($campId, $userId);

            // Convert to key-value map: "sessionId-campPlayerId-skillId" => {rating, comment}
            $map = [];
            foreach ($evals as $e) {
                $key = $e['session_id'] . '-' . $e['camp_player_id'] . '-' . $e['skill_id'];
                $map[$key] = [
                    'rating' => (int)$e['rating'],
                    'comment' => $e['comment'] ?? '',
                    'timestamp' => $e['evaluated_at'],
                ];
            }

            return $json($res, ['evaluations' => $map]);
        });

        // POST /api/camps/{id}/evaluations — Sync batch of evaluations
        $api->post('/{id:[0-9]+}/evaluations', function (Request $r, Response $res, array $args) use ($json, $checkAccess) {
            $campId = (int)$args['id'];
            $userId = (int)($_SESSION['user_id'] ?? 0);
            $camp = $checkAccess($campId, $userId);
            if (!$camp) return $json($res, ['error' => 'Accès refusé'], 403);

            $body = json_decode((string)$r->getBody(), true);
            $items = $body['evaluations'] ?? [];
            $deletes = $body['deletes'] ?? [];

            if (!is_array($items)) {
                return $json($res, ['error' => 'Format invalide'], 400);
            }

            // Validate rating range
            $rMin = (int)$camp['rating_min'];
            $rMax = (int)$camp['rating_max'];
            foreach ($items as &$item) {
                $item['rating'] = max($rMin, min($rMax, (int)($item['rating'] ?? 0)));
            }
            unset($item);

            $count = EvaluationRepository::upsertBatch($items, $userId);

            // Process deletes
            $deleteCount = 0;
            if (is_array($deletes)) {
                foreach ($deletes as $d) {
                    $sId = (int)($d['session_id'] ?? 0);
                    $cpId = (int)($d['camp_player_id'] ?? 0);
                    $skId = (int)($d['skill_id'] ?? 0);
                    if ($sId > 0 && $cpId > 0 && $skId > 0) {
                        EvaluationRepository::deleteEval($sId, $cpId, $skId, $userId);
                        $deleteCount++;
                    }
                }
            }

            return $json($res, [
                'ok' => true,
                'synced' => $count,
                'deleted' => $deleteCount,
                'server_time' => date('c'),
            ]);
        });

        // GET /api/camps/{id}/results — Aggregated results (all evaluators)
        $api->get('/{id:[0-9]+}/results', function (Request $r, Response $res, array $args) use ($json, $checkAccess) {
            $campId = (int)$args['id'];
            $userId = (int)($_SESSION['user_id'] ?? 0);
            $camp = $checkAccess($campId, $userId);
            if (!$camp) return $json($res, ['error' => 'Accès refusé'], 403);

            $results = EvaluationRepository::getResultsForCamp($campId);

            return $json($res, [
                'results' => $results,
                'rating_min' => (int)$camp['rating_min'],
                'rating_max' => (int)$camp['rating_max'],
            ]);
        });

    })->add(new RequireAuthMiddleware());
};
