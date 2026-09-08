<?php

namespace App\Support;

final class DirectionalSignalRating
{
    /**
     * User-facing directional rating. A high value means a strong positive
     * signal; a low value means a strong negative signal. Model quality only
     * controls how far the rating may move away from neutral.
     *
     * @param  array<int|string, mixed>  $horizonReturns Expected returns in percent.
     * @return array{percent:float,label:string,weighted_return:float,agreement:float,quality:float,complete:bool}
     */
    public static function calculate(array $horizonReturns, mixed $modelQuality = null): array
    {
        $weights = [5 => .10, 10 => .20, 15 => .25, 20 => .45];
        $values = [];
        $availableWeight = 0.0;

        foreach ($weights as $horizon => $weight) {
            $value = $horizonReturns[$horizon]
                ?? $horizonReturns[(string) $horizon]
                ?? $horizonReturns[$horizon.'d']
                ?? null;
            if (! is_numeric($value) || ! is_finite((float) $value)) continue;

            $values[$horizon] = (float) $value;
            $availableWeight += $weight;
        }

        $quality = AiScore::toPercent($modelQuality) ?? 50.0;
        if ($availableWeight <= 0.0) {
            return self::result(50.0, 0.0, 0.0, $quality, false);
        }

        $weightedReturn = 0.0;
        $positiveWeight = 0.0;
        $negativeWeight = 0.0;
        foreach ($values as $horizon => $value) {
            $weight = $weights[$horizon] / $availableWeight;
            $weightedReturn += $value * $weight;
            if ($value > .25) $positiveWeight += $weight;
            elseif ($value < -.25) $negativeWeight += $weight;
        }

        $agreement = max($positiveWeight, $negativeWeight);
        $direction = $weightedReturn <=> 0.0;
        $returnStrength = tanh(abs($weightedReturn) / 6.0);
        $qualityFactor = .55 + (.45 * max(0.0, min(100.0, $quality)) / 100.0);
        $agreementFactor = .70 + (.30 * $agreement);
        $evidenceFactor = .72 + (.28 * $availableWeight);
        $strength = min(1.0, $returnStrength * $qualityFactor * $agreementFactor * $evidenceFactor);
        $percent = 50.0 + ($direction * 50.0 * $strength);
        $complete = count($values) === 4;

        // The extreme grades require complete agreement across all horizons.
        if (! $complete || $positiveWeight < .999) $percent = min(89.99, $percent);
        if (! $complete || $negativeWeight < .999) $percent = max(10.0, $percent);

        return self::result($percent, $weightedReturn, $agreement * 100, $quality, $complete);
    }

    private static function result(
        float $percent,
        float $weightedReturn,
        float $agreement,
        float $quality,
        bool $complete,
    ): array {
        $percent = round(max(0.0, min(100.0, $percent)), 2);

        return [
            'percent' => $percent,
            'label' => QualityGrade::fromPercent($percent) ?? '3+',
            'weighted_return' => round($weightedReturn, 2),
            'agreement' => round($agreement, 1),
            'quality' => round($quality, 1),
            'complete' => $complete,
        ];
    }
}
