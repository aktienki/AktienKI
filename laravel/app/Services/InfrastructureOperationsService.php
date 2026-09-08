<?php

namespace App\Services;

use Carbon\CarbonImmutable;
use Illuminate\Process\ProcessResult;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use Throwable;

class InfrastructureOperationsService
{
    public function status(): array
    {
        $database = $this->applicationDatabaseStatus();
        $databaseStats = $this->databaseStats();

        return [
            'reachable' => true,
            'error' => $database['connected'] ? null : $database['error'],
            'metrics' => [],
            'database' => $database,
            'database_stats' => $databaseStats,
            'errors' => [],
            'log' => [],
            'runs' => $this->walkForwardRuns(),
        ];
    }

    public function restartApplicationServices(): array
    {
        Artisan::call('queue:restart');

        $phpService = 'php'.PHP_MAJOR_VERSION.'.'.PHP_MINOR_VERSION.'-fpm';
        $result = Process::timeout(15)->run([
            '/usr/bin/sudo', '-n', '/usr/bin/systemctl', 'reload', $phpService, 'nginx',
        ]);

        Log::notice('Admin requested application service restart.', [
            'successful' => $result->successful(),
            'services' => [$phpService, 'nginx'],
        ]);

        return $this->actionResult($result, __('Die Anwendungsdienste wurden neu geladen.'));
    }

    public function restartRemoteServer(): array
    {
        $result = Process::timeout(5)->run([
            '/usr/bin/sudo', '-n', '/usr/bin/systemd-run',
            '--on-active=3s',
            '/usr/bin/systemctl', 'reboot',
        ]);

        Log::warning('Admin scheduled a remote server reboot.', [
            'successful' => $result->successful(),
        ]);

        return $this->actionResult($result, __('Der Remote-Server wird in wenigen Sekunden neu gestartet.'));
    }

    private function applicationDatabaseStatus(): array
    {
        try {
            $database = DB::selectOne(<<<'SQL'
SELECT current_database() AS name,
       COALESCE(inet_server_addr()::text, 'lokal') AS server,
       inet_server_port() AS port
SQL);

            return [
                'connected' => true,
                'name' => $database->name ?? null,
                'server' => $database->server ?? null,
                'host' => $database->server ?? null,
                'port' => $database->port ?? null,
                'error' => null,
            ];
        } catch (Throwable $exception) {
            return [
                'connected' => false,
                'name' => null,
                'server' => null,
                'host' => null,
                'port' => null,
                'error' => $exception->getMessage(),
            ];
        }
    }

    private function databaseStats(): array
    {
        $currentStocks = $this->currentStockQualityStats();
        $latestPrediction = $this->latestPredictionStats();

        try {
            $oldestModel = DB::table('trained_models as model')
                ->join('instruments as instrument', 'instrument.id', '=', 'model.instrument_id')
                ->where('model.status', 'active')
                ->whereNotNull('model.trained_at')
                ->orderBy('model.trained_at')
                ->orderBy('model.id')
                ->first(['instrument.symbol', 'model.trained_at']);
            $subscriptionCounts = DB::table('tariff_plans as plan')
                ->leftJoin('users as user', 'user.tariff_plan_id', '=', 'plan.id')
                ->whereNull('plan.deleted_at')
                ->groupBy('plan.id', 'plan.code', 'plan.name', 'plan.sort_order')
                ->orderBy('plan.sort_order')
                ->get([
                    'plan.code',
                    'plan.name',
                    DB::raw('COUNT(user.id) AS users'),
                ])
                ->map(fn (object $plan): array => [
                    'code' => (string) $plan->code,
                    'name' => (string) $plan->name,
                    'users' => (int) $plan->users,
                ])->all();

            return [
                'users' => DB::table('users')->count(),
                'active_users' => DB::table('sessions')
                    ->whereNotNull('user_id')
                    ->where('last_activity', '>=', now()->subMinutes(15)->timestamp)
                    ->distinct()
                    ->count('user_id'),
                'walk_forward_stocks' => DB::table('walk_forward_backtest_trades as trade')
                    ->join('walk_forward_backtest_runs as run', 'run.id', '=', 'trade.run_id')
                    ->whereIn('run.status', ['completed', 'completed_with_errors'])
                    ->distinct()
                    ->count('trade.instrument_id'),
                'oldest_model_symbol' => $oldestModel?->symbol,
                'oldest_model_trained_at' => $oldestModel?->trained_at,
                'subscription_counts' => $subscriptionCounts,
                'current_stocks' => $currentStocks,
                'latest_prediction' => $latestPrediction,
            ];
        } catch (Throwable) {
            return [
                'users' => null,
                'active_users' => null,
                'walk_forward_stocks' => null,
                'oldest_model_symbol' => null,
                'oldest_model_trained_at' => null,
                'subscription_counts' => [],
                'current_stocks' => $currentStocks,
                'latest_prediction' => $latestPrediction,
            ];
        }
    }

    private function currentStockQualityStats(): array
    {
        $empty = [
            'available' => false,
            'total' => null,
            'classified' => null,
            'latest_calculated_at' => null,
            'source' => 'stock_individual_thresholds',
            'quality_counts' => [
                'quality' => 0,
                'solid' => 0,
                'basic' => 0,
                'unqualified' => 0,
                'unclassified' => 0,
            ],
        ];

        try {
            $latestThresholds = DB::table('stock_individual_thresholds as candidate')
                ->where('candidate.horizon_days', 20)
                ->where('candidate.algorithm_version', 'like', 'historical-action-%')
                ->selectRaw('candidate.instrument_id, MAX(candidate.id) AS threshold_id')
                ->groupBy('candidate.instrument_id');

            $stocks = DB::table('instruments as instrument')
                ->leftJoinSub($latestThresholds, 'latest_threshold', fn ($join) => $join
                    ->on('latest_threshold.instrument_id', '=', 'instrument.id'))
                ->leftJoin('stock_individual_thresholds as threshold', 'threshold.id', '=', 'latest_threshold.threshold_id')
                ->whereNull('instrument.deleted_at')
                ->where('instrument.is_active', true)
                ->whereRaw('LOWER(instrument.type) = ?', ['stock'])
                ->get([
                    'instrument.meta',
                    'threshold.status',
                    'threshold.score_result',
                    'threshold.calculated_at',
                ]);

            $counts = $empty['quality_counts'];
            foreach ($stocks as $stock) {
                $counts[$this->stockQualityClass($stock)]++;
            }

            return [
                'available' => true,
                'total' => $stocks->count(),
                'classified' => $stocks->count() - $counts['unclassified'],
                'latest_calculated_at' => $stocks->pluck('calculated_at')->filter()->sortDesc()->first(),
                'source' => 'stock_individual_thresholds',
                'quality_counts' => $counts,
            ];
        } catch (Throwable) {
            return $empty;
        }
    }

    private function stockQualityClass(object $stock): string
    {
        $scoreResult = is_array($stock->score_result)
            ? $stock->score_result
            : (json_decode((string) $stock->score_result, true) ?: []);
        $meta = is_array($stock->meta)
            ? $stock->meta
            : (json_decode((string) $stock->meta, true) ?: []);
        $qualityClass = data_get($scoreResult, 'final_quality_class')
            ?? data_get($scoreResult, 'post_filter_evaluation.quality_class')
            ?? data_get($scoreResult, 'post_filter_evaluation.selected.quality_class')
            ?? data_get($scoreResult, 'raw_pre_filter_quality_class')
            ?? data_get($meta, 'model_quality_class');

        if (! is_string($qualityClass) || trim($qualityClass) === '') {
            $qualityClass = preg_replace('/_(active|documented)$/', '', strtolower((string) $stock->status));
        }

        return match (strtolower(trim((string) $qualityClass))) {
            'quality' => 'quality',
            'solid' => 'solid',
            'basic' => 'basic',
            'unqualified', 'observation' => 'unqualified',
            default => 'unclassified',
        };
    }

    private function latestPredictionStats(): array
    {
        $empty = [
            'available' => false,
            'successful' => null,
            'created_at' => null,
            'prediction_time' => null,
            'rows' => 0,
            'stocks' => 0,
            'horizons' => 0,
            'eligible' => 0,
            'complete' => 0,
            'missing' => 0,
            'coverage_percent' => null,
            'minimum_coverage_percent' => 95.0,
        ];

        try {
            $horizons = [7200, 14400, 21600, 28800];
            $latest = DB::table('predictions')
                ->where('ai_type', 'horizon')
                ->where('timeframe', '1d')
                ->whereIn('prediction_horizon_minutes', $horizons)
                ->orderByDesc('created_at')
                ->orderByDesc('id')
                ->first(['created_at', 'prediction_time']);

            if (! $latest?->created_at) {
                return $empty;
            }

            $latestAt = CarbonImmutable::parse((string) $latest->created_at);
            $batch = DB::table('predictions')
                ->where('ai_type', 'horizon')
                ->where('timeframe', '1d')
                ->whereIn('prediction_horizon_minutes', $horizons)
                ->whereBetween('created_at', [$latestAt->startOfDay(), $latestAt->endOfDay()])
                ->selectRaw('COUNT(*) AS rows')
                ->selectRaw('COUNT(DISTINCT instrument_id) AS stocks')
                ->selectRaw('COUNT(DISTINCT prediction_horizon_minutes) AS horizons')
                ->first();
            $coverage = DB::selectOne(<<<'SQL'
WITH eligible AS (
    SELECT instrument.id
    FROM instruments instrument
    WHERE instrument.deleted_at IS NULL
      AND instrument.is_active = TRUE
      AND LOWER(instrument.type) = 'stock'
      AND (
          SELECT COUNT(DISTINCT model.prediction_horizon_minutes)
          FROM trained_models model
          WHERE model.instrument_id = instrument.id
            AND model.deleted_at IS NULL
            AND model.status = 'active'
            AND model.ai_type = 'horizon'
            AND model.feature_set_version = 'triple_daily_macro_v1'
            AND model.prediction_horizon_minutes IN (7200, 14400, 21600, 28800)
      ) = 4
), batch_prediction AS (
    SELECT prediction.*
    FROM predictions prediction
    WHERE prediction.ai_type = 'horizon'
      AND prediction.timeframe = '1d'
      AND prediction.prediction_horizon_minutes IN (7200, 14400, 21600, 28800)
      AND prediction.created_at >= ?
      AND prediction.created_at <= ?
), latest_batch_bar AS (
    SELECT prediction.instrument_id, MAX(prediction.source_bar_time) AS source_bar_time
    FROM batch_prediction prediction
    JOIN eligible ON eligible.id = prediction.instrument_id
    GROUP BY prediction.instrument_id
), complete AS (
    SELECT prediction.instrument_id
    FROM batch_prediction prediction
    JOIN latest_batch_bar ON latest_batch_bar.instrument_id = prediction.instrument_id
                         AND latest_batch_bar.source_bar_time = prediction.source_bar_time
    GROUP BY prediction.instrument_id
    HAVING COUNT(DISTINCT prediction.prediction_horizon_minutes) = 4
)
SELECT (SELECT COUNT(*) FROM eligible) AS eligible,
       (SELECT COUNT(*) FROM complete) AS complete
SQL, [$latestAt->startOfDay(), $latestAt->endOfDay()]);
            $eligible = (int) ($coverage->eligible ?? 0);
            $complete = (int) ($coverage->complete ?? 0);
            $coveragePercent = $eligible > 0 ? round(($complete / $eligible) * 100, 1) : null;

            return [
                'available' => true,
                'successful' => $coveragePercent !== null && $coveragePercent >= 95.0,
                'created_at' => $latest->created_at,
                'prediction_time' => $latest->prediction_time,
                'rows' => (int) ($batch->rows ?? 0),
                'stocks' => (int) ($batch->stocks ?? 0),
                'horizons' => (int) ($batch->horizons ?? 0),
                'eligible' => $eligible,
                'complete' => $complete,
                'missing' => max(0, $eligible - $complete),
                'coverage_percent' => $coveragePercent,
                'minimum_coverage_percent' => 95.0,
            ];
        } catch (Throwable) {
            return $empty;
        }
    }

    private function walkForwardRuns(): array
    {
        try {
            return DB::table('walk_forward_backtest_runs as run')
                ->orderByDesc('run.id')
                ->limit(12)
                ->get(['run.id', 'run.status', 'run.horizon_days', 'run.started_at', 'run.finished_at', 'run.error_message'])
                ->map(function (object $run): array {
                    $run->stocks = DB::table('walk_forward_backtest_trades')
                        ->where('run_id', $run->id)->distinct('instrument_id')->count('instrument_id');
                    $run->trades = DB::table('walk_forward_backtest_trades')->where('run_id', $run->id)->count();

                    return (array) $run;
                })->all();
        } catch (Throwable $exception) {
            return [['status' => 'unavailable', 'error_message' => $exception->getMessage()]];
        }
    }

    private function actionResult(ProcessResult $result, string $successMessage): array
    {
        return [
            'successful' => $result->successful(),
            'message' => $result->successful()
                ? $successMessage
                : (trim($result->errorOutput() ?: $result->output()) ?: __('Aktion fehlgeschlagen.')),
        ];
    }
}
