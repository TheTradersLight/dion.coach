<?php
declare(strict_types=1);

namespace App\Database;

final class EvaluatorRepository
{
    public static function findByCamp(int $campId): array
    {
        return Database::fetchAll(
            "SELECT ce.*, u.email AS user_email
             FROM camp_evaluators ce
             LEFT JOIN users u ON ce.user_id = u.id
             WHERE ce.camp_id = ?
             ORDER BY ce.invited_at DESC",
            [$campId]
        );
    }

    public static function findById(int $id): ?array
    {
        $row = Database::fetch("SELECT * FROM camp_evaluators WHERE id = ?", [$id]);
        return $row ?: null;
    }

    public static function invite(int $campId, string $email): int
    {
        // Check if already exists
        $existing = Database::fetch(
            "SELECT id, status FROM camp_evaluators WHERE camp_id = ? AND email = ?",
            [$campId, $email]
        );

        if ($existing) {
            // Re-invite if revoked
            if ($existing['status'] === 'revoked') {
                Database::execute(
                    "UPDATE camp_evaluators SET status = 'invited', invited_at = NOW(), accepted_at = NULL WHERE id = ?",
                    [(int)$existing['id']]
                );
            }
            return (int)$existing['id'];
        }

        Database::execute(
            "INSERT INTO camp_evaluators (camp_id, email, status) VALUES (?, ?, 'invited')",
            [$campId, $email]
        );
        return (int)Database::lastId();
    }

    public static function revoke(int $id): void
    {
        Database::execute(
            "UPDATE camp_evaluators SET status = 'revoked' WHERE id = ?",
            [$id]
        );
    }

    public static function activate(int $id): void
    {
        Database::execute(
            "UPDATE camp_evaluators SET status = 'active', accepted_at = NOW() WHERE id = ?",
            [$id]
        );
    }

    public static function delete(int $id): void
    {
        Database::execute("DELETE FROM camp_evaluators WHERE id = ?", [$id]);
    }

    public static function isEvaluator(int $campId, int $userId): bool
    {
        $row = Database::fetch(
            "SELECT id FROM camp_evaluators WHERE camp_id = ? AND user_id = ? AND status = 'active'",
            [$campId, $userId]
        );
        return $row !== null;
    }

    /**
     * Auto-accept: link a user to any pending invitations matching their email.
     */
    public static function autoAcceptByEmail(string $email, int $userId): void
    {
        Database::execute(
            "UPDATE camp_evaluators SET user_id = ?, status = 'active', accepted_at = NOW()
             WHERE email = ? AND user_id IS NULL AND status = 'invited'",
            [$userId, $email]
        );
    }

    /**
     * Find camps where a user is an active evaluator (invited by someone else).
     */
    public static function findCampsForEvaluator(int $userId): array
    {
        return Database::fetchAll(
            "SELECT c.*, ce.status AS eval_status
             FROM camps c
             JOIN camp_evaluators ce ON ce.camp_id = c.id
             WHERE ce.user_id = ? AND ce.status = 'active'
             ORDER BY c.created_at DESC",
            [$userId]
        );
    }

    public static function countByCamp(int $campId): int
    {
        $row = Database::fetch(
            "SELECT COUNT(*) AS c FROM camp_evaluators WHERE camp_id = ? AND status IN ('invited', 'active')",
            [$campId]
        );
        return (int)($row['c'] ?? 0);
    }

    /**
     * Get upcoming sessions for all camps where user is owner or active evaluator.
     * Returns sessions sorted by date (nearest first), with camp info.
     */
    public static function getSessionsForUser(int $userId): array
    {
        return Database::fetchAll(
            "SELECT cs.id AS session_id, cs.name AS session_name, cs.session_date, cs.session_order, cs.status AS session_status,
                    c.id AS camp_id, c.name AS camp_name, c.sport, c.season, c.status AS camp_status,
                    'owner' AS role
             FROM camp_sessions cs
             JOIN camps c ON cs.camp_id = c.id
             WHERE c.created_by = ? AND c.status IN ('active', 'draft')

             UNION ALL

             SELECT cs.id AS session_id, cs.name AS session_name, cs.session_date, cs.session_order, cs.status AS session_status,
                    c.id AS camp_id, c.name AS camp_name, c.sport, c.season, c.status AS camp_status,
                    'evaluator' AS role
             FROM camp_sessions cs
             JOIN camps c ON cs.camp_id = c.id
             JOIN camp_evaluators ce ON ce.camp_id = c.id
             WHERE ce.user_id = ? AND ce.status = 'active' AND c.status IN ('active', 'draft')

             ORDER BY session_date IS NULL, session_date ASC, session_order ASC",
            [$userId, $userId]
        );
    }
}
