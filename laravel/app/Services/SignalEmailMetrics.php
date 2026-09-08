<?php

namespace App\Services;

use App\Models\Prediction;
use App\Support\AiScore;
use App\Support\QualityGrade;
use App\Support\RiskScore;

final class SignalEmailMetrics
{
    private const HORIZONS = [
        5 => [7200, 'predicted_price_5d'],
        10 => [14400, 'predicted_price_10d'],
        15 => [21600, 'predicted_price_15d'],
        20 => [28800, 'predicted_price_20d'],
    ];

    public function forPrediction(Prediction $prediction): array
    {
        $score = AiScore::toTen($prediction->ai_score ?? $prediction->prediction_score);
        $scorePercent = $score === null ? null : $score * 10;
        $scoreGrade = QualityGrade::fromPercent($scorePercent);
        $riskPercent = RiskScore::toPercent($prediction->risk_score, $prediction->drawdown_risk_factor);
        $riskLevel = QualityGrade::riskLevel($riskPercent);

        return [
            'score' => $score,
            'score_percent' => $scorePercent,
            'score_grade' => $scoreGrade,
            'score_level' => $this->scoreLevel($scoreGrade),
            'risk_percent' => $riskPercent,
            'risk_level' => $riskLevel,
            'horizon_targets' => $this->horizonTargets($prediction),
        ];
    }

    private function horizonTargets(Prediction $prediction): array
    {
        $query = Prediction::query()
            ->where('instrument_id', $prediction->instrument_id)
            ->whereIn('prediction_horizon_minutes', array_column(self::HORIZONS, 0));

        if ($prediction->prediction_time !== null) {
            $query->where('prediction_time', '<=', $prediction->prediction_time);
        }

        $rows = $query
            ->orderByDesc('prediction_time')
            ->orderByDesc('id')
            ->get([
                'prediction_horizon_minutes',
                'predicted_price_5d',
                'predicted_price_10d',
                'predicted_price_15d',
                'predicted_price_20d',
            ]);
        $currentPrice = is_numeric($prediction->current_price) && (float) $prediction->current_price > 0
            ? (float) $prediction->current_price
            : null;

        return collect(self::HORIZONS)->mapWithKeys(function (array $definition, int $days) use ($prediction, $rows, $currentPrice): array {
            [$minutes, $field] = $definition;
            $row = $rows->first(fn (Prediction $candidate): bool => (int) $candidate->prediction_horizon_minutes === $minutes && is_numeric($candidate->{$field})
            );
            $rawTarget = $row?->{$field};
            if (! is_numeric($rawTarget) && is_numeric($prediction->{$field})) {
                $rawTarget = $prediction->{$field};
            }
            $target = is_numeric($rawTarget) ? (float) $rawTarget : null;

            return [$days => [
                'target' => $target,
                'return' => $currentPrice !== null && $target !== null
                    ? (($target / $currentPrice) - 1) * 100
                    : null,
            ]];
        })->all();
    }

    private function scoreLevel(?string $grade): int
    {
        if ($grade === null || ! ctype_digit($grade[0] ?? '')) {
            return 0;
        }

        return max(0, min(5, 6 - (int) $grade[0]));
    }
}
