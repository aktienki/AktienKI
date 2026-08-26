<?php

namespace App\Support;

final class QualityGrade
{
    /**
     * Convert a quality value to the common user-facing 1+ … 5− scale.
     * A higher quality is always better. Raw measurements remain available
     * separately and must not be discarded.
     */
    public static function fromPercent(?float $qualityPercent): ?string
    {
        if ($qualityPercent === null || ! is_finite($qualityPercent)) {
            return null;
        }

        $decile = min(9, (int) floor(max(0, min(100, $qualityPercent)) / 10));

        return match ($decile) {
            9 => '1+', 8 => '1−',
            7 => '2+', 6 => '2−',
            5 => '3+', 4 => '3−',
            3 => '4+', 2 => '4−',
            1 => '5+', default => '5−',
        };
    }

    public static function risk(?float $riskPercent): ?string
    {
        return $riskPercent === null ? null : self::fromPercent(100 - $riskPercent);
    }

    /**
     * Risk is intentionally shown without +/- and always retains a visible
     * residual-risk floor. Even a very small measured value is therefore
     * communicated as level 2 instead of suggesting "risk-free" with 1.
     */
    public static function riskLevel(?float $riskPercent): ?int
    {
        if ($riskPercent === null || ! is_finite($riskPercent)) return null;
        $risk = max(0, min(100, $riskPercent));
        return match (true) {
            $risk <= 40 => 2,
            $risk <= 60 => 3,
            $risk <= 80 => 4,
            default => 5,
        };
    }
}
