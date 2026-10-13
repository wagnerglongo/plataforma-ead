<?php

declare(strict_types=1);

namespace App\Core;

/** Configurações editáveis pelo admin (nome do site, GTM, WhatsApp…). */
final class Settings
{
    private static ?array $cache = null;

    public static function all(): array
    {
        if (self::$cache === null) {
            try {
                $rows = Database::fetchAll('SELECT setting_key, setting_value FROM settings');
                self::$cache = array_column($rows, 'setting_value', 'setting_key');
            } catch (\Throwable) {
                self::$cache = [];
            }
        }
        return self::$cache;
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        $value = self::all()[$key] ?? null;
        return ($value === null || $value === '') ? $default : $value;
    }

    public static function set(string $key, ?string $value): void
    {
        Database::query(
            'INSERT INTO settings (setting_key, setting_value) VALUES (:k, :v)
             ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)',
            ['k' => $key, 'v' => $value],
        );
        self::$cache = null;
    }
}
