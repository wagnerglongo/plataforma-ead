<?php

declare(strict_types=1);

namespace App\Lib;

/**
 * Gerador de PDF mínimo (PDF 1.4) sem bibliotecas.
 * Cada página é uma imagem JPEG em tamanho total (DCTDecode, sem recompressão)
 * com áreas clicáveis opcionais (anotações /Link com URI).
 */
final class PdfDocument
{
    private array $pages = [];
    private array $info = [];

    public function setInfo(string $title, string $author, string $subject = ''): self
    {
        $this->info = compact('title', 'author', 'subject');
        return $this;
    }

    /**
     * @param string $jpeg   bytes de um JPEG RGB
     * @param float  $widthPt / $heightPt tamanho da página em pontos (A4 paisagem = 841.89 x 595.28)
     * @param array  $links  [[x, y, w, h, url], …] em pontos, origem no canto superior esquerdo
     */
    public function addImagePage(string $jpeg, float $widthPt, float $heightPt, array $links = []): self
    {
        $size = getimagesizefromstring($jpeg);
        if (!$size || $size[2] !== IMAGETYPE_JPEG) {
            throw new \InvalidArgumentException('A página precisa ser um JPEG.');
        }
        $this->pages[] = ['jpeg' => $jpeg, 'pw' => $size[0], 'ph' => $size[1], 'w' => $widthPt, 'h' => $heightPt, 'links' => $links];
        return $this;
    }

    public function output(): string
    {
        $objects = [];
        $add = function (string $body) use (&$objects): int {
            $objects[] = $body;
            return count($objects);
        };

        $catalogId = $add(''); // preenchido depois
        $pagesId = $add('');
        $kids = [];
        foreach ($this->pages as $i => $p) {
            $imgId = $add(sprintf(
                "<< /Type /XObject /Subtype /Image /Width %d /Height %d /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /DCTDecode /Length %d >>\nstream\n%s\nendstream",
                $p['pw'], $p['ph'], strlen($p['jpeg']), $p['jpeg'],
            ));
            $content = sprintf("q %.2F 0 0 %.2F 0 0 cm /Im%d Do Q", $p['w'], $p['h'], $i + 1);
            $contentId = $add("<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream");
            $annots = [];
            foreach ($p['links'] as [$x, $y, $w, $h, $uri]) {
                $rect = sprintf('%.2F %.2F %.2F %.2F', $x, $p['h'] - $y - $h, $x + $w, $p['h'] - $y);
                $annots[] = $add("<< /Type /Annot /Subtype /Link /Rect [{$rect}] /Border [0 0 0] /A << /S /URI /URI " . self::str($uri) . " >> >>") . ' 0 R';
            }
            $kids[] = $add(sprintf(
                '<< /Type /Page /Parent %d 0 R /MediaBox [0 0 %.2F %.2F] /Resources << /XObject << /Im%d %d 0 R >> >> /Contents %d 0 R%s >>',
                $pagesId, $p['w'], $p['h'], $i + 1, $imgId, $contentId,
                $annots ? ' /Annots [' . implode(' ', $annots) . ']' : '',
            )) . ' 0 R';
        }
        $objects[$catalogId - 1] = "<< /Type /Catalog /Pages {$pagesId} 0 R >>";
        $objects[$pagesId - 1] = '<< /Type /Pages /Kids [' . implode(' ', $kids) . '] /Count ' . count($kids) . ' >>';

        $infoId = $add(sprintf(
            '<< /Title %s /Author %s /Subject %s /Creator %s /Producer %s /CreationDate (D:%s) >>',
            self::text($this->info['title'] ?? ''),
            self::text($this->info['author'] ?? ''),
            self::text($this->info['subject'] ?? ''),
            self::text('Plataforma EAD'),
            self::text('PdfDocument (PHP)'),
            date('YmdHis'),
        ));

        $pdf = "%PDF-1.4\n%\xE2\xE3\xCF\xD3\n";
        $offsets = [];
        foreach ($objects as $i => $body) {
            $offsets[] = strlen($pdf);
            $pdf .= ($i + 1) . " 0 obj\n{$body}\nendobj\n";
        }
        $xref = strlen($pdf);
        $pdf .= "xref\n0 " . (count($objects) + 1) . "\n0000000000 65535 f \n";
        foreach ($offsets as $o) {
            $pdf .= sprintf("%010d 00000 n \n", $o);
        }
        $pdf .= "trailer\n<< /Size " . (count($objects) + 1) . " /Root {$catalogId} 0 R /Info {$infoId} 0 R >>\nstartxref\n{$xref}\n%%EOF\n";
        return $pdf;
    }

    /** String ASCII literal com escape. */
    private static function str(string $s): string
    {
        return '(' . strtr($s, ['\\' => '\\\\', '(' => '\\(', ')' => '\\)', "\r" => '', "\n" => '']) . ')';
    }

    /** Texto Unicode (UTF-16BE com BOM) para metadados. */
    private static function text(string $s): string
    {
        return '<FEFF' . strtoupper(bin2hex(mb_convert_encoding($s, 'UTF-16BE', 'UTF-8'))) . '>';
    }
}
