<?php

namespace App\Jobs;

use App\Services\TwelveDataService;
use App\Services\YahooIndexService;
use App\Services\HistoricalDynamicExitService;
use App\Services\HistoricalForecastScoreRotationService;
use App\Services\HistoricalAreaEntryRotationService;
use App\Services\HistoricalIndicatorMatrixService;
use App\Services\HistoricalIndicatorProbabilityService;
use App\Services\HistoricalPortfolioExecutionCalculator;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
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
        [$periodStart, $periodEnd] = $this->periodBounds();
        DB::table('backtest_runs')->where('id', $this->runId)->update([
            'status' => 'running',
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
            ->where('trade.entry_date', '>=', $periodStart)
            ->where('trade.exit_date', '<=', $periodEnd)
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

        $usesServingConfigurations = $this->usesServingConfigurations();
        $drawdownMaximum = is_numeric($this->filters['drawdown_max'] ?? null)
            ? (float) $this->filters['drawdown_max']
            : 50.0;
        // Keep this criterion identical to HistoricalPortfolioSimulator: the
        // stored key is legacy, but its value means average profit per trade
        // in percent (not the dimensionless profit factor).
        $profitPerTradeMinimum = is_numeric($this->filters['profit_per_trade_min'] ?? null)
            ? (float) $this->filters['profit_per_trade_min']
            : 0.0;
        $medianReturnMinimum = is_numeric($this->filters['median_return_min'] ?? null)
            ? (float) $this->filters['median_return_min']
            : null;
        $hitRateMinimum = is_numeric($this->filters['hit_rate_min'] ?? null)
            ? (float) $this->filters['hit_rate_min']
            : 0.0;
        $minimumTrades = is_numeric($this->filters['minimum_trades'] ?? null)
            ? max(1, (int) $this->filters['minimum_trades'])
            : 1;
        if (! $usesServingConfigurations && ($drawdownMaximum < 50 || $profitPerTradeMinimum > 0 || $medianReturnMinimum !== null || $hitRateMinimum > 0 || $minimumTrades > 1)) {
            $eligibleInstruments = DB::table('backtest_trades as eligible_trade')
                ->where('eligible_trade.backtest_run_id', $this->sourceRunId)
                ->where('eligible_trade.entry_date', '>=', $periodStart)
                ->where('eligible_trade.exit_date', '<=', $periodEnd)
                ->groupBy('eligible_trade.instrument_id')
                ->select('eligible_trade.instrument_id')
                ->when($drawdownMaximum < 50, fn (Builder $query) =>
                    $query->havingRaw('MAX(ABS(eligible_trade.max_drawdown)) <= ?', [max(0, $drawdownMaximum) / 100]))
                ->when($profitPerTradeMinimum > 0, fn (Builder $query) =>
                    $query->havingRaw(
                        'AVG(eligible_trade.net_return) * 100 >= ?',
                        [$profitPerTradeMinimum],
                    ))
                ->when($medianReturnMinimum !== null, fn (Builder $query) =>
                    $query->havingRaw(
                        'PERCENTILE_CONT(0.5) WITHIN GROUP (ORDER BY eligible_trade.net_return) * 100 >= ?',
                        [$medianReturnMinimum],
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

        $candidates = $usesServingConfigurations
            ? $this->servingStrategyCandidates($periodStart, $periodEnd)
            : $query->select(
                'trade.*',
                'technical.volatility_20 as entry_volatility',
                'model_quality.quality_score as model_quality_score',
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
        if ($usesServingConfigurations && $candidates->isEmpty()) {
            throw new RuntimeException(
                'Die Service-Auswahl enthält Modelle, aber keine ausführbaren historischen Trades.',
            );
        }
        $heatmapSelections = json_decode((string) ($this->filters['heatmap_selection'] ?? ''), true);
        $heatmapSelections = is_array($heatmapSelections) ? $heatmapSelections : [];
        $profitFactorMinimum = max(0.0, min(3.0, (float) ($this->filters['profit_factor_min'] ?? 0)));
        $signalQualityMinimum = max(0.0, min(100.0, (float) ($this->filters['signal_quality_min'] ?? 0)));
        if (! $usesServingConfigurations && (collect($heatmapSelections)->flatten()->isNotEmpty() || $profitFactorMinimum > 0 || $medianReturnMinimum !== null || $signalQualityMinimum > 0) && $candidates->isNotEmpty()) {
            $cellFor = static function (string $map, object $stats): ?string {
                [$x, $y] = match ($map) {
                    'profit_factor_hit_rate' => [$stats->profit_factor, $stats->hit_rate],
                    'signal_risk' => [$stats->signal_quality, $stats->risk],
                    'volatility_drawdown' => [$stats->model_quality, $stats->risk],
                    'trades_return' => [$stats->confidence, $stats->average_return],
                    default => [null, null],
                };
                if (! is_numeric($x) || ! is_numeric($y)) return null;
                [$xMin, $xMax, $xStep, $yMin, $yMax, $yStep] = match ($map) {
                    'profit_factor_hit_rate' => [0, 3, .3, 0, 100, 10],
                    'signal_risk' => [0, 100, 10, 0, 50, 5],
                    'volatility_drawdown' => [0, 100, 10, 0, 50, 5],
                    'trades_return' => [0, 100, 10, -5, 15, 2],
                    default => [0, 1, 1, 0, 1, 1],
                };
                $xBucket = (int) max(0, min(9, floor((max($xMin, min($xMax, (float) $x)) - $xMin) / $xStep)));
                $yBucket = (int) max(0, min(9, floor((max($yMin, min($yMax, (float) $y)) - $yMin) / $yStep)));

                return $xBucket.'-'.$yBucket;
            };
            $configurationKey = static fn (object $row): string => implode('|', [
                (int) $row->instrument_id,
                (int) ($row->trained_model_id ?? 0),
                (int) ($row->model_definition_id ?? 0),
                (int) ($row->horizon_days ?? 0),
            ]);
            $allowedConfigurationKeys = $candidates->groupBy($configurationKey)->filter(function ($rows) use ($heatmapSelections, $cellFor, $profitFactorMinimum, $medianReturnMinimum, $signalQualityMinimum): bool {
                $wins = $rows->filter(fn (object $row): bool => (float) $row->net_return > 0)->count();
                $positive = (float) $rows->sum(fn (object $row): float => max(0, (float) $row->net_return));
                $negative = abs((float) $rows->sum(fn (object $row): float => min(0, (float) $row->net_return)));
                $stats = (object) [
                    'profit_factor' => min(3.0, $negative > 0 ? $positive / $negative : 3.0),
                    'hit_rate' => $rows->count() ? $wins / $rows->count() * 100 : 0,
                    'signal_quality' => (float) $rows->avg('signal_quality_score'),
                    'risk' => min(50.0, (float) $rows->avg(fn (object $row): float => abs((float) $row->max_drawdown) * 100)),
                    'volatility' => (float) $rows->avg(fn (object $row): float => (float) $row->entry_volatility * 100),
                    'model_quality' => (float) $rows->avg(fn (object $row): float => (float) $row->model_quality_score * 100),
                    'confidence' => (float) $rows->avg('confidence'),
                    'trades_per_year' => $rows->count() / 3,
                    'average_return' => (float) $rows->avg(fn (object $row): float => (float) $row->net_return * 100),
                    'median_return' => (float) ($rows->pluck('net_return')->median() ?? 0) * 100,
                ];
                foreach ($heatmapSelections as $map => $selectedCells) {
                    if (! is_array($selectedCells) || $selectedCells === []) continue;
                    if (in_array($cellFor((string) $map, $stats), $selectedCells, true)) return false;
                }
                if ($stats->profit_factor < $profitFactorMinimum || $stats->signal_quality < $signalQualityMinimum) return false;
                if ($medianReturnMinimum !== null && $stats->median_return < $medianReturnMinimum) return false;

                return true;
            })->keys();
            $candidates = $candidates->filter(fn (object $row): bool => $allowedConfigurationKeys->contains($configurationKey($row)))->values();
        }
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
        if (filter_var($this->filters['combined_area_forecast_priority'] ?? false, FILTER_VALIDATE_BOOL) && $candidates->isNotEmpty()) {
            $stockWeight = (float) ($this->filters['stock_forecast_weight'] ?? .20);
            $sectorWeight = (float) ($this->filters['sector_forecast_weight'] ?? .30);
            $indexWeight = (float) ($this->filters['index_forecast_weight'] ?? .50);
            $memberships = DB::table('index_memberships')->whereNull('removed_at')
                ->whereIn('instrument_id', $candidates->pluck('instrument_id')->unique())
                ->get(['instrument_id', 'market_index_id'])->groupBy('instrument_id');
            $candidates = $candidates->groupBy('entry_date')->flatMap(function (Collection $daily) use ($stockWeight, $sectorWeight, $indexWeight, $memberships): Collection {
                $minimum = (float) $daily->min('predicted_return');
                $maximum = (float) $daily->max('predicted_return');
                $range = max(.000001, $maximum - $minimum);
                $normalise = static fn (float $value): float => ($value - $minimum) / $range * 10;
                $sectorForecasts = $daily->filter(fn (object $row): bool => filled($row->rotation_sector))
                    ->groupBy('rotation_sector')->map(fn (Collection $rows): float => (float) $rows->avg('predicted_return'));
                $byInstrument = $daily->keyBy('instrument_id');
                $indexForecasts = $memberships->flatten(1)->groupBy('market_index_id')->map(function (Collection $rows) use ($byInstrument): float {
                    return (float) $rows->map(fn (object $membership): ?float => is_numeric($byInstrument->get($membership->instrument_id)?->predicted_return)
                        ? (float) $byInstrument->get($membership->instrument_id)->predicted_return : null)->filter(fn (?float $value): bool => $value !== null)->avg();
                });

                return $daily->each(function (object $row) use ($stockWeight, $sectorWeight, $indexWeight, $memberships, $sectorForecasts, $indexForecasts, $normalise, $minimum): void {
                    $sectorForecast = (float) $sectorForecasts->get((string) ($row->rotation_sector ?? ''), $minimum);
                    $indexForecast = (float) collect($memberships->get($row->instrument_id, collect()))
                        ->map(fn (object $membership): float => (float) $indexForecasts->get($membership->market_index_id, $minimum))->max();
                    $row->combined_area_forecast_score = ($stockWeight * $normalise((float) $row->predicted_return))
                        + ($sectorWeight * $normalise($sectorForecast)) + ($indexWeight * $normalise($indexForecast));
                });
            })->values();
        }
        // Rank simultaneous candidates only with information available on the
        // signal day. Realized hit rate, drawdown or profit factor would make
        // the execution order look ahead into the result period.
        $candidates = $candidates->sort(fn (object $left, object $right): int => strcmp((string) $left->entry_date, (string) $right->entry_date)
            ?: ((float) ($right->combined_area_forecast_score ?? 0) <=> (float) ($left->combined_area_forecast_score ?? 0))
            ?: ((float) ($right->serving_entry_score ?? 0) <=> (float) ($left->serving_entry_score ?? 0))
            ?: ((float) $right->predicted_return <=> (float) $left->predicted_return)
            ?: ((int) $left->id <=> (int) $right->id))->values();
        [$rows, $executionSummary] = $this->capitalConstrainedTrades($candidates);
        $rows->values()->each(function (object $trade, int $index): void {
            $trade->execution_rank = $index;
        });
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
                $allocatedCapital = max(0.01, (float) ($row['allocated_capital_eur'] ?? $positionCapital));
                $quantity = (int) ($row['quantity'] ?? 0);
                $entryDebit = (float) ($row['entry_debit'] ?? ($allocatedCapital + $tradeCost));
                $exitCredit = (float) ($row['exit_credit'] ?? 0);
                $profit = (float) ($row['profit_eur'] ?? ($exitCredit - $entryDebit));
                $netReturn = (float) ($row['net_return_after_cost'] ?? ($entryDebit > 0 ? $profit / $entryDebit : 0));
                $transactionCostReturn = (float) ($row['transaction_cost_return'] ?? ((float) $trade->gross_return - $netReturn));
                $executionRank = (int) ($row['execution_rank'] ?? 0);
                unset($row['id']);
                unset(
                    $row['rotation_sector'],
                    $row['entry_volatility'],
                    $row['model_quality_score'],
                    $row['combined_area_forecast_score'],
                    $row['serving_entry_score'],
                    $row['allocated_capital_eur'],
                    $row['quantity'],
                    $row['entry_debit'],
                    $row['exit_credit'],
                    $row['profit_eur'],
                    $row['net_return_after_cost'],
                    $row['transaction_cost_return'],
                    $row['effective_target_position_budget'],
                    $row['dynamic_capital_factor'],
                    $row['execution_rank'],
                );
                $indicatorProbability = is_numeric($row['indicator_probability'] ?? null)
                    ? (float) $row['indicator_probability']
                    : null;
                unset($row['indicator_probability']);
                $sourceCurrency = strtoupper((string) ($row['source_currency'] ?? 'EUR'));
                $eurListingSymbol = $row['eur_listing_symbol'] ?? null;
                $eurListingExchange = $row['eur_listing_exchange'] ?? null;
                unset($row['source_currency'], $row['eur_listing_symbol'], $row['eur_listing_exchange']);
                $row['backtest_run_id'] = $this->runId;
                $row['transaction_cost'] = $transactionCostReturn;
                $row['net_return'] = $netReturn;
                $metadata = is_string($row['metadata'] ?? null)
                    ? (json_decode($row['metadata'], true) ?: [])
                    : (array) ($row['metadata'] ?? []);
                $row['metadata'] = json_encode([
                    ...$metadata,
                    'allocated_capital' => $allocatedCapital,
                    'allocated_capital_eur' => $allocatedCapital,
                    'quantity' => $quantity,
                    'trade_cost_eur' => $tradeCost,
                    'entry_value_eur' => $allocatedCapital,
                    'entry_debit_eur' => $entryDebit,
                    'exit_value_eur' => $exitCredit,
                    'exit_credit_eur' => $exitCredit,
                    'profit_eur' => $profit,
                    'net_return_basis' => 'profit_over_entry_debit',
                    'execution_rank' => $executionRank,
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
        $preserveServingFixedHorizon = $usesServingConfigurations
            && ($this->filters['exit_strategy'] ?? 'fixed_20d') === 'fixed_20d'
            && ! filter_var($this->filters['dynamic_horizon_exit_enabled'] ?? false, FILTER_VALIDATE_BOOL)
            && ! filter_var($this->filters['support_stop_enabled'] ?? false, FILTER_VALIDATE_BOOL)
            && ! filter_var($this->filters['resistance_trailing_stop_enabled'] ?? false, FILTER_VALIDATE_BOOL)
            && ! filter_var($this->filters['entry_wait_5d_enabled'] ?? false, FILTER_VALIDATE_BOOL);
        $dynamicExitSummary = $preserveServingFixedHorizon
            ? [
                'trades' => $rows->count(),
                'changed' => 0,
                'policy' => 'selected_serving_model_horizon',
            ]
            : $dynamicExits->apply($this->runId, [
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
        $positiveProfit = (float) $rows->sum(fn (object $trade): float => max(0.0, (float) ($trade->profit_eur ?? 0)));
        $negativeProfit = abs((float) $rows->sum(fn (object $trade): float => min(0.0, (float) ($trade->profit_eur ?? 0))));
        $summary->profit_factor = $negativeProfit > 0 ? $positiveProfit / $negativeProfit : ($positiveProfit > 0 ? null : 0.0);

        $completed = DB::table('backtest_runs')->where('id', $this->runId)->where('status', 'running')->update([
            'status' => 'completed',
            'finished_at' => now(),
            'instruments_total' => (int) ($summary->instruments ?? 0),
            'instruments_completed' => (int) ($summary->instruments ?? 0),
            'trades_count' => (int) ($summary->trades ?? 0),
            'summary' => json_encode([
                ...(array) $summary,
                'candidate_trades' => $candidates->count(),
                'candidate_source' => $usesServingConfigurations ? 'service_database' : 'legacy_backtest',
                'selected_service_configurations' => $usesServingConfigurations
                    ? count($this->filters['serving_model_configurations'])
                    : 0,
                'executed_non_overlapping_trades' => $rows->count(),
                'excluded_same_instrument_overlap' => $executionSummary['excluded_same_instrument_overlap'],
                'excluded_capacity_or_cash' => $executionSummary['excluded_capacity_or_cash'],
                'excluded_invalid_holding_period' => $executionSummary['excluded_invalid_holding_period'],
                'excluded_invalid_price' => $executionSummary['excluded_invalid_price'],
                'excluded_invalid_gross_return' => $executionSummary['excluded_invalid_gross_return'],
                'final_cash' => $executionSummary['cash'],
                'portfolio_max_drawdown' => round($executionSummary['max_drawdown_percent'], 2),
                'overlap_policy' => 'one_position_per_instrument_held_through_horizon_exit_date',
                'same_exit_date_reentry_allowed' => false,
                'initial_capital' => $initialCapital,
                'position_capital' => $positionCapital,
                'position_factor' => $this->positionFactor(),
                'dynamic_capital_weighting' => $this->dynamicCapitalWeighting(),
                'max_parallel_positions' => $this->maxPositions(),
                'trade_cost_eur' => $tradeCost,
                'total_costs' => round($executionSummary['total_costs'], 2),
                'calculation_version' => 'historical-portfolio-ledger-v1',
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
        $servingConfigurations = $filter('serving_model_configurations');
        $usesServingConfigurations = $this->usesServingConfigurations();
        if ($usesServingConfigurations) {
            $pairs = collect($servingConfigurations)
                ->filter(fn ($row): bool => is_array($row) && filled($row['symbol'] ?? null) && is_numeric($row['horizon'] ?? null))
                ->map(fn (array $row): array => [
                    'symbol' => (string) $row['symbol'],
                    'horizon' => (int) $row['horizon'],
                ])->unique(fn (array $row): string => $row['symbol'].'|'.$row['horizon'])->values();
            if ($pairs->isEmpty()) {
                $query->whereRaw('1 = 0');
            } else {
                $query->where(function (Builder $nested) use ($pairs): void {
                    foreach ($pairs as $pair) {
                        $nested->orWhere(fn (Builder $configuration) => $configuration
                            ->where('instrument.symbol', $pair['symbol'])
                            ->where('trade.horizon_days', $pair['horizon']));
                    }
                });
            }
        }
        if (! $usesServingConfigurations && is_numeric($filter('model_quality_min')) && (float) $filter('model_quality_min') > 0) {
            $query->whereRaw('COALESCE(model_quality.quality_score, 0) * 100 >= ?', [max(0, min(100, (float) $filter('model_quality_min')))]);
        }
        $servingQualitySymbols = $filter('serving_quality_symbols');
        if (! $usesServingConfigurations && is_array($servingQualitySymbols)) {
            $query->whereIn('instrument.symbol', $servingQualitySymbols);
        }
        $minimumQualityTiers = [
            'top' => ['strong'],
            'strong' => ['strong'],
            'solid' => ['strong', 'solid'],
            'test' => ['strong', 'solid', 'test'],
        ];
        if (! is_array($servingQualitySymbols) && array_key_exists((string) $filter('quality_tier'), $minimumQualityTiers)) {
            $query->whereIn('quality_tier.code', $minimumQualityTiers[(string) $filter('quality_tier')]);
        }
        if (! is_array($servingQualitySymbols) && $filter('quality_tier') === 'unqualified') $query->whereNull('quality_tier.code');
        if (in_array(strtoupper((string) $filter('signal')), ['BUY', 'WAIT', 'WATCH', 'HOLD', 'SELL'], true)) $query->where('trade.signal', strtoupper((string) $filter('signal')));
        if (is_numeric($filter('score_min'))) $query->where('trade.ki_score', '>=', max(0, min(10, (float) $filter('score_min'))));
        if (! $usesServingConfigurations && is_numeric($filter('confidence_min'))) $query->where('trade.confidence', '>=', max(0, min(100, (float) $filter('confidence_min'))));
        if (! $usesServingConfigurations && is_numeric($filter('risk_max')) && (float) $filter('risk_max') < 100) {
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

    private function usesServingConfigurations(): bool
    {
        $configurations = $this->filters['serving_model_configurations'] ?? null;

        return is_array($configurations) && $configurations !== [];
    }

    private function capitalConstrainedTrades($candidates): array
    {
        $result = app(HistoricalPortfolioExecutionCalculator::class)->calculate(
            $candidates,
            $this->initialCapital(),
            $this->maxPositions(),
            $this->positionCapital(),
            $this->tradeCost(),
            $this->dynamicCapitalWeighting(),
        );
        $executed = collect($result['trade_log'])->map(static fn (array $trade): object => (object) $trade);

        return [$executed, [
            'cash' => (float) $result['final_cash'],
            'total_costs' => (float) $result['total_costs'],
            'max_drawdown_percent' => (float) $result['max_drawdown_percent'],
            'excluded_same_instrument_overlap' => (int) $result['skipped_due_same_instrument_open'],
            'excluded_capacity_or_cash' => (int) $result['skipped_due_capacity'] + (int) $result['skipped_due_cash'],
            'excluded_invalid_holding_period' => (int) $result['skipped_due_invalid_date'],
            'excluded_invalid_price' => (int) $result['skipped_due_invalid_price'],
            'excluded_invalid_gross_return' => (int) $result['skipped_due_invalid_gross_return'],
        ]];
    }

    /**
     * Historical trades belonging to the exact active Service-DB releases
     * selected by the heatmaps. Prices are already normalized to EUR there.
     */
    private function servingStrategyCandidates(string $periodStart, string $periodEnd): Collection
    {
        $configurations = collect($this->filters['serving_model_configurations'] ?? [])
            ->filter(fn ($row): bool => is_array($row)
                && filled($row['symbol'] ?? null)
                && filled($row['release_id'] ?? null)
                && is_numeric($row['horizon'] ?? null)
                && filled($row['variant'] ?? null))
            ->map(static fn (array $row): array => [
                'symbol' => strtoupper(trim((string) $row['symbol'])),
                'release_id' => (string) $row['release_id'],
                'horizon' => (int) $row['horizon'],
                'variant' => (string) $row['variant'],
            ])
            ->unique(static fn (array $row): string => implode('|', $row))
            ->values();
        if ($configurations->isEmpty()) return collect();

        $allowed = $configurations->mapWithKeys(fn (array $row): array => [
            implode('|', [$row['release_id'], $row['symbol'], $row['horizon'], $row['variant']]) => true,
        ]);
        // Queue workers are long-lived. Never reuse a serving PDO connection
        // that may still point at an earlier tunnel/database target from when
        // the worker was started. Each strategy run must read the canonical
        // Service DB configured at execution time.
        DB::purge('serving');
        $serving = DB::connection('serving');
        $runs = $serving->table('serving_strategy_runs')
            ->where('status', 'complete')
            ->whereIn(DB::raw("source_metadata::jsonb->>'release_id'"), $configurations->pluck('release_id')->unique())
            ->orderByDesc('finished_at')
            ->orderByDesc('calculation_date')
            ->orderByDesc('id')
            ->get(['id', 'strategy_version', 'source_metadata', 'finished_at', 'calculation_date'])
            ->map(function (object $run): array {
                $metadata = is_array($run->source_metadata)
                    ? $run->source_metadata
                    : (json_decode((string) $run->source_metadata, true) ?: []);
                $variant = (string) ($metadata['variant'] ?? '');
                if ($variant === '') {
                    $variant = str_contains((string) $run->strategy_version, 'pure-tcn') ? 'pure_tcn' : 'standard';
                }

                return [
                    'id' => (string) $run->id,
                    'release_id' => (string) ($metadata['release_id'] ?? ''),
                    'variant' => $variant,
                    'strategy_version' => (string) $run->strategy_version,
                ];
            })
            ->filter(fn (array $run): bool => $run['release_id'] !== '' && $configurations->contains(
                fn (array $configuration): bool => $configuration['release_id'] === $run['release_id']
                    && $configuration['variant'] === $run['variant'],
            ))
            // Rebuilds can leave more than one complete row for the same
            // immutable release and variant. Use exactly the latest one.
            ->unique(fn (array $run): string => $run['release_id'].'|'.$run['variant'])
            ->keyBy('id');
        if ($runs->isEmpty()) return collect();

        $rows = $serving->table('serving_strategy_trades as trade')
            ->join('serving_instruments as instrument', 'instrument.id', '=', 'trade.instrument_id')
            ->whereIn('trade.strategy_run_id', $runs->keys())
            ->where('trade.entry_signal', 'BUY')
            ->where('trade.entry_date', '>=', $periodStart)
            ->where('trade.exit_date', '<=', $periodEnd)
            ->whereNotNull('trade.entry_close_eur')
            ->whereNotNull('trade.exit_close_eur')
            ->whereNotNull('trade.net_return')
            ->get([
                'trade.*', 'instrument.symbol', 'instrument.sector_code as rotation_sector',
            ])->filter(function (object $row) use ($allowed, $runs): bool {
                $run = $runs->get((string) $row->strategy_run_id);
                if (! is_array($run)) return false;

                return isset($allowed[implode('|', [
                    $run['release_id'],
                    strtoupper((string) $row->symbol),
                    (int) $row->horizon,
                    $run['variant'],
                ])]);
            });
        if ($rows->isEmpty()) {
            throw new RuntimeException(
                'Für die ausgewählten Service-Modellkonfigurationen sind keine historischen Service-Trades verfügbar.',
            );
        }

        $localInstrumentIds = DB::table('instruments')
            ->whereIn('symbol', $rows->pluck('symbol')->unique())
            ->whereNull('deleted_at')
            ->pluck('id', 'symbol');

        return $rows->map(function (object $row) use ($localInstrumentIds, $runs): ?object {
            $instrumentId = $localInstrumentIds->get((string) $row->symbol);
            if (! $instrumentId) return null;
            $run = $runs->get((string) $row->strategy_run_id);
            if (! is_array($run)) return null;
            $netReturn = (float) $row->net_return;
            $transactionCost = max(0.0, (float) $row->transaction_cost);

            return (object) [
                'id' => (int) $row->id,
                'backtest_run_id' => $this->sourceRunId,
                'instrument_id' => (int) $instrumentId,
                'trained_model_id' => null,
                'model_definition_id' => null,
                'ai_type' => 'horizon',
                'timeframe' => '1d',
                'horizon_days' => (int) $row->horizon,
                'entry_date' => (string) $row->entry_date,
                'exit_date' => (string) $row->exit_date,
                'signal' => 'BUY',
                'entry_price' => (float) $row->entry_close_eur,
                'exit_price' => (float) $row->exit_close_eur,
                // The serving trade source stores the executed BUY decision,
                // not a separate return forecast. Keep this minimally
                // positive so generic result queries recognize the entry.
                'predicted_return' => 0.000001,
                'gross_return' => ((float) $row->exit_close_eur / (float) $row->entry_close_eur) - 1,
                'net_return' => $netReturn,
                'max_drawdown' => 0.0,
                'ki_score' => max(0.0, min(10.0, 5.0 + ((float) $row->entry_tcn_score * 100))),
                'confidence' => 0.0,
                'quality_gate_score' => 1.0,
                'serving_entry_score' => (float) $row->entry_tcn_score,
                'transaction_cost' => $transactionCost,
                'signal_quality_score' => 0.0,
                'rotation_sector' => $row->rotation_sector,
                'source_currency' => 'EUR',
                'eur_listing_symbol' => $row->symbol,
                'eur_listing_exchange' => null,
                'metadata' => json_encode([
                    'source' => 'serving_strategy_trades',
                    'serving_strategy_run_id' => (string) $row->strategy_run_id,
                    'serving_trade_id' => (int) $row->id,
                    'serving_release_id' => $run['release_id'],
                    'serving_variant' => $run['variant'],
                    'serving_strategy_version' => $run['strategy_version'],
                    'exit_reason' => (string) $row->exit_reason,
                ], JSON_THROW_ON_ERROR),
                'created_at' => now(),
                'updated_at' => now(),
            ];
        })->filter()->sortBy([['entry_date', 'asc'], ['id', 'asc']])->values();
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

    /** @return array{0: string, 1: string} */
    private function periodBounds(): array
    {
        $run = DB::table('backtest_runs')->where('id', $this->runId)->first(['started_at', 'settings']);
        $settings = is_string($run?->settings)
            ? (json_decode($run->settings, true) ?: [])
            : (array) ($run?->settings ?? []);
        $lookbackYears = max(1, min(10, (int) ($settings['lookback_years'] ?? 3)));
        $periodEnd = $this->filters['period_end']
            ?? $settings['period_end']
            ?? $settings['as_of_date']
            ?? $run?->started_at
            ?? now();
        $periodEnd = Carbon::parse($periodEnd)->utc()->toDateString();
        $periodStart = $this->filters['period_start']
            ?? $settings['period_start']
            ?? Carbon::parse($periodEnd, 'UTC')->subYears($lookbackYears)->toDateString();
        $periodStart = Carbon::parse($periodStart)->utc()->toDateString();

        if ($periodStart > $periodEnd) {
            throw new RuntimeException('Der gespeicherte Backtest-Zeitraum ist ungültig.');
        }

        return [$periodStart, $periodEnd];
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

    private function dynamicCapitalWeighting(): bool
    {
        return filter_var($this->filters['dynamic_capital_weighting'] ?? false, FILTER_VALIDATE_BOOL);
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
