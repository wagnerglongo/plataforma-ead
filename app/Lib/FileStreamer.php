<?php

declare(strict_types=1);

namespace App\Lib;

use App\Core\Response;

/**
 * Entrega arquivos privados com suporte a Range (HTTP 206), necessário para o
 * aluno avançar e voltar o vídeo sem baixar o arquivo inteiro.
 */
final class FileStreamer
{
    public static function serve(string $path, string $mime, ?string $downloadName = null): Response
    {
        $size = (int) filesize($path);
        $start = 0;
        $end = $size - 1;
        $status = 200;

        $range = $_SERVER['HTTP_RANGE'] ?? '';
        if ($range !== '' && preg_match('/bytes=(\d*)-(\d*)/', $range, $m)) {
            if ($m[1] === '' && $m[2] !== '') { // últimos N bytes
                $start = max(0, $size - (int) $m[2]);
            } else {
                $start = (int) $m[1];
                $end = $m[2] !== '' ? min((int) $m[2], $size - 1) : $end;
            }
            if ($start > $end || $start >= $size) {
                return new Response('', 416, ['Content-Range' => "bytes */{$size}"]);
            }
            $status = 206;
        }
        $length = $end - $start + 1;
        $headers = [
            'Content-Type' => $mime,
            'Accept-Ranges' => 'bytes',
            'Content-Length' => (string) $length,
            'Cache-Control' => 'private, max-age=3600',
            'X-Content-Type-Options' => 'nosniff',
            'Content-Disposition' => $downloadName
                ? 'attachment; filename="' . addcslashes($downloadName, '"\\') . '"; filename*=UTF-8\'\'' . rawurlencode($downloadName)
                : 'inline',
        ];
        if ($status === 206) {
            $headers['Content-Range'] = "bytes {$start}-{$end}/{$size}";
        }

        return Response::stream(static function () use ($path, $start, $length): void {
            if (session_status() === PHP_SESSION_ACTIVE) {
                session_write_close(); // não trava outras abas do aluno durante o download
            }
            while (ob_get_level() > 0) {
                ob_end_clean();
            }
            $fp = fopen($path, 'rb');
            fseek($fp, $start);
            $remaining = $length;
            while ($remaining > 0 && !feof($fp) && !connection_aborted()) {
                $chunk = fread($fp, (int) min(65536, $remaining));
                if ($chunk === false) {
                    break;
                }
                echo $chunk;
                flush();
                $remaining -= strlen($chunk);
            }
            fclose($fp);
        }, $headers, $status);
    }
}
