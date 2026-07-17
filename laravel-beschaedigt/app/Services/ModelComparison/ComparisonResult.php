<?php

namespace App\Services\ModelComparison;

use App\Services\Elo\RatingResult;

final readonly class ComparisonResult
{
    public function __construct(
        public string $winner,
        public SelectionScoreResult $championScore,
        public SelectionScoreResult $challengerScore,
        public RatingResult $eloResult,
        public bool $promotionRecommended,
        public array $reasons,
        public array $rules,
    ) {
    }

    public function toArray(): array
    {
        return [
            'winner' => $this->winner,
            'champion_score' => $this->championScore->toArray(),
            'challenger_score' => $this->challengerScore->toArray(),
            'elo_result' => $this->eloResult->toArray(),
            'promotion_recommended' => $this->promotionRecommended,
            'reasons' => $this->reasons,
            'rules' => $this->rules,
        ];
    }
}
