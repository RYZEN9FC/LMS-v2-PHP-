<?php

namespace App\Support;

class StockFormatter
{
    public static function bottlesAndMl(float $volumeMl, float $bottleSizeMl, bool $withBottleSize = false): string
    {
        $absoluteMl = (int) round(abs($volumeMl) * 100);
        $size = max(1, (int) round($bottleSizeMl));
        $bottles = intdiv($absoluteMl, $size * 100);
        $remainingMl = ($absoluteMl % ($size * 100)) / 100;
        $text = $bottles.' bottles + '.rtrim(rtrim(number_format($remainingMl, 2, '.', ''), '0'), '.').' ml';
        if ($volumeMl < 0) {
            $text = '-('.$text.')';
        }

        return $withBottleSize ? sprintf('%s / (Bottle size: %d ml)', $text, $size) : $text;
    }

    public static function ml(float $volumeMl): string
    {
        return rtrim(rtrim(number_format($volumeMl, 2), '0'), '.').' ml';
    }

    public static function money(?float $amount): string
    {
        if ($amount === null) {
            return 'Unknown';
        }

        return '₹'.number_format($amount, 2);
    }

    /** HTML for Dompdf reports, where the currency glyph uses a dedicated Unicode font. */
    public static function pdfMoney(?float $amount): string
    {
        if ($amount === null) {
            return 'Unknown';
        }

        return '<span class="rupee">₹</span>'.number_format($amount, 2);
    }
}
