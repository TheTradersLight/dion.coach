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
        try {
            $stmt = $this->db->prepare("SELECT payload FROM sessions WHERE id = :id");
            $stmt->execute(['id' => $id]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            $result = $row ? (string)$row['payload'] : "";
            error_log("[SESSION-DEBUG] READ id=$id found=" . ($row ? 'YES' : 'NO') . " len=" . strlen($result));
            return $result;
        } catch (\Throwable $e) {
            error_log("[SESSION-DEBUG] READ ERROR: " . $e->getMessage());
            return "";
        }
    }

    public function write($id, $data): bool
    {
        try {
            error_log("[SESSION-DEBUG] WRITE id=$id len=" . strlen($data) . " data=" . substr($data, 0, 200));
            $stmt = $this->db->prepare("REPLACE INTO sessions (id, payload, last_activity) VALUES (:id, :data, :time)");
            $result = $stmt->execute([
                'id' => $id,
                'data' => $data,
                'time' => time()
            ]);
            error_log("[SESSION-DEBUG] WRITE result=" . ($result ? 'OK' : 'FAIL'));
            return $result;
        } catch (\Throwable $e) {
            error_log("[SESSION-DEBUG] WRITE ERROR: " . $e->getMessage());
            return false;
        }
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
