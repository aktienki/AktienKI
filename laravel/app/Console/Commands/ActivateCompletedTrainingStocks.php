<?php

namespace App\Console\Commands;

use App\Services\TrainingActivationQualityGate;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

final class ActivateCompletedTrainingStocks extends Command
{
    protected $signature = 'stocks:activate-completed-training
        {--feature-version=triple_daily_macro_v1 : Erforderliche Feature-Version}
        {--dry-run : Nur die Zahl der freigabefähigen Aktien anzeigen}';

    protected $description = 'Aktiviert validierte Aktien mit 20T und mindestens einem weiteren Prognosehorizont.';

    private const HORIZONS = [7200, 14400, 21600, 28800];
    private const HORIZON_DAYS = [5, 10, 15, 20];

    public function handle(TrainingActivationQualityGate $qualityGate): int
    {
        $rules = $qualityGate->rules();
        $qualifiedModelIds = DB::table('trained_models')
            ->whereNull('deleted_at')
            ->where('feature_set_version', (string) $this->option('feature-version'))
            ->where('status', 'active')
            ->whereIn('prediction_horizon_minutes', self::HORIZONS)
            ->get(['instrument_id', 'prediction_horizon_minutes', 'metrics'])
            ->filter(fn (object $model): bool => $qualityGate->passes($model->metrics))
            ->groupBy('instrument_id')
            ->filter(function ($models): bool {
                $horizons = $models->pluck('prediction_horizon_minutes')->map(fn ($value): int => (int) $value)->unique();

                return $horizons->contains(28800) && $horizons->count() >= 2;
            })
            ->keys();

        $walkForwardIds = DB::table('walk_forward_backtest_scores as score')
            ->join('walk_forward_backtest_runs as run', 'run.id', '=', 'score.run_id')
            ->where('run.status', 'completed')
            ->whereIn('score.horizon_days', self::HORIZON_DAYS)
            ->where(function ($query) use ($rules): void {
                $query->where(function ($standard) use ($rules): void {
                    $standard->where('score.trade_count', '>=', $rules['minimum_trade_count'])
                        ->where('score.raw_win_probability', '>=', $rules['minimum_direction_accuracy']);
                })->orWhere(function ($reduced) use ($rules): void {
                    $reduced->where('score.trade_count', '>=', $rules['reduced_minimum_trade_count'])
                        ->where('score.raw_win_probability', '>=', $rules['reduced_trade_count_minimum_direction_accuracy']);
                });
            })
            ->whereIn('score.instrument_id', $qualifiedModelIds)
            ->groupBy('score.instrument_id')
            ->havingRaw('COUNT(DISTINCT score.horizon_days) >= 2')
            ->havingRaw('BOOL_OR(score.horizon_days = 20)')
            ->pluck('score.instrument_id');

        $phaseFilterIds = DB::table('market_context_predictions')
            ->where('scope_type', 'stock_phase20')
            ->whereRaw("meta->>'source' = ?", ['pytorch_stock_three_phase_gru_20t'])
            ->pluck('scope_key')->map(fn ($value): int => (int) $value);

        $stocks = DB::table('instruments')
            ->whereIn('id', $walkForwardIds->intersect($phaseFilterIds))
            ->where('type', 'stock')
            ->whereNull('deleted_at')
            ->where('is_active', false)
            ->whereRaw("COALESCE(meta->>'deactivated_reason', '') = 'incomplete_training'")
            ->get(['id', 'symbol', 'meta']);

        if ($this->option('dry-run')) {
            $this->info("{$stocks->count()} Aktien erfüllen das vollständige Quality-Gate und können aktiviert werden.");
            return self::SUCCESS;
        }

        DB::transaction(function () use ($stocks): void {
            foreach ($stocks as $stock) {
                $meta = is_string($stock->meta) ? json_decode($stock->meta, true) : (array) $stock->meta;
                unset($meta['deactivated_reason'], $meta['deactivated_at']);

                DB::table('instruments')->where('id', $stock->id)->update([
                    'is_active' => true,
                    'meta' => json_encode($meta, JSON_THROW_ON_ERROR),
                    'updated_at' => now(),
                ]);
            }
        });

        if ($stocks->isNotEmpty()) {
            foreach (['defensive', 'balanced', 'opportunity', 'risk'] as $level) {
                Cache::forget("dashboard.profile-universe.{$level}.v2");
                Cache::forget("dashboard.profile-universe.{$level}.v3");
            }
        }

        $this->info("{$stocks->count()} Aktien nach vollständigem Quality-Gate aktiviert.");
        return self::SUCCESS;
    }
}
