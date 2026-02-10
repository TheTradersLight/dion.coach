<?php
declare(strict_types=1);

namespace App\Database;

final class CampRepository
{
    public static function findAll(int $userId): array
    {
        return Database::fetchAll(
            "SELECT id, name, sport, season, status, eval_mode, rating_min, rating_max, created_at
             FROM camps
             WHERE created_by = ?
             ORDER BY created_at DESC",
            [$userId]
        );
    }

    public static function findById(int $id): ?array
    {
        $row = Database::fetch(
            "SELECT * FROM camps WHERE id = ?",
            [$id]
        );
        return $row ?: null;
    }

    public static function create(array $data): int
    {
        Database::execute(
            "INSERT INTO camps (name, description, sport, season, status, eval_mode, rating_min, rating_max, created_by)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)",
            [
                (string)($data['name'] ?? ''),
                $data['description'] ?? null,
                (string)($data['sport'] ?? ''),
                (string)($data['season'] ?? ''),
                (string)($data['status'] ?? 'draft'),
                (string)($data['eval_mode'] ?? 'cumulative'),
                (int)($data['rating_min'] ?? 1),
                (int)($data['rating_max'] ?? 5),
                (int)$data['created_by'],
            ]
        );
        return (int)Database::lastId();
    }

    public static function update(int $id, array $data): void
    {
        Database::execute(
            "UPDATE camps
             SET name = ?, description = ?, sport = ?, season = ?, status = ?, eval_mode = ?, rating_min = ?, rating_max = ?
             WHERE id = ?",
            [
                (string)($data['name'] ?? ''),
                $data['description'] ?? null,
                (string)($data['sport'] ?? ''),
                (string)($data['season'] ?? ''),
                (string)($data['status'] ?? 'draft'),
                (string)($data['eval_mode'] ?? 'cumulative'),
                (int)($data['rating_min'] ?? 1),
                (int)($data['rating_max'] ?? 5),
                $id,
            ]
        );
    }

    public static function delete(int $id): void
    {
        Database::execute("DELETE FROM camps WHERE id = ?", [$id]);
    }

    public static function countStats(int $campId): array
    {
        $players = Database::fetch(
            "SELECT COUNT(*) AS c FROM camp_players WHERE camp_id = ? AND status = 'active'",
            [$campId]
        );
        $sessions = Database::fetch(
            "SELECT COUNT(*) AS c FROM camp_sessions WHERE camp_id = ?",
            [$campId]
        );
        $skills = Database::fetch(
            "SELECT COUNT(*) AS c FROM skills s
             JOIN skill_categories sc ON s.category_id = sc.id
             WHERE sc.camp_id = ?",
            [$campId]
        );
        $groups = Database::fetch(
            "SELECT COUNT(*) AS c FROM camp_groups WHERE camp_id = ?",
            [$campId]
        );
        $evaluators = Database::fetch(
            "SELECT COUNT(*) AS c FROM camp_evaluators WHERE camp_id = ? AND status IN ('invited', 'active')",
            [$campId]
        );

        return [
            'players'    => (int)($players['c'] ?? 0),
            'sessions'   => (int)($sessions['c'] ?? 0),
            'skills'     => (int)($skills['c'] ?? 0),
            'groups'     => (int)($groups['c'] ?? 0),
            'evaluators' => (int)($evaluators['c'] ?? 0),
        ];
    }
}
