<?php

namespace App\Helpers;

class Aktienki
{
    public static function normalizeSymbol(string $symbol): string
    {
        return strtoupper(trim($symbol));
    }

    public static function signalFromReturn(float|int|null $expectedReturn, float $buyThreshold = 2.0, float $sellThreshold = -2.0): string
    {
        if ($expectedReturn === null) {
            return 'hold';
        }

        if ($expectedReturn >= $buyThreshold) {
            return 'buy';
        }

        if ($expectedReturn <= $sellThreshold) {
            return 'sell';
        }

        return 'hold';
    }

    public static function clamp(float|int|null $value, float $min = 0, float $max = 100): ?float
    {
        if ($value === null) {
            return null;
        }

        return max($min, min($max, (float) $value));
    }
}
