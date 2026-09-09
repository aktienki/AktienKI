<?php

namespace App\Services;

final class ServingVariantSelector
{
    /**
     * Promote Pure TCN only when it passed the complete quality gate and is
     * stronger than Standard. A failed Standard gate is sufficient; if both
     * pass, TCN must improve both average net return and profit factor.
     */
    public static function select(array $horizon): string
    {
        $standard = (array) ($horizon['standard'] ?? []);
        $tcn = (array) ($horizon['pure_tcn'] ?? []);

        if (! self::gatePassed($tcn) || ! self::predictionEnabled($tcn)) {
            return 'standard';
        }

        if (! self::gatePassed($standard)) {
            return 'pure_tcn';
        }

        $standardMetrics = (array) ($standard['metrics'] ?? []);
        $tcnMetrics = (array) ($tcn['metrics'] ?? []);

        return self::greater($tcnMetrics['average_net_trade'] ?? null, $standardMetrics['average_net_trade'] ?? null)
            && self::greater($tcnMetrics['profit_factor'] ?? null, $standardMetrics['profit_factor'] ?? null)
                ? 'pure_tcn'
                : 'standard';
    }

    private static function gatePassed(array $variant): bool
    {
        return data_get($variant, 'prediction_status.quality_gate.passed') === true;
    }

    private static function predictionEnabled(array $variant): bool
    {
        return data_get($variant, 'prediction_status.prediction_enabled') === true;
    }

    private static function greater(mixed $candidate, mixed $baseline): bool
    {
        return is_numeric($candidate)
            && is_numeric($baseline)
            && is_finite((float) $candidate)
            && is_finite((float) $baseline)
            && (float) $candidate > (float) $baseline;
    }
}
