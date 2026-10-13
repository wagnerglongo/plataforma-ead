<?php

declare(strict_types=1);

namespace App\Core;

/** Templates PHP nativos com layout e pilhas (scripts por página). */
final class View
{
    private static array $stacks = [];
    private static array $shared = [];

    public static function share(string $key, mixed $value): void
    {
        self::$shared[$key] = $value;
    }

    public static function render(string $template, array $data = [], ?string $layout = null): string
    {
        $data = self::$shared + $data;
        $content = self::capture($template, $data);
        if ($layout === null) {
            return $content;
        }
        return self::capture('layouts/' . $layout, $data + ['content' => $content]);
    }

    public static function partial(string $template, array $data = []): string
    {
        return self::capture($template, self::$shared + $data);
    }

    public static function push(string $stack, string $html): void
    {
        self::$stacks[$stack][] = $html;
    }

    public static function stack(string $stack): string
    {
        return implode("\n", self::$stacks[$stack] ?? []);
    }

    private static function capture(string $__template, array $__data): string
    {
        $__file = BASE_PATH . '/views/' . $__template . '.php';
        if (!is_file($__file)) {
            throw new \RuntimeException("View não encontrada: {$__template}");
        }
        extract($__data, EXTR_SKIP);
        ob_start();
        try {
            require $__file;
        } catch (\Throwable $e) {
            ob_end_clean();
            throw $e;
        }
        return (string) ob_get_clean();
    }
}
