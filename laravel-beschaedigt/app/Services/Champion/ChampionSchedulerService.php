<?php

namespace App\Services\Champion;

use App\Repositories\ChampionSchedulerRepository;
use App\Services\Elo\EloCalculator;
use App\Services\Elo\EloRatingService;
use App\Services\ModelComparison\ModelComparisonService;
use App\Services\ModelComparison\ModelMetrics;
use App\Services\ModelComparison\SelectionScoreCalculator;
use DateTimeImmutable;
use Throwable;

class ChampionSchedulerService
{
    public function __construct(
        private readonly ChampionSchedulerRepository $repository,
        private readonly PromotionValidator $validator,
        private readonly ChampionPromotionService $promotionService,
    ) {
    }

    public function run(
        ?int $limit = null,
        bool $dryRun = false,
    ): array {
        $statistics = [
            'total' => 0,
            'promoted' => 0,
            'rejected' => 0,
            'skipped' => 0,
            'failed' => 0,
            'items' => [],
        ];

        foreach (
            $this->repository->pendingComparisons($limit)
            as $comparison
        ) {
            $statistics['total']++;

            try {
                $champion = $this->repository->championFor(
                    $comparison
                );
                $challenger = $this->repository->challengerFor(
                    $comparison
                );

                if ($champion === null || $challenger === null) {
                    $statistics['skipped']++;
                    $statistics['items'][] = [
                        'comparison_id' => $comparison->id,
                        'status' => 'skipped',
                        'reason' => 'Champion oder Challenger nicht gefunden.',
                    ];
                    continue;
                }

                $championMetrics = new ModelMetrics(
                    directionAccuracy: (float) (
                        $champion->direction_accuracy ?? 0
                    ),
                    strategyReturn: (float) (
                        $champion->average_strategy_return ?? 0
                    ),
                    rmse: (float) ($champion->rmse ?? 0),
                    stability: (float) (
                        $champion->stability_score ?? 0
                    ),
                    predictionCount: (int) (
                        $champion->validated_predictions_count ?? 0
                    ),
                    eloRating: (float) (
                        $champion->elo_rating ?? 1500
                    ),
                );

                $challengerMetrics = new ModelMetrics(
                    directionAccuracy: (float) (
                        $challenger->direction_accuracy ?? 0
                    ),
                    strategyReturn: (float) (
                        $challenger->average_strategy_return ?? 0
                    ),
                    rmse: (float) ($challenger->rmse ?? 0),
                    stability: (float) (
                        $challenger->stability_score ?? 0
                    ),
                    predictionCount: (int) (
                        $challenger->validated_predictions_count ?? 0
                    ),
                    eloRating: (float) (
                        $challenger->elo_rating ?? 1500
                    ),
                );

                $comparisonResult = (
                    new ModelComparisonService(
                        new SelectionScoreCalculator(),
                        new EloRatingService(
                            new EloCalculator()
                        ),
                    )
                )->compare(
                    champion: $championMetrics,
                    challenger: $challengerMetrics,
                );

                $decision = $this->validator->validate(
                    championModelId: (int)
                        $champion->active_trained_model_id,
                    challengerModelId: (int)
                        $challenger->trained_model_id,
                    champion: $championMetrics,
                    challenger: $challengerMetrics,
                    comparison: $comparisonResult,
                    championActivatedAt: new DateTimeImmutable(
                        $champion->activated_at->toAtomString()
                    ),
                    decidedAt: new DateTimeImmutable(),
                    metadata: [
                        'comparison_id' => $comparison->id,
                        'scheduler' => true,
                    ],
                );

                if (! $decision->promote) {
                    $statistics['rejected']++;
                    $statistics['items'][] = [
                        'comparison_id' => $comparison->id,
                        'status' => 'rejected',
                        'reason' => $decision->reason,
                        'checks' => $decision->checks,
                    ];

                    $this->repository->markComparisonProcessed(
                        $comparison,
                        [
                            'scheduler_checked_at' => now()->toAtomString(),
                            'scheduler_decision' => $decision->toArray(),
                        ],
                    );
                    continue;
                }

                if ($dryRun) {
                    $statistics['items'][] = [
                        'comparison_id' => $comparison->id,
                        'status' => 'dry_run',
                        'reason' => $decision->reason,
                    ];
                    continue;
                }

                $this->promotionService->execute(
                    decision: $decision,
                    context: new ChampionPromotionContext(
                        championRecordId: (int) $champion->id,
                        challengerRecordId: (int) $challenger->id,
                        comparisonId: (int) $comparison->id,
                        metrics: [
                            'champion_selection_score' =>
                                $comparisonResult->championScore->score,
                            'challenger_selection_score' =>
                                $comparisonResult->challengerScore->score,
                        ],
                        metadata: [
                            'source' => 'champion_scheduler',
                        ],
                    ),
                );

                $this->repository->markComparisonProcessed(
                    $comparison,
                    [
                        'scheduler_checked_at' => now()->toAtomString(),
                        'scheduler_promoted_at' => now()->toAtomString(),
                        'scheduler_decision' => $decision->toArray(),
                    ],
                );

                $statistics['promoted']++;
                $statistics['items'][] = [
                    'comparison_id' => $comparison->id,
                    'status' => 'promoted',
                    'new_champion_model_id' =>
                        $decision->challengerModelId,
                ];
            } catch (Throwable $exception) {
                report($exception);

                $statistics['failed']++;
                $statistics['items'][] = [
                    'comparison_id' => $comparison->id,
                    'status' => 'failed',
                    'error' => $exception->getMessage(),
                ];
            }
        }

        return $statistics;
    }
}
