<?php
declare(strict_types=1);

namespace App\Database;

final class TestPhysiqueRepository
{
    public static function getDefinitions(int $campId, ?int $testTypeId = null): array
    {
        $types = Database::fetchAll(
            "SELECT id, camp_id, name, description, sort_order
             FROM test_types
             WHERE is_active = 1 AND (camp_id IS NULL OR camp_id = ?)
             " . ($testTypeId ? "AND id = ?" : "") . "
             ORDER BY sort_order ASC, name ASC",
            $testTypeId ? [$campId, $testTypeId] : [$campId]
        );

        if (count($types) === 0) {
            return [];
        }

        $typeIds = array_map(fn($t) => (int)$t['id'], $types);
        $placeholders = implode(',', array_fill(0, count($typeIds), '?'));

        $metrics = Database::fetchAll(
            "SELECT tm.id, tm.test_type_id, tm.name, tm.label, tm.unit, tm.value_type, tm.calc_rule, tm.is_output, tm.sort_order
             FROM test_metrics tm
             WHERE tm.test_type_id IN ($placeholders)
             ORDER BY tm.sort_order ASC, tm.id ASC",
            $typeIds
        );

        $metricIds = array_map(fn($m) => (int)$m['id'], $metrics);
        $optionsByMetric = [];
        if (count($metricIds) > 0) {
            $mPlace = implode(',', array_fill(0, count($metricIds), '?'));
            $options = Database::fetchAll(
                "SELECT metric_id, value, label, sort_order
                 FROM test_metric_options
                 WHERE is_active = 1 AND metric_id IN ($mPlace)
                 ORDER BY sort_order ASC, id ASC",
                $metricIds
            );
            foreach ($options as $o) {
                $mid = (int)$o['metric_id'];
                if (!isset($optionsByMetric[$mid])) $optionsByMetric[$mid] = [];
                $optionsByMetric[$mid][] = [
                    'value' => $o['value'],
                    'label' => $o['label'],
                ];
            }
        }

        $metricsByType = [];
        foreach ($metrics as $m) {
            $mid = (int)$m['id'];
            $typeId = (int)$m['test_type_id'];
            if (!isset($metricsByType[$typeId])) $metricsByType[$typeId] = [];
            $metricsByType[$typeId][] = [
                'id' => $mid,
                'name' => $m['name'],
                'label' => $m['label'],
                'unit' => $m['unit'],
                'value_type' => $m['value_type'],
                'calc_rule' => $m['calc_rule'],
                'is_output' => (bool)$m['is_output'],
                'sort_order' => (int)$m['sort_order'],
                'options' => $optionsByMetric[$mid] ?? [],
            ];
        }

        $out = [];
        foreach ($types as $t) {
            $typeId = (int)$t['id'];
            $out[] = [
                'id' => $typeId,
                'camp_id' => $t['camp_id'] !== null ? (int)$t['camp_id'] : null,
                'name' => $t['name'],
                'description' => $t['description'] ?? '',
                'sort_order' => (int)$t['sort_order'],
                'metrics' => $metricsByType[$typeId] ?? [],
            ];
        }

        return $out;
    }

    public static function getResults(
        int $campId,
        int $userId,
        ?int $playerId,
        ?int $groupId,
        ?int $sessionId
    ): array {
        $params = [$campId, $userId];
        $where = "tr.camp_id = ? AND tr.created_by = ?";

        if ($playerId !== null) {
            $where .= " AND tr.player_id = ?";
            $params[] = $playerId;
        }

        if ($groupId !== null) {
            $where .= " AND gp.group_id = ?";
            $params[] = $groupId;
        }

        if ($sessionId === null) {
            $where .= " AND tr.session_id IS NULL";
        } else {
            $where .= " AND tr.session_id = ?";
            $params[] = $sessionId;
        }

        $joinGroup = $groupId !== null
            ? "JOIN camp_players cp ON cp.player_id = tr.player_id AND cp.camp_id = tr.camp_id
               JOIN group_players gp ON gp.camp_player_id = cp.id"
            : "";

        return Database::fetchAll(
            "SELECT tr.player_id, tr.test_type_id, tr.metric_id, tr.session_id, tr.value_number, tr.value_text, tr.updated_at
             FROM test_results tr
             $joinGroup
             WHERE $where
             ORDER BY tr.player_id ASC, tr.metric_id ASC",
            $params
        );
    }

    public static function getResultsForUser(
        int $campId,
        int $userId,
        ?int $testTypeId,
        ?int $groupId,
        ?int $sessionId
    ): array {
        $params = [$campId, $userId];
        $where = "tr.camp_id = ? AND tr.created_by = ?";

        if ($testTypeId !== null) {
            $where .= " AND tr.test_type_id = ?";
            $params[] = $testTypeId;
        }

        if ($groupId !== null) {
            $where .= " AND gp.group_id = ?";
            $params[] = $groupId;
        }

        if ($sessionId === null) {
            $where .= " AND tr.session_id IS NULL";
        } else {
            $where .= " AND tr.session_id = ?";
            $params[] = $sessionId;
        }

        $joinGroup = $groupId !== null
            ? "JOIN camp_players cp ON cp.player_id = tr.player_id AND cp.camp_id = tr.camp_id
               JOIN group_players gp ON gp.camp_player_id = cp.id"
            : "";

        return Database::fetchAll(
            "SELECT tr.player_id, tr.test_type_id, tr.metric_id, tr.session_id, tr.value_number, tr.value_text, tr.updated_at
             FROM test_results tr
             $joinGroup
             WHERE $where
             ORDER BY tr.player_id ASC, tr.metric_id ASC",
            $params
        );
    }

    public static function getResultsAll(
        int $campId,
        ?int $testTypeId,
        ?int $groupId,
        ?int $sessionId
    ): array {
        $params = [$campId];
        $where = "tr.camp_id = ?";

        if ($testTypeId !== null) {
            $where .= " AND tr.test_type_id = ?";
            $params[] = $testTypeId;
        }

        if ($groupId !== null) {
            $where .= " AND gp.group_id = ?";
            $params[] = $groupId;
        }

        if ($sessionId === null) {
            $where .= " AND tr.session_id IS NULL";
        } else {
            $where .= " AND tr.session_id = ?";
            $params[] = $sessionId;
        }

        $joinGroup = $groupId !== null
            ? "JOIN camp_players cp ON cp.player_id = tr.player_id AND cp.camp_id = tr.camp_id
               JOIN group_players gp ON gp.camp_player_id = cp.id"
            : "";

        return Database::fetchAll(
            "SELECT tr.player_id, tr.test_type_id, tr.metric_id, tr.session_id, tr.value_number, tr.value_text, tr.updated_at, tr.created_by
             FROM test_results tr
             $joinGroup
             WHERE $where
             ORDER BY tr.player_id ASC, tr.metric_id ASC",
            $params
        );
    }

    public static function upsertBatch(
        int $campId,
        int $userId,
        ?int $sessionId,
        array $items,
        ?int $allowedTestTypeId = null
    ): int {
        if (count($items) === 0) return 0;

        $playerIds = [];
        $metricIds = [];
        foreach ($items as $it) {
            $pid = (int)($it['player_id'] ?? 0);
            $mid = (int)($it['metric_id'] ?? 0);
            if ($pid > 0) $playerIds[$pid] = true;
            if ($mid > 0) $metricIds[$mid] = true;
        }
        $playerIds = array_keys($playerIds);
        $metricIds = array_keys($metricIds);
        if (count($playerIds) === 0 || count($metricIds) === 0) return 0;

        $pPlace = implode(',', array_fill(0, count($playerIds), '?'));
        $players = Database::fetchAll(
            "SELECT player_id FROM camp_players WHERE camp_id = ? AND player_id IN ($pPlace)",
            array_merge([$campId], $playerIds)
        );
        $allowedPlayers = [];
        foreach ($players as $p) $allowedPlayers[(int)$p['player_id']] = true;

        $mPlace = implode(',', array_fill(0, count($metricIds), '?'));
        $metrics = Database::fetchAll(
            "SELECT tm.id AS metric_id, tm.value_type, tm.calc_rule, tm.is_output, tt.id AS test_type_id
             FROM test_metrics tm
             JOIN test_types tt ON tt.id = tm.test_type_id
             WHERE tm.id IN ($mPlace) AND (tt.camp_id IS NULL OR tt.camp_id = ?)",
            array_merge($metricIds, [$campId])
        );
        $metricMap = [];
        foreach ($metrics as $m) {
            $metricMap[(int)$m['metric_id']] = [
                'test_type_id' => (int)$m['test_type_id'],
                'value_type' => $m['value_type'],
            ];
        }
        if (count($metricMap) === 0) return 0;

        $options = Database::fetchAll(
            "SELECT metric_id, value FROM test_metric_options WHERE is_active = 1 AND metric_id IN ($mPlace)",
            $metricIds
        );
        $optionMap = [];
        foreach ($options as $o) {
            $mid = (int)$o['metric_id'];
            if (!isset($optionMap[$mid])) $optionMap[$mid] = [];
            $optionMap[$mid][$o['value']] = true;
        }

        $rows = [];
        $deletePairs = [];
        $params = [];
        foreach ($items as $it) {
            $playerId = (int)($it['player_id'] ?? 0);
            $metricId = (int)($it['metric_id'] ?? 0);
            if ($playerId <= 0 || $metricId <= 0) continue;
            if (!isset($allowedPlayers[$playerId])) continue;
            if (!isset($metricMap[$metricId])) continue;
            if ($allowedTestTypeId !== null && $metricMap[$metricId]['test_type_id'] !== $allowedTestTypeId) continue;

            $valueType = $metricMap[$metricId]['value_type'];
            $valueNumber = $it['value_number'] ?? null;
            $valueText = $it['value_text'] ?? null;

            if ($valueType === 'text') {
                if ($valueText === null || $valueText === '') continue;
                if (isset($optionMap[$metricId]) && !isset($optionMap[$metricId][$valueText])) continue;
                $valueNumber = null;
            } elseif ($valueType === 'integer') {
                if ($valueNumber === null && $valueText !== null && is_numeric($valueText)) {
                    $valueNumber = (int)$valueText;
                }
                if ($valueNumber === null || !is_numeric($valueNumber)) continue;
                $valueNumber = (int)$valueNumber;
                $valueText = null;
            } else {
                if ($valueNumber === null && $valueText !== null && is_numeric($valueText)) {
                    $valueNumber = (float)$valueText;
                }
                if ($valueNumber === null || !is_numeric($valueNumber)) continue;
                $valueNumber = (float)$valueNumber;
                $valueText = null;
            }

            $testTypeId = $metricMap[$metricId]['test_type_id'];
            $rows[] = "(?, ?, ?, ?, ?, ?, ?, ?)";
            $params[] = $campId;
            $params[] = $playerId;
            $params[] = $testTypeId;
            $params[] = $sessionId;
            $params[] = $metricId;
            $params[] = $valueNumber;
            $params[] = $valueText;
            $params[] = $userId;

            if ($sessionId === null) {
                $deletePairs[] = [$playerId, $metricId];
            }
        }

        if (count($rows) === 0) return 0;

        if ($sessionId === null && count($deletePairs) > 0) {
            $clauses = [];
            $delParams = [$campId, $userId];
            foreach ($deletePairs as $pair) {
                $clauses[] = "(player_id = ? AND metric_id = ?)";
                $delParams[] = $pair[0];
                $delParams[] = $pair[1];
            }
            $delSql = "DELETE FROM test_results
                       WHERE camp_id = ? AND created_by = ? AND session_id IS NULL
                         AND (" . implode(' OR ', $clauses) . ")";
            Database::execute($delSql, $delParams);
        }

        $sql = "INSERT INTO test_results
                (camp_id, player_id, test_type_id, session_id, metric_id, value_number, value_text, created_by)
                VALUES " . implode(',', $rows) . " AS new
                ON DUPLICATE KEY UPDATE
                    value_number = new.value_number,
                    value_text = new.value_text,
                    updated_at = CURRENT_TIMESTAMP";

        Database::execute($sql, $params);
        return count($rows);
    }

    public static function deleteBatch(
        int $campId,
        int $userId,
        ?int $sessionId,
        array $items
    ): int {
        if (count($items) === 0) return 0;

        $clauses = [];
        $params = [$campId, $userId];
        $i = 0;
        foreach ($items as $it) {
            $playerId = (int)($it['player_id'] ?? 0);
            $metricId = (int)($it['metric_id'] ?? 0);
            if ($playerId <= 0 || $metricId <= 0) continue;

            $clauses[] = "(player_id = ? AND metric_id = ?)";
            $params[] = $playerId;
            $params[] = $metricId;
            $i++;
        }
        if ($i === 0) return 0;

        $sessionWhere = $sessionId === null ? "session_id IS NULL" : "session_id = ?";
        if ($sessionId !== null) $params[] = $sessionId;

        $sql = "DELETE FROM test_results
                WHERE camp_id = ? AND created_by = ? AND $sessionWhere
                  AND (" . implode(' OR ', $clauses) . ")";

        Database::execute($sql, $params);
        return $i;
    }
}
