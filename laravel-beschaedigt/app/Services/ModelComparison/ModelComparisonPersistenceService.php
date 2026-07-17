<?php

namespace App\Services\ModelComparison;

use App\Models\ModelComparison;
use App\Repositories\ModelComparisonRepository;
use Illuminate\Support\Carbon;

final class ModelComparisonPersistenceService
{
    public function __construct(
        private readonly ModelComparisonRepository $repository,
    ) {
    }

    public function persist(
        ComparisonResult $result,
        ComparisonPersistenceContext $context,
    ): ModelComparison {
        return $this->repository->transaction(
            function () use ($result, $context): ModelComparison {
                $comparedAt = Carbon::now();

                $comparison = $this->repository->createComparison([
                    'strategy_profile_id' => $context->strategyProfileId,
                    'instrument_id' => $context->instrumentId,
                    'champion_model_id' => $context->championModelId,
                    'challenger_model_id' => $context->challengerModelId,
                    'prediction_count' => $context->predictionCount,

                    'champion_direction_accuracy' =>
                        $context->championMetrics->directionAccuracy,
                    'challenger_direction_accuracy' =>
                        $context->challengerMetrics->directionAccuracy,

                    'champion_strategy_return' =>
                        $context->championMetrics->strategyReturn,
                    'challenger_strategy_return' =>
                        $context->challengerMetrics->strategyReturn,

                    'champion_rmse' =>
                        $context->championMetrics->rmse,
                    'challenger_rmse' =>
                        $context->challengerMetrics->rmse,

                    'champion_stability_score' =>
                        $context->championMetrics->stability,
                    'challenger_stability_score' =>
                        $context->challengerMetrics->stability,

                    'champion_selection_score' =>
                        $result->championScore->score,
                    'challenger_selection_score' =>
                        $result->challengerScore->score,

                    'winner' => $result->winner,
                    'promotion_recommended' =>
                        $result->promotionRecommended,
                    'compared_at' => $comparedAt,
                    'comparison_rules' => $result->rules,
                    'metrics' => [
                        'champion' => $context->championMetrics->toArray(),
                        'challenger' => $context->challengerMetrics->toArray(),
                        'selection' => [
                            'champion' =>
                                $result->championScore->toArray(),
                            'challenger' =>
                                $result->challengerScore->toArray(),
                        ],
                        'elo' => $result->eloResult->toArray(),
                        'reasons' => $result->reasons,
                    ],
                    'metadata' => $context->metadata,
                ]);

                $this->repository->createEloHistory([
                    'trained_model_id' => $context->championModelId,
                    'model_comparison_id' => $comparison->id,
                    'rating_before' =>
                        $result->eloResult->championBefore,
                    'rating_after' =>
                        $result->eloResult->championAfter,
                    'rating_change' =>
                        $result->eloResult->championChange,
                    'result' => $this->eloResultFor(
                        side: 'champion',
                        winner: $result->winner,
                    ),
                    'opponent_type' => 'challenger',
                    'opponent_model_id' =>
                        $context->challengerModelId,
                    'rated_at' => $comparedAt,
                    'metadata' => $context->metadata,
                ]);

                $this->repository->createEloHistory([
                    'trained_model_id' => $context->challengerModelId,
                    'model_comparison_id' => $comparison->id,
                    'rating_before' =>
                        $result->eloResult->challengerBefore,
                    'rating_after' =>
                        $result->eloResult->challengerAfter,
                    'rating_change' =>
                        $result->eloResult->challengerChange,
                    'result' => $this->eloResultFor(
                        side: 'challenger',
                        winner: $result->winner,
                    ),
                    'opponent_type' => 'champion',
                    'opponent_model_id' =>
                        $context->championModelId,
                    'rated_at' => $comparedAt,
                    'metadata' => $context->metadata,
                ]);

                $this->repository->updateChampionRating(
                    championId: $context->championRecordId,
                    eloRating: $result->eloResult->championAfter,
                );

                $this->repository->updateChallengerRating(
                    challengerId: $context->challengerRecordId,
                    eloRating: $result->eloResult->challengerAfter,
                );

                return $comparison;
            }
        );
    }

    private function eloResultFor(
        string $side,
        string $winner,
    ): string {
        if ($winner === 'draw') {
            return 'draw';
        }

        return $winner === $side ? 'win' : 'loss';
    }
}
