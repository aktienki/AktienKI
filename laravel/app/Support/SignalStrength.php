<?php

namespace App\Support;

final class SignalStrength
{
    /**
     * Convert an expected return into a compact directional strength.
     * Model quality is deliberately not part of this value.
     */
    public static function fromReturn(?float $returnPercent): ?int
    {
        if ($returnPercent === null || ! is_finite($returnPercent)) return null;

        $absolute = abs($returnPercent);
        $strength = match (true) {
            $absolute < 0.5 => 0,
            $absolute < 2.0 => 1,
            $absolute < 5.0 => 2,
            $absolute < 10.0 => 3,
            $absolute < 20.0 => 4,
            default => 5,
        };

        return $returnPercent < 0 ? -$strength : $strength;
    }

    public static function label(?float $returnPercent): string
    {
        $strength = self::fromReturn($returnPercent);
        if ($strength === null) return '—';
        if ($strength === 0) return '0';

        return ($strength > 0 ? '+' : '−').abs($strength);
    }
}
