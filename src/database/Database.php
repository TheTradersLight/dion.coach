<?php
namespace App;

use PDO;
use PDOException;
use Exception;

class Database
{
    private static ?PDO $pdo = null;

    /**
     * Initialise la connexion PDO (singleton)
     */
    private static function init(): void
    {
        if (self::$pdo !== null) {
            return;
        }

        // Variables d'environnement (local + Cloud Run)
        $dbHost = getenv('DB_HOST') ?: '34.23.43.4';
        $dbName = getenv('DB_NAME') ?: 'dioncoach';
        $dbUser = getenv('DB_USER') ?: 'dioncoach_web';
        $dbPass = getenv('DB_PASS') ?: '3r$hS8j[ztjn{00r';

        $dsn = "mysql:host={$dbHost};dbname={$dbName};charset=utf8mb4";

        try {
            self::$pdo = new PDO($dsn, $dbUser, $dbPass, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]);
        } catch (PDOException $e) {
            throw new Exception("Erreur connexion BD : " . $e->getMessage());
        }
    }

    /**
     * Récupère l'instance PDO
     */
    public static function pdo(): PDO
    {
        if (self::$pdo === null) {
            self::init();
        }
        return self::$pdo;
    }

    /**
     * SELECT 1 ligne
     */
    public static function fetch(string $sql, array $params = []): ?array
    {
        $stmt = self::pdo()->prepare($sql);
        $stmt->execute($params);
        $result = $stmt->fetch();
        return $result ?: null;
    }

    /**
     * SELECT plusieurs lignes
     */
    public static function fetchAll(string $sql, array $params = []): array
    {
        $stmt = self::pdo()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /**
     * INSERT / UPDATE / DELETE
     */
    public static function execute(string $sql, array $params = []): bool
    {
        $stmt = self::pdo()->prepare($sql);
        return $stmt->execute($params);
    }

    /**
     * Dernier ID inséré
     */
    public static function lastId(): string
    {
        return self::pdo()->lastInsertId();
    }
}
