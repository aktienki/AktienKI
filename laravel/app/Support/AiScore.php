<?php

namespace App\Support;

final class AiScore
{
    public static function toTen(mixed $value): ?float
    {
        if (! is_numeric($value)) {
            return null;
        }

        $score = (float) $value;

        if ($score >= 0 && $score <= 1) {
            $score *= 10;
        } elseif ($score > 10) {
            $score /= 10;
        }

        return max(0, min(10, $score));
    }

    public static function toPercent(mixed $value): ?float
    {
        $score = self::toTen($value);

        return $score === null ? null : $score * 10;
    }
}
