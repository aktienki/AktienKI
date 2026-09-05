<?php

namespace App\Support;

use Carbon\CarbonImmutable;

final class TimeAdjustedSignalRating
{
    /**
     * Rate the still achievable net return. Expired targets no longer count,
     * while estimated round-trip costs are deducted from every open horizon.
     *
     * @param  array<int|string, mixed>  $grossReturns
     * @return array{percent:float,label:string,weighted_return:float,remaining_horizons:array<int,int>,viable:bool}
     */
    public static function calculate(
        array $grossReturns,
        mixed $predictionAt,
        mixed $modelQuality = null,
        ?float $roundTripCostPercent = null,
        ?float $minimumNetReturnPercent = null,
    ): array {
        $cost = max(0.0, $roundTripCostPercent ?? (float) config('aktienki.signals.round_trip_cost_percent', 0.5));
        $minimum = max(0.0, $minimumNetReturnPercent ?? (float) config('aktienki.signals.minimum_net_return_percent', 1.0));
        $elapsed = self::elapsedTradingDays($predictionAt);
        $configuredWeights = [5 => .10, 10 => .20, 15 => .20, 20 => .30, 40 => .20];
        $values = [];
        $remaining = [];

        foreach ($configuredWeights as $horizon => $weight) {
            $value = $grossReturns[$horizon] ?? $grossReturns[(string) $horizon] ?? null;
            $daysLeft = max(0, $horizon - $elapsed);
            if (! is_numeric($value) || ! is_finite((float) $value) || $daysLeft === 0) continue;
            $values[$horizon] = ['return' => (float) $value - $cost, 'weight' => $weight * ($daysLeft / $horizon)];
            $remaining[$horizon] = $daysLeft;
        }

        $weightSum = array_sum(array_column($values, 'weight'));
        if ($weightSum <= 0.0) return self::result(0.0, 0.0, $remaining, false);

        $weightedReturn = 0.0;
        $positiveWeight = 0.0;
        $negativeWeight = 0.0;
        foreach ($values as $value) {
            $weight = $value['weight'] / $weightSum;
            $weightedReturn += $value['return'] * $weight;
            if ($value['return'] > .25) $positiveWeight += $weight;
            elseif ($value['return'] < -.25) $negativeWeight += $weight;
        }

        $quality = AiScore::toPercent($modelQuality) ?? 50.0;
        $agreement = max($positiveWeight, $negativeWeight);
        $direction = $weightedReturn <=> 0.0;
        $strength = min(1.0,
            tanh(abs($weightedReturn) / 6.0)
            * (.55 + (.45 * $quality / 100.0))
            * (.70 + (.30 * $agreement))
        );
        $percent = 50.0 + ($direction * 50.0 * $strength);
        $viable = $weightedReturn >= $minimum;

        // A forecast whose remaining net potential does not cover the minimum
        // return must never continue to look like an attractive investment.
        if (! $viable) $percent = min($percent, 49.99);

        return self::result($percent, $weightedReturn, $remaining, $viable);
    }

    private static function elapsedTradingDays(mixed $predictionAt): int
    {
        if (! $predictionAt) return 0;

        try {
            $start = CarbonImmutable::parse($predictionAt)->startOfDay();
        } catch (\Throwable) {
            return 0;
        }

        $end = CarbonImmutable::now($start->timezone)->startOfDay();
        if ($end->lessThanOrEqualTo($start)) return 0;

        $days = 0;
        for ($date = $start->addDay(); $date->lessThanOrEqualTo($end); $date = $date->addDay()) {
            if ($date->isWeekday()) $days++;
        }

        return $days;
    }

    /** @param array<int,int> $remaining */
    private static function result(float $percent, float $weightedReturn, array $remaining, bool $viable): array
    {
        $percent = round(max(0.0, min(100.0, $percent)), 2);

        return [
            'percent' => $percent,
            'label' => QualityGrade::fromPercent($percent) ?? '5−',
            'weighted_return' => round($weightedReturn, 2),
            'remaining_horizons' => $remaining,
            'viable' => $viable,
        ];
    }
}
