<?php

namespace App\Repositories;

use App\Models\ModelChallenger;
use App\Models\ModelChampion;
use App\Models\ModelComparison;
use App\Models\ModelEloHistory;
use Illuminate\Database\Eloquent\Collection;

class AiDashboardRepository
{
    public function champions(
        ?int $strategyProfileId = null,
        ?int $instrumentId = null,
        int $limit = 50,
    ): Collection {
        return ModelChampion::query()
            ->with([
                'strategyProfile:id,name,code',
                'instrument:id,symbol,name',
                'activeModel:id,model_definition_id,instrument_id,version,status,trained_at,metrics,metadata',
                'previousModel:id,version,status,trained_at',
            ])
            ->when(
                $strategyProfileId !== null,
                fn ($query) => $query->where(
                    'strategy_profile_id',
                    $strategyProfileId,
                ),
            )
            ->when(
                $instrumentId !== null,
                fn ($query) => $query->where(
                    'instrument_id',
                    $instrumentId,
                ),
            )
            ->where('status', 'active')
            ->orderByDesc('elo_rating')
            ->limit($limit)
            ->get();
    }

    public function challengersFor(
        int $strategyProfileId,
        ?int $instrumentId,
        int $limit = 10,
    ): Collection {
        return ModelChallenger::query()
            ->with([
                'trainedModel:id,model_definition_id,instrument_id,version,status,trained_at,metrics,metadata',
            ])
            ->where('strategy_profile_id', $strategyProfileId)
            ->where('instrument_id', $instrumentId)
            ->where('status', 'evaluating')
            ->orderByDesc('elo_rating')
            ->orderByDesc('direction_accuracy')
            ->limit($limit)
            ->get();
    }

    public function latestComparisonFor(
        int $strategyProfileId,
        ?int $instrumentId,
    ): ?ModelComparison {
        return ModelComparison::query()
            ->where('strategy_profile_id', $strategyProfileId)
            ->where('instrument_id', $instrumentId)
            ->latest('compared_at')
            ->first();
    }

    public function eloHistoryForModel(
        int $trainedModelId,
        int $limit = 30,
    ): Collection {
        return ModelEloHistory::query()
            ->where('trained_model_id', $trainedModelId)
            ->orderByDesc('rated_at')
            ->limit($limit)
            ->get()
            ->sortBy('rated_at')
            ->values();
    }
}
