<?php

namespace App\Services\ModelComparison;

final readonly class SelectionScoreResult
{
    public function __construct(
        public float $score,
        public array $normalizedMetrics,
        public array $weightedMetrics,
        public array $weights,
    ) {
    }

    public function toArray(): array
    {
        return [
            'score' => $this->score,
            'normalized_metrics' => $this->normalizedMetrics,
            'weighted_metrics' => $this->weightedMetrics,
            'weights' => $this->weights,
        ];
    }
}
