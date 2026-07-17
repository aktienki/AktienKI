<?php

namespace App\Services\Champion;

use App\Models\ModelChampion;
use App\Repositories\ChampionPromotionRepository;
use Illuminate\Support\Carbon;
use LogicException;

final class ChampionPromotionService
{
    public function __construct(
        private readonly ChampionPromotionRepository $repository,
    ) {
    }

    public function execute(
        PromotionDecision $decision,
        ChampionPromotionContext $context,
    ): ModelChampion {
        if (! $decision->promote) {
            throw new LogicException(
                'Eine abgelehnte Promotion darf nicht ausgeführt werden.'
            );
        }

        return $this->repository->transaction(
            function () use ($decision, $context): ModelChampion {
                $champion = $this->repository->lockChampion(
                    $context->championRecordId
                );

                $challenger = $this->repository->lockChallenger(
                    $context->challengerRecordId
                );

                $this->assertModelsMatchDecision(
                    championModelId: (int) $champion->active_trained_model_id,
                    challengerModelId: (int) $challenger->trained_model_id,
                    decision: $decision,
                );

                $activatedAt = Carbon::instance(
                    $decision->decidedAt
                );

                return $this->repository->promote(
                    champion: $champion,
                    challenger: $challenger,
                    reason: $decision->reason,
                    metrics: [
                        ...$context->metrics,
                        'promotion_checks' => $decision->checks,
                        'comparison_id' => $context->comparisonId,
                    ],
                    metadata: [
                        ...$context->metadata,
                        'decision' => $decision->toArray(),
                    ],
                    activatedAt: $activatedAt,
                );
            }
        );
    }

    private function assertModelsMatchDecision(
        int $championModelId,
        int $challengerModelId,
        PromotionDecision $decision,
    ): void {
        if ($championModelId !== $decision->championModelId) {
            throw new LogicException(
                'Champion-Modell der Entscheidung stimmt nicht '
                .'mit dem gesperrten Champion-Datensatz überein.'
            );
        }

        if ($challengerModelId !== $decision->challengerModelId) {
            throw new LogicException(
                'Challenger-Modell der Entscheidung stimmt nicht '
                .'mit dem gesperrten Challenger-Datensatz überein.'
            );
        }
    }
}
