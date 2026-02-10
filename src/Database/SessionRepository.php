<?php
declare(strict_types=1);

namespace App\Database;

final class SessionRepository
{
    public static function findByCamp(int $campId): array
    {
        return Database::fetchAll(
            "SELECT * FROM camp_sessions WHERE camp_id = ? ORDER BY session_order ASC, id ASC",
            [$campId]
        );
    }

    public static function findById(int $id): ?array
    {
        $row = Database::fetch("SELECT * FROM camp_sessions WHERE id = ?", [$id]);
        return $row ?: null;
    }

    public static function create(array $data): int
    {
        $nextOrder = Database::fetch(
            "SELECT COALESCE(MAX(session_order), 0) + 1 AS next_order FROM camp_sessions WHERE camp_id = ?",
            [(int)$data['camp_id']]
        );

        Database::execute(
            "INSERT INTO camp_sessions (camp_id, name, session_date, session_order, status)
             VALUES (?, ?, ?, ?, ?)",
            [
                (int)$data['camp_id'],
                (string)($data['name'] ?? ''),
                !empty($data['session_date']) ? $data['session_date'] : null,
                (int)($nextOrder['next_order'] ?? 1),
                (string)($data['status'] ?? 'planned'),
            ]
        );
        return (int)Database::lastId();
    }

    public static function update(int $id, array $data): void
    {
        Database::execute(
            "UPDATE camp_sessions SET name = ?, session_date = ?, status = ? WHERE id = ?",
            [
                (string)($data['name'] ?? ''),
                !empty($data['session_date']) ? $data['session_date'] : null,
                (string)($data['status'] ?? 'planned'),
                $id,
            ]
        );
    }

    public static function delete(int $id): void
    {
        Database::execute("DELETE FROM camp_sessions WHERE id = ?", [$id]);
    }
}
