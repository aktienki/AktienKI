<?php

namespace App\Services\AiDashboard;

final readonly class AiDashboardItem
{
    public function __construct(
        public int $championRecordId,
        public int $strategyProfileId,
        public ?int $instrumentId,
        public string $strategyCode,
        public string $strategyName,
        public ?string $instrumentSymbol,
        public ?string $instrumentName,
        public int $activeModelId,
        public ?int $previousModelId,
        public string $algorithm,
        public float $eloRating,
        public string $eloTrend,
        public int $validatedPredictions,
        public ?float $directionAccuracy,
        public ?float $strategyReturn,
        public ?float $rmse,
        public ?float $stabilityScore,
        public ?float $selectionScore,
        public bool $promotionRecommended,
        public array $challengers,
        public array $eloHistory,
        public array $metadata = [],
    ) {
    }

    public function toArray(): array
    {
        return [
            'champion_record_id' => $this->championRecordId,
            'strategy_profile_id' => $this->strategyProfileId,
            'instrument_id' => $this->instrumentId,
            'strategy_code' => $this->strategyCode,
            'strategy_name' => $this->strategyName,
            'instrument_symbol' => $this->instrumentSymbol,
            'instrument_name' => $this->instrumentName,
            'active_model_id' => $this->activeModelId,
            'previous_model_id' => $this->previousModelId,
            'algorithm' => $this->algorithm,
            'elo_rating' => $this->eloRating,
            'elo_trend' => $this->eloTrend,
            'validated_predictions' => $this->validatedPredictions,
            'direction_accuracy' => $this->directionAccuracy,
            'strategy_return' => $this->strategyReturn,
            'rmse' => $this->rmse,
            'stability_score' => $this->stabilityScore,
            'selection_score' => $this->selectionScore,
            'promotion_recommended' => $this->promotionRecommended,
            'challengers' => $this->challengers,
            'elo_history' => $this->eloHistory,
            'metadata' => $this->metadata,
        ];
    }
}
