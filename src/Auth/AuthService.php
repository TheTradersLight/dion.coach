<?php
namespace App\Auth;

use App\Database\UserRepository;

class AuthService
{
    public static function findOrCreateFromProvider(
        string $provider,
        string $providerUserId,
        ?string $email = null,
        ?string $name = null,
        ?string $avatarUrl = null
    ): array {
        $provider = strtolower($provider);

        // 1) Déjà lié ?
        $user = UserRepository::findByProvider($provider, $providerUserId);
        if ($user) {
            UserRepository::touchLogin($user['id'], $provider, $providerUserId, $avatarUrl);
            return [
                'user' => $user,
                'is_new_user' => false,
                'linked_new_provider' => false,
            ];
        }

        // 2) Matching par email
        $linkedNewProvider = false;
        if ($email) {
            $user = UserRepository::findByEmail($email);
            if ($user) {
                UserRepository::linkProvider(
                    $user['id'],
                    $provider,
                    $providerUserId,
                    $email,
                    $name,
                    $avatarUrl
                );
                UserRepository::touchLogin($user['id'], $provider, $providerUserId, $avatarUrl);

                return [
                    'user' => $user,
                    'is_new_user' => false,
                    'linked_new_provider' => true,
                ];
            }
        }

        // 3) Nouveau user
        $roleId = UserRepository::getDefaultRoleId();
        $userId = UserRepository::createUser($name, $email, $roleId);

        UserRepository::linkProvider(
            $userId,
            $provider,
            $providerUserId,
            $email,
            $name,
            $avatarUrl
        );
        UserRepository::touchLogin($userId, $provider, $providerUserId, $avatarUrl);

        $user = UserRepository::findById($userId);

        return [
            'user' => $user,
            'is_new_user' => true,
            'linked_new_provider' => false,
        ];
    }
}
