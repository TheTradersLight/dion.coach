<?php
declare(strict_types=1);

namespace App\Database;

final class PlayerRepository
{
    public static function findByCamp(int $campId): array
    {
        return Database::fetchAll(
            "SELECT p.id, p.first_name, p.last_name, p.date_of_birth, p.jersey_number, p.position, p.notes,
                    cp.id AS camp_player_id, cp.status, cp.transferred_to_camp_id,
                    cg.name AS group_name, cg.color AS group_color
             FROM camp_players cp
             JOIN players p ON p.id = cp.player_id
             LEFT JOIN group_players gp ON gp.camp_player_id = cp.id
             LEFT JOIN camp_groups cg ON cg.id = gp.group_id
             WHERE cp.camp_id = ?
             ORDER BY p.last_name ASC, p.first_name ASC",
            [$campId]
        );
    }

    public static function findById(int $id): ?array
    {
        $row = Database::fetch("SELECT * FROM players WHERE id = ?", [$id]);
        return $row ?: null;
    }

    public static function create(array $data): int
    {
        Database::execute(
            "INSERT INTO players (first_name, last_name, date_of_birth, jersey_number, position, notes)
             VALUES (?, ?, ?, ?, ?, ?)",
            [
                (string)($data['first_name'] ?? ''),
                (string)($data['last_name'] ?? ''),
                !empty($data['date_of_birth']) ? $data['date_of_birth'] : null,
                $data['jersey_number'] ?? null,
                $data['position'] ?? null,
                $data['notes'] ?? null,
            ]
        );
        return (int)Database::lastId();
    }

    public static function addToCamp(int $playerId, int $campId): int
    {
        Database::execute(
            "INSERT IGNORE INTO camp_players (camp_id, player_id, status) VALUES (?, ?, 'active')",
            [$campId, $playerId]
        );
        return (int)Database::lastId();
    }

    public static function cutFromCamp(int $campPlayerId): void
    {
        Database::execute(
            "UPDATE camp_players SET status = 'cut' WHERE id = ?",
            [$campPlayerId]
        );
    }

    public static function reinstateInCamp(int $campPlayerId): void
    {
        Database::execute(
            "UPDATE camp_players SET status = 'active' WHERE id = ?",
            [$campPlayerId]
        );
    }

    public static function removeFromCamp(int $campPlayerId): void
    {
        Database::execute("DELETE FROM camp_players WHERE id = ?", [$campPlayerId]);
    }

    public static function generateTestPlayers(int $campId, int $count = 30): void
    {
        $prenoms = [
            'Alexis','Samuel','Nathan','Gabriel','William','Olivier','Thomas','Félix',
            'Raphaël','Mathis','Émile','Antoine','Jacob','Xavier','Édouard','Julien',
            'Zachary','Hugo','Simon','Louis','Maxime','Étienne','Vincent','Cédric',
            'Philippe','Benoît','Tristan','Marc-Antoine','Charles','Sébastien',
            'Léo','Noah','Adam','Liam','Benjamin','Arnaud','Jérémy','Mathieu',
            'Alexandre','Nicolas',
        ];
        $noms = [
            'Tremblay','Gagnon','Bouchard','Côté','Fortin','Gauthier','Morin','Lavoie',
            'Roy','Pelletier','Bélanger','Lévesque','Bergeron','Leblanc','Girard','Simard',
            'Boucher','Ouellet','Poirier','Beaulieu','Cloutier','Dubois','Deschênes','Plante',
            'Demers','Lachance','Martel','Savard','Therrien','Leclerc',
            'Gervais','Thibault','Dufour','Larouche','Ménard','Picard','Goulet','Paquette',
            'Champagne','Nadeau',
        ];
        $positions = ['Attaquant','Défenseur','Gardien','Centre','Ailier gauche','Ailier droit'];

        $count = min($count, 60);

        for ($i = 0; $i < $count; $i++) {
            $firstName = $prenoms[array_rand($prenoms)];
            $lastName = $noms[array_rand($noms)];
            $jersey = (string)($i + 1);
            $position = $positions[array_rand($positions)];

            $playerId = self::create([
                'first_name'    => $firstName,
                'last_name'     => $lastName,
                'jersey_number' => $jersey,
                'position'      => $position,
            ]);

            self::addToCamp($playerId, $campId);
        }
    }

    public static function getCampPlayer(int $campPlayerId): ?array
    {
        $row = Database::fetch(
            "SELECT cp.*, p.first_name, p.last_name
             FROM camp_players cp
             JOIN players p ON p.id = cp.player_id
             WHERE cp.id = ?",
            [$campPlayerId]
        );
        return $row ?: null;
    }
}
