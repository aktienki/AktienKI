<?php

namespace App\Repositories;

use App\Models\ModelChallenger;
use App\Models\ModelChampion;
use App\Models\ModelComparison;
use Illuminate\Database\Eloquent\Collection;

class ChampionSchedulerRepository
{
    public function pendingComparisons(?int $limit = null): Collection
    {
        $query = ModelComparison::query()
            ->where('promotion_recommended', true)
            ->where('winner', 'challenger')
            ->orderBy('compared_at')
            ->orderBy('id');

        if ($limit !== null) {
            $query->limit($limit);
        }

        return $query->get();
    }

    public function championFor(ModelComparison $comparison): ?ModelChampion
    {
        return ModelChampion::query()
            ->where(
                'strategy_profile_id',
                $comparison->strategy_profile_id
            )
            ->where(
                'instrument_id',
                $comparison->instrument_id
            )
            ->where(
                'active_trained_model_id',
                $comparison->champion_model_id
            )
            ->where('status', 'active')
            ->first();
    }

    public function challengerFor(
        ModelComparison $comparison
    ): ?ModelChallenger {
        return ModelChallenger::query()
            ->where(
                'strategy_profile_id',
                $comparison->strategy_profile_id
            )
            ->where(
                'instrument_id',
                $comparison->instrument_id
            )
            ->where(
                'trained_model_id',
                $comparison->challenger_model_id
            )
            ->where('status', 'evaluating')
            ->first();
    }

    public function markComparisonProcessed(
        ModelComparison $comparison,
        array $metadata,
    ): void {
        $comparison->forceFill([
            'metadata' => array_merge(
                $comparison->metadata ?? [],
                $metadata,
            ),
        ])->save();
    }
}
