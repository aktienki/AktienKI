<?php

namespace App\Services\ModelComparison;

use InvalidArgumentException;

final class SelectionScoreCalculator
{
    public const DEFAULT_WEIGHTS = [
        'direction_accuracy' => 0.35,
        'strategy_return' => 0.30,
        'rmse' => 0.15,
        'stability' => 0.15,
        'prediction_count' => 0.05,
    ];

    public function calculate(
        float $directionAccuracy,
        float $strategyReturn,
        float $rmse,
        float $stability,
        int $predictionCount,
        array $weights = self::DEFAULT_WEIGHTS,
        int $precision = 6,
    ): SelectionScoreResult {
        $this->validateInputs(
            directionAccuracy: $directionAccuracy,
            strategyReturn: $strategyReturn,
            rmse: $rmse,
            stability: $stability,
            predictionCount: $predictionCount,
            precision: $precision,
        );

        $resolvedWeights = $this->normalizeWeights($weights);

        $normalized = [
            'direction_accuracy' => $this->normalizeDirectionAccuracy(
                $directionAccuracy
            ),
            'strategy_return' => $this->normalizeStrategyReturn(
                $strategyReturn
            ),
            'rmse' => $this->normalizeRmse($rmse),
            'stability' => $this->clamp($stability),
            'prediction_count' => $this->normalizePredictionCount(
                $predictionCount
            ),
        ];

        $weighted = [];

        foreach ($normalized as $metric => $value) {
            $weighted[$metric] = round(
                $value * $resolvedWeights[$metric],
                $precision,
            );
        }

        $score = round(array_sum($weighted), $precision);

        return new SelectionScoreResult(
            score: $score,
            normalizedMetrics: array_map(
                fn (float $value): float => round($value, $precision),
                $normalized,
            ),
            weightedMetrics: $weighted,
            weights: $resolvedWeights,
        );
    }

    private function normalizeDirectionAccuracy(
        float $value,
    ): float {
        /*
         * 50 % entspricht Zufall und ergibt 0 Punkte.
         * 70 % oder höher ergibt 1 Punkt.
         */
        return $this->clamp(
            ($value - 0.50) / 0.20
        );
    }

    private function normalizeStrategyReturn(
        float $value,
    ): float {
        /*
         * 0 % ergibt 0 Punkte.
         * 10 % durchschnittliche Strategierendite oder mehr ergibt 1 Punkt.
         */
        return $this->clamp($value / 0.10);
    }

    private function normalizeRmse(float $value): float
    {
        /*
         * 1 % RMSE oder besser ergibt 1 Punkt.
         * 5 % RMSE oder schlechter ergibt 0 Punkte.
         */
        return $this->clamp(
            (0.05 - $value) / 0.04
        );
    }

    private function normalizePredictionCount(
        int $value,
    ): float {
        /*
         * Ab 1.000 validierten Predictions wird die volle Punktzahl erreicht.
         */
        return $this->clamp($value / 1000);
    }

    private function normalizeWeights(array $weights): array
    {
        $expectedKeys = array_keys(self::DEFAULT_WEIGHTS);

        foreach ($expectedKeys as $key) {
            if (! array_key_exists($key, $weights)) {
                throw new InvalidArgumentException(
                    "Gewichtung '{$key}' fehlt."
                );
            }

            if (! is_numeric($weights[$key])) {
                throw new InvalidArgumentException(
                    "Gewichtung '{$key}' muss numerisch sein."
                );
            }

            if ((float) $weights[$key] < 0) {
                throw new InvalidArgumentException(
                    "Gewichtung '{$key}' darf nicht negativ sein."
                );
            }
        }

        $unknownKeys = array_diff(
            array_keys($weights),
            $expectedKeys,
        );

        if ($unknownKeys !== []) {
            throw new InvalidArgumentException(
                'Unbekannte Gewichtungen: '
                .implode(', ', $unknownKeys)
            );
        }

        $sum = array_sum(
            array_map('floatval', $weights)
        );

        if ($sum <= 0) {
            throw new InvalidArgumentException(
                'Die Summe der Gewichtungen muss größer als null sein.'
            );
        }

        $resolved = [];

        foreach ($expectedKeys as $key) {
            $resolved[$key] = (float) $weights[$key] / $sum;
        }

        return $resolved;
    }

    private function validateInputs(
        float $directionAccuracy,
        float $strategyReturn,
        float $rmse,
        float $stability,
        int $predictionCount,
        int $precision,
    ): void {
        foreach ([
            'directionAccuracy' => $directionAccuracy,
            'strategyReturn' => $strategyReturn,
            'rmse' => $rmse,
            'stability' => $stability,
        ] as $name => $value) {
            if (! is_finite($value)) {
                throw new InvalidArgumentException(
                    "{$name} muss eine endliche Zahl sein."
                );
            }
        }

        if ($directionAccuracy < 0 || $directionAccuracy > 1) {
            throw new InvalidArgumentException(
                'Direction Accuracy muss zwischen 0 und 1 liegen.'
            );
        }

        if ($rmse < 0) {
            throw new InvalidArgumentException(
                'RMSE darf nicht negativ sein.'
            );
        }

        if ($stability < 0 || $stability > 1) {
            throw new InvalidArgumentException(
                'Stability muss zwischen 0 und 1 liegen.'
            );
        }

        if ($predictionCount < 0) {
            throw new InvalidArgumentException(
                'Prediction Count darf nicht negativ sein.'
            );
        }

        if ($precision < 0 || $precision > 8) {
            throw new InvalidArgumentException(
                'Precision muss zwischen 0 und 8 liegen.'
            );
        }
    }

    private function clamp(float $value): float
    {
        return max(0.0, min(1.0, $value));
    }
}
