<?php
declare(strict_types=1);

namespace App\Database;

final class GroupRepository
{
    public static function findByCamp(int $campId): array
    {
        return Database::fetchAll(
            "SELECT * FROM camp_groups WHERE camp_id = ? ORDER BY sort_order ASC, id ASC",
            [$campId]
        );
    }

    public static function findById(int $id): ?array
    {
        $row = Database::fetch("SELECT * FROM camp_groups WHERE id = ?", [$id]);
        return $row ?: null;
    }

    public static function create(array $data): int
    {
        $nextOrder = Database::fetch(
            "SELECT COALESCE(MAX(sort_order), 0) + 1 AS next_order FROM camp_groups WHERE camp_id = ?",
            [(int)$data['camp_id']]
        );

        Database::execute(
            "INSERT INTO camp_groups (camp_id, name, color, sort_order) VALUES (?, ?, ?, ?)",
            [
                (int)$data['camp_id'],
                (string)($data['name'] ?? ''),
                $data['color'] ?? null,
                (int)($nextOrder['next_order'] ?? 1),
            ]
        );
        return (int)Database::lastId();
    }

    public static function update(int $id, array $data): void
    {
        Database::execute(
            "UPDATE camp_groups SET name = ?, color = ? WHERE id = ?",
            [(string)($data['name'] ?? ''), $data['color'] ?? null, $id]
        );
    }

    public static function delete(int $id): void
    {
        Database::execute("DELETE FROM camp_groups WHERE id = ?", [$id]);
    }

    public static function getPlayersInGroup(int $groupId): array
    {
        return Database::fetchAll(
            "SELECT p.id, p.first_name, p.last_name, p.jersey_number, p.position,
                    cp.id AS camp_player_id, cp.status
             FROM group_players gp
             JOIN camp_players cp ON cp.id = gp.camp_player_id
             JOIN players p ON p.id = cp.player_id
             WHERE gp.group_id = ?
             ORDER BY p.last_name ASC",
            [$groupId]
        );
    }

    public static function getUnassignedPlayers(int $campId): array
    {
        return Database::fetchAll(
            "SELECT p.id, p.first_name, p.last_name, p.jersey_number, p.position,
                    cp.id AS camp_player_id
             FROM camp_players cp
             JOIN players p ON p.id = cp.player_id
             LEFT JOIN group_players gp ON gp.camp_player_id = cp.id
             WHERE cp.camp_id = ? AND cp.status = 'active' AND gp.id IS NULL
             ORDER BY p.last_name ASC",
            [$campId]
        );
    }

    public static function assignPlayer(int $groupId, int $campPlayerId): void
    {
        // Remove from any other group first
        Database::execute("DELETE FROM group_players WHERE camp_player_id = ?", [$campPlayerId]);
        // Add to new group
        Database::execute(
            "INSERT INTO group_players (group_id, camp_player_id) VALUES (?, ?)",
            [$groupId, $campPlayerId]
        );
    }

    public static function removePlayer(int $campPlayerId): void
    {
        Database::execute("DELETE FROM group_players WHERE camp_player_id = ?", [$campPlayerId]);
    }
}
