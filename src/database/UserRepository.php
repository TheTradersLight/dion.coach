<?php
namespace App\Database;

class UserRepository
{
    public static function findById(int $id): ?array
    {
        return Database::fetch("SELECT * FROM users WHERE id = ? LIMIT 1", [$id]);
    }

    public static function findByEmail(string $email): ?array
    {
        return Database::fetch("SELECT * FROM users WHERE email = ? LIMIT 1", [$email]);
    }

    public static function findByProvider(string $provider, string $providerUserId): ?array
    {
        $sql = "
            SELECT u.*
            FROM user_providers up
            JOIN users u ON u.id = up.user_id
            WHERE up.provider = ? AND up.provider_user_id = ?
            LIMIT 1
        ";
        return Database::fetch($sql, [$provider, $providerUserId]);
    }

    public static function createUser(?string $name, ?string $email, int $roleId): int
    {
        $name = $name ?: 'Utilisateur';

        Database::execute(
            "INSERT INTO users (name, email, password, role_id, email_verified_at, created_at, updated_at)
             VALUES (?, ?, NULL, ?, NOW(), NOW(), NOW())",
            [$name, $email, $roleId]
        );

        return (int) Database::lastId();
    }

    public static function linkProvider(
        int $userId,
        string $provider,
        string $providerUserId,
        ?string $email,
        ?string $name,
        ?string $avatarUrl
    ): void {
        Database::execute(
            "INSERT INTO user_providers
                (user_id, provider, provider_user_id, provider_email, provider_name, avatar_url, created_at)
             VALUES (?, ?, ?, ?, ?, ?, NOW())",
            [$userId, $provider, $providerUserId, $email, $name, $avatarUrl]
        );
    }

    public static function touchLogin(
        int $userId,
        string $provider,
        string $providerUserId,
        ?string $avatarUrl
    ): void {
        Database::execute(
            "UPDATE users SET last_login = NOW() WHERE id = ?",
            [$userId]
        );

        if ($avatarUrl) {
            Database::execute(
                "UPDATE user_providers
                 SET last_login_at = NOW(), avatar_url = ?
                 WHERE user_id = ? AND provider = ? AND provider_user_id = ?",
                [$avatarUrl, $userId, $provider, $providerUserId]
            );
        } else {
            Database::execute(
                "UPDATE user_providers
                 SET last_login_at = NOW()
                 WHERE user_id = ? AND provider = ? AND provider_user_id = ?",
                [$userId, $provider, $providerUserId]
            );
        }
    }

    public static function getDefaultRoleId(): int
    {
        $row = Database::fetch("SELECT id FROM roles WHERE slug = 'user' LIMIT 1");
        return $row ? (int) $row['id'] : 1;
    }
}
