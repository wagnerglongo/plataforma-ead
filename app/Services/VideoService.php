<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Integração com provedores de vídeo.
 *  - local: arquivo enviado para o servidor (bom para começar; consome banda da hospedagem)
 *  - youtube / vimeo: com API do player, o tempo assistido é medido
 *  - embed: Panda Video, Bunny Stream e afins (iframe; conclusão pelo botão)
 */
final class VideoService
{
    public const PROVIDERS = [
        'local' => 'Arquivo enviado (MP4)',
        'youtube' => 'YouTube',
        'vimeo' => 'Vimeo',
        'embed' => 'Panda Video, Bunny Stream ou outro (link de incorporação)',
    ];

    /** Hosts permitidos em iframes (mesma lista da Content-Security-Policy). */
    private const EMBED_HOSTS = [
        '/\.tv\.pandavideo\.com\.br$/',
        '/^iframe\.mediadelivery\.net$/',
        '/^player\.vimeo\.com$/',
        '/^www\.youtube(-nocookie)?\.com$/',
    ];

    public static function youtubeId(string $ref): string
    {
        if (preg_match('~(?:youtu\.be/|youtube(?:-nocookie)?\.com/(?:watch\?(?:.*&)?v=|embed/|shorts/|live/))([A-Za-z0-9_-]{11})~', $ref, $m)) {
            return $m[1];
        }
        return preg_match('/^[A-Za-z0-9_-]{11}$/', $ref) ? $ref : '';
    }

    public static function vimeoId(string $ref): string
    {
        return preg_match('~(\d{6,12})~', $ref, $m) ? $m[1] : '';
    }

    public static function safeEmbed(string $url): ?string
    {
        $host = parse_url($url, PHP_URL_HOST);
        if (!$host || parse_url($url, PHP_URL_SCHEME) !== 'https') {
            return null;
        }
        foreach (self::EMBED_HOSTS as $pattern) {
            if (preg_match($pattern, $host)) {
                return $url;
            }
        }
        return null;
    }

    /**
     * Arquivo local do vídeo. Se existir uma versão .webm ao lado do .mp4, ela é oferecida
     * como fonte alternativa (navegadores sem H.264, como o Chromium puro).
     * @return array{0:string,1:string}|null caminho e mime
     */
    public static function localFile(string $ref, string $format = 'mp4'): ?array
    {
        $path = \App\Core\Upload::privatePath($ref);
        if (!$path) {
            return null;
        }
        if ($format === 'webm') {
            $alt = preg_replace('/\.mp4$/', '.webm', $path);
            return $alt && is_file($alt) ? [$alt, 'video/webm'] : null;
        }
        return [$path, str_ends_with($path, '.webm') ? 'video/webm' : 'video/mp4'];
    }

    public static function hasWebm(string $ref): bool
    {
        return self::localFile($ref, 'webm') !== null;
    }

    /** O tempo assistido pode ser medido? (senão, conclusão pelo botão) */
    public static function measurable(?string $provider): bool
    {
        return in_array($provider, ['local', 'youtube', 'vimeo'], true);
    }

    /** Valida a referência informada pelo admin; retorna mensagem de erro ou null. */
    public static function validateRef(string $provider, string $ref): ?string
    {
        return match ($provider) {
            'youtube' => self::youtubeId($ref) === '' ? 'Cole o link do vídeo do YouTube (ex.: https://youtu.be/…).' : null,
            'vimeo' => self::vimeoId($ref) === '' ? 'Cole o link do vídeo do Vimeo (ex.: https://vimeo.com/123456789).' : null,
            'embed' => self::safeEmbed($ref) === null ? 'Use o link de incorporação (https) do Panda Video, Bunny Stream, YouTube ou Vimeo.' : null,
            default => null,
        };
    }
}
