<?php

declare(strict_types=1);

namespace App\Core;

abstract class Controller
{
    public function __construct(protected Request $request)
    {
    }

    protected function view(string $template, array $data = [], string $layout = 'public'): Response
    {
        return Response::html(View::render($template, $data, $layout));
    }

    protected function redirect(string $to): Response
    {
        return Response::redirect($to);
    }

    protected function back(string $fallback = '/'): Response
    {
        return Response::back($fallback);
    }

    protected function json(mixed $data, int $status = 200): Response
    {
        return Response::json($data, $status);
    }

    /** Valida e devolve só os campos das regras. Lança ValidationException. */
    protected function validate(array $rules, array $labels = [], ?array $data = null): array
    {
        $data ??= $this->request->all();
        $v = new Validator($data, $rules, $labels);
        if ($v->fails()) {
            throw new ValidationException($v->errors());
        }
        return $v->validated();
    }

    protected function authorize(string $permission): void
    {
        if (!Auth::can($permission)) {
            throw HttpException::forbidden();
        }
    }

    protected function page(): int
    {
        return max(1, (int) $this->request->query('pagina', 1));
    }
}
