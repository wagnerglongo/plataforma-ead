<?php

declare(strict_types=1);

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Env;
use App\Core\Session;
use App\Core\Settings;

function env(string $key, mixed $default = null): mixed
{
    return Env::get($key, $default);
}

function config(string $key, mixed $default = null): mixed
{
    static $config = null;
    $config ??= require BASE_PATH . '/config/app.php';
    $value = $config;
    foreach (explode('.', $key) as $part) {
        if (!is_array($value) || !array_key_exists($part, $value)) {
            return $default;
        }
        $value = $value[$part];
    }
    return $value;
}

/** Escapa para HTML. Toda saída de dado do usuário passa por aqui. */
function e(mixed $value): string
{
    return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function base_path_url(): string
{
    static $base = null;
    return $base ??= rtrim((string) (parse_url(config('app.url'), PHP_URL_PATH) ?? ''), '/');
}

/** URL relativa à raiz da aplicação (funciona em subpasta). */
function url(string $path = '/', array $query = []): string
{
    $url = base_path_url() . '/' . ltrim($path, '/');
    if ($query) {
        $url .= '?' . http_build_query($query);
    }
    return $url === '' ? '/' : $url;
}

function absolute_url(string $path = '/'): string
{
    $origin = preg_replace('#^(https?://[^/]+).*$#', '$1', config('app.url'));
    return $origin . url($path);
}

/** Arquivo estático com versão pela data de modificação (cache busting). */
function asset(string $path): string
{
    $file = BASE_PATH . '/public/assets/' . ltrim($path, '/');
    $v = is_file($file) ? filemtime($file) : 0;
    return url('assets/' . ltrim($path, '/')) . '?v=' . $v;
}

function upload_url(?string $path): ?string
{
    return $path ? url('uploads/' . ltrim($path, '/')) : null;
}

function csrf_token(): string
{
    return Csrf::token();
}

function csrf_field(): string
{
    return '<input type="hidden" name="_token" value="' . e(Csrf::token()) . '">';
}

function method_field(string $method): string
{
    return '<input type="hidden" name="_method" value="' . e(strtoupper($method)) . '">';
}

function old(string $key, mixed $default = ''): mixed
{
    $old = Session::get('_old_input', []);
    return $old[$key] ?? $default;
}

function errors(): array
{
    return Session::get('_errors', []);
}

function error_for(string $field): ?string
{
    return errors()[$field][0] ?? null;
}

/** Atributos de acessibilidade + mensagem para campos com erro. */
function field_error(string $field): string
{
    $msg = error_for($field);
    return $msg ? '<p class="field-error" id="err-' . e($field) . '">' . e($msg) . '</p>' : '';
}

function invalid_attr(string $field): string
{
    return error_for($field) ? ' aria-invalid="true" aria-describedby="err-' . e($field) . '"' : '';
}

function flash(string $type, string $message): void
{
    Session::flash('_flash', ['type' => $type, 'message' => $message]);
}

function auth(): ?array
{
    return Auth::user();
}

function can(string $permission): bool
{
    return Auth::can($permission);
}

function setting(string $key, mixed $default = null): mixed
{
    return Settings::get($key, $default);
}

function site_name(): string
{
    return (string) setting('site_name', config('app.name'));
}

function now(): string
{
    return date('Y-m-d H:i:s');
}

function date_br(?string $date, bool $withTime = false): string
{
    if (!$date) {
        return '—';
    }
    $ts = strtotime($date);
    return $withTime ? date('d/m/Y \à\s H:i', $ts) : date('d/m/Y', $ts);
}

function date_long_br(?string $date): string
{
    if (!$date) {
        return '';
    }
    $months = ['janeiro', 'fevereiro', 'março', 'abril', 'maio', 'junho', 'julho',
        'agosto', 'setembro', 'outubro', 'novembro', 'dezembro'];
    $ts = strtotime($date);
    return date('j', $ts) . ' de ' . $months[(int) date('n', $ts) - 1] . ' de ' . date('Y', $ts);
}

function time_ago(?string $date): string
{
    if (!$date) {
        return 'nunca';
    }
    $diff = time() - strtotime($date);
    return match (true) {
        $diff < 60 => 'agora há pouco',
        $diff < 3600 => 'há ' . plural((int) floor($diff / 60), 'minuto', 'minutos'),
        $diff < 86400 => 'há ' . plural((int) floor($diff / 3600), 'hora', 'horas'),
        $diff < 86400 * 30 => 'há ' . plural((int) floor($diff / 86400), 'dia', 'dias'),
        default => 'em ' . date_br($date),
    };
}

function plural(int $n, string $singular, string $pluralForm): string
{
    return number_format($n, 0, ',', '.') . ' ' . ($n === 1 ? $singular : $pluralForm);
}

/** 754 → "12 min" · 4000 → "1 h 07 min" */
function duration_fmt(int $seconds): string
{
    if ($seconds <= 0) {
        return '—';
    }
    $h = intdiv($seconds, 3600);
    $m = intdiv($seconds % 3600, 60);
    if ($h > 0) {
        return $h . ' h ' . str_pad((string) $m, 2, '0', STR_PAD_LEFT) . ' min';
    }
    return max(1, $m) . ' min';
}

function clock_fmt(int $seconds): string
{
    return sprintf('%d:%02d', intdiv($seconds, 60), $seconds % 60);
}

function str_slug(string $text): string
{
    $text = transliterator_transliterate('Any-Latin; Latin-ASCII; Lower()', $text) ?: strtolower($text);
    $text = preg_replace('/[^a-z0-9]+/', '-', $text);
    return trim((string) $text, '-') ?: 'item';
}

function str_limit(?string $text, int $limit = 120): string
{
    $text = trim((string) $text);
    return mb_strlen($text) > $limit ? rtrim(mb_substr($text, 0, $limit - 1)) . '…' : $text;
}

function initials(string $name): string
{
    $parts = preg_split('/\s+/', trim($name)) ?: [];
    $first = mb_substr($parts[0] ?? '', 0, 1);
    $last = count($parts) > 1 ? mb_substr((string) end($parts), 0, 1) : '';
    return mb_strtoupper($first . $last);
}

function level_label(string $level): string
{
    return ['iniciante' => 'Iniciante', 'intermediario' => 'Intermediário', 'avancado' => 'Avançado'][$level] ?? $level;
}

function bytes_fmt(int $bytes): string
{
    $units = ['B', 'KB', 'MB', 'GB'];
    $i = 0;
    $value = (float) $bytes;
    while ($value >= 1024 && $i < count($units) - 1) {
        $value /= 1024;
        $i++;
    }
    return number_format($value, $i === 0 ? 0 : 1, ',', '.') . ' ' . $units[$i];
}

/** Texto simples com parágrafos e listas "- item" → HTML seguro. */
function rich_text(?string $text): string
{
    $text = trim((string) $text);
    if ($text === '') {
        return '';
    }
    $html = '';
    foreach (preg_split("/\n\s*\n/", str_replace("\r", '', $text)) as $block) {
        $lines = array_values(array_filter(array_map('trim', explode("\n", $block)), 'strlen'));
        if ($lines && count(array_filter($lines, fn ($l) => str_starts_with($l, '- '))) === count($lines)) {
            $html .= '<ul>' . implode('', array_map(fn ($l) => '<li>' . inline_md(substr($l, 2)) . '</li>', $lines)) . '</ul>';
        } elseif ($lines && str_starts_with($lines[0], '## ')) {
            $html .= '<h3>' . inline_md(substr($lines[0], 3)) . '</h3>';
            if (count($lines) > 1) {
                $html .= '<p>' . implode('<br>', array_map('inline_md', array_slice($lines, 1))) . '</p>';
            }
        } else {
            $html .= '<p>' . implode('<br>', array_map('inline_md', $lines)) . '</p>';
        }
    }
    return $html;
}

function inline_md(string $line): string
{
    $line = e($line);
    return (string) preg_replace('/\*\*(.+?)\*\*/', '<strong>$1</strong>', $line);
}

function lines(?string $text): array
{
    return array_values(array_filter(array_map(
        fn ($l) => ltrim(trim($l), '- '),
        explode("\n", str_replace("\r", '', (string) $text))
    ), 'strlen'));
}

function csp_nonce(): string
{
    static $nonce = null;
    return $nonce ??= base64_encode(random_bytes(16));
}

function is_path(string $prefix): bool
{
    $path = App\Core\Request::current()?->path() ?? '/';
    return $prefix === '/' ? $path === '/' : str_starts_with($path, $prefix);
}

function mask_cpf(?string $cpf): ?string
{
    $digits = preg_replace('/\D/', '', (string) $cpf);
    if (strlen((string) $digits) !== 11) {
        return null;
    }
    return '***.' . substr($digits, 3, 3) . '.' . substr($digits, 6, 3) . '-**';
}

function icon(string $name, string $class = 'icon'): string
{
    return App\Core\Icons::svg($name, $class);
}

function partial(string $template, array $data = []): void
{
    echo App\Core\View::partial($template, $data);
}
