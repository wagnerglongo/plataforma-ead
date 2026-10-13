<?php

declare(strict_types=1);

namespace App\Core;

/** Limita tentativas (login, recuperação de senha) por chave e janela de tempo. */
final class RateLimiter
{
    public static function tooMany(string $key, int $max, int $windowSeconds): bool
    {
        $since = date('Y-m-d H:i:s', time() - $windowSeconds);
        $count = (int) Database::value(
            'SELECT COUNT(*) FROM login_attempts WHERE rl_key = :k AND created_at >= :s',
            ['k' => $key, 's' => $since],
        );
        return $count >= $max;
    }

    public static function hit(string $key): void
    {
        Database::insert('login_attempts', ['rl_key' => mb_substr($key, 0, 190), 'created_at' => now()]);
        // Limpeza oportunista de registros antigos.
        if (random_int(1, 50) === 1) {
            Database::query('DELETE FROM login_attempts WHERE created_at < :d', ['d' => date('Y-m-d H:i:s', time() - 86400)]);
        }
    }

    public static function clear(string $key): void
    {
        Database::query('DELETE FROM login_attempts WHERE rl_key = :k', ['k' => $key]);
    }
}
