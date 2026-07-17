<?php

namespace App\Services\Elo;

final readonly class RatingResult
{
    public function __construct(
        public float $championBefore,
        public float $challengerBefore,
        public float $championAfter,
        public float $challengerAfter,
        public float $championChange,
        public float $challengerChange,
        public float $championExpectedScore,
        public float $challengerExpectedScore,
        public string $winner,
        public int $kFactor,
    ) {
    }

    public function toArray(): array
    {
        return [
            'champion_before' => $this->championBefore,
            'challenger_before' => $this->challengerBefore,
            'champion_after' => $this->championAfter,
            'challenger_after' => $this->challengerAfter,
            'champion_change' => $this->championChange,
            'challenger_change' => $this->challengerChange,
            'champion_expected_score' => $this->championExpectedScore,
            'challenger_expected_score' => $this->challengerExpectedScore,
            'winner' => $this->winner,
            'k_factor' => $this->kFactor,
        ];
    }
}
