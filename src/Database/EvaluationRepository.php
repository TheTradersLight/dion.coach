<?php
declare(strict_types=1);

namespace App\Database;

final class EvaluationRepository
{
    public static function upsert(int $sessionId, int $campPlayerId, int $skillId, int $evaluatedBy, int $rating, ?string $comment): void
    {
        Database::execute(
            "INSERT INTO evaluations (session_id, camp_player_id, skill_id, evaluated_by, rating, comment)
             VALUES (?, ?, ?, ?, ?, ?) AS new
             ON DUPLICATE KEY UPDATE rating = new.rating, comment = new.comment, updated_at = NOW()",
            [$sessionId, $campPlayerId, $skillId, $evaluatedBy, $rating, $comment]
        );
    }

    public static function upsertBatch(array $items, int $evaluatedBy): int
    {
        $count = 0;
        foreach ($items as $item) {
            $sessionId = (int)($item['session_id'] ?? 0);
            $campPlayerId = (int)($item['camp_player_id'] ?? 0);
            $skillId = (int)($item['skill_id'] ?? 0);
            $rating = (int)($item['rating'] ?? 0);
            $comment = $item['comment'] ?? null;

            if ($sessionId > 0 && $campPlayerId > 0 && $skillId > 0 && $rating > 0) {
                self::upsert($sessionId, $campPlayerId, $skillId, $evaluatedBy, $rating, $comment);
                $count++;
            }
        }
        return $count;
    }

    /**
     * Get all evaluations by a specific user for a camp.
     */
    public static function findByUserForCamp(int $campId, int $userId): array
    {
        return Database::fetchAll(
            "SELECT e.session_id, e.camp_player_id, e.skill_id, e.rating, e.comment, e.evaluated_at
             FROM evaluations e
             JOIN camp_sessions cs ON e.session_id = cs.id
             WHERE cs.camp_id = ? AND e.evaluated_by = ?",
            [$campId, $userId]
        );
    }

    /**
     * Get aggregated results for a camp (all evaluators).
     */
    public static function getResultsForCamp(int $campId): array
    {
        return Database::fetchAll(
            "SELECT e.camp_player_id, e.skill_id, e.session_id,
                    AVG(e.rating) AS avg_rating,
                    COUNT(DISTINCT e.evaluated_by) AS evaluator_count
             FROM evaluations e
             JOIN camp_sessions cs ON e.session_id = cs.id
             WHERE cs.camp_id = ?
             GROUP BY e.camp_player_id, e.skill_id, e.session_id",
            [$campId]
        );
    }

    /**
     * Delete a specific evaluation.
     */
    public static function deleteEval(int $sessionId, int $campPlayerId, int $skillId, int $evaluatedBy): void
    {
        Database::execute(
            "DELETE FROM evaluations WHERE session_id = ? AND camp_player_id = ? AND skill_id = ? AND evaluated_by = ?",
            [$sessionId, $campPlayerId, $skillId, $evaluatedBy]
        );
    }
}
