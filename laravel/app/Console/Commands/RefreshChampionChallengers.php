<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

final class RefreshChampionChallengers extends Command
{
    protected $signature = 'models:refresh-champion-challengers';
    protected $description = 'Aktualisiert Champions und Challenger aus den Modellqualitätswerten.';

    public function handle(): int
    {
        $now = now();
        $profileId = DB::transaction(function () use ($now): int {
            DB::table('strategy_profiles')->updateOrInsert(['code' => 'aki-daily-20d'], [
                'name' => 'aKI Daily 20D', 'description' => 'Automatisches Champion-Challenger-Profil',
                'scope' => 'instrument', 'status' => 'active', 'target_type' => 'future_return',
                'target_horizon_days' => 20, 'interval' => '1d', 'history_years' => 3,
                'configuration' => json_encode(['promotion_margin' => .03, 'minimum_trades' => 30]),
                'version' => 1, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now,
            ]);
            return (int) DB::table('strategy_profiles')->where('code', 'aki-daily-20d')->value('id');
        });

        $latest = DB::table('model_quality_rankings')->selectRaw('trained_model_id, MAX(id) ranking_id')->groupBy('trained_model_id');
        $ranked = DB::table('trained_models as m')
            ->joinSub($latest, 'latest', fn ($join) => $join->on('latest.trained_model_id', '=', 'm.id'))
            ->join('model_quality_rankings as q', 'q.id', '=', 'latest.ranking_id')
            ->join('model_definitions as d', 'd.id', '=', 'm.model_definition_id')
            ->whereNull('m.deleted_at')->whereNotNull('m.instrument_id')
            ->whereIn('m.status', ['active', 'candidate'])->where('q.eligible', true)
            ->selectRaw('m.id, m.instrument_id, d.algorithm, q.quality_score, q.direction_accuracy, q.profit_factor, q.sharpe, q.trade_count,
                ROW_NUMBER() OVER (PARTITION BY m.instrument_id ORDER BY q.quality_score DESC, q.id DESC) AS quality_rank');
        $groups = DB::query()->fromSub($ranked, 'ranked')
            ->where('quality_rank', '<=', 2)
            ->orderBy('instrument_id')->orderBy('quality_rank')
            ->get()
            ->groupBy('instrument_id');

        DB::transaction(function () use ($groups, $profileId, $now): void {
            foreach ($groups as $instrumentId => $models) {
                $existing = DB::table('model_champions')->where('strategy_profile_id', $profileId)->where('instrument_id', $instrumentId)->first();
                $champion = $existing ? $models->firstWhere('id', $existing->active_trained_model_id) : null;
                $champion ??= $models->first();
                $challenger = $models->first(fn ($model) => $model->id !== $champion->id);
                if ($challenger && $challenger->trade_count >= 30 && $challenger->quality_score >= $champion->quality_score + .03) {
                    [$champion, $challenger] = [$challenger, $champion];
                }
                DB::table('model_champions')->updateOrInsert(
                    ['strategy_profile_id' => $profileId, 'instrument_id' => $instrumentId],
                    [
                        'active_trained_model_id' => $champion->id,
                        'previous_trained_model_id' => $existing && $existing->active_trained_model_id !== $champion->id ? $existing->active_trained_model_id : $existing?->previous_trained_model_id,
                        'algorithm' => $champion->algorithm, 'status' => 'active', 'elo_rating' => 1500 + $champion->quality_score * 100,
                        'validated_predictions_count' => $champion->trade_count, 'direction_accuracy' => $champion->direction_accuracy,
                        'average_strategy_return' => $champion->profit_factor, 'stability_score' => $champion->sharpe,
                        'activated_at' => $existing?->activated_at ?? $now, 'activation_reason' => $existing ? 'quality_refresh' : 'initial_quality_ranking',
                        'created_at' => $existing?->created_at ?? $now, 'updated_at' => $now,
                    ]
                );
                DB::table('model_challengers')->where('strategy_profile_id', $profileId)->where('instrument_id', $instrumentId)->delete();
                if (! $challenger) continue;
                DB::table('model_challengers')->insert([
                    'strategy_profile_id' => $profileId, 'instrument_id' => $instrumentId,
                    'trained_model_id' => $challenger->id, 'champion_model_id' => $champion->id,
                    'algorithm' => $challenger->algorithm, 'status' => 'evaluating',
                    'elo_rating' => 1500 + $challenger->quality_score * 100,
                    'validated_predictions_count' => $challenger->trade_count,
                    'direction_accuracy' => $challenger->direction_accuracy,
                    'average_strategy_return' => $challenger->profit_factor, 'stability_score' => $challenger->sharpe,
                    'evaluation_started_at' => $now, 'status_reason' => 'second_best_eligible_model',
                    'created_at' => $now, 'updated_at' => $now,
                ]);
            }
        });
        $this->info("Aktualisiert: {$groups->count()} Instrumente.");
        return self::SUCCESS;
    }
}
