<?php

namespace App\Jobs;

use App\Services\TwelveDataService;
use App\Services\YahooIndexService;
use App\Services\HistoricalDynamicExitService;
use App\Services\HistoricalForecastScoreRotationService;
use App\Services\HistoricalAreaEntryRotationService;
use App\Services\HistoricalIndicatorMatrixService;
use App\Services\HistoricalIndicatorProbabilityService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use RuntimeException;
use Symfony\Component\Process\Process;
use Throwable;

final class RunFilteredBacktest implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * A full three-year strategy test can take considerably longer than an
     * ordinary web/notification job. The dedicated backtests worker uses the
     * same limit, so Laravel and the process supervisor agree about when a job
     * is actually stale.
     */
    public int $timeout = 7200;

    public bool $failOnTimeout = true;

    public int $tries = 1;

    public function __construct(
        public readonly int $runId,
        public readonly int $sourceRunId,
        public readonly array $filters,
    ) {
        $this->onQueue('backtests');
    }

    public function handle(TwelveDataService $marketData, YahooIndexService $fallbackMarketData, HistoricalDynamicExitService $dynamicExits, HistoricalForecastScoreRotationService $scoreRotation, HistoricalAreaEntryRotationService $areaRotations, HistoricalIndicatorMatrixService $indicatorMatrix, HistoricalIndicatorProbabilityService $indicatorProbability): void
    {
        if ($this->isCancelled()) {
            $this->clearCancellationMarker();
            return;
        }
        DB::table('backtest_runs')->where('id', $this->runId)->update([
            'status' => 'running',
            'started_at' => now(),
            'updated_at' => now(),
        ]);
        // A retry of a partially completed run may already have copied some
        // trades before a later step failed. Clear those rows so the retry is
        // deterministic and cannot hit the unique trade constraint.
        DB::table('backtest_trades')->where('backtest_run_id', $this->runId)->delete();

        $latestQuality = DB::table('model_quality_rankings')
            ->selectRaw('trained_model_id, MAX(id) AS ranking_id')
            ->groupBy('trained_model_id');
        $latestFundamental = DB::table('instrument_fundamentals')
            ->selectRaw('instrument_id, MAX(id) AS fundamental_id')
            ->groupBy('instrument_id');
        $latestTechnical = DB::table('technical_indicators')
            ->where('interval', '1d')
            ->selectRaw('instrument_id, MAX(id) AS technical_id')
            ->groupBy('instrument_id');
        $fundamentalNumber = static fn (string $key): string => match ($key) {
            'trailingPE' => "COALESCE(fundamental.trailing_pe, CASE WHEN NULLIF(fundamental.data::jsonb->>'trailingPE', '') ~ '^-?[0-9]+([.][0-9]+)?$' THEN (fundamental.data::jsonb->>'trailingPE')::numeric END)",
            'dividendYield' => "COALESCE(fundamental.dividend_yield, CASE WHEN NULLIF(fundamental.data::jsonb->>'dividendYield', '') ~ '^-?[0-9]+([.][0-9]+)?$' THEN (fundamental.data::jsonb->>'dividendYield')::numeric END)",
            'marketCap' => "COALESCE(fundamental.market_cap, CASE WHEN NULLIF(fundamental.data::jsonb->>'marketCap', '') ~ '^-?[0-9]+([.][0-9]+)?$' THEN (fundamental.data::jsonb->>'marketCap')::numeric END)",
            'revenueGrowth' => "COALESCE(fundamental.revenue_growth, CASE WHEN NULLIF(fundamental.data::jsonb->>'revenueGrowth', '') ~ '^-?[0-9]+([.][0-9]+)?$' THEN (fundamental.data::jsonb->>'revenueGrowth')::numeric END)",
        };

        $query = DB::table('backtest_trades as trade')
            ->join('instruments as instrument', 'instrument.id', '=', 'trade.instrument_id')
            ->leftJoin('exchanges as exchange', 'exchange.id', '=', 'instrument.exchange_id')
            ->leftJoinSub($latestQuality, 'latest_quality', fn ($join) =>
                $join->on('latest_quality.trained_model_id', '=', 'trade.trained_model_id'))
            ->leftJoin('model_quality_rankings as model_quality', 'model_quality.id', '=', 'latest_quality.ranking_id')
            ->leftJoin('model_quality_tiers as quality_tier', 'quality_tier.id', '=', 'model_quality.tier_id')
            ->leftJoinSub($latestFundamental, 'latest_fundamental', fn ($join) =>
                $join->on('latest_fundamental.instrument_id', '=', 'instrument.id'))
            ->leftJoin('instrument_fundamentals as fundamental', 'fundamental.id', '=', 'latest_fundamental.fundamental_id')
            ->leftJoinSub($latestTechnical, 'latest_technical', fn ($join) =>
                $join->on('latest_technical.instrument_id', '=', 'instrument.id'))
            ->leftJoin('technical_indicators as technical', 'technical.id', '=', 'latest_technical.technical_id')
            ->where('trade.backtest_run_id', $this->sourceRunId)
            ->where('trade.entry_date', '>=', now()->subYears(3)->toDateString())
            ->where('trade.signal', 'BUY')
            ->whereNotNull('trade.predicted_return')
            ->where('trade.predicted_return', '>', 0)
            // Reject corrupt source rows caused by mixed quote units (for
            // example ZAR versus South-African cents). A long-only trade can
            // never lose more than 100%; returns above 300% inside one
            // 20-day horizon are treated as broken market data rather than
            // investable backtest results.
            ->whereBetween('trade.gross_return', [-1.0, 3.0])
            ->where('instrument.is_active', true)
            ->where('instrument.is_german_tradeable', true)
            // A German listing flag is not sufficient for a historical EUR
            // execution: without a complete listing-price history, using the
            // native quote (USD, ZAc, GBP, ...) as EUR corrupts position sizes.
            ->whereRaw("UPPER(COALESCE(instrument.currency, '')) = 'EUR'")
            ->whereNull('instrument.deleted_at');

        $drawdownMaximum = is_numeric($this->filters['drawdown_max'] ?? null)
            ? (float) $this->filters['drawdown_max']
            : 50.0;
        // Keep this criterion identical to HistoricalPortfolioSimulator: the
        // stored key is legacy, but its value means average profit per trade
        // in percent (not the dimensionless profit factor).
        $profitPerTradeMinimum = is_numeric($this->filters['profit_per_trade_min'] ?? null)
            ? (float) $this->filters['profit_per_trade_min']
            : 0.0;
        $hitRateMinimum = is_numeric($this->filters['hit_rate_min'] ?? null)
            ? (float) $this->filters['hit_rate_min']
            : 0.0;
        $minimumTrades = is_numeric($this->filters['minimum_trades'] ?? null)
            ? max(1, (int) $this->filters['minimum_trades'])
            : 1;
        if ($drawdownMaximum < 50 || $profitPerTradeMinimum > 0 || $hitRateMinimum > 0 || $minimumTrades > 1) {
            $eligibleInstruments = DB::table('backtest_trades as eligible_trade')
                ->where('eligible_trade.backtest_run_id', $this->sourceRunId)
                ->where('eligible_trade.entry_date', '>=', now()->subYears(3)->toDateString())
                ->groupBy('eligible_trade.instrument_id')
                ->select('eligible_trade.instrument_id')
                ->when($drawdownMaximum < 50, fn (Builder $query) =>
                    $query->havingRaw('MAX(ABS(eligible_trade.max_drawdown)) <= ?', [max(0, $drawdownMaximum) / 100]))
                ->when($profitPerTradeMinimum > 0, fn (Builder $query) =>
                    $query->havingRaw(
                        'AVG(eligible_trade.net_return) * 100 >= ?',
                        [$profitPerTradeMinimum],
                    ))
                ->when($hitRateMinimum > 0, fn (Builder $query) =>
                    $query->havingRaw(
                        'AVG(CASE WHEN eligible_trade.net_return > 0 THEN 1.0 ELSE 0.0 END) * 100 >= ?',
                        [min(100, $hitRateMinimum)],
                    ))
                ->when($minimumTrades > 1, fn (Builder $query) =>
                    $query->havingRaw('COUNT(*) >= ?', [$minimumTrades]));
            $query->whereIn('trade.instrument_id', $eligibleInstruments);
        }

        $this->applyFilters($query, $fundamentalNumber);

        $candidates = $query->select(
            'trade.*',
            'instrument.sector as rotation_sector',
            'instrument.currency as source_currency',
            'instrument.german_listing_symbol as eur_listing_symbol',
            'instrument.german_listing_exchange as eur_listing_exchange',
        )
            ->orderBy('trade.entry_date')
            ->orderByDesc('trade.ki_score')
            ->orderByDesc('trade.confidence')
            ->orderBy('trade.id')
            ->get();
        $sectorScoreMinimum = is_numeric($this->filters['sector_score_min'] ?? null)
            ? max(-1.0, min(10.0, (float) $this->filters['sector_score_min'])) : -1.0;
        if ($sectorScoreMinimum >= 0) {
            $sectorScores = DB::table('backtest_trades as sector_trade')
                ->join('instruments as sector_instrument', 'sector_instrument.id', '=', 'sector_trade.instrument_id')
                ->where('sector_trade.backtest_run_id', $this->sourceRunId)
                ->whereNotNull('sector_instrument.sector')
                ->groupBy('sector_trade.entry_date', 'sector_instrument.sector')
                ->get(['sector_trade.entry_date', 'sector_instrument.sector', DB::raw('AVG(sector_trade.ki_score) AS average_score')])
                ->mapWithKeys(fn (object $row): array => [(string) $row->entry_date.'|'.(string) $row->sector => (float) $row->average_score]);
            $candidates = $candidates->filter(fn (object $trade): bool => filled($trade->rotation_sector)
                && (float) $sectorScores->get((string) $trade->entry_date.'|'.(string) $trade->rotation_sector, 0) > $sectorScoreMinimum)->values();
        }
        $candidates = $indicatorMatrix->filterEntries($candidates, $this->filters);
        $candidates = $indicatorProbability->filter(
            $candidates,
            max(0.0, min(100.0, (float) ($this->filters['indicator_probability_min'] ?? 0))),
        );
        $minimumNoiseScore = max(0.0, min(100.0, (float) ($this->filters['noise_score_min'] ?? 0)));
        if ($minimumNoiseScore > 0 && $candidates->isNotEmpty()) {
            $noiseScores = DB::table('historical_noise_scores')
                ->whereIn('instrument_id', $candidates->pluck('instrument_id')->unique())
                ->where('calculation_version', 'noise-score-tanh-v1')
                ->get(['instrument_id', 'signal_date', 'score'])
                ->mapWithKeys(fn (object $row): array => [(int) $row->instrument_id.'|'.(string) $row->signal_date => (float) $row->score]);
            $candidates = $candidates->filter(fn (object $trade): bool =>
                (float) $noiseScores->get((int) $trade->instrument_id.'|'.(string) $trade->entry_date, -1) >= $minimumNoiseScore
            )->values();
        }
        $riskStyle = in_array($this->filters['entry_risk_style'] ?? null, ['conservative', 'balanced', 'chance'], true)
            ? $this->filters['entry_risk_style'] : 'balanced';
        $metrics = $candidates->groupBy('instrument_id')->map(function ($rows): array {
            $wins = $rows->filter(fn (object $trade): bool => (float) $trade->net_return > 0);
            $losses = $rows->filter(fn (object $trade): bool => (float) $trade->net_return < 0);
            $profit = (float) $wins->sum('net_return');
            $loss = abs((float) $losses->sum('net_return'));
            return [
                'drawdown' => (float) $rows->max(fn (object $trade): float => abs((float) ($trade->max_drawdown ?? 0))),
                'hit_rate' => $rows->isNotEmpty() ? $wins->count() / $rows->count() : 0.0,
                'profit_factor' => $loss > 0 ? $profit / $loss : ($profit > 0 ? INF : 0.0),
            ];
        });
        $candidates->each(function (object $trade) use ($metrics): void {
            $metric = $metrics->get($trade->instrument_id, ['drawdown' => 0.0, 'hit_rate' => 0.0, 'profit_factor' => 0.0]);
            $trade->selection_drawdown = $metric['drawdown'];
            $trade->selection_hit_rate = $metric['hit_rate'];
            $trade->selection_profit_factor = $metric['profit_factor'];
        });
        $candidates = $candidates->sort(fn (object $left, object $right): int => strcmp((string) $left->entry_date, (string) $right->entry_date)
            ?: $this->compareSelectionProfile($left, $right, $riskStyle)
            ?: ((float) $right->ki_score <=> (float) $left->ki_score)
            ?: ((int) $left->id <=> (int) $right->id))->values();
        $rows = $candidates;
        $initialCapital = $this->initialCapital();
        $positionCapital = $this->positionCapital();
        $tradeCost = $this->tradeCost();
        foreach ($rows->chunk(500) as $chunk) {
            if ($this->isCancelled()) {
                $this->clearCancellationMarker();
                return;
            }
            DB::table('backtest_trades')->insertOrIgnore($chunk->map(function (object $trade) use ($positionCapital, $tradeCost): array {
                $row = (array) $trade;
                unset($row['id']);
                unset($row['rotation_sector'], $row['selection_drawdown'], $row['selection_hit_rate'], $row['selection_profit_factor']);
                $indicatorProbability = is_numeric($row['indicator_probability'] ?? null)
                    ? (float) $row['indicator_probability']
                    : null;
                unset($row['indicator_probability']);
                $sourceCurrency = strtoupper((string) ($row['source_currency'] ?? 'EUR'));
                $eurListingSymbol = $row['eur_listing_symbol'] ?? null;
                $eurListingExchange = $row['eur_listing_exchange'] ?? null;
                unset($row['source_currency'], $row['eur_listing_symbol'], $row['eur_listing_exchange']);
                $row['backtest_run_id'] = $this->runId;
                $row['transaction_cost'] = $positionCapital > 0 ? $tradeCost / $positionCapital : 0;
                $row['net_return'] = (float) $trade->gross_return - (float) $row['transaction_cost'];
                $metadata = is_string($row['metadata'] ?? null)
                    ? (json_decode($row['metadata'], true) ?: [])
                    : (array) ($row['metadata'] ?? []);
                $row['metadata'] = json_encode([
                    ...$metadata,
                    'allocated_capital' => $positionCapital,
                    'allocated_capital_eur' => $positionCapital,
                    'trade_cost_eur' => $tradeCost,
                    'entry_value_eur' => $positionCapital,
                    'exit_value_eur' => $positionCapital * (1 + (float) $row['net_return']),
                    'execution_currency' => 'EUR',
                    'source_quote_currency' => $sourceCurrency,
                    'eur_listing_symbol' => $eurListingSymbol,
                    'eur_listing_exchange' => $eurListingExchange,
                    'execution_basis' => $sourceCurrency === 'EUR'
                        ? 'native_eur_quote'
                        : 'verified_german_eur_listing_return_proxy',
                    'capital_constrained' => true,
                    'indicator_probability_20d' => $indicatorProbability,
                ], JSON_THROW_ON_ERROR);
                $row['created_at'] = now();
                $row['updated_at'] = now();

                return $row;
            })->all());
        }

        $indicatorMatrixSummary = $indicatorMatrix->applyExits($this->runId, $this->filters);
        if (! $this->calculateExitStrategies()) {
            $this->clearCancellationMarker();
            return;
        }
        $automaticComparison = filter_var($this->filters['automatic_strategy_comparison'] ?? false, FILTER_VALIDATE_BOOL);
        $automaticExitSummary = $automaticComparison ? $dynamicExits->compareAll($this->runId) : [];
        $dynamicExitSummary = $dynamicExits->apply($this->runId, [
            'fixed_20d' => ($this->filters['exit_strategy'] ?? 'fixed_20d') === 'fixed_20d',
            'dynamic_horizon' => filter_var($this->filters['dynamic_horizon_exit_enabled'] ?? false, FILTER_VALIDATE_BOOL),
            'support_stop' => filter_var($this->filters['support_stop_enabled'] ?? false, FILTER_VALIDATE_BOOL),
            'resistance_trailing_stop' => filter_var($this->filters['resistance_trailing_stop_enabled'] ?? false, FILTER_VALIDATE_BOOL),
            'entry_wait_5d' => filter_var($this->filters['entry_wait_5d_enabled'] ?? false, FILTER_VALIDATE_BOOL),
            'signal_change_exit' => ($this->filters['exit_strategy'] ?? 'fixed_20d') === 'signal_change',
            'forecast_below_price_exit' => ($this->filters['exit_strategy'] ?? 'fixed_20d') === 'forecast_below_price',
        ]);
        if (isset($dynamicExitSummary['rules'])) {
            $run = DB::table('backtest_runs')->where('id', $this->runId)->first(['settings']);
            $settings = is_string($run?->settings) ? (json_decode($run->settings, true) ?: []) : (array) ($run?->settings ?? []);
            foreach ([
                'fixed_20d_exit_enabled' => 'fixed_20d',
                'dynamic_horizon_exit_enabled' => 'dynamic_horizon',
                'support_stop_enabled' => 'support_stop',
                'resistance_trailing_stop_enabled' => 'resistance_trailing_stop',
                'entry_wait_5d_enabled' => 'entry_wait_5d',
                'signal_change_exit_enabled' => 'signal_change_exit',
                'forecast_below_price_exit_enabled' => 'forecast_below_price_exit',
            ] as $filterKey => $ruleKey) data_set($settings, 'selection_filters.'.$filterKey, ! empty($dynamicExitSummary['rules'][$ruleKey]) ? 1 : 0);
            data_set($settings, 'optimization.dynamic_exit', $dynamicExitSummary);
            DB::table('backtest_runs')->where('id', $this->runId)->update(['settings' => json_encode($settings, JSON_THROW_ON_ERROR), 'updated_at' => now()]);
        }
        $scoreRotationSummary = $scoreRotation->apply(
            $this->runId,
            $this->maxPositions(),
            $automaticComparison || filter_var($this->filters['forecast_score_rotation_5d_enabled'] ?? false, FILTER_VALIDATE_BOOL),
            false,
            false,
            in_array($this->filters['strategy_priority'] ?? null, ['rotation_first', 'exit_first'], true)
                ? $this->filters['strategy_priority'] : 'rotation_first',
        );
        $areaRotationSummary = $areaRotations->apply(
            $this->runId,
            $automaticComparison || filter_var($this->filters['sector_score_rotation'] ?? false, FILTER_VALIDATE_BOOL),
            $automaticComparison || filter_var($this->filters['index_score_rotation'] ?? false, FILTER_VALIDATE_BOOL),
            $riskStyle,
        );
        if ($this->isCancelled()) {
            $this->clearCancellationMarker();
            return;
        }

        $summary = DB::table('backtest_trades')->where('backtest_run_id', $this->runId)
            ->selectRaw('COUNT(*) AS trades')
            ->selectRaw('COUNT(DISTINCT instrument_id) AS instruments')
            ->selectRaw('AVG(net_return) * 100 AS average_return')
            ->selectRaw('AVG(CASE WHEN net_return > 0 THEN 1.0 ELSE 0.0 END) * 100 AS hit_rate')
            ->selectRaw('SUM(CASE WHEN net_return > 0 THEN net_return ELSE 0 END) / NULLIF(ABS(SUM(CASE WHEN net_return < 0 THEN net_return ELSE 0 END)), 0) AS profit_factor')
            ->selectRaw('MAX(ABS(max_drawdown)) * 100 AS max_drawdown')
            ->first();

        $completed = DB::table('backtest_runs')->where('id', $this->runId)->where('status', 'running')->update([
            'status' => 'completed',
            'finished_at' => now(),
            'instruments_total' => (int) ($summary->instruments ?? 0),
            'instruments_completed' => (int) ($summary->instruments ?? 0),
            'trades_count' => (int) ($summary->trades ?? 0),
            'summary' => json_encode([
                ...(array) $summary,
                'candidate_trades' => $candidates->count(),
                'initial_capital' => $initialCapital,
                'position_capital' => $positionCapital,
                'position_factor' => $this->positionFactor(),
                'max_parallel_positions' => $this->maxPositions(),
                'trade_cost_eur' => $tradeCost,
                'total_costs' => round($rows->count() * $tradeCost, 2),
                'exit_strategies' => $automaticComparison
                    ? [
                        'fixed_20d',
                        'adaptive_rotation_20d',
                        ...array_values(array_filter(
                            array_keys(HistoricalDynamicExitService::AUTOMATIC_VARIANTS),
                            fn (string $strategy): bool => str_starts_with($strategy, 'auto_exit_'),
                        )),
                    ]
                    : ['fixed_20d', 'adaptive_rotation_20d'],
                'entry_selection_profile' => $riskStyle,
                'automatic_strategy_comparison' => $automaticComparison,
                'automatic_exit_comparison_summary' => $automaticExitSummary,
                'dynamic_exit_summary' => $dynamicExitSummary,
                'forecast_score_rotation_summary' => $scoreRotationSummary,
                'area_entry_rotation_summary' => $areaRotationSummary,
                'indicator_matrix_summary' => $indicatorMatrixSummary,
            ], JSON_THROW_ON_ERROR),
            'updated_at' => now(),
        ]);

        if ($completed === 0) {
            return;
        }

        $this->ensureBenchmarkHistory($marketData, $fallbackMarketData);
    }

    private function compareSelectionProfile(object $left, object $right, string $profile): int
    {
        $drawdown = fn (object $trade): float => (float) ($trade->selection_drawdown ?? 0);
        $hitRate = fn (object $trade): float => (float) ($trade->selection_hit_rate ?? 0);
        $profitFactor = fn (object $trade): float => (float) ($trade->selection_profit_factor ?? 0);

        return match ($profile) {
            'conservative' => ($drawdown($left) <=> $drawdown($right))
                ?: ($hitRate($right) <=> $hitRate($left))
                ?: ($profitFactor($right) <=> $profitFactor($left)),
            'chance' => ($profitFactor($right) <=> $profitFactor($left))
                ?: ($hitRate($right) <=> $hitRate($left))
                ?: ($drawdown($left) <=> $drawdown($right)),
            default => ($hitRate($right) <=> $hitRate($left))
                ?: ($profitFactor($right) <=> $profitFactor($left))
                ?: ($drawdown($left) <=> $drawdown($right)),
        };
    }

    public function failed(Throwable $exception): void
    {
        DB::table('backtest_runs')
            ->where('id', $this->runId)
            ->where('status', '<>', 'cancelled')
            ->update([
            'status' => 'failed',
            'finished_at' => now(),
            'error_message' => mb_substr($exception->getMessage(), 0, 4000),
            'updated_at' => now(),
        ]);
    }

    private function applyFilters(Builder $query, callable $fundamentalNumber): void
    {
        $filter = fn (string $key, mixed $default = null): mixed => $this->filters[$key] ?? $default;
        if (trim((string) $filter('q')) !== '') {
            $term = '%'.strtolower(trim((string) $filter('q'))).'%';
            $query->where(fn (Builder $query) => $query
                ->whereRaw('LOWER(instrument.symbol) LIKE ?', [$term])
                ->orWhereRaw('LOWER(instrument.name) LIKE ?', [$term]));
        }
        if ($filter('country')) $query->where('instrument.country', strtoupper(trim((string) $filter('country'))));
        if ($filter('sector')) $query->where('instrument.sector', trim((string) $filter('sector')));
        if ($filter('exchange')) $query->where('exchange.code', strtoupper(trim((string) $filter('exchange'))));
        if (in_array($filter('ai_type'), ['horizon', 'pulse'], true)) $query->where('trade.ai_type', $filter('ai_type'));
        $modelIds = collect(is_array($filter('model')) ? $filter('model') : [$filter('model')])
            ->filter(fn ($id) => is_numeric($id) && (int) $id > 0)
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();
        if ($modelIds !== []) $query->whereIn('trade.model_definition_id', $modelIds);
        $minimumQualityTiers = [
            'top' => ['strong'],
            'strong' => ['strong'],
            'solid' => ['strong', 'solid'],
            'test' => ['strong', 'solid', 'test'],
        ];
        if (array_key_exists((string) $filter('quality_tier'), $minimumQualityTiers)) {
            $query->whereIn('quality_tier.code', $minimumQualityTiers[(string) $filter('quality_tier')]);
        }
        if ($filter('quality_tier') === 'unqualified') $query->whereNull('quality_tier.code');
        if (in_array(strtoupper((string) $filter('signal')), ['BUY', 'WAIT', 'WATCH', 'HOLD', 'SELL'], true)) $query->where('trade.signal', strtoupper((string) $filter('signal')));
        if (is_numeric($filter('score_min'))) $query->where('trade.ki_score', '>=', max(0, min(10, (float) $filter('score_min'))));
        if (is_numeric($filter('confidence_min'))) $query->where('trade.confidence', '>=', max(0, min(100, (float) $filter('confidence_min'))));
        if (is_numeric($filter('risk_max')) && (float) $filter('risk_max') < 100) {
            $query->whereRaw('ABS(COALESCE(trade.max_drawdown, 0)) <= ?', [max(0, (float) $filter('risk_max')) / 100]);
        }
        $minimumReturn = is_numeric($filter('predicted_return_min')) ? (float) $filter('predicted_return_min') : null;
        if ($minimumReturn !== null) $query->where('trade.predicted_return', '>=', $minimumReturn / 100);
        elseif ((bool) $filter('positive_prediction_required', false)) $query->where('trade.predicted_return', '>', 0);
        if (is_numeric($filter('volatility_max')) && (float) $filter('volatility_max') < 100) $query->where('technical.volatility_20', '<=', max(0, (float) $filter('volatility_max')) / 100);
        if (is_numeric($filter('pe_max')) && (float) $filter('pe_max') < 100) $query->whereRaw($fundamentalNumber('trailingPE').' <= ?', [(float) $filter('pe_max')]);
        if (is_numeric($filter('dividend_yield_min')) && ($filter('dividend_yield_operator', 'gte') === 'lte' || (float) $filter('dividend_yield_min') > 0)) {
            $operator = $filter('dividend_yield_operator', 'gte') === 'lte' ? '<=' : '>=';
            $query->whereRaw($fundamentalNumber('dividendYield').' '.$operator.' ?', [(float) $filter('dividend_yield_min') / 100]);
        }
        if (is_numeric($filter('market_cap_min')) && (float) $filter('market_cap_min') > 0) $query->whereRaw($fundamentalNumber('marketCap').' >= ?', [(float) $filter('market_cap_min') * 1_000_000_000]);
        if (in_array($filter('market_cap_group'), ['small', 'mid', 'large'], true)) {
            $value = $fundamentalNumber('marketCap');
            match ($filter('market_cap_group')) {
                'small' => $query->whereRaw($value.' < ?', [2_000_000_000]),
                'mid' => $query->whereRaw($value.' >= ? AND '.$value.' < ?', [2_000_000_000, 10_000_000_000]),
                'large' => $query->whereRaw($value.' >= ?', [10_000_000_000]),
            };
        }
        if (is_numeric($filter('revenue_growth_min')) && (float) $filter('revenue_growth_min') > -50) $query->whereRaw($fundamentalNumber('revenueGrowth').' >= ?', [(float) $filter('revenue_growth_min') / 100]);
    }

    private function capitalConstrainedTrades($candidates): array
    {
        $cash = $this->initialCapital();
        $positionCapital = $this->positionCapital();
        $maxPositions = $this->maxPositions();
        $openPositions = [];
        $executed = collect();

        foreach ($candidates as $trade) {
            $entryDate = (string) $trade->entry_date;
            foreach ($openPositions as $key => $position) {
                if ($position['exit_date'] > $entryDate) continue;
                $cash += $position['capital'] * (1 + $position['return']);
                unset($openPositions[$key]);
            }
            if (count($openPositions) >= $maxPositions || $cash + 0.00001 < $positionCapital) continue;

            $cash -= $positionCapital;
            $openPositions[] = [
                'exit_date' => (string) $trade->exit_date,
                'capital' => $positionCapital,
                'return' => $this->netReturn($trade),
            ];
            $executed->push($trade);
        }
        foreach ($openPositions as $position) {
            $cash += $position['capital'] * (1 + $position['return']);
        }

        return [$executed, ['cash' => $cash]];
    }

    private function calculateExitStrategies(): bool
    {
        $enginePath = rtrim((string) config('aktienki.python_engine.path', '/Users/silviotaubert/Downloads/python-engine'), '/');
        // The fixed 20-trading-day exit is already present on the copied
        // source trades.  The optional dynamic-exit model is not guaranteed
        // to be installed on every host (e.g. after moving models to the
        // Mac mini); in that case keep the valid fixed-exit result instead of
        // failing the whole strategy test.
        $dynamicExitModel = $enginePath.'/models_storage/exit/global/horizon_entry_exit_model.pkl';
        if (! File::exists($dynamicExitModel)) {
            return true;
        }
        $python = (string) (config('aktienki.python_engine.executable') ?: $enginePath.'/.venv/bin/python');
        $process = new Process([
            $python,
            base_path('scripts/calculate_exit_strategies.py'),
            '--run-id',
            (string) $this->runId,
        ], base_path(), [
            'AKTIENKI_PYTHON_ENGINE_PATH' => $enginePath,
        ]);
        $process->setTimeout(1200);
        $process->start();
        while ($process->isRunning()) {
            if ($this->isCancelled()) {
                $process->stop(3);
                return false;
            }
            usleep(750000);
        }
        if (! $process->isSuccessful()) {
            throw new RuntimeException(trim($process->getErrorOutput() ?: $process->getOutput()));
        }

        return true;
    }

    private function isCancelled(): bool
    {
        if (File::exists($this->cancellationMarker())) {
            return true;
        }

        return in_array(DB::table('backtest_runs')->where('id', $this->runId)->value('status'), ['cancelled', 'failed'], true);
    }

    private function cancellationMarker(): string
    {
        return storage_path('app/backtest-cancellations/'.$this->runId);
    }

    private function clearCancellationMarker(): void
    {
        File::delete($this->cancellationMarker());
    }

    private function initialCapital(): float
    {
        return max(1000.0, min(1000000.0, (float) ($this->filters['initial_capital'] ?? 10000)));
    }

    private function maxPositions(): int
    {
        return max(1, min(50, (int) ($this->filters['max_positions'] ?? 5)));
    }

    private function positionCapital(): float
    {
        return ($this->initialCapital() / $this->maxPositions()) * $this->positionFactor();
    }

    private function positionFactor(): int
    {
        return max(1, min($this->maxPositions(), (int) ($this->filters['position_factor'] ?? 1)));
    }

    private function tradeCost(): float
    {
        return max(0.0, min(1000.0, (float) ($this->filters['trade_cost'] ?? 10)));
    }

    private function netReturn(object $trade): float
    {
        return (float) $trade->gross_return - ($this->tradeCost() / $this->positionCapital());
    }

    private function ensureBenchmarkHistory(TwelveDataService $marketData, YahooIndexService $fallbackMarketData): void
    {
        try {
            $instrumentId = DB::table('instruments')->where('symbol', '^GSPC')->value('id');
            if (! $instrumentId) return;
            $firstBar = DB::table('price_bars')
                ->where('instrument_id', $instrumentId)
                ->where('interval', '1d')
                ->min('bar_time');
            if ($firstBar !== null && strtotime((string) $firstBar) <= now()->subYears(3)->timestamp) return;

            $history = $marketData->dailyHistory('^GSPC', 820);
            $source = 'twelve_data';
            if ($history === []) {
                $history = $fallbackMarketData->dailyHistory('^GSPC', '3y');
                $source = 'yahoo_index_rest';
            }
            $rows = collect($history)->map(fn (array $bar): array => [
                'instrument_id' => $instrumentId,
                'interval' => '1d',
                'bar_time' => date('Y-m-d H:i:sP', (int) $bar['timestamp']),
                'open' => $bar['open'],
                'high' => $bar['high'],
                'low' => $bar['low'],
                'close' => $bar['close'],
                'adjusted_close' => $bar['adjusted_close'],
                'volume' => $bar['volume'],
                'source' => $source,
                'created_at' => now(),
                'updated_at' => now(),
            ])->all();
            foreach (array_chunk($rows, 500) as $chunk) {
                DB::table('price_bars')->upsert($chunk, ['instrument_id', 'interval', 'bar_time'], [
                    'open', 'high', 'low', 'close', 'adjusted_close', 'volume', 'source', 'updated_at',
                ]);
            }
        } catch (Throwable) {
            // The strategy result remains valid if the external benchmark is temporarily unavailable.
        }
    }
}
