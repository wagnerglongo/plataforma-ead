<?php

declare(strict_types=1);

namespace App\Core;

final class Csrf
{
    public static function token(): string
    {
        $token = Session::get('_csrf');
        if (!is_string($token) || strlen($token) !== 64) {
            $token = bin2hex(random_bytes(32));
            Session::put('_csrf', $token);
        }
        return $token;
    }

    public static function verify(Request $request): void
    {
        $sent = (string) ($request->input('_token') ?? $request->header('X-CSRF-Token') ?? '');
        if ($sent === '' || !hash_equals(self::token(), $sent)) {
            throw new HttpException(419, 'A página ficou aberta por muito tempo. Recarregue e tente de novo.');
        }
    }
}
