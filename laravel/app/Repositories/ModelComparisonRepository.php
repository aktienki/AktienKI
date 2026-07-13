<?php

namespace App\Repositories;

use App\Models\ModelChallenger;
use App\Models\ModelChampion;
use App\Models\ModelComparison;
use App\Models\ModelEloHistory;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class ModelComparisonRepository
{
    public function transaction(callable $callback): mixed
    {
        return DB::transaction($callback);
    }

    public function createComparison(array $attributes): ModelComparison
    {
        return ModelComparison::query()->create($attributes);
    }

    public function createEloHistory(array $attributes): ModelEloHistory
    {
        return ModelEloHistory::query()->create($attributes);
    }

    public function updateChampionRating(
        int $championId,
        float $eloRating,
    ): ModelChampion {
        $champion = ModelChampion::query()
            ->lockForUpdate()
            ->find($championId);

        if ($champion === null) {
            throw new RuntimeException(
                "Champion-Datensatz {$championId} wurde nicht gefunden."
            );
        }

        $champion->forceFill([
            'elo_rating' => $eloRating,
        ])->save();

        return $champion->refresh();
    }

    public function updateChallengerRating(
        int $challengerId,
        float $eloRating,
    ): ModelChallenger {
        $challenger = ModelChallenger::query()
            ->lockForUpdate()
            ->find($challengerId);

        if ($challenger === null) {
            throw new RuntimeException(
                "Challenger-Datensatz {$challengerId} wurde nicht gefunden."
            );
        }

        $challenger->forceFill([
            'elo_rating' => $eloRating,
        ])->save();

        return $challenger->refresh();
    }
}
