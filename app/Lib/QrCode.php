<?php

declare(strict_types=1);

namespace App\Lib;

/**
 * Codificador de QR Code (ISO/IEC 18004) escrito do zero, sem dependências.
 * Modo byte, correção de erro nível M, versões 1 a 10 (até 213 bytes):
 * suficiente para URLs de validação de certificado.
 *
 * Etapas: dados → bits → blocos + Reed-Solomon → intercalação →
 * padrões fixos → posicionamento em zigue-zague → melhor máscara → informação de formato.
 */
final class QrCode
{
    /** [total de codewords, EC por bloco, blocos grupo 1, dados/bloco g1, blocos g2, dados/bloco g2] — nível M */
    private const BLOCKS = [
        1 => [26, 10, 1, 16, 0, 0],
        2 => [44, 16, 1, 28, 0, 0],
        3 => [70, 26, 1, 44, 0, 0],
        4 => [100, 18, 2, 32, 0, 0],
        5 => [134, 24, 2, 43, 0, 0],
        6 => [172, 16, 4, 27, 0, 0],
        7 => [196, 18, 4, 31, 0, 0],
        8 => [242, 22, 2, 38, 2, 39],
        9 => [292, 22, 3, 36, 2, 37],
        10 => [346, 26, 4, 43, 1, 44],
    ];

    private const ALIGNMENT = [
        1 => [], 2 => [6, 18], 3 => [6, 22], 4 => [6, 26], 5 => [6, 30],
        6 => [6, 34], 7 => [6, 22, 38], 8 => [6, 24, 42], 9 => [6, 26, 46], 10 => [6, 28, 50],
    ];

    private int $size;
    /** @var array<int, array<int, bool>> [y][x] */
    private array $modules = [];
    /** @var array<int, array<int, bool>> */
    private array $isFunction = [];

    private function __construct(private int $version)
    {
        $this->size = $version * 4 + 17;
        $row = array_fill(0, $this->size, false);
        $this->modules = array_fill(0, $this->size, $row);
        $this->isFunction = array_fill(0, $this->size, $row);
    }

    /** @return array<int, array<int, bool>> matriz [linha][coluna], true = módulo escuro */
    public static function matrix(string $text): array
    {
        $bytes = array_values(unpack('C*', $text) ?: []);
        $version = null;
        foreach (self::BLOCKS as $v => $b) {
            $dataCapacity = $b[2] * $b[3] + $b[4] * $b[5];
            $countBits = $v <= 9 ? 8 : 16;
            if (4 + $countBits + count($bytes) * 8 <= $dataCapacity * 8) {
                $version = $v;
                break;
            }
        }
        if ($version === null) {
            throw new \InvalidArgumentException('Texto longo demais para o QR Code (máximo 213 bytes).');
        }
        $qr = new self($version);
        $codewords = $qr->addErrorCorrection($qr->encodeData($bytes));
        $qr->drawFunctionPatterns();
        $qr->drawCodewords($codewords);
        $qr->applyBestMask();
        return $qr->modules;
    }

    /** SVG pronto para HTML/impressão. */
    public static function svg(string $text, int $pixelSize = 6, int $margin = 4, string $color = '#000', string $bg = '#fff'): string
    {
        $m = self::matrix($text);
        $n = count($m);
        $dim = ($n + $margin * 2);
        $path = '';
        foreach ($m as $y => $row) {
            foreach ($row as $x => $dark) {
                if ($dark) {
                    $path .= 'M' . ($x + $margin) . ' ' . ($y + $margin) . 'h1v1h-1z';
                }
            }
        }
        $px = $dim * $pixelSize;
        return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 ' . $dim . ' ' . $dim . '" width="' . $px . '" height="' . $px
            . '" shape-rendering="crispEdges" role="img" aria-label="QR Code"><rect width="100%" height="100%" fill="' . $bg . '"/><path fill="' . $color . '" d="' . $path . '"/></svg>';
    }

    /** Desenha em uma imagem GD existente. */
    public static function drawOnImage(\GdImage $img, string $text, int $x, int $y, int $sizePx, int $color, ?int $bg = null): void
    {
        $m = self::matrix($text);
        $n = count($m);
        $margin = 2;
        $module = $sizePx / ($n + $margin * 2);
        if ($bg !== null) {
            imagefilledrectangle($img, $x, $y, $x + $sizePx, $y + $sizePx, $bg);
        }
        foreach ($m as $row => $cols) {
            foreach ($cols as $col => $dark) {
                if ($dark) {
                    $x1 = (int) round($x + ($col + $margin) * $module);
                    $y1 = (int) round($y + ($row + $margin) * $module);
                    $x2 = (int) round($x + ($col + $margin + 1) * $module) - 1;
                    $y2 = (int) round($y + ($row + $margin + 1) * $module) - 1;
                    imagefilledrectangle($img, $x1, $y1, $x2, $y2, $color);
                }
            }
        }
    }

    // ---------------------------------------------------------------- dados

    private function encodeData(array $bytes): array
    {
        $b = self::BLOCKS[$this->version];
        $capacityBits = ($b[2] * $b[3] + $b[4] * $b[5]) * 8;
        $bits = [];
        $append = static function (int $value, int $length) use (&$bits): void {
            for ($i = $length - 1; $i >= 0; $i--) {
                $bits[] = ($value >> $i) & 1;
            }
        };
        $append(0b0100, 4); // modo byte
        $append(count($bytes), $this->version <= 9 ? 8 : 16);
        foreach ($bytes as $byte) {
            $append($byte, 8);
        }
        $append(0, min(4, $capacityBits - count($bits))); // terminador
        $append(0, (8 - count($bits) % 8) % 8);
        for ($pad = 0xEC; count($bits) < $capacityBits; $pad ^= 0xEC ^ 0x11) {
            $append($pad, 8);
        }
        $codewords = [];
        foreach (array_chunk($bits, 8) as $chunk) {
            $codewords[] = array_reduce($chunk, fn ($acc, $bit) => ($acc << 1) | $bit, 0);
        }
        return $codewords;
    }

    private function addErrorCorrection(array $data): array
    {
        [, $ecLen, $g1, $g1Len, $g2, $g2Len] = self::BLOCKS[$this->version];
        $generator = self::rsGenerator($ecLen);
        $blocks = [];
        $ecBlocks = [];
        $offset = 0;
        foreach ([[$g1, $g1Len], [$g2, $g2Len]] as [$count, $len]) {
            for ($i = 0; $i < $count; $i++) {
                $block = array_slice($data, $offset, $len);
                $offset += $len;
                $blocks[] = $block;
                $ecBlocks[] = self::rsRemainder($block, $generator);
            }
        }
        $result = [];
        $maxLen = max($g1Len, $g2Len);
        for ($i = 0; $i < $maxLen; $i++) {
            foreach ($blocks as $block) {
                if ($i < count($block)) {
                    $result[] = $block[$i];
                }
            }
        }
        for ($i = 0; $i < $ecLen; $i++) {
            foreach ($ecBlocks as $ec) {
                $result[] = $ec[$i];
            }
        }
        return $result;
    }

    private static function rsGenerator(int $degree): array
    {
        $result = array_fill(0, $degree, 0);
        $result[$degree - 1] = 1;
        $root = 1;
        for ($i = 0; $i < $degree; $i++) {
            for ($j = 0; $j < $degree; $j++) {
                $result[$j] = self::gfMul($result[$j], $root);
                if ($j + 1 < $degree) {
                    $result[$j] ^= $result[$j + 1];
                }
            }
            $root = self::gfMul($root, 0x02);
        }
        return $result;
    }

    private static function rsRemainder(array $data, array $generator): array
    {
        $result = array_fill(0, count($generator), 0);
        foreach ($data as $byte) {
            $factor = $byte ^ array_shift($result);
            $result[] = 0;
            foreach ($generator as $i => $coef) {
                $result[$i] ^= self::gfMul($coef, $factor);
            }
        }
        return $result;
    }

    /** Multiplicação no corpo finito GF(2^8) com polinômio 0x11D. */
    private static function gfMul(int $x, int $y): int
    {
        $z = 0;
        for ($i = 7; $i >= 0; $i--) {
            $z = ($z << 1) ^ (($z >> 7) * 0x11D);
            $z ^= (($y >> $i) & 1) * $x;
        }
        return $z & 0xFF;
    }

    // ------------------------------------------------------------- desenho

    private function setFunction(int $x, int $y, bool $dark): void
    {
        $this->modules[$y][$x] = $dark;
        $this->isFunction[$y][$x] = true;
    }

    private function drawFunctionPatterns(): void
    {
        for ($i = 0; $i < $this->size; $i++) { // padrões de sincronismo
            $this->setFunction(6, $i, $i % 2 === 0);
            $this->setFunction($i, 6, $i % 2 === 0);
        }
        $this->drawFinder(3, 3);
        $this->drawFinder($this->size - 4, 3);
        $this->drawFinder(3, $this->size - 4);

        $pos = self::ALIGNMENT[$this->version];
        $n = count($pos);
        for ($i = 0; $i < $n; $i++) {
            for ($j = 0; $j < $n; $j++) {
                $corner = ($i === 0 && $j === 0) || ($i === 0 && $j === $n - 1) || ($i === $n - 1 && $j === 0);
                if (!$corner) {
                    $this->drawAlignment($pos[$i], $pos[$j]);
                }
            }
        }
        $this->drawFormatBits(0); // reserva a área; reescrita depois com a máscara real
        $this->drawVersion();
    }

    private function drawFinder(int $cx, int $cy): void
    {
        for ($dy = -4; $dy <= 4; $dy++) {
            for ($dx = -4; $dx <= 4; $dx++) {
                $x = $cx + $dx;
                $y = $cy + $dy;
                if ($x >= 0 && $x < $this->size && $y >= 0 && $y < $this->size) {
                    $dist = max(abs($dx), abs($dy));
                    $this->setFunction($x, $y, $dist !== 2 && $dist !== 4);
                }
            }
        }
    }

    private function drawAlignment(int $cx, int $cy): void
    {
        for ($dy = -2; $dy <= 2; $dy++) {
            for ($dx = -2; $dx <= 2; $dx++) {
                $this->setFunction($cx + $dx, $cy + $dy, max(abs($dx), abs($dy)) !== 1);
            }
        }
    }

    private function drawFormatBits(int $mask): void
    {
        $data = (0b00 << 3) | $mask; // nível M = 00
        $rem = $data;
        for ($i = 0; $i < 10; $i++) {
            $rem = ($rem << 1) ^ (($rem >> 9) * 0x537);
        }
        $bits = (($data << 10) | $rem) ^ 0x5412;
        $bit = fn (int $i): bool => (($bits >> $i) & 1) === 1;

        for ($i = 0; $i <= 5; $i++) {
            $this->setFunction(8, $i, $bit($i));
        }
        $this->setFunction(8, 7, $bit(6));
        $this->setFunction(8, 8, $bit(7));
        $this->setFunction(7, 8, $bit(8));
        for ($i = 9; $i < 15; $i++) {
            $this->setFunction(14 - $i, 8, $bit($i));
        }
        for ($i = 0; $i < 8; $i++) {
            $this->setFunction($this->size - 1 - $i, 8, $bit($i));
        }
        for ($i = 8; $i < 15; $i++) {
            $this->setFunction(8, $this->size - 15 + $i, $bit($i));
        }
        $this->setFunction(8, $this->size - 8, true); // módulo escuro fixo
    }

    private function drawVersion(): void
    {
        if ($this->version < 7) {
            return;
        }
        $rem = $this->version;
        for ($i = 0; $i < 12; $i++) {
            $rem = ($rem << 1) ^ (($rem >> 11) * 0x1F25);
        }
        $bits = ($this->version << 12) | $rem;
        for ($i = 0; $i < 18; $i++) {
            $dark = (($bits >> $i) & 1) === 1;
            $a = $this->size - 11 + $i % 3;
            $b = intdiv($i, 3);
            $this->setFunction($a, $b, $dark);
            $this->setFunction($b, $a, $dark);
        }
    }

    private function drawCodewords(array $codewords): void
    {
        $total = count($codewords) * 8;
        $i = 0;
        for ($right = $this->size - 1; $right >= 1; $right -= 2) {
            if ($right === 6) {
                $right = 5;
            }
            for ($vert = 0; $vert < $this->size; $vert++) {
                for ($j = 0; $j < 2; $j++) {
                    $x = $right - $j;
                    $upward = (($right + 1) & 2) === 0;
                    $y = $upward ? $this->size - 1 - $vert : $vert;
                    if (!$this->isFunction[$y][$x] && $i < $total) {
                        $this->modules[$y][$x] = (($codewords[$i >> 3] >> (7 - ($i & 7))) & 1) === 1;
                        $i++;
                    }
                }
            }
        }
    }

    private static function maskBit(int $mask, int $x, int $y): bool
    {
        return match ($mask) {
            0 => ($x + $y) % 2 === 0,
            1 => $y % 2 === 0,
            2 => $x % 3 === 0,
            3 => ($x + $y) % 3 === 0,
            4 => (intdiv($x, 3) + intdiv($y, 2)) % 2 === 0,
            5 => ($x * $y) % 2 + ($x * $y) % 3 === 0,
            6 => (($x * $y) % 2 + ($x * $y) % 3) % 2 === 0,
            7 => (($x + $y) % 2 + ($x * $y) % 3) % 2 === 0,
        };
    }

    private function applyMask(int $mask): void
    {
        for ($y = 0; $y < $this->size; $y++) {
            for ($x = 0; $x < $this->size; $x++) {
                if (!$this->isFunction[$y][$x] && self::maskBit($mask, $x, $y)) {
                    $this->modules[$y][$x] = !$this->modules[$y][$x];
                }
            }
        }
    }

    private function applyBestMask(): void
    {
        $best = 0;
        $bestPenalty = PHP_INT_MAX;
        for ($mask = 0; $mask < 8; $mask++) {
            $this->applyMask($mask);
            $this->drawFormatBits($mask);
            $penalty = $this->penalty();
            if ($penalty < $bestPenalty) {
                [$best, $bestPenalty] = [$mask, $penalty];
            }
            $this->applyMask($mask); // desfaz (XOR é reversível)
        }
        $this->applyMask($best);
        $this->drawFormatBits($best);
    }

    /** Regras de penalidade da norma: sequências, blocos 2x2, padrões falsos de localizador e equilíbrio. */
    private function penalty(): int
    {
        $n = $this->size;
        $score = 0;
        $lines = [];
        for ($i = 0; $i < $n; $i++) {
            $row = '';
            $col = '';
            for ($j = 0; $j < $n; $j++) {
                $row .= $this->modules[$i][$j] ? '1' : '0';
                $col .= $this->modules[$j][$i] ? '1' : '0';
            }
            $lines[] = $row;
            $lines[] = $col;
        }
        foreach ($lines as $line) {
            preg_match_all('/0{5,}|1{5,}/', $line, $m);
            foreach ($m[0] as $run) {
                $score += 3 + strlen($run) - 5;
            }
            $score += 40 * (substr_count($line, '10111010000') + substr_count($line, '00001011101'));
        }
        $dark = 0;
        for ($y = 0; $y < $n; $y++) {
            for ($x = 0; $x < $n; $x++) {
                $c = $this->modules[$y][$x];
                $dark += $c ? 1 : 0;
                if ($x < $n - 1 && $y < $n - 1
                    && $c === $this->modules[$y][$x + 1]
                    && $c === $this->modules[$y + 1][$x]
                    && $c === $this->modules[$y + 1][$x + 1]) {
                    $score += 3;
                }
            }
        }
        $percent = $dark * 100 / ($n * $n);
        $score += 10 * (int) floor(abs($percent - 50) / 5);
        return $score;
    }
}
