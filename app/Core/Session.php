<?php

declare(strict_types=1);

namespace App\Core;

final class Session
{
    public static function start(?Request $request = null): void
    {
        if (session_status() === PHP_SESSION_ACTIVE || PHP_SAPI === 'cli') {
            return;
        }
        $path = base_path_url() ?: '/';
        session_name(config('session.name'));
        session_set_cookie_params([
            'lifetime' => 0,
            'path' => $path,
            'secure' => $request?->isSecure() ?? false,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        ini_set('session.use_strict_mode', '1');
        ini_set('session.use_only_cookies', '1');
        ini_set('session.gc_maxlifetime', (string) (config('session.lifetime') * 60));
        $savePath = BASE_PATH . '/storage/sessions';
        if (is_dir($savePath) && is_writable($savePath)) {
            session_save_path($savePath);
        }
        session_start();

        // Expira a sessão por inatividade.
        $last = $_SESSION['_last_activity'] ?? null;
        if ($last && time() - $last > config('session.lifetime') * 60) {
            session_unset();
            session_regenerate_id(true);
            self::flash('_flash', ['type' => 'info', 'message' => 'Sua sessão expirou por inatividade. Entre novamente.']);
        }
        $_SESSION['_last_activity'] = time();
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        return $_SESSION[$key] ?? $_SESSION['_flash_now'][$key] ?? $default;
    }

    public static function put(string $key, mixed $value): void
    {
        $_SESSION[$key] = $value;
    }

    public static function forget(string $key): void
    {
        unset($_SESSION[$key], $_SESSION['_flash_now'][$key]);
    }

    public static function pull(string $key, mixed $default = null): mixed
    {
        $value = self::get($key, $default);
        self::forget($key);
        return $value;
    }

    /** Valor disponível apenas na próxima requisição. */
    public static function flash(string $key, mixed $value): void
    {
        $_SESSION['_flash_next'][$key] = $value;
    }

    /** Move os flashes "próximos" para "atuais" no fim da requisição. */
    public static function ageFlash(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            return;
        }
        $_SESSION['_flash_now'] = $_SESSION['_flash_next'] ?? [];
        unset($_SESSION['_flash_next']);
        foreach (array_keys($_SESSION['_flash_now']) as $key) {
            unset($_SESSION[$key]);
        }
    }

    public static function regenerate(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
        }
    }

    public static function destroy(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            return;
        }
        $_SESSION = [];
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 3600, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
        session_destroy();
        session_start();
        session_regenerate_id(true);
    }
}
