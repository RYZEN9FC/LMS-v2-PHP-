<?php

namespace App\Support;

class FoodStockFormatter
{
    public static function quantity(float $quantity, string $unit): string
    {
        if ($unit === 'g' && abs($quantity) >= 1000) {
            return self::number($quantity / 1000).' kg';
        }
        if ($unit === 'ml' && abs($quantity) >= 1000) {
            return self::number($quantity / 1000).' L';
        }

        return self::number($quantity).' '.($unit === 'piece' ? 'pieces' : $unit);
    }

    private static function number(float $value): string
    {
        return number_format($value, abs($value - round($value)) < 0.0001 ? 0 : 3, '.', ',');
    }
}
