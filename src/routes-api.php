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
use App\Database\TestPhysiqueRepository;
use App\Database\AccessCodeRepository;

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

        // ============================================================
        // Test Physique
        // ============================================================

        // GET /api/camps/{id}/tests/definitions
        $api->get('/{id:[0-9]+}/tests/definitions', function (Request $r, Response $res, array $args) use ($json, $checkAccess) {
            $campId = (int)$args['id'];
            $userId = (int)($_SESSION['user_id'] ?? 0);
            $camp = $checkAccess($campId, $userId);
            if (!$camp) return $json($res, ['error' => 'Accès refusé'], 403);

            $defs = TestPhysiqueRepository::getDefinitions($campId);
            return $json($res, ['camp_id' => $campId, 'test_types' => $defs]);
        });

        // GET /api/camps/{id}/tests/results?player_id=...&group_id=...&session_id=...
        $api->get('/{id:[0-9]+}/tests/results', function (Request $r, Response $res, array $args) use ($json, $checkAccess) {
            $campId = (int)$args['id'];
            $userId = (int)($_SESSION['user_id'] ?? 0);
            $camp = $checkAccess($campId, $userId);
            if (!$camp) return $json($res, ['error' => 'Accès refusé'], 403);

            $q = $r->getQueryParams();
            $playerId = isset($q['player_id']) ? (int)$q['player_id'] : null;
            $groupId = isset($q['group_id']) ? (int)$q['group_id'] : null;
            $sessionId = isset($q['session_id']) && $q['session_id'] !== '' ? (int)$q['session_id'] : null;

            if ($playerId === 0) $playerId = null;
            if ($groupId === 0) $groupId = null;

            $results = TestPhysiqueRepository::getResults($campId, $userId, $playerId, $groupId, $sessionId);

            return $json($res, [
                'camp_id' => $campId,
                'player_id' => $playerId,
                'group_id' => $groupId,
                'session_id' => $sessionId,
                'results' => $results,
            ]);
        });

        // POST /api/camps/{id}/tests/results â€” Sync batch of results
        $api->post('/{id:[0-9]+}/tests/results', function (Request $r, Response $res, array $args) use ($json, $checkAccess) {
            $campId = (int)$args['id'];
            $userId = (int)($_SESSION['user_id'] ?? 0);
            $camp = $checkAccess($campId, $userId);
            if (!$camp) return $json($res, ['error' => 'Accès refusé'], 403);

            $body = json_decode((string)$r->getBody(), true);
            if (!is_array($body)) return $json($res, ['error' => 'Format invalide'], 400);

            $sessionId = isset($body['session_id']) && $body['session_id'] !== '' ? (int)$body['session_id'] : null;
            $items = $body['results'] ?? [];
            $deletes = $body['deletes'] ?? [];
            if (!is_array($items) || !is_array($deletes)) {
                return $json($res, ['error' => 'Format invalide'], 400);
            }

            $count = TestPhysiqueRepository::upsertBatch($campId, $userId, $sessionId, $items);
            $deleted = TestPhysiqueRepository::deleteBatch($campId, $userId, $sessionId, $deletes);

            return $json($res, [
                'ok' => true,
                'synced' => $count,
                'deleted' => $deleted,
                'server_time' => date('c'),
            ]);
        });

    })->add(new RequireAuthMiddleware());

    // ============================================================
    // Public access (code/token) for test-physique
    // ============================================================
    $app->group('/api/public/camps', function (RouteCollectorProxy $api) use ($json) {

        // POST /api/public/camps/{id}/access-code
        $api->post('/{id:[0-9]+}/access-code', function (Request $r, Response $res, array $args) use ($json) {
            $campId = (int)$args['id'];
            $body = json_decode((string)$r->getBody(), true);
            $code = is_array($body) ? (string)($body['code'] ?? '') : '';
            if ($code === '') return $json($res, ['error' => 'Code manquant'], 400);

            $access = AccessCodeRepository::validateCode($campId, $code);
            if (!$access) return $json($res, ['error' => 'Code invalide'], 403);

            $expiresAt = date('Y-m-d H:i:s', time() + (14 * 24 * 60 * 60));
            $token = AccessCodeRepository::createToken($campId, (int)$access['id'], $expiresAt);

            return $json($res, [
                'ok' => true,
                'token' => $token,
                'test_type_id' => $access['test_type_id'] !== null ? (int)$access['test_type_id'] : null,
                'user_id' => (int)$access['user_id'],
                'role' => $access['role'] ?? 'station',
                'expires_at' => $expiresAt,
            ]);
        });

        // GET /api/public/camps/{id}/test-physique/preload
        $api->get('/{id:[0-9]+}/test-physique/preload', function (Request $r, Response $res, array $args) use ($json) {
            $campId = (int)$args['id'];
            $token = $r->getHeaderLine('X-Access-Token');
            if ($token === '') {
                $q = $r->getQueryParams();
                $token = (string)($q['token'] ?? '');
            }
            if ($token === '') return $json($res, ['error' => 'Token manquant'], 401);

            $access = AccessCodeRepository::validateToken($campId, $token);
            if (!$access) return $json($res, ['error' => 'Token invalide'], 403);

            $camp = CampRepository::findById($campId);
            if (!$camp) return $json($res, ['error' => 'Camp introuvable'], 404);

            $testTypeId = $access['test_type_id'] !== null ? (int)$access['test_type_id'] : null;
            $userId = (int)$access['user_id'];
            $role = $access['role'] ?? 'station';

            $campData = [
                'id' => (int)$camp['id'],
                'name' => $camp['name'],
                'sport' => $camp['sport'],
            ];

            $sessions = Database::fetchAll(
                "SELECT id, name, session_date, session_order FROM camp_sessions WHERE camp_id = ? ORDER BY session_order ASC, id ASC",
                [$campId]
            );

            $groups = Database::fetchAll(
                "SELECT id, name, color, sort_order FROM camp_groups WHERE camp_id = ? ORDER BY sort_order ASC, id ASC",
                [$campId]
            );

            $players = Database::fetchAll(
                "SELECT cp.id AS camp_player_id, p.id AS player_id, p.first_name, p.last_name,
                        p.jersey_number, p.position, cp.status
                 FROM camp_players cp
                 JOIN players p ON cp.player_id = p.id
                 WHERE cp.camp_id = ?
                 ORDER BY p.last_name ASC, p.first_name ASC",
                [$campId]
            );

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

            $defs = TestPhysiqueRepository::getDefinitions($campId, $role === 'admin' ? null : $testTypeId);
            $results = $role === 'admin'
                ? TestPhysiqueRepository::getResultsAll($campId, $testTypeId, null, null)
                : TestPhysiqueRepository::getResultsForUser($campId, $userId, $testTypeId, null, null);

            return $json($res, [
                'camp' => $campData,
                'sessions' => $sessions,
                'groups' => $groups,
                'players' => $players,
                'test_types' => $defs,
                'results' => $results,
                'test_type_id' => $testTypeId,
                'role' => $role,
            ]);
        });

        // POST /api/public/camps/{id}/tests/results
        $api->post('/{id:[0-9]+}/tests/results', function (Request $r, Response $res, array $args) use ($json) {
            $campId = (int)$args['id'];
            $token = $r->getHeaderLine('X-Access-Token');
            if ($token === '') {
                $q = $r->getQueryParams();
                $token = (string)($q['token'] ?? '');
            }
            if ($token === '') return $json($res, ['error' => 'Token manquant'], 401);

            $access = AccessCodeRepository::validateToken($campId, $token);
            if (!$access) return $json($res, ['error' => 'Token invalide'], 403);

            $body = json_decode((string)$r->getBody(), true);
            if (!is_array($body)) return $json($res, ['error' => 'Format invalide'], 400);

            $sessionId = isset($body['session_id']) && $body['session_id'] !== '' ? (int)$body['session_id'] : null;
            $items = $body['results'] ?? [];
            $deletes = $body['deletes'] ?? [];
            if (!is_array($items) || !is_array($deletes)) {
                return $json($res, ['error' => 'Format invalide'], 400);
            }

            $userId = (int)$access['user_id'];
            $testTypeId = $access['test_type_id'] !== null ? (int)$access['test_type_id'] : null;
            $role = $access['role'] ?? 'station';

            if ($role === 'admin') {
                return $json($res, ['error' => 'Interdit'], 403);
            }

            $count = TestPhysiqueRepository::upsertBatch($campId, $userId, $sessionId, $items, $testTypeId);
            $deleted = TestPhysiqueRepository::deleteBatch($campId, $userId, $sessionId, $deletes);

            return $json($res, [
                'ok' => true,
                'synced' => $count,
                'deleted' => $deleted,
                'server_time' => date('c'),
            ]);
        });

        // GET /api/public/camps/{id}/tests/results?test_type_id=...&group_id=...&session_id=...
        $api->get('/{id:[0-9]+}/tests/results', function (Request $r, Response $res, array $args) use ($json) {
            $campId = (int)$args['id'];
            $token = $r->getHeaderLine('X-Access-Token');
            if ($token === '') {
                $q = $r->getQueryParams();
                $token = (string)($q['token'] ?? '');
            }
            if ($token === '') return $json($res, ['error' => 'Token manquant'], 401);

            $access = AccessCodeRepository::validateToken($campId, $token);
            if (!$access) return $json($res, ['error' => 'Token invalide'], 403);

            $role = $access['role'] ?? 'station';
            if ($role !== 'admin') return $json($res, ['error' => 'Interdit'], 403);

            $q = $r->getQueryParams();
            $testTypeId = isset($q['test_type_id']) ? (int)$q['test_type_id'] : null;
            $groupId = isset($q['group_id']) ? (int)$q['group_id'] : null;
            $sessionId = isset($q['session_id']) && $q['session_id'] !== '' ? (int)$q['session_id'] : null;

            if ($testTypeId === 0) $testTypeId = null;
            if ($groupId === 0) $groupId = null;

            $results = TestPhysiqueRepository::getResultsAll($campId, $testTypeId, $groupId, $sessionId);

            return $json($res, [
                'camp_id' => $campId,
                'test_type_id' => $testTypeId,
                'group_id' => $groupId,
                'session_id' => $sessionId,
                'results' => $results,
            ]);
        });

    });
};
