<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Envio de e-mail sem bibliotecas: cliente SMTP próprio (SSL/STARTTLS + AUTH LOGIN)
 * ou driver "log", que grava a mensagem em storage/logs/mail.log (desenvolvimento).
 */
final class Mailer
{
    public static function send(string $to, string $subject, string $html): bool
    {
        $cfg = config('mail');
        try {
            if ($cfg['driver'] === 'smtp') {
                self::smtp($cfg, $to, $subject, $html);
            } else {
                $line = sprintf("[%s] Para: %s | Assunto: %s\n%s\n%s\n", now(), $to, $subject, strip_tags(str_replace(['<br>', '</p>'], "\n", $html)), str_repeat('-', 60));
                @file_put_contents(BASE_PATH . '/storage/logs/mail.log', $line, FILE_APPEND | LOCK_EX);
            }
            return true;
        } catch (\Throwable $e) {
            Logger::error('Falha ao enviar e-mail', ['to' => $to, 'erro' => $e->getMessage()]);
            return false;
        }
    }

    /** Monta o e-mail com o layout padrão da plataforma. */
    public static function template(string $title, string $bodyHtml, ?string $buttonText = null, ?string $buttonUrl = null): string
    {
        $button = $buttonText && $buttonUrl
            ? '<p style="margin:28px 0"><a href="' . e($buttonUrl) . '" style="background:#1F3A93;color:#fff;padding:12px 22px;border-radius:8px;text-decoration:none;font-weight:bold">' . e($buttonText) . '</a></p>'
            : '';
        return '<!doctype html><html><body style="margin:0;background:#F3F5FA;font-family:Arial,sans-serif;color:#1E2430">'
            . '<div style="max-width:560px;margin:0 auto;padding:32px 20px">'
            . '<p style="font-weight:bold;color:#1F3A93;font-size:18px;margin:0 0 20px">' . e(site_name()) . '</p>'
            . '<div style="background:#fff;border-radius:12px;padding:28px;border:1px solid #DCE1EC">'
            . '<h1 style="font-size:20px;margin:0 0 16px">' . e($title) . '</h1>' . $bodyHtml . $button . '</div>'
            . '<p style="font-size:12px;color:#5B6478;margin-top:20px">Você recebeu este e-mail porque tem uma conta em ' . e(site_name()) . '.</p>'
            . '</div></body></html>';
    }

    private static function smtp(array $cfg, string $to, string $subject, string $html): void
    {
        $host = ($cfg['encryption'] === 'ssl' ? 'ssl://' : '') . $cfg['host'];
        $socket = @stream_socket_client($host . ':' . $cfg['port'], $errno, $errstr, 15);
        if (!$socket) {
            throw new \RuntimeException("SMTP indisponível: {$errstr} ({$errno})");
        }
        stream_set_timeout($socket, 15);
        $expect = static function (array $codes) use ($socket): string {
            $response = '';
            while (($line = fgets($socket, 515)) !== false) {
                $response .= $line;
                if (isset($line[3]) && $line[3] === ' ') {
                    break;
                }
            }
            if (!in_array((int) substr($response, 0, 3), $codes, true)) {
                throw new \RuntimeException('SMTP respondeu: ' . trim($response));
            }
            return $response;
        };
        $cmd = static function (string $command, array $codes) use ($socket, $expect): string {
            fwrite($socket, $command . "\r\n");
            return $expect($codes);
        };

        $domain = parse_url(config('app.url'), PHP_URL_HOST) ?: 'localhost';
        $expect([220]);
        $cmd('EHLO ' . $domain, [250]);
        if ($cfg['encryption'] === 'tls') {
            $cmd('STARTTLS', [220]);
            if (!stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                throw new \RuntimeException('Falha ao iniciar TLS.');
            }
            $cmd('EHLO ' . $domain, [250]);
        }
        if ($cfg['username'] !== '') {
            $cmd('AUTH LOGIN', [334]);
            $cmd(base64_encode($cfg['username']), [334]);
            $cmd(base64_encode($cfg['password']), [235]);
        }
        $cmd('MAIL FROM:<' . $cfg['from'] . '>', [250]);
        $cmd('RCPT TO:<' . $to . '>', [250, 251]);
        $cmd('DATA', [354]);

        $headers = [
            'Date: ' . date('r'),
            'From: =?UTF-8?B?' . base64_encode($cfg['from_name']) . '?= <' . $cfg['from'] . '>',
            'To: <' . $to . '>',
            'Subject: =?UTF-8?B?' . base64_encode($subject) . '?=',
            'Message-ID: <' . bin2hex(random_bytes(12)) . '@' . $domain . '>',
            'MIME-Version: 1.0',
            'Content-Type: text/html; charset=UTF-8',
            'Content-Transfer-Encoding: base64',
        ];
        $body = implode("\r\n", $headers) . "\r\n\r\n" . chunk_split(base64_encode($html));
        $cmd($body . "\r\n.", [250]);
        $cmd('QUIT', [221]);
        fclose($socket);
    }
}
