<?php

declare(strict_types=1);

namespace App\Lib;

/**
 * Desenha o certificado com GD (A4 paisagem, ~254 dpi) e exporta PNG ou PDF.
 * Fontes TTF locais em resources/fonts (licença OFL).
 */
final class CertificateRenderer
{
    private const W = 2970;
    private const H = 2100;

    private \GdImage $img;
    private float $s;
    private array $fonts;

    public function __construct()
    {
        $dir = BASE_PATH . '/resources/fonts/';
        $this->fonts = [
            'display' => $dir . 'BricolageGrotesque-Bold.ttf',
            'display-medium' => $dir . 'BricolageGrotesque-Medium.ttf',
            'body' => $dir . 'AtkinsonHyperlegible-Regular.ttf',
            'body-bold' => $dir . 'AtkinsonHyperlegible-Bold.ttf',
            'name' => $dir . 'CormorantGaramond-SemiBold.ttf',
        ];
        $this->s = self::W / 1000;
    }

    /**
     * @param array{code:string, student_name:string, course_title:string, workload_hours:int,
     *              issued_at:string, final_score:?int, site_name:string, signer_name:string,
     *              signer_role:string, validation_url:string, student_doc:?string} $c
     */
    public function render(array $c): \GdImage
    {
        $this->img = imagecreatetruecolor(self::W, self::H);
        imagealphablending($this->img, true);
        imageantialias($this->img, true);

        $paper = $this->rgb('#FBFCFE');
        $ink = $this->rgb('#1F3A93');
        $inkDeep = $this->rgb('#172C70');
        $graphite = $this->rgb('#1E2430');
        $muted = $this->rgb('#5B6478');
        $rule = $this->rgb('#DCE1EC');
        $white = $this->rgb('#FFFFFF');

        imagefilledrectangle($this->img, 0, 0, self::W, self::H, $paper);

        // Faixa lateral com padrão de segurança (linhas senoidais finas, como em papel-moeda).
        $band = (int) $this->u(300);
        imagefilledrectangle($this->img, 0, 0, $band, self::H, $ink);
        $this->guilloche(0, $band, $inkDeep);
        imagefilledrectangle($this->img, $band, 0, $band + (int) $this->u(4), self::H, $this->rgb('#F5E04B'));

        // Moldura fina no lado do texto.
        imagesetthickness($this->img, 3);
        imagerectangle($this->img, $band + (int) $this->u(30), (int) $this->u(26), self::W - (int) $this->u(26), self::H - (int) $this->u(26), $rule);
        imagesetthickness($this->img, 1);

        // --- Faixa: instituição, selo, QR
        $this->paragraph($c['site_name'], 'display', 19, $this->u(44), $this->u(78), $this->u(215), $white, 1.15);
        $this->text('Certificado nº ' . $c['code'], 'display-medium', 10.5, $this->u(44), $this->u(150), $this->rgba('#FFFFFF', 30));

        $this->seal((int) $this->u(150), (int) $this->u(318), (int) $this->u(84), date('Y', strtotime($c['issued_at'])));

        $qrSize = (int) $this->u(176);
        $qx = (int) $this->u(62);
        $qy = (int) $this->u(462);
        $this->roundedRect($qx - 10, $qy - 10, $qx + $qrSize + 10, $qy + $qrSize + 10, 24, $white);
        QrCode::drawOnImage($this->img, $c['validation_url'], $qx, $qy, $qrSize, $graphite);
        $this->text('Aponte a câmera para', 'body', 10.5, $this->u(44), $this->u(672), $this->rgba('#FFFFFF', 20));
        $this->text('conferir a autenticidade', 'body', 10.5, $this->u(44), $this->u(688), $this->rgba('#FFFFFF', 20));

        // --- Conteúdo
        $x = $this->u(372);
        $maxW = $this->u(560);
        $this->text('Certificado de conclusão', 'display', 30, $x, $this->u(128), $ink);
        $this->text('Certificamos que', 'body', 15, $x, $this->u(206), $muted);

        // Nome com traço de marca-texto por trás: o elemento de assinatura visual da plataforma.
        $nameSize = $this->fitSize($c['student_name'], 'name', 52, $maxW);
        $nameBox = $this->measure($c['student_name'], 'name', $nameSize);
        $baseline = $this->u(290);
        $this->highlighter($x - $this->u(8), $baseline - $this->u(30), $nameBox + $this->u(16), $this->u(34));
        $this->text($c['student_name'], 'name', $nameSize, $x, $baseline, $graphite);

        $y = $this->u(352);
        $this->text('concluiu o curso', 'body', 15, $x, $y, $muted);
        $y = $this->paragraph($c['course_title'], 'display', 24, $x, $y + $this->u(48), $maxW, $graphite, 1.2, 2);

        $details = sprintf('Carga horária de %d horas, concluído em %s', $c['workload_hours'], date_long_br($c['issued_at']));
        $details .= $c['final_score'] !== null ? sprintf(', com nota final de %d%%.', $c['final_score']) : '.';
        if (!empty($c['student_doc'])) {
            $details .= ' Documento do aluno: ' . $c['student_doc'] . '.';
        }
        $this->paragraph($details, 'display-medium', 14.5, $x, $y + $this->u(44), $maxW, $graphite, 1.5);

        // Assinatura e código
        $sy = $this->u(600);
        $this->text($c['signer_name'], 'name', 22, $x, $sy, $graphite);
        imagesetthickness($this->img, 3);
        imageline($this->img, (int) $x, (int) ($sy + $this->u(14)), (int) ($x + $this->u(230)), (int) ($sy + $this->u(14)), $graphite);
        imagesetthickness($this->img, 1);
        $this->text($c['signer_role'], 'body', 11.5, $x, $sy + $this->u(38), $muted);

        $cx = $this->u(690);
        $this->text('Código de validação', 'body', 11.5, $cx, $sy - $this->u(28), $muted);
        $this->text($c['code'], 'display', 22, $cx, $sy + $this->u(4), $ink);
        $host = preg_replace('#^https?://#', '', preg_replace('#/certificado/.*$#', '/validar', $c['validation_url']));
        $this->text($host, 'body', 11.5, $cx, $sy + $this->u(38), $muted);

        return $this->img;
    }

    public function png(array $c, int $width = 1400): string
    {
        $img = $this->render($c);
        $h = (int) round($width * self::H / self::W);
        $small = imagecreatetruecolor($width, $h);
        imagecopyresampled($small, $img, 0, 0, 0, 0, $width, $h, self::W, self::H);
        ob_start();
        imagepng($small, null, 7);
        imagedestroy($small);
        imagedestroy($img);
        return (string) ob_get_clean();
    }

    public function pdf(array $c): string
    {
        $img = $this->render($c);
        ob_start();
        imagejpeg($img, null, 90);
        $jpeg = (string) ob_get_clean();
        imagedestroy($img);

        // Área clicável sobre o QR (coordenadas em pontos, A4 paisagem).
        $k = 841.89 / self::W;
        $qx = $this->u(62) * $k;
        $qy = $this->u(462) * $k;
        $qs = $this->u(176) * $k;

        return (new PdfDocument())
            ->setInfo('Certificado ' . $c['code'] . ' · ' . $c['student_name'], $c['site_name'], $c['course_title'])
            ->addImagePage($jpeg, 841.89, 595.28, [[$qx, $qy, $qs, $qs, $c['validation_url']]])
            ->output();
    }

    // ------------------------------------------------------------ desenho

    private function u(float $v): float
    {
        return $v * $this->s;
    }

    private function rgb(string $hex): int
    {
        [$r, $g, $b] = sscanf($hex, '#%02x%02x%02x');
        return imagecolorallocate($this->img, $r, $g, $b);
    }

    /** $alpha: 0 = opaco, 127 = transparente */
    private function rgba(string $hex, int $alpha): int
    {
        [$r, $g, $b] = sscanf($hex, '#%02x%02x%02x');
        return imagecolorallocatealpha($this->img, $r, $g, $b, $alpha);
    }

    private function pt(float $sizeUnits): float
    {
        return $this->u($sizeUnits) / 1.333;
    }

    private function text(string $text, string $font, float $size, float $x, float $y, int $color): void
    {
        imagettftext($this->img, $this->pt($size), 0, (int) $x, (int) $y, $color, $this->fonts[$font], $text);
    }

    private function measure(string $text, string $font, float $size): float
    {
        $box = imagettfbbox($this->pt($size), 0, $this->fonts[$font], $text);
        return (float) ($box[2] - $box[0]);
    }

    private function fitSize(string $text, string $font, float $size, float $maxW): float
    {
        while ($size > 26 && $this->measure($text, $font, $size) > $maxW) {
            $size -= 1;
        }
        return $size;
    }

    /** Quebra o texto em linhas; retorna o y da última linha. */
    private function paragraph(string $text, string $font, float $size, float $x, float $y, float $maxW, int $color, float $lineHeight, int $maxLines = 6): float
    {
        $words = preg_split('/\s+/', trim($text)) ?: [];
        $lines = [];
        $line = '';
        foreach ($words as $word) {
            $try = $line === '' ? $word : $line . ' ' . $word;
            if ($this->measure($try, $font, $size) > $maxW && $line !== '') {
                $lines[] = $line;
                $line = $word;
            } else {
                $line = $try;
            }
        }
        if ($line !== '') {
            $lines[] = $line;
        }
        if (count($lines) > $maxLines) {
            $lines = array_slice($lines, 0, $maxLines);
            $lines[$maxLines - 1] = rtrim($lines[$maxLines - 1], '.,;') . '…';
        }
        foreach ($lines as $i => $l) {
            $this->text($l, $font, $size, $x, $y + $i * $this->u($size) * $lineHeight, $color);
        }
        return $y + (count($lines) - 1) * $this->u($size) * $lineHeight;
    }

    private function highlighter(float $x, float $y, float $w, float $h): void
    {
        $color = $this->rgba('#F5E04B', 38);
        // Leve inclinação e pontas irregulares, como um traço de caneta.
        $points = [
            (int) $x, (int) ($y + $h * 0.18),
            (int) ($x + $w), (int) $y,
            (int) ($x + $w - $this->u(3)), (int) ($y + $h),
            (int) ($x + $this->u(2)), (int) ($y + $h * 1.06),
        ];
        imagefilledpolygon($this->img, $points, $color);
    }

    private function guilloche(int $x0, int $x1, int $color): void
    {
        $line = $this->rgba('#3D5BB8', 70);
        $cx = ($x0 + $x1) / 2;
        $amp = ($x1 - $x0) * 0.42;
        for ($k = 0; $k < 22; $k++) {
            $phase = $k * 0.28;
            $prev = null;
            for ($y = 0; $y <= self::H; $y += 6) {
                $t = $y / self::H * M_PI * 7;
                $x = $cx + $amp * sin($t + $phase) * cos($t * 0.31 - $phase * 0.5);
                if ($prev) {
                    imageline($this->img, (int) $prev[0], (int) $prev[1], (int) $x, $y, $line);
                }
                $prev = [$x, $y];
            }
        }
    }

    private function seal(int $cx, int $cy, int $r, string $year): void
    {
        $white = $this->rgb('#FFFFFF');
        $soft = $this->rgba('#FFFFFF', 85);
        // Roseta: pétalas ao redor do círculo.
        for ($i = 0; $i < 36; $i++) {
            $a = $i * M_PI * 2 / 36;
            $px = $cx + cos($a) * ($r + 6);
            $py = $cy + sin($a) * ($r + 6);
            imagefilledellipse($this->img, (int) $px, (int) $py, (int) ($r * 0.32), (int) ($r * 0.32), $soft);
        }
        imagefilledellipse($this->img, $cx, $cy, $r * 2, $r * 2, $this->rgb('#F5E04B'));
        imagesetthickness($this->img, 4);
        imageellipse($this->img, $cx, $cy, (int) ($r * 1.68), (int) ($r * 1.68), $this->rgb('#1F3A93'));
        imagesetthickness($this->img, 1);
        $ink = $this->rgb('#1F3A93');
        $w = $this->measure($year, 'display', 24);
        $this->text($year, 'display', 24, $cx - $w / 2, $cy + $this->u(4), $ink);
        $label = 'concluído';
        $lw = $this->measure($label, 'body-bold', 10);
        $this->text($label, 'body-bold', 10, $cx - $lw / 2, $cy + $this->u(24), $ink);
        unset($white);
    }

    private function roundedRect(int $x1, int $y1, int $x2, int $y2, int $r, int $color): void
    {
        imagefilledrectangle($this->img, $x1 + $r, $y1, $x2 - $r, $y2, $color);
        imagefilledrectangle($this->img, $x1, $y1 + $r, $x2, $y2 - $r, $color);
        foreach ([[$x1 + $r, $y1 + $r], [$x2 - $r, $y1 + $r], [$x1 + $r, $y2 - $r], [$x2 - $r, $y2 - $r]] as [$x, $y]) {
            imagefilledellipse($this->img, $x, $y, $r * 2, $r * 2, $color);
        }
    }
}
