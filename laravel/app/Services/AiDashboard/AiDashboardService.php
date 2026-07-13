<?php

namespace App\Services\AiDashboard;

use App\Models\ModelChampion;
use App\Repositories\AiDashboardRepository;
use Illuminate\Support\Collection;

final class AiDashboardService
{
    public function __construct(
        private readonly AiDashboardRepository $repository,
    ) {
    }

    public function overview(
        ?int $strategyProfileId = null,
        ?int $instrumentId = null,
        int $limit = 50,
    ): array {
        $items = $this->repository
            ->champions(
                strategyProfileId: $strategyProfileId,
                instrumentId: $instrumentId,
                limit: $limit,
            )
            ->map(
                fn (ModelChampion $champion): array =>
                    $this->mapChampion($champion)->toArray()
            )
            ->values()
            ->all();

        return [
            'summary' => $this->summary($items),
            'items' => $items,
        ];
    }

    private function mapChampion(
        ModelChampion $champion,
    ): AiDashboardItem {
        $challengers = $this->repository->challengersFor(
            strategyProfileId: (int) $champion->strategy_profile_id,
            instrumentId: $champion->instrument_id === null
                ? null
                : (int) $champion->instrument_id,
        );

        $comparison = $this->repository->latestComparisonFor(
            strategyProfileId: (int) $champion->strategy_profile_id,
            instrumentId: $champion->instrument_id === null
                ? null
                : (int) $champion->instrument_id,
        );

        $history = $this->repository->eloHistoryForModel(
            trainedModelId: (int) $champion->active_trained_model_id,
        );

        $eloHistory = $history
            ->map(fn ($row): array => [
                'rated_at' => $row->rated_at?->toAtomString(),
                'rating_before' => (float) $row->rating_before,
                'rating_after' => (float) $row->rating_after,
                'rating_change' => (float) $row->rating_change,
                'result' => $row->result,
            ])
            ->values()
            ->all();

        $eloTrend = $this->resolveEloTrend($eloHistory);

        return new AiDashboardItem(
            championRecordId: (int) $champion->id,
            strategyProfileId: (int) $champion->strategy_profile_id,
            instrumentId: $champion->instrument_id === null
                ? null
                : (int) $champion->instrument_id,
            strategyCode: (string) (
                $champion->strategyProfile?->code ?? ''
            ),
            strategyName: (string) (
                $champion->strategyProfile?->name ?? ''
            ),
            instrumentSymbol: $champion->instrument?->symbol,
            instrumentName: $champion->instrument?->name,
            activeModelId: (int) $champion->active_trained_model_id,
            previousModelId: $champion->previous_trained_model_id === null
                ? null
                : (int) $champion->previous_trained_model_id,
            algorithm: (string) $champion->algorithm,
            eloRating: (float) $champion->elo_rating,
            eloTrend: $eloTrend,
            validatedPredictions: (int)
                $champion->validated_predictions_count,
            directionAccuracy: $this->nullableFloat(
                $champion->direction_accuracy
            ),
            strategyReturn: $this->nullableFloat(
                $champion->average_strategy_return
            ),
            rmse: $this->nullableFloat($champion->rmse),
            stabilityScore: $this->nullableFloat(
                $champion->stability_score
            ),
            selectionScore: $comparison === null
                ? null
                : $this->nullableFloat(
                    $comparison->champion_selection_score
                ),
            promotionRecommended: (bool) (
                $comparison?->promotion_recommended ?? false
            ),
            challengers: $challengers
                ->map(fn ($challenger): array => [
                    'record_id' => (int) $challenger->id,
                    'trained_model_id' => (int)
                        $challenger->trained_model_id,
                    'algorithm' => (string) $challenger->algorithm,
                    'status' => (string) $challenger->status,
                    'elo_rating' => (float) $challenger->elo_rating,
                    'validated_predictions' => (int)
                        $challenger->validated_predictions_count,
                    'direction_accuracy' => $this->nullableFloat(
                        $challenger->direction_accuracy
                    ),
                    'strategy_return' => $this->nullableFloat(
                        $challenger->average_strategy_return
                    ),
                    'rmse' => $this->nullableFloat(
                        $challenger->rmse
                    ),
                    'stability_score' => $this->nullableFloat(
                        $challenger->stability_score
                    ),
                ])
                ->values()
                ->all(),
            eloHistory: $eloHistory,
            metadata: [
                'activated_at' =>
                    $champion->activated_at?->toAtomString(),
                'activation_reason' =>
                    $champion->activation_reason,
                'active_model_version' =>
                    $champion->activeModel?->version,
                'previous_model_version' =>
                    $champion->previousModel?->version,
            ],
        );
    }

    private function summary(array $items): array
    {
        $count = count($items);

        if ($count === 0) {
            return [
                'champions_total' => 0,
                'average_elo' => null,
                'average_direction_accuracy' => null,
                'average_strategy_return' => null,
                'challengers_total' => 0,
                'promotions_recommended' => 0,
            ];
        }

        $eloValues = array_column($items, 'elo_rating');
        $directionValues = array_values(array_filter(
            array_column($items, 'direction_accuracy'),
            static fn ($value): bool => $value !== null,
        ));
        $returnValues = array_values(array_filter(
            array_column($items, 'strategy_return'),
            static fn ($value): bool => $value !== null,
        ));

        return [
            'champions_total' => $count,
            'average_elo' => round(
                array_sum($eloValues) / count($eloValues),
                4,
            ),
            'average_direction_accuracy' =>
                $directionValues === []
                    ? null
                    : round(
                        array_sum($directionValues)
                        / count($directionValues),
                        6,
                    ),
            'average_strategy_return' =>
                $returnValues === []
                    ? null
                    : round(
                        array_sum($returnValues)
                        / count($returnValues),
                        6,
                    ),
            'challengers_total' => array_sum(
                array_map(
                    static fn (array $item): int =>
                        count($item['challengers']),
                    $items,
                )
            ),
            'promotions_recommended' => count(
                array_filter(
                    $items,
                    static fn (array $item): bool =>
                        $item['promotion_recommended'],
                )
            ),
        ];
    }

    private function resolveEloTrend(array $history): string
    {
        if (count($history) < 2) {
            return 'stable';
        }

        $first = (float) $history[0]['rating_after'];
        $last = (float) $history[array_key_last($history)]['rating_after'];
        $difference = $last - $first;

        if ($difference > 1.0) {
            return 'up';
        }

        if ($difference < -1.0) {
            return 'down';
        }

        return 'stable';
    }

    private function nullableFloat(mixed $value): ?float
    {
        return $value === null ? null : (float) $value;
    }
}
