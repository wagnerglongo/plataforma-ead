<?php

declare(strict_types=1);

namespace App\Core;

final class ValidationException extends \RuntimeException
{
    public function __construct(public readonly array $errors, public readonly ?string $redirectTo = null)
    {
        parent::__construct('Dados inválidos.', 422);
    }

    public static function withMessage(string $field, string $message, ?string $redirectTo = null): self
    {
        return new self([$field => [$message]], $redirectTo);
    }
}
