<?php

declare(strict_types=1);

namespace App\Core;

/** Front controller: sessão → rota → CSRF → middlewares → controller → resposta. */
final class App
{
    public static function run(): void
    {
        $request = Request::capture();
        Session::start($request);

        try {
            $router = new Router();
            require BASE_PATH . '/routes/web.php';

            [$route, $params] = $router->match($request->method(), $request->path());

            if (!in_array($request->method(), ['GET', 'HEAD'], true)) {
                Csrf::verify($request);
            }
            if ($early = self::runMiddleware($route['middleware'], $request)) {
                $response = $early;
            } else {
                [$class, $method] = $route['handler'];
                $result = (new $class($request))->{$method}(...array_values($params));
                $response = $result instanceof Response ? $result : Response::html((string) $result);
            }
            if ($request->method() === 'GET' && !$request->wantsJson()) {
                Session::forget('_old_input');
                Session::forget('_errors');
            }
        } catch (ValidationException $e) {
            $response = self::handleValidation($e, $request);
        } catch (HttpException $e) {
            $response = self::errorResponse($e->getCode(), $e->getMessage(), $request);
        } catch (\Throwable $e) {
            Logger::error($e->getMessage(), [
                'file' => $e->getFile() . ':' . $e->getLine(),
                'path' => $request->path(),
                'trace' => array_slice(explode("\n", $e->getTraceAsString()), 0, 8),
            ]);
            $message = config('app.debug') ? get_class($e) . ': ' . $e->getMessage() . ' em ' . $e->getFile() . ':' . $e->getLine() : '';
            $response = self::errorResponse(500, $message, $request);
        }

        self::securityHeaders($request);
        Session::ageFlash();
        $response->send();
    }

    /** @return Response|null resposta antecipada quando o middleware bloqueia */
    private static function runMiddleware(array $middleware, Request $request): ?Response
    {
        foreach (array_unique($middleware) as $mw) {
            [$name, $arg] = array_pad(explode(':', $mw, 2), 2, null);
            switch ($name) {
                case 'auth':
                    if (!Auth::check()) {
                        if ($request->wantsJson()) {
                            return Response::json(['error' => 'Sessão expirada. Entre novamente.'], 401);
                        }
                        Session::put('_intended', $request->path());
                        flash('info', 'Entre na sua conta para continuar.');
                        return Response::redirect('/entrar');
                    }
                    break;
                case 'guest':
                    if (Auth::check()) {
                        return Response::redirect(Auth::isStaff() ? '/admin' : '/aluno');
                    }
                    break;
                case 'staff':
                    if (!Auth::isStaff()) {
                        throw HttpException::forbidden();
                    }
                    break;
                case 'can':
                    if (!Auth::can((string) $arg)) {
                        throw HttpException::forbidden();
                    }
                    break;
                case 'not-demo':
                    if (config('app.demo')) {
                        $msg = 'Esta ação fica desativada na versão de demonstração.';
                        if ($request->wantsJson()) {
                            return Response::json(['error' => $msg], 403);
                        }
                        flash('warning', $msg);
                        return Response::back('/admin');
                    }
                    break;
            }
        }
        return null;
    }

    private static function handleValidation(ValidationException $e, Request $request): Response
    {
        if ($request->wantsJson()) {
            return Response::json(['errors' => $e->errors, 'message' => reset($e->errors)[0] ?? 'Dados inválidos.'], 422);
        }
        $input = $request->all();
        unset($input['password'], $input['password_confirmation'], $input['current_password'], $input['_token']);
        Session::flash('_old_input', $input);
        Session::flash('_errors', $e->errors);
        flash('error', 'Confira os campos destacados.');
        return $e->redirectTo ? Response::redirect($e->redirectTo) : Response::back();
    }

    public static function errorResponse(int $status, string $message, Request $request): Response
    {
        $titles = [
            403 => 'Acesso negado',
            404 => 'Página não encontrada',
            405 => 'Método não permitido',
            419 => 'Sessão expirada',
            429 => 'Muitas tentativas',
            500 => 'Algo deu errado do nosso lado',
        ];
        $status = isset($titles[$status]) ? $status : 500;
        if ($request->wantsJson()) {
            return Response::json(['error' => $message ?: $titles[$status]], $status);
        }
        try {
            $html = View::render('errors/error', [
                'status' => $status,
                'title' => $titles[$status],
                'message' => $message,
            ], 'public');
        } catch (\Throwable) {
            $html = '<h1>' . $status . ' · ' . e($titles[$status]) . '</h1><p>' . e($message) . '</p>';
        }
        return Response::html($html, $status);
    }

    private static function securityHeaders(Request $request): void
    {
        if (headers_sent()) {
            return;
        }
        $nonce = csp_nonce();
        $csp = implode('; ', [
            "default-src 'self'",
            "script-src 'self' 'nonce-{$nonce}' https://www.googletagmanager.com https://www.google-analytics.com https://www.youtube.com https://player.vimeo.com",
            "style-src 'self' 'unsafe-inline'",
            "img-src 'self' data: https:",
            "font-src 'self'",
            "media-src 'self' blob:",
            "connect-src 'self' https://*.google-analytics.com https://*.analytics.google.com https://www.googletagmanager.com",
            "frame-src 'self' https://www.youtube-nocookie.com https://www.youtube.com https://player.vimeo.com https://*.tv.pandavideo.com.br https://iframe.mediadelivery.net https://www.googletagmanager.com",
            "frame-ancestors 'self'",
            "base-uri 'self'",
            "form-action 'self'",
            "object-src 'none'",
        ]);
        header('Content-Security-Policy: ' . $csp);
        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: SAMEORIGIN');
        header('Referrer-Policy: strict-origin-when-cross-origin');
        header('Permissions-Policy: camera=(), microphone=(), geolocation=(), payment=()');
        if ($request->isSecure()) {
            header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
        }
        header_remove('X-Powered-By');
    }
}
