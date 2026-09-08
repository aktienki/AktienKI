<?php

namespace App\Support;

final class ChanceRiskScore
{
    /**
     * Build user-facing opportunity and risk scores without changing the
     * meaning of the model-quality score.
     *
     * The result is an orientation score (0-100), not a probability.
     * Missing values stay neutral instead of being treated as positive.
     *
     * @param  array<int|string, mixed>  $horizonReturns  Expected returns in percent.
     * @return array{chance: float, risk: float, quality: float, weighted_return: float, agreement: float, completeness: float}
     */
    public static function calculate(
        mixed $modelQuality,
        array $horizonReturns,
        mixed $confidence = null,
        mixed $baseRisk = null,
    ): array {
        $quality = self::percent($modelQuality) ?? 50.0;
        $confidencePercent = self::percent($confidence) ?? 50.0;
        $riskPercent = self::percent($baseRisk) ?? 50.0;

        $weights = [5 => 0.10, 10 => 0.20, 15 => 0.25, 20 => 0.45];
        $values = [];
        $availableWeight = 0.0;

        foreach ($weights as $horizon => $weight) {
            $value = $horizonReturns[$horizon]
                ?? $horizonReturns[(string) $horizon]
                ?? $horizonReturns[$horizon.'d']
                ?? $horizonReturns[$horizon.'t']
                ?? null;

            if (! is_numeric($value) || ! is_finite((float) $value)) continue;

            $values[$horizon] = (float) $value;
            $availableWeight += $weight;
        }

        if ($availableWeight <= 0.0) {
            return [
                'chance' => 50.0,
                'risk' => round(self::clamp(($riskPercent * 0.55) + 22.5 + ((100 - $quality) * 0.225)), 1),
                'quality' => round($quality, 1),
                'weighted_return' => 0.0,
                'agreement' => 50.0,
                'completeness' => 0.0,
            ];
        }

        $weightedReturn = 0.0;
        $positiveWeight = 0.0;
        $negativeWeight = 0.0;

        foreach ($values as $horizon => $value) {
            $normalWeight = $weights[$horizon] / $availableWeight;
            $weightedReturn += $value * $normalWeight;
            if ($value > 0.25) $positiveWeight += $normalWeight;
            if ($value < -0.25) $negativeWeight += $normalWeight;
        }

        $agreement = max($positiveWeight, $negativeWeight) * 100;
        $positiveAgreement = $positiveWeight * 100;
        $completeness = $availableWeight * 100;
        $qualityFactor = $quality / 100;

        // +/- 12% weighted return spans the useful display range. Values
        // beyond it are capped so outliers cannot dominate the score.
        $direction = self::clamp($weightedReturn / 12, -1, 1);
        $qualityAdjustedDirection = $direction * $qualityFactor;
        $directionScore = 50 + ($qualityAdjustedDirection * 50);
        $positiveAgreementScore = 50 + (($positiveAgreement - 50) * $qualityFactor);

        $chance =
            ($directionScore * 0.45)
            + ($positiveAgreementScore * 0.20)
            + ($confidencePercent * 0.15)
            + ($quality * 0.15)
            + ($completeness * 0.05);

        // A reliable negative forecast raises risk; a reliable positive one
        // lowers it. Uncertainty and disagreement always add risk.
        $directionalRisk = 50 - ($qualityAdjustedDirection * 50);
        $uncertainty = 100 - (($confidencePercent * 0.55) + ($quality * 0.45));
        $disagreement = 100 - $agreement;
        $incompleteness = 100 - $completeness;
        $risk =
            ($riskPercent * 0.20)
            + ($directionalRisk * 0.65)
            + ($uncertainty * 0.05)
            + ($disagreement * 0.05)
            + ($incompleteness * 0.05);

        return [
            'chance' => round(self::clamp($chance), 1),
            'risk' => round(self::clamp($risk), 1),
            'quality' => round($quality, 1),
            'weighted_return' => round($weightedReturn, 2),
            'agreement' => round($agreement, 1),
            'completeness' => round($completeness, 1),
        ];
    }

    public static function grade(float $score, bool $lowerIsBetter = false): int
    {
        $value = $lowerIsBetter ? 100 - self::clamp($score) : self::clamp($score);

        return match (true) {
            $value >= 80 => 1,
            $value >= 65 => 2,
            $value >= 50 => 3,
            $value >= 35 => 4,
            default => 5,
        };
    }

    /**
     * Public equity-risk labels retain a visible residual risk. Grade 1 is
     * deliberately not issued for ordinary stock forecasts.
     */
    public static function equityRiskGrade(float $score): int
    {
        return max(2, self::grade($score, true));
    }

    public static function chanceLabel(float $score): string
    {
        $grade = self::grade($score);
        [$minimum, $maximum] = match ($grade) {
            1 => [80.0, 100.0], 2 => [65.0, 80.0], 3 => [50.0, 65.0],
            4 => [35.0, 50.0], default => [0.0, 35.0],
        };

        return $grade.self::modifier(self::clamp($score), $minimum, $maximum, false);
    }

    public static function equityRiskLabel(float $score): string
    {
        $grade = self::equityRiskGrade($score);
        [$minimum, $maximum] = match ($grade) {
            2 => [0.0, 35.0], 3 => [35.0, 50.0], 4 => [50.0, 65.0],
            default => [65.0, 100.0],
        };

        return $grade.self::modifier(self::clamp($score), $minimum, $maximum, true);
    }

    private static function modifier(float $score, float $minimum, float $maximum, bool $lowerIsBetter): string
    {
        $position = ($score - $minimum) / max(0.0001, $maximum - $minimum);
        if ($lowerIsBetter) $position = 1 - $position;

        return match (true) {
            $position >= (2 / 3) => '+',
            $position < (1 / 3) => '−',
            default => '',
        };
    }

    private static function percent(mixed $value): ?float
    {
        if (! is_numeric($value) || ! is_finite((float) $value)) return null;

        $number = (float) $value;
        if ($number >= 0 && $number <= 1) $number *= 100;
        elseif ($number > 1 && $number <= 10) $number *= 10;

        return self::clamp($number);
    }

    private static function clamp(float $value, float $minimum = 0, float $maximum = 100): float
    {
        return max($minimum, min($maximum, $value));
    }
}
