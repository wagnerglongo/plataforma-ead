<?php

declare(strict_types=1);

namespace App\Core;

final class Auth
{
    private static ?array $user = null;
    private static bool $loaded = false;
    private static ?array $permissions = null;

    public static function attempt(string $email, string $password): ?array
    {
        $user = Database::fetch('SELECT * FROM users WHERE email = :email LIMIT 1', ['email' => mb_strtolower(trim($email))]);
        // Hash "falso" para o tempo de resposta não revelar se o e-mail existe.
        $hash = $user['password_hash'] ?? '$2y$10$7GvT0qhxNm9MHgmqDllJpuRfXor8M202Soy31aHw17a1xMUbO2tRe';
        if (!password_verify($password, $hash) || !$user) {
            return null;
        }
        if ($user['status'] !== 'active') {
            return ['_inactive' => true];
        }
        if (password_needs_rehash($user['password_hash'], PASSWORD_DEFAULT)) {
            Database::update('users', ['password_hash' => password_hash($password, PASSWORD_DEFAULT)], 'id = :id', ['id' => $user['id']]);
        }
        return $user;
    }

    public static function login(array $user): void
    {
        Session::regenerate();
        Session::put('uid', (int) $user['id']);
        Session::put('auth_at', time());
        Database::update('users', ['last_login_at' => now()], 'id = :id', ['id' => $user['id']]);
        self::$loaded = false;
        self::$permissions = null;
    }

    public static function logout(): void
    {
        Session::destroy();
        self::$user = null;
        self::$loaded = true;
        self::$permissions = null;
    }

    public static function user(): ?array
    {
        if (!self::$loaded) {
            self::$loaded = true;
            $id = Session::get('uid');
            if ($id) {
                self::$user = Database::fetch(
                    'SELECT u.*, r.slug AS role_slug, r.name AS role_name
                       FROM users u JOIN roles r ON r.id = u.role_id
                      WHERE u.id = :id AND u.status = \'active\'',
                    ['id' => $id],
                );
                if (!self::$user) {
                    Session::forget('uid');
                }
            }
        }
        return self::$user;
    }

    public static function refresh(): void
    {
        self::$loaded = false;
    }

    public static function check(): bool
    {
        return self::user() !== null;
    }

    public static function id(): ?int
    {
        return self::user() ? (int) self::$user['id'] : null;
    }

    public static function isStaff(): bool
    {
        $u = self::user();
        return $u !== null && $u['role_slug'] !== 'aluno';
    }

    public static function isAdmin(): bool
    {
        return (self::user()['role_slug'] ?? null) === 'admin';
    }

    public static function can(string $permission): bool
    {
        $u = self::user();
        if (!$u) {
            return false;
        }
        if ($u['role_slug'] === 'admin') {
            return true;
        }
        self::$permissions ??= Database::column(
            'SELECT p.slug FROM permissions p JOIN role_permissions rp ON rp.permission_id = p.id WHERE rp.role_id = :r',
            ['r' => $u['role_id']],
        );
        return in_array($permission, self::$permissions, true);
    }
}
