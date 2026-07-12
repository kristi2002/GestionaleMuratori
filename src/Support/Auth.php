<?php
declare(strict_types=1);

namespace App\Support;

use App\Models\UserModel;

/**
 * Session-based authentication (§2). Stores a minimal user snapshot in the
 * session; never trusts client-supplied role/identity.
 */
final class Auth
{
    /** Verify credentials; returns the DB user row on success, null otherwise. */
    public static function attempt(string $email, string $password): ?array
    {
        $user = (new UserModel())->findByEmail($email);
        if ($user === null || (int) $user['is_active'] !== 1) {
            return null;
        }
        if (!password_verify($password, (string) $user['password_hash'])) {
            return null;
        }
        return $user;
    }

    public static function login(array $user): void
    {
        Session::regenerate();
        Session::set('user', [
            'id'               => (int) $user['id'],
            'name'             => (string) $user['name'],
            'email'            => (string) $user['email'],
            'role'             => (string) $user['role'],
            'client_id'        => $user['client_id'] !== null ? (int) $user['client_id'] : null,
            'subcontractor_id' => ($user['subcontractor_id'] ?? null) !== null ? (int) $user['subcontractor_id'] : null,
            'shortcuts'        => $user['shortcuts'] ?? null,
        ]);
    }

    public static function logout(): void
    {
        Session::destroy();
    }

    public static function check(): bool
    {
        return Session::get('user') !== null;
    }

    public static function user(): ?array
    {
        return Session::get('user');
    }

    public static function role(): ?string
    {
        return self::user()['role'] ?? null;
    }

    public static function id(): ?int
    {
        return self::user()['id'] ?? null;
    }

    public static function clientId(): ?int
    {
        return self::user()['client_id'] ?? null;
    }

    public static function subcontractorId(): ?int
    {
        return self::user()['subcontractor_id'] ?? null;
    }

    /** Absolute path of the landing page for a role. */
    public static function homeFor(?string $role): string
    {
        $path = match ($role) {
            'admin'         => '/admin',
            'worker'        => '/worker',
            'client'        => '/client',
            'subcontractor' => '/sub',
            default         => '/login',
        };
        return Url::to($path);
    }
}
