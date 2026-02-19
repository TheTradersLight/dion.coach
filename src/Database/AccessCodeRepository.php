<?php
declare(strict_types=1);

namespace App\Database;

final class AccessCodeRepository
{
    public static function validateCode(int $campId, string $code): ?array
    {
        $hash = hash('sha256', strtoupper(trim($code)));
        $row = Database::fetch(
            "SELECT id, camp_id, test_type_id, user_id, role, status, expires_at
             FROM camp_access_codes
             WHERE camp_id = ? AND code_hash = ? AND status = 'active'
               AND (expires_at IS NULL OR expires_at >= NOW())",
            [$campId, $hash]
        );
        return $row ?: null;
    }

    public static function createCode(
        int $campId,
        ?int $testTypeId,
        int $userId,
        string $role,
        ?string $expiresAt,
        int $createdBy
    ): array {
        $code = self::generateCode(4);
        $hash = hash('sha256', strtoupper($code));

        Database::execute(
            "INSERT INTO camp_access_codes (camp_id, test_type_id, user_id, code_hash, role, status, expires_at, created_by)
             VALUES (?, ?, ?, ?, ?, 'active', ?, ?)",
            [$campId, $testTypeId, $userId, $hash, $role, $expiresAt, $createdBy]
        );

        return ['id' => (int)Database::lastId(), 'code' => $code];
    }

    public static function listCodes(int $campId): array
    {
        return Database::fetchAll(
            "SELECT c.id, c.role, c.status, c.expires_at, c.created_at, c.test_type_id,
                    tt.name AS test_type_name,
                    u.email AS user_email,
                    cu.email AS created_by_email
             FROM camp_access_codes c
             LEFT JOIN test_types tt ON tt.id = c.test_type_id
             LEFT JOIN users u ON u.id = c.user_id
             LEFT JOIN users cu ON cu.id = c.created_by
             WHERE c.camp_id = ?
             ORDER BY c.created_at DESC",
            [$campId]
        );
    }

    public static function revokeCode(int $campId, int $codeId): void
    {
        Database::execute(
            "UPDATE camp_access_codes SET status = 'revoked' WHERE camp_id = ? AND id = ?",
            [$campId, $codeId]
        );
    }

    public static function createToken(int $campId, int $accessCodeId, ?string $expiresAt = null): string
    {
        $token = bin2hex(random_bytes(16));
        $hash = hash('sha256', $token);

        Database::execute(
            "INSERT INTO camp_access_tokens (access_code_id, camp_id, token_hash, expires_at)
             VALUES (?, ?, ?, ?)",
            [$accessCodeId, $campId, $hash, $expiresAt]
        );

        return $token;
    }

    public static function validateToken(int $campId, string $token): ?array
    {
        $hash = hash('sha256', $token);
        $row = Database::fetch(
            "SELECT t.id AS token_id, t.camp_id, t.access_code_id, t.expires_at,
                    c.test_type_id, c.user_id, c.role
             FROM camp_access_tokens t
             JOIN camp_access_codes c ON c.id = t.access_code_id
             WHERE t.camp_id = ? AND t.token_hash = ?
               AND (t.expires_at IS NULL OR t.expires_at >= NOW())
               AND c.status = 'active'",
            [$campId, $hash]
        );

        if ($row) {
            Database::execute(
                "UPDATE camp_access_tokens SET last_used_at = NOW() WHERE id = ?",
                [(int)$row['token_id']]
            );
        }

        return $row ?: null;
    }

    private static function generateCode(int $length): string
    {
        $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        $out = '';
        for ($i = 0; $i < $length; $i++) {
            $out .= $alphabet[random_int(0, strlen($alphabet) - 1)];
        }
        return $out;
    }
}
