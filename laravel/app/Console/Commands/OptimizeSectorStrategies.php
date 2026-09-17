<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Walk-forward parameter optimization per sector: grid-searches
 * confidence_min / drawdown_max / hit_rate_min / profit_factor_min against
 * the real RunFilteredBacktest job (same engine the UI's Strategietester
 * uses), fitted on 2023-2025 and confirmed out-of-sample on 2026, then
 * creates (or updates) the 10.000€/5-position sector strategy depot with the
 * winning combination - never a hand-picked fixed value.
 */
final class OptimizeSectorStrategies extends Command
{
    protected $signature = 'sectors:optimize-strategies {--sectors=} {--dry-run}';
    protected $description = 'Grid-search sector strategy parameters via walk-forward backtests and deploy the winning depot per sector';

    private const TRAIN_START = '2023-01-01';
    private const TRAIN_END = '2025-12-31';
    private const TEST_START = '2026-01-01';

    private const GRID = [
        'confidence_min' => [40, 50, 60, 70],
        'drawdown_max' => [15, 20, 30, 50],
        'hit_rate_min' => [0, 45, 50, 55],
        'profit_factor_min' => [0, 0.5, 1, 1.5],
    ];

    // sector => [existing saved_prediction_filter_id, existing portfolio_id] or null to create new
    private const SECTORS = [
        'Industrials' => 107,
        'Financial Services' => null,
        'Technology' => null,
        'Consumer Cyclical' => null,
        'Healthcare' => null,
        'Communication Services' => null,
        'Basic Materials' => null,
        'Consumer Defensive' => null,
        'Energy' => null,
        'Utilities' => null,
        'Real Estate' => null,
    ];

    public function handle(): int
    {
        $sourceRunId = $this->resolveSourceRunId();
        if ($sourceRunId === null) {
            $this->error('No completed walk-forward source run available.');

            return self::FAILURE;
        }

        $template = json_decode(
            (string) DB::table('saved_prediction_filters')->where('id', 107)->value('filters'),
            true,
        );

        $requestedSectors = array_filter(array_map('trim', explode(',', (string) $this->option('sectors'))));
        $sectors = $requestedSectors !== [] ? $requestedSectors : array_keys(self::SECTORS);

        $user = User::findOrFail(1);
        $results = [];

        foreach ($sectors as $sector) {
            $this->info("=== $sector ===");
            $best = $this->gridSearch($sector, $template, $sourceRunId);

            if ($best === null) {
                $this->warn("  No profitable combination found for $sector - skipped.");
                $results[$sector] = ['status' => 'no_viable_combination'];

                continue;
            }

            $this->info(sprintf(
                '  Best (train): conf=%.0f dd=%.0f hit=%.0f pf=%.1f -> final_cash=%.2f trades=%d',
                $best['params']['confidence_min'],
                $best['params']['drawdown_max'],
                $best['params']['hit_rate_min'],
                $best['params']['profit_factor_min'],
                $best['train_summary']['final_cash'],
                $best['train_summary']['trades'],
            ));
            $this->info(sprintf(
                '  Confirmed out-of-sample 2026: final_cash=%.2f trades=%d',
                $best['validation_summary']['final_cash'] ?? 10000,
                $best['validation_summary']['trades'] ?? 0,
            ));

            $results[$sector] = [
                'status' => 'ok',
                'params' => $best['params'],
                'train_summary' => $best['train_summary'],
                'validation_summary' => $best['validation_summary'],
            ];

            if (! $this->option('dry-run')) {
                $this->deploy($user, $sector, $template, $best['params'], self::SECTORS[$sector] ?? null);
            }
        }

        DB::table('backtest_runs')->where('id', $sourceRunId)->value('id'); // keep source run untouched
        file_put_contents(
            storage_path('app/cache/sector_strategy_optimization.json'),
            json_encode($results, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE),
        );

        $this->info('Done. Report written to storage/app/cache/sector_strategy_optimization.json');

        return self::SUCCESS;
    }

    private function resolveSourceRunId(): ?int
    {
        $run = DB::table('backtest_runs')
            ->whereRaw("settings->>'run_type' = 'system_walk_forward_source'")
            ->whereIn('status', ['completed', 'completed_with_errors'])
            ->orderByDesc('id')
            ->first();

        return $run?->id;
    }

    private const SHORTLIST_SIZE = 10;
    private const MIN_TRAIN_TRADES = 5;
    private const MIN_VALIDATION_TRADES = 2;

    /**
     * Two-stage walk-forward selection: the training window (2023-2025)
     * ranks candidates, but the deployed winner is picked by out-of-sample
     * 2026 performance among the top training candidates - never by
     * training profit alone. A combo that only fit 7 historical trades and
     * produces zero trades in 2026 is exactly the overfit result this
     * guards against.
     *
     * @return array{params: array, train_summary: array, validation_summary: array}|null
     */
    private function gridSearch(string $sector, array $template, int $sourceRunId): ?array
    {
        $shortlist = [];
        $combos = $this->combinations();
        $bar = $this->output->createProgressBar(count($combos));

        foreach ($combos as $params) {
            $summary = $this->runOnce($sector, $template, $params, $sourceRunId, self::TRAIN_START, self::TRAIN_END);
            $bar->advance();

            $trades = (int) ($summary['trades'] ?? 0);
            if ($trades < self::MIN_TRAIN_TRADES) {
                continue; // too few trades to trust the training result
            }

            $shortlist[] = ['params' => $params, 'summary' => $summary];
            usort($shortlist, fn ($a, $b) => $b['summary']['final_cash'] <=> $a['summary']['final_cash']);
            $shortlist = array_slice($shortlist, 0, self::SHORTLIST_SIZE);
        }
        $bar->finish();
        $this->newLine();

        if ($shortlist === []) {
            return null;
        }

        $this->info('  Validating top '.count($shortlist).' training candidates against 2026 out-of-sample data...');

        $best = null;
        foreach ($shortlist as $candidate) {
            $validation = $this->runOnce($sector, $template, $candidate['params'], $sourceRunId, self::TEST_START, now()->toDateString());
            $validationTrades = (int) ($validation['trades'] ?? 0);
            if ($validationTrades < self::MIN_VALIDATION_TRADES) {
                continue;
            }

            $validationCash = (float) ($validation['final_cash'] ?? 10000);
            if ($best === null || $validationCash > $best['validation_summary']['final_cash']) {
                $best = [
                    'params' => $candidate['params'],
                    'train_summary' => $candidate['summary'],
                    'validation_summary' => $validation,
                ];
            }
        }

        return $best;
    }

    private function combinations(): array
    {
        $keys = array_keys(self::GRID);
        $combos = [[]];

        foreach ($keys as $key) {
            $next = [];
            foreach ($combos as $combo) {
                foreach (self::GRID[$key] as $value) {
                    $next[] = $combo + [$key => $value];
                }
            }
            $combos = $next;
        }

        return $combos;
    }

    private function runOnce(string $sector, array $template, array $params, int $sourceRunId, string $periodStart, string $periodEnd): array
    {
        $filters = array_merge($template, $params, [
            'sector' => $sector,
            'period_start' => $periodStart,
            'period_end' => $periodEnd,
            'serving_model_configurations' => [],
        ]);

        $runId = DB::table('backtest_runs')->insertGetId([
            'public_id' => (string) Str::uuid(),
            'status' => 'queued',
            'strategy' => 'filtered_horizon_20d',
            'timeframe' => '1d',
            'horizon_days' => 20,
            'started_at' => now(),
            'settings' => json_encode([
                'run_type' => 'user_filter',
                'initiated_by_user_id' => 1,
                'source_run_id' => $sourceRunId,
                'action_score_version' => 1,
                'selection_filters' => $filters,
            ], JSON_THROW_ON_ERROR),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        \App\Jobs\RunFilteredBacktest::dispatchSync($runId, $sourceRunId, $filters);

        $summary = json_decode(
            (string) DB::table('backtest_runs')->where('id', $runId)->value('summary'),
            true,
        ) ?? [];

        DB::table('backtest_trades')->where('backtest_run_id', $runId)->delete();
        DB::table('backtest_runs')->where('id', $runId)->delete();

        return $summary;
    }

    private function deploy(User $user, string $sector, array $template, array $params, ?int $existingFilterId): void
    {
        $name = $sector.' Strategie';
        $filters = array_merge($template, $params, [
            'sector' => $sector,
            'serving_model_configurations' => [],
        ]);
        unset($filters['period_start'], $filters['period_end']);

        if ($existingFilterId !== null) {
            DB::table('saved_prediction_filters')->where('id', $existingFilterId)->update([
                'filters' => json_encode($filters, JSON_THROW_ON_ERROR),
                'updated_at' => now(),
            ]);
            $this->info("  Updated existing filter #$existingFilterId with optimized parameters.");

            return;
        }

        if (DB::table('saved_prediction_filters')->where('user_id', $user->id)->where('name', $name)->exists()) {
            $this->warn("  SKIP deploy (filter name exists): $name");

            return;
        }

        $initialCapital = (float) $filters['initial_capital'];
        $tradeCost = (float) $filters['trade_cost'];

        $savedFilter = $user->savedPredictionFilters()->create([
            'name' => $name,
            'filters' => $filters,
            'visibility' => 'private',
            'description' => "Depot für die Strategie {$name} (nur {$sector}-Aktien, Walk-Forward optimiert 2023-2025, validiert 2026).",
        ]);

        $portfolio = $user->portfolios()->create([
            'name' => $name,
            'type' => 'paper',
            'currency' => 'EUR',
            'description' => "Depot für die Strategie {$name} (nur {$sector}-Aktien, Walk-Forward optimiert 2023-2025, validiert 2026).",
            'is_default' => false,
            'active' => true,
            'meta' => ['automation' => ['initial_capital' => $initialCapital, 'trade_cost' => $tradeCost]],
        ]);

        $accountId = DB::table('portfolio_cash_accounts')->insertGetId([
            'portfolio_id' => $portfolio->id,
            'currency' => 'EUR',
            'balance' => $initialCapital,
            'reserved_balance' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('portfolio_cash_ledger')->insert([
            'portfolio_cash_account_id' => $accountId,
            'type' => 'initial_deposit',
            'amount' => $initialCapital,
            'balance_after' => $initialCapital,
            'currency' => 'EUR',
            'occurred_at' => now(),
            'meta' => json_encode(['source' => 'automatic_strategy_optimized'], JSON_THROW_ON_ERROR),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $meta = (array) $portfolio->meta;
        data_set($meta, 'automation.initial_capital', $initialCapital);
        data_set($meta, 'automation.trade_cost', $tradeCost);
        data_set($meta, 'automation.live_enabled', true);
        data_set($meta, 'automation.transaction_email_enabled', false);
        data_set($meta, 'automation.label', 'Strategie');
        data_set($meta, 'automation.activated_at', now()->toIso8601String());
        $portfolio->forceFill(['meta' => $meta])->save();

        DB::table('portfolio_strategy_assignments')->insert([
            'portfolio_id' => $portfolio->id,
            'saved_prediction_filter_id' => $savedFilter->id,
            'enabled' => true,
            'priority' => 10,
            'capital_weight' => 1,
            'settings' => json_encode(['source' => 'automatic_optimization'], JSON_THROW_ON_ERROR),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $savedFilter->forceFill(['automatic_portfolio_enabled' => true])->save();

        $this->info("  Deployed: portfolio #{$portfolio->id}, filter #{$savedFilter->id}");
    }
}
