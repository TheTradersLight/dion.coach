<?php
namespace App\Database;

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
        // Define environment variables
        $db_user = getenv('DB_USER') ?: 'dioncoach_web';
        $db_pass = getenv('DB_PASS') ?: '3r$hS8j[ztjn{00r';
        $db_name = getenv('DB_NAME') ?: 'dbcoach';
        // The INSTANCE_CONNECTION_NAME is automatically exposed by Cloud Run as an environment variable.
        $instance_connection_name = 'dioncoach:us-east1:dbcoach';//getenv('INSTANCE_CONNECTION_NAME');

        // Construct the DSN using a Unix socket
        $dsn = sprintf(
            'mysql:unix_socket=/cloudsql/%s;dbname=%s',
            $instance_connection_name,
            $db_name
        );
        // Variables d'environnement (local + Cloud Run)
//        $dbHost = getenv('DB_HOST') ?: '34.23.43.4';
//        $dbName = getenv('DB_NAME') ?: 'dbcoach';
//        $dbUser = getenv('DB_USER') ?: 'dioncoach_web';
//        $dbPass = getenv('DB_PASS') ?: '3r$hS8j[ztjn{00r';

       // $dsn = "mysql:host={$dbHost};dbname={$dbName};charset=utf8mb4";

        try {
            self::$pdo = new PDO($dsn, $db_user, $db_pass, [
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
