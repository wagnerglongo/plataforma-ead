<?php

declare(strict_types=1);

namespace App\Core;

final class Response
{
    private ?\Closure $streamer = null;

    public function __construct(
        private string $body = '',
        private int $status = 200,
        private array $headers = [],
    ) {
    }

    public static function html(string $body, int $status = 200): self
    {
        return new self($body, $status, ['Content-Type' => 'text/html; charset=UTF-8']);
    }

    public static function json(mixed $data, int $status = 200): self
    {
        return new self(
            (string) json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            $status,
            ['Content-Type' => 'application/json; charset=UTF-8', 'Cache-Control' => 'no-store'],
        );
    }

    public static function redirect(string $to, int $status = 302): self
    {
        $location = preg_match('#^https?://#', $to) ? $to : url($to);
        return new self('', $status, ['Location' => $location]);
    }

    /** Volta para a página anterior, só se ela for do próprio site. */
    public static function back(string $fallback = '/'): self
    {
        $ref = Request::current()?->referer();
        $host = $_SERVER['HTTP_HOST'] ?? '';
        $origin = preg_replace('#^(https?://[^/]+).*$#', '$1', config('app.url'));
        // Só volta para páginas do próprio site (evita redirecionamento aberto).
        if ($ref && (str_starts_with($ref, $origin . '/') || $ref === $origin || ($host !== '' && parse_url($ref, PHP_URL_HOST) . (parse_url($ref, PHP_URL_PORT) ? ':' . parse_url($ref, PHP_URL_PORT) : '') === $host))) {
            return new self('', 302, ['Location' => $ref]);
        }
        return self::redirect($fallback);
    }

    public static function download(string $content, string $filename, string $mime): self
    {
        return new self($content, 200, [
            'Content-Type' => $mime,
            'Content-Disposition' => 'attachment; filename="' . addslashes($filename) . '"',
            'Content-Length' => (string) strlen($content),
            'Cache-Control' => 'private, no-store',
        ]);
    }

    public static function stream(callable $streamer, array $headers, int $status = 200): self
    {
        $r = new self('', $status, $headers);
        $r->streamer = \Closure::fromCallable($streamer);
        return $r;
    }

    public function withHeader(string $name, string $value): self
    {
        $this->headers[$name] = $value;
        return $this;
    }

    public function status(): int
    {
        return $this->status;
    }

    public function send(): void
    {
        if (!headers_sent()) {
            http_response_code($this->status);
            foreach ($this->headers as $name => $value) {
                header($name . ': ' . $value);
            }
        }
        if ($this->streamer) {
            ($this->streamer)();
            return;
        }
        echo $this->body;
    }
}
