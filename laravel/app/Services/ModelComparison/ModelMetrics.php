<?php

namespace App\Services\ModelComparison;

use InvalidArgumentException;

final readonly class ModelMetrics
{
    public function __construct(
        public float $directionAccuracy,
        public float $strategyReturn,
        public float $rmse,
        public float $stability,
        public int $predictionCount,
        public float $eloRating,
    ) {
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

        if ($eloRating < 0) {
            throw new InvalidArgumentException(
                'ELO Rating darf nicht negativ sein.'
            );
        }
    }

    public function toArray(): array
    {
        return [
            'direction_accuracy' => $this->directionAccuracy,
            'strategy_return' => $this->strategyReturn,
            'rmse' => $this->rmse,
            'stability' => $this->stability,
            'prediction_count' => $this->predictionCount,
            'elo_rating' => $this->eloRating,
        ];
    }
}
