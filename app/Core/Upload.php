<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Uploads seguros:
 *  - extensão E tipo real (finfo) precisam bater com a lista permitida;
 *  - nome aleatório (nunca o nome enviado pelo usuário);
 *  - imagens são reprocessadas pelo GD (remove metadados e qualquer carga escondida);
 *  - materiais e vídeos ficam FORA da pasta pública e só saem por um controller
 *    que confere a matrícula do aluno.
 */
final class Upload
{
    public const IMAGES = [
        'jpg' => ['image/jpeg'], 'jpeg' => ['image/jpeg'],
        'png' => ['image/png'], 'webp' => ['image/webp'],
    ];

    public const MATERIALS = [
        'pdf' => ['application/pdf'],
        'doc' => ['application/msword'],
        'docx' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/zip'],
        'xls' => ['application/vnd.ms-excel'],
        'xlsx' => ['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'application/zip'],
        'ppt' => ['application/vnd.ms-powerpoint'],
        'pptx' => ['application/vnd.openxmlformats-officedocument.presentationml.presentation', 'application/zip'],
        'zip' => ['application/zip', 'application/x-zip-compressed'],
        'txt' => ['text/plain'],
        'csv' => ['text/csv', 'text/plain'],
        'png' => ['image/png'], 'jpg' => ['image/jpeg'], 'jpeg' => ['image/jpeg'],
    ];

    public const VIDEOS = [
        'mp4' => ['video/mp4'],
        'webm' => ['video/webm'],
    ];

    /** Arquivo privado em storage/app/{dir}. @return array{path:string, original:string, mime:string, size:int} */
    public static function storePrivate(array $file, string $dir, array $allowed, int $maxMb): array
    {
        [$ext, $mime] = self::check($file, $allowed, $maxMb);
        $relative = trim($dir, '/') . '/' . date('Y/m') . '/' . bin2hex(random_bytes(16)) . '.' . $ext;
        $target = BASE_PATH . '/storage/app/' . $relative;
        self::ensureDir(dirname($target));
        if (!self::move($file['tmp_name'], $target)) {
            throw ValidationException::withMessage('file', 'Não foi possível salvar o arquivo. Tente de novo.');
        }
        return ['path' => $relative, 'original' => self::cleanName($file['name']), 'mime' => $mime, 'size' => (int) $file['size']];
    }

    /** Imagem pública em public/uploads/{dir}, redimensionada. Retorna o caminho relativo. */
    public static function storeImage(array $file, string $dir, int $maxWidth = 1280, ?float $ratio = null): string
    {
        self::check($file, self::IMAGES, (int) config('uploads.max_image_mb'));
        $src = @imagecreatefromstring((string) file_get_contents($file['tmp_name']));
        if (!$src) {
            throw ValidationException::withMessage('image', 'A imagem enviada está corrompida ou em formato não suportado.');
        }
        $w = imagesx($src);
        $h = imagesy($src);
        [$sx, $sy, $sw, $sh] = [0, 0, $w, $h];
        if ($ratio) { // recorte central na proporção pedida (ex.: 16/9)
            if ($w / $h > $ratio) {
                $sw = (int) round($h * $ratio);
                $sx = (int) (($w - $sw) / 2);
            } else {
                $sh = (int) round($w / $ratio);
                $sy = (int) (($h - $sh) / 2);
            }
        }
        $tw = min($maxWidth, $sw);
        $th = (int) round($sh * ($tw / $sw));
        $dst = imagecreatetruecolor($tw, $th);
        imagefill($dst, 0, 0, imagecolorallocate($dst, 255, 255, 255));
        imagecopyresampled($dst, $src, 0, 0, $sx, $sy, $tw, $th, $sw, $sh);

        $useWebp = function_exists('imagewebp');
        $relative = trim($dir, '/') . '/' . bin2hex(random_bytes(12)) . ($useWebp ? '.webp' : '.jpg');
        $target = BASE_PATH . '/public/uploads/' . $relative;
        self::ensureDir(dirname($target));
        $useWebp ? imagewebp($dst, $target, 82) : imagejpeg($dst, $target, 84);
        imagedestroy($src);
        imagedestroy($dst);
        return $relative;
    }

    public static function deletePublic(?string $relative): void
    {
        if ($relative && !str_contains($relative, '..')) {
            @unlink(BASE_PATH . '/public/uploads/' . $relative);
        }
    }

    public static function deletePrivate(?string $relative): void
    {
        if ($relative && !str_contains($relative, '..') && !str_starts_with($relative, 'demo/')) {
            @unlink(BASE_PATH . '/storage/app/' . $relative);
        }
    }

    public static function privatePath(string $relative): ?string
    {
        if (str_contains($relative, '..')) {
            return null;
        }
        $path = BASE_PATH . '/storage/app/' . $relative;
        return is_file($path) ? $path : null;
    }

    /** @return array{0:string,1:string} extensão e mime */
    private static function check(array $file, array $allowed, int $maxMb): array
    {
        $error = $file['error'] ?? UPLOAD_ERR_NO_FILE;
        if ($error === UPLOAD_ERR_INI_SIZE || $error === UPLOAD_ERR_FORM_SIZE) {
            throw ValidationException::withMessage('file', "O arquivo passa do limite de {$maxMb} MB.");
        }
        if ($error !== UPLOAD_ERR_OK || !is_uploaded_file($file['tmp_name']) && PHP_SAPI !== 'cli') {
            throw ValidationException::withMessage('file', 'O envio do arquivo falhou. Tente de novo.');
        }
        if ($file['size'] > $maxMb * 1024 * 1024) {
            throw ValidationException::withMessage('file', "O arquivo passa do limite de {$maxMb} MB.");
        }
        $ext = strtolower(pathinfo((string) $file['name'], PATHINFO_EXTENSION));
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']) ?: 'application/octet-stream';
        if (!isset($allowed[$ext]) || !in_array($mime, $allowed[$ext], true)) {
            $list = implode(', ', array_map('strtoupper', array_keys($allowed)));
            throw ValidationException::withMessage('file', "Formato não aceito. Envie: {$list}.");
        }
        return [$ext, $mime];
    }

    private static function move(string $tmp, string $target): bool
    {
        return PHP_SAPI === 'cli' ? copy($tmp, $target) : move_uploaded_file($tmp, $target);
    }

    private static function ensureDir(string $dir): void
    {
        if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
            throw new \RuntimeException("Não foi possível criar a pasta {$dir}");
        }
    }

    private static function cleanName(string $name): string
    {
        $name = preg_replace('/[^\pL\pN._ -]+/u', '', basename($name)) ?: 'arquivo';
        return mb_substr($name, 0, 200);
    }
}
