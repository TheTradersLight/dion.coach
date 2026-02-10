<?php
namespace App\Auth;

use PDO;
use SessionHandlerInterface;

class MySQLSessionHandler implements SessionHandlerInterface
{
    private $db;

    public function __construct(PDO $pdo)
    {
        $this->db = $pdo;
    }

    public function open($path, $name): bool { return true; }
    public function close(): bool { return true; }

    public function read($id): string
    {
        $stmt = $this->db->prepare("SELECT payload FROM sessions WHERE id = :id");
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ? (string)$row['payload'] : "";
    }

    public function write($id, $data): bool
    {
        $stmt = $this->db->prepare("REPLACE INTO sessions (id, payload, last_activity) VALUES (:id, :data, :time)");
        return $stmt->execute([
            'id' => $id,
            'data' => $data,
            'time' => time()
        ]);
    }

    public function destroy($id): bool
    {
        $stmt = $this->db->prepare("DELETE FROM sessions WHERE id = :id");
        return $stmt->execute(['id' => $id]);
    }

    public function gc($maxlifetime): int|false
    {
        $stmt = $this->db->prepare("DELETE FROM sessions WHERE last_activity < :old");
        $result = $stmt->execute(['old' => time() - $maxlifetime]);
        return $result ? 1 : false;
    }
}
