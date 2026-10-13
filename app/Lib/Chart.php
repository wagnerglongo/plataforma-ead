<?php

declare(strict_types=1);

namespace App\Lib;

/** Gráficos em SVG gerados no servidor: leves, acessíveis e sem biblioteca JavaScript. */
final class Chart
{
    /**
     * Linha com área.
     * @param array<int,string> $labels rótulos do eixo X
     * @param array<int,int|float> $values
     */
    public static function line(array $labels, array $values, string $title, int $w = 720, int $h = 240): string
    {
        $n = count($values);
        if ($n < 2) {
            return '';
        }
        [$padL, $padR, $padT, $padB] = [36, 12, 14, 28];
        $max = max(1, (int) ceil(max($values) * 1.15));
        $step = self::niceStep($max);
        $max = (int) (ceil($max / $step) * $step);
        $x = fn (int $i) => $padL + ($w - $padL - $padR) * $i / ($n - 1);
        $y = fn (float $v) => $padT + ($h - $padT - $padB) * (1 - $v / $max);

        $svg = sprintf('<svg class="chart" viewBox="0 0 %d %d" role="img" aria-label="%s">', $w, $h, e($title));
        for ($v = 0; $v <= $max; $v += $step) {
            $svg .= sprintf('<line class="grid-line" x1="%d" x2="%d" y1="%.1f" y2="%.1f"/><text x="%d" y="%.1f" text-anchor="end">%d</text>', $padL, $w - $padR, $y($v), $y($v), $padL - 6, $y($v) + 4, $v);
        }
        $points = [];
        foreach ($values as $i => $v) {
            $points[] = sprintf('%.1f,%.1f', $x($i), $y((float) $v));
        }
        $svg .= sprintf('<polygon class="area" points="%.1f,%.1f %s %.1f,%.1f"/>', $x(0), $y(0), implode(' ', $points), $x($n - 1), $y(0));
        $svg .= '<polyline class="line" points="' . implode(' ', $points) . '"/>';
        $every = max(1, (int) ceil($n / 8));
        foreach ($values as $i => $v) {
            if ($i % $every === 0 || ($i === $n - 1 && ($n - 1) % $every >= $every / 2)) {
                $svg .= sprintf('<text x="%.1f" y="%d" text-anchor="middle">%s</text>', $x($i), $h - 8, e($labels[$i]));
            }
            $svg .= sprintf('<circle class="dot" cx="%.1f" cy="%.1f" r="%s"><title>%s: %s</title></circle>', $x($i), $y((float) $v), $n > 40 ? '0' : '3', e($labels[$i]), $v);
        }
        return $svg . '</svg>';
    }

    /**
     * Colunas agrupadas (duas séries).
     * @param array<int,string> $labels
     */
    public static function bars(array $labels, array $a, array $b, string $title, int $w = 720, int $h = 240): string
    {
        $n = count($labels);
        if ($n === 0) {
            return '';
        }
        [$padL, $padR, $padT, $padB] = [36, 8, 14, 28];
        $max = max(1, (int) ceil(max(array_merge($a, $b)) * 1.1));
        $step = self::niceStep($max);
        $max = (int) (ceil($max / $step) * $step);
        $slot = ($w - $padL - $padR) / $n;
        $bw = min(22, $slot * 0.32);
        $y = fn (float $v) => $padT + ($h - $padT - $padB) * (1 - $v / $max);
        $svg = sprintf('<svg class="chart" viewBox="0 0 %d %d" role="img" aria-label="%s">', $w, $h, e($title));
        for ($v = 0; $v <= $max; $v += $step) {
            $svg .= sprintf('<line class="grid-line" x1="%d" x2="%d" y1="%.1f" y2="%.1f"/><text x="%d" y="%.1f" text-anchor="end">%d</text>', $padL, $w - $padR, $y($v), $y($v), $padL - 6, $y($v) + 4, $v);
        }
        foreach ($labels as $i => $label) {
            $cx = $padL + $slot * ($i + 0.5);
            $svg .= sprintf('<rect class="bar" x="%.1f" y="%.1f" width="%.1f" height="%.1f" rx="2"><title>%s: %d matrículas</title></rect>', $cx - $bw - 1, $y($a[$i]), $bw, $y(0) - $y($a[$i]), e($label), $a[$i]);
            $svg .= sprintf('<rect class="bar-alt" x="%.1f" y="%.1f" width="%.1f" height="%.1f" rx="2"><title>%s: %d conclusões</title></rect>', $cx + 1, $y($b[$i]), $bw, $y(0) - $y($b[$i]), e($label), $b[$i]);
            $svg .= sprintf('<text x="%.1f" y="%d" text-anchor="middle">%s</text>', $cx, $h - 8, e($label));
        }
        return $svg . '</svg>';
    }

    private static function niceStep(int $max): int
    {
        foreach ([1, 2, 5, 10, 20, 25, 50, 100, 200, 250, 500, 1000] as $s) {
            if ($max / $s <= 5) {
                return $s;
            }
        }
        return (int) ceil($max / 5);
    }
}
