<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Roteador com parâmetros nomeados ({slug}, {id:\d+}), grupos com prefixo
 * e middlewares por rota.
 */
final class Router
{
    private array $routes = [];
    private string $prefix = '';
    private array $middleware = [];

    public function get(string $path, array $handler, array $middleware = []): void
    {
        $this->add(['GET', 'HEAD'], $path, $handler, $middleware);
    }

    public function post(string $path, array $handler, array $middleware = []): void
    {
        $this->add(['POST'], $path, $handler, $middleware);
    }

    public function delete(string $path, array $handler, array $middleware = []): void
    {
        $this->add(['DELETE'], $path, $handler, $middleware);
    }

    public function group(string $prefix, array $middleware, callable $routes): void
    {
        [$prevPrefix, $prevMw] = [$this->prefix, $this->middleware];
        $this->prefix .= $prefix;
        $this->middleware = array_merge($this->middleware, $middleware);
        $routes($this);
        [$this->prefix, $this->middleware] = [$prevPrefix, $prevMw];
    }

    private function add(array $methods, string $path, array $handler, array $middleware): void
    {
        $full = '/' . trim($this->prefix . $path, '/');
        $regex = preg_replace_callback(
            '#\{(\w+)(?::([^}]+))?\}#',
            fn ($m) => '(?P<' . $m[1] . '>' . ($m[2] ?? '[^/]+') . ')',
            $full,
        );
        $this->routes[] = [
            'methods' => $methods,
            'path' => $full,
            'regex' => '#^' . $regex . '$#u',
            'handler' => $handler,
            'middleware' => array_merge($this->middleware, $middleware),
        ];
    }

    /** @return array{0: array, 1: array<string,string>} */
    public function match(string $method, string $path): array
    {
        $allowed = false;
        foreach ($this->routes as $route) {
            if (!preg_match($route['regex'], $path, $m)) {
                continue;
            }
            if (!in_array($method, $route['methods'], true)) {
                $allowed = true;
                continue;
            }
            $params = array_filter($m, 'is_string', ARRAY_FILTER_USE_KEY);
            return [$route, $params];
        }
        throw $allowed ? new HttpException(405, 'Método não permitido.') : HttpException::notFound();
    }
}
