<?php

namespace App\Support;

final class RiskScore
{
    public static function toPercent(mixed ...$candidates): ?float
    {
        foreach ($candidates as $candidate) {
            if (! is_numeric($candidate) || (float) $candidate <= 0) continue;

            $percent = (float) $candidate <= 1 ? (float) $candidate * 100 : (float) $candidate;

            return max(1.0, min(100.0, $percent));
        }

        return null;
    }
}
