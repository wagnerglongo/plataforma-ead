<?php

declare(strict_types=1);

namespace App\Core;

final class HttpException extends \RuntimeException
{
    public function __construct(int $status, string $message = '')
    {
        parent::__construct($message, $status);
    }

    public static function notFound(string $message = 'Página não encontrada.'): self
    {
        return new self(404, $message);
    }

    public static function forbidden(string $message = 'Você não tem permissão para acessar esta página.'): self
    {
        return new self(403, $message);
    }
}
