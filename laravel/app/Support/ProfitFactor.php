<?php

namespace App\Support;

final class ProfitFactor
{
    public const MAX = 3.0;

    public static function cap(mixed $value): ?float
    {
        if (! is_numeric($value)) {
            return null;
        }

        return max(0.0, min(self::MAX, (float) $value));
    }
}
