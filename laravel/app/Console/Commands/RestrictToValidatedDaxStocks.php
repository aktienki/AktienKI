<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

final class RestrictToValidatedDaxStocks extends Command
{
    protected $signature = 'stocks:restrict-validated-dax
        {--feature-version=triple_daily_macro_v1 : Erforderliche Feature-Version}
        {--dry-run : Änderungen nur anzeigen}';

    protected $description = 'Zeigt ausschließlich DAX-Aktien, deren vollständige Trainings- und Validierungspipeline bestanden ist.';

    private const HORIZONS = [7200, 14400, 21600, 28800];
    private const HORIZON_DAYS = [5, 10, 15, 20];
    private const RAW_MINIMUM_HIT_RATE = 50.0;
    private const RAW_MINIMUM_PROFIT_FACTOR = 1.05;
    private const RAW_MINIMUM_AVERAGE_RETURN = 0.0;
    private const RAW_MINIMUM_TRADES = 15;
    private const RAW_REDUCED_MINIMUM_TRADES = 10;
    private const RAW_REDUCED_TRADE_HIT_RATE = 65.0;

    public function handle(): int
    {
        $indexId = DB::table('market_indices')->where('symbol', '^GDAXI')->value('id');
        if (! $indexId) {
            $this->error('DAX-Index ^GDAXI wurde nicht gefunden.');
            return self::FAILURE;
        }

        $daxIds = DB::table('index_memberships')
            ->where('market_index_id', $indexId)->whereNull('removed_at')
            ->pluck('instrument_id')->map(fn ($id): int => (int) $id);
        // The raw horizon models are inputs to the post-filter pipeline. Their
        // unfiltered metrics must not decide publication; the individually
        // calibrated threshold below is the final out-of-sample quality gate.
        $modelQualified = DB::table('trained_models')
            ->whereNull('deleted_at')->where('status', 'active')
            ->where('feature_set_version', (string) $this->option('feature-version'))
            ->whereIn('prediction_horizon_minutes', self::HORIZONS)
            ->whereIn('instrument_id', $daxIds)
            ->get(['instrument_id', 'prediction_horizon_minutes'])
            ->groupBy('instrument_id')
            ->filter(function ($models): bool {
                $horizons = $models->pluck('prediction_horizon_minutes')->map(fn ($value): int => (int) $value)->unique();

                return $horizons->contains(28800) && $horizons->count() >= 2;
            })->keys();

        $walkForwardQualified = DB::table('walk_forward_backtest_scores as score')
            ->join('walk_forward_backtest_runs as run', 'run.id', '=', 'score.run_id')
            ->where('run.status', 'completed')->whereIn('score.horizon_days', self::HORIZON_DAYS)
            ->whereIn('score.instrument_id', $modelQualified)
            ->groupBy('score.instrument_id')
            ->havingRaw('COUNT(DISTINCT score.horizon_days) >= 2')
            ->havingRaw('BOOL_OR(score.horizon_days = 20)')
            ->pluck('score.instrument_id')->map(fn ($id): int => (int) $id);

        $rawQualified = $walkForwardQualified->filter(function (int $instrumentId): bool {
            $latestRunId = DB::table('walk_forward_backtest_runs as run')
                ->join('walk_forward_backtest_trades as trade', 'trade.run_id', '=', 'run.id')
                ->where('run.status', 'completed')->where('run.horizon_days', 20)
                ->where('trade.instrument_id', $instrumentId)
                ->orderByDesc('run.finished_at')->orderByDesc('run.id')->value('run.id');
            if (! $latestRunId) return false;
            $stats = DB::table('walk_forward_backtest_trades')->where('run_id', $latestRunId)
                ->where('instrument_id', $instrumentId)
                ->selectRaw('COUNT(*) AS trades')
                ->selectRaw('AVG(net_return) * 100 AS average_return')
                ->selectRaw('AVG(CASE WHEN net_return > 0 THEN 1.0 ELSE 0.0 END) * 100 AS hit_rate')
                ->selectRaw('SUM(CASE WHEN net_return > 0 THEN net_return ELSE 0 END) / NULLIF(ABS(SUM(CASE WHEN net_return < 0 THEN net_return ELSE 0 END)), 0) AS profit_factor')
                ->first();
            if (! $stats || ! is_numeric($stats->profit_factor) || ! is_numeric($stats->average_return)) return false;
            $hitRate = (float) $stats->hit_rate;
            $requiredTrades = $hitRate >= self::RAW_REDUCED_TRADE_HIT_RATE
                ? self::RAW_REDUCED_MINIMUM_TRADES : self::RAW_MINIMUM_TRADES;
            return (int) $stats->trades >= $requiredTrades
                && $hitRate >= self::RAW_MINIMUM_HIT_RATE
                && (float) $stats->profit_factor >= self::RAW_MINIMUM_PROFIT_FACTOR
                && (float) $stats->average_return > self::RAW_MINIMUM_AVERAGE_RETURN;
        });

        $qualified = $daxIds
            ->intersect($modelQualified)
            ->intersect($walkForwardQualified)
            ->intersect($rawQualified)
            ->unique();
        $this->info("DAX-Mitglieder: {$daxIds->count()}, vollständig validiert: {$qualified->count()}.");
        if ($this->option('dry-run')) {
            return self::SUCCESS;
        }

        $daxLookup = $daxIds->flip();
        $qualifiedLookup = $qualified->flip();
        DB::transaction(function () use ($daxLookup, $qualifiedLookup): void {
            DB::table('instruments')->where('type', 'stock')->whereNull('deleted_at')
                ->orderBy('id')->chunkById(200, function ($stocks) use ($daxLookup, $qualifiedLookup): void {
                    foreach ($stocks as $stock) {
                        $meta = is_string($stock->meta) ? (json_decode($stock->meta, true) ?: []) : (array) $stock->meta;
                        $isQualified = $qualifiedLookup->has((int) $stock->id);
                        if ($isQualified) {
                            unset($meta['deactivated_reason'], $meta['deactivated_at']);
                        } else {
                            $meta['deactivated_reason'] = $daxLookup->has((int) $stock->id) ? 'incomplete_training' : 'dax_only_rollout';
                            $meta['deactivated_at'] = now()->toIso8601String();
                        }
                        DB::table('instruments')->where('id', $stock->id)->update([
                            'is_active' => $isQualified,
                            'meta' => json_encode($meta, JSON_THROW_ON_ERROR),
                            'updated_at' => now(),
                        ]);
                    }
                });
        });

        foreach (['defensive', 'balanced', 'opportunity', 'risk'] as $level) {
            Cache::forget("dashboard.profile-universe.{$level}.v2");
            Cache::forget("dashboard.profile-universe.{$level}.v3");
        }

        $this->info("DAX-only-Freigabe aktiv: {$qualified->count()} Aktien sichtbar; alle übrigen Aktien deaktiviert.");
        return self::SUCCESS;
    }
}
