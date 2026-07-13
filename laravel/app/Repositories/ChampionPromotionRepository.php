<?php

namespace App\Repositories;

use App\Models\ModelChallenger;
use App\Models\ModelChampion;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class ChampionPromotionRepository
{
    public function transaction(callable $callback): mixed
    {
        return DB::transaction($callback);
    }

    public function lockChampion(int $championRecordId): ModelChampion
    {
        $champion = ModelChampion::query()
            ->lockForUpdate()
            ->find($championRecordId);

        if ($champion === null) {
            throw new RuntimeException(
                "Champion-Datensatz {$championRecordId} wurde nicht gefunden."
            );
        }

        return $champion;
    }

    public function lockChallenger(int $challengerRecordId): ModelChallenger
    {
        $challenger = ModelChallenger::query()
            ->lockForUpdate()
            ->find($challengerRecordId);

        if ($challenger === null) {
            throw new RuntimeException(
                "Challenger-Datensatz {$challengerRecordId} wurde nicht gefunden."
            );
        }

        return $challenger;
    }

    public function promote(
        ModelChampion $champion,
        ModelChallenger $challenger,
        string $reason,
        array $metrics,
        array $metadata,
        Carbon $activatedAt,
    ): ModelChampion {
        $oldModelId = (int) $champion->active_trained_model_id;
        $newModelId = (int) $challenger->trained_model_id;

        $champion->forceFill([
            'previous_trained_model_id' => $oldModelId,
            'active_trained_model_id' => $newModelId,
            'algorithm' => $challenger->algorithm,
            'status' => 'active',
            'elo_rating' => $challenger->elo_rating,
            'validated_predictions_count' =>
                $challenger->validated_predictions_count,
            'direction_accuracy' =>
                $challenger->direction_accuracy,
            'average_strategy_return' =>
                $challenger->average_strategy_return,
            'rmse' => $challenger->rmse,
            'stability_score' =>
                $challenger->stability_score,
            'activated_at' => $activatedAt,
            'activation_reason' => $reason,
            'activation_metrics' => $metrics,
            'metadata' => $metadata,
        ])->save();

        $challenger->forceFill([
            'status' => 'promoted',
            'evaluation_finished_at' => $activatedAt,
            'status_reason' => $reason,
        ])->save();

        ModelChallenger::query()
            ->where('strategy_profile_id', $champion->strategy_profile_id)
            ->where('instrument_id', $champion->instrument_id)
            ->whereKeyNot($challenger->id)
            ->where('status', 'evaluating')
            ->update([
                'status' => 'superseded',
                'evaluation_finished_at' => $activatedAt,
                'status_reason' => 'Durch neue Champion-Promotion überholt.',
                'updated_at' => $activatedAt,
            ]);

        return $champion->refresh();
    }
}
