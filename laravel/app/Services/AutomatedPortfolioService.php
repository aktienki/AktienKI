<?php

namespace App\Services;

use App\Console\Commands\EnsureStrategyTrackingPortfolios;
use App\Models\Portfolio;
use App\Models\PortfolioPosition;
use App\Models\PortfolioTransaction;
use App\Models\SavedPredictionFilter;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

final class AutomatedPortfolioService
{
    public function __construct(
        private readonly PersonalizedSignalService $signals,
        private readonly VariableExitStrategyService $exitStrategies,
        private readonly TechnicalPriceLevelService $priceLevels,
        private readonly TwelveDataService $marketData,
    ) {}

    /**
     * Re-enabled 2026-09-18: candidates() was rewritten against the serving
     * pipeline (serving_predictions, 10/20/40T, Standard-Ensemble/Pure TCN)
     * instead of the retired local walk-forward pipeline (predictions/
     * trained_models, 5/10/15/20T). RunAutomatedPortfolios (scheduled every
     * minute) and EnsureStrategyTrackingPortfolios's hidden tracking
     * portfolios both funnel through this single method.
     */
    public function scan(): array
    {
        $stats = ['strategies' => 0, 'candidates' => 0, 'purchases' => 0, 'skipped' => 0];

        DB::table('portfolio_strategy_assignments as assignment')
            ->join('portfolios as portfolio', 'portfolio.id', '=', 'assignment.portfolio_id')
            ->join('saved_prediction_filters as strategy', 'strategy.id', '=', 'assignment.saved_prediction_filter_id')
            ->where('assignment.enabled', true)
            ->where('portfolio.active', true)
            ->where(function (Builder $query): void {
                // Real (user-visible) paper depots still require the owner to
                // have opted into live automation for that strategy. Hidden
                // strategy-tracking portfolios (EnsureStrategyTrackingPortfolios)
                // run for every strategy regardless of that flag - tracking is
                // meant to happen independent of whether the owner automated
                // their own real depot.
                $query->where(function (Builder $paper): void {
                    $paper->where('portfolio.type', 'paper')
                        ->where('strategy.automatic_portfolio_enabled', true);
                })->orWhere('portfolio.type', EnsureStrategyTrackingPortfolios::PORTFOLIO_TYPE);
            })
            ->select('assignment.id', 'assignment.portfolio_id', 'assignment.saved_prediction_filter_id')
            ->orderBy('assignment.id')
            ->chunkById(50, function ($assignments) use (&$stats): void {
                foreach ($assignments as $assignment) {
                    $portfolio = Portfolio::query()->with('positions')->find($assignment->portfolio_id);
                    $strategy = SavedPredictionFilter::query()->with('user')->find($assignment->saved_prediction_filter_id);
                    if (! $portfolio || ! $strategy || ! data_get($portfolio->meta, 'automation.live_enabled', false)) {
                        $stats['skipped']++;

                        continue;
                    }
                    $stats['strategies']++;
                    $result = $this->execute($strategy, $portfolio);
                    $stats['candidates'] += $result['candidates'];
                    $stats['purchases'] += $result['purchases'];
                    $stats['skipped'] += $result['skipped'];
                }
            }, 'assignment.id', 'id');

        return $stats;
    }

    public function execute(SavedPredictionFilter $strategy, ?Portfolio $portfolio = null): array
    {
        $strategy->loadMissing('user');
        $portfolio ??= $strategy->portfolio;
        if (! $strategy->user || ! $portfolio || ! $portfolio->active || ! data_get($portfolio->meta, 'automation.live_enabled', false)) {
            return ['candidates' => 0, 'purchases' => 0, 'skipped' => 1];
        }

        $this->processDynamicExits($strategy, $portfolio);
        $candidates = $this->candidates($strategy);
        $reservedInstrumentIds = $this->processEntryReservations($strategy, $portfolio, $candidates);
        $candidates = $candidates->reject(fn (object $candidate): bool => $reservedInstrumentIds->contains((int) $candidate->instrument_id))->values();
        $sectorRotation = (bool) data_get($strategy->filters, 'sector_score_rotation', false);
        $indexRotation = (bool) data_get($strategy->filters, 'index_score_rotation', false);
        $combinedAreaPriority = (bool) data_get($strategy->filters, 'combined_area_forecast_priority', false);
        $sectorAverages = $candidates->groupBy(fn (object $row) => (string) ($row->sector ?: 'Other'))
            ->map(fn (Collection $rows): float => (float) $rows->avg('score_10'));
        $memberships = $indexRotation
            ? DB::table('index_memberships')->whereNull('removed_at')
                ->whereIn('instrument_id', $candidates->pluck('instrument_id')->unique())
                ->get(['instrument_id', 'market_index_id'])->groupBy('instrument_id')
            : collect();
        $candidateByInstrument = $candidates->keyBy('instrument_id');
        $indexAverages = $indexRotation
            ? $memberships->flatten(1)->groupBy('market_index_id')->map(function (Collection $rows) use ($candidateByInstrument): float {
                return (float) $rows->map(fn (object $membership): float => (float) ($candidateByInstrument->get($membership->instrument_id)?->score_10 ?? 0))->avg();
            })
            : collect();
        if (Schema::hasTable('market_context_predictions') && ($sectorRotation || $indexRotation)) {
            $snapshotDate = DB::table('market_context_predictions')->max('prediction_date');
            if ($snapshotDate) {
                if ($sectorRotation) {
                    $storedSectorAverages = DB::table('market_context_predictions')
                        ->where('prediction_date', $snapshotDate)->where('scope_type', 'sector')
                        ->pluck('score', 'scope_key')->map(fn ($score): float => (float) $score);
                    if ($storedSectorAverages->isNotEmpty()) {
                        $sectorAverages = $storedSectorAverages;
                    }
                }
                if ($indexRotation) {
                    $storedIndexAverages = DB::table('market_context_predictions')
                        ->where('prediction_date', $snapshotDate)->where('scope_type', 'index')
                        ->pluck('score', 'scope_key')->mapWithKeys(fn ($score, $key): array => [(int) $key => (float) $score]);
                    if ($storedIndexAverages->isNotEmpty()) {
                        $indexAverages = $storedIndexAverages;
                    }
                }
            }
        }
        if ($indexRotation && $memberships->isNotEmpty()) {
            $marketIndices = DB::table('market_indices')->whereIn('id', $memberships->flatten(1)->pluck('market_index_id')->unique())
                ->get(['id', 'symbol']);
            $serviceSymbols = $marketIndices->pluck('symbol')->map(fn (string $symbol): string => $symbol === '^GDAXI' ? 'DAX' : $symbol);
            $serviceForecasts = DB::connection('serving')->table('serving_predictions as prediction')
                ->join('serving_instruments as instrument', 'instrument.id', '=', 'prediction.instrument_id')
                ->join('serving_active_models as active', fn ($join) => $join->on('active.instrument_id', '=', 'prediction.instrument_id')->on('active.release_id', '=', 'prediction.release_id'))
                ->where('instrument.instrument_type', 'index')->whereIn('instrument.symbol', $serviceSymbols)
                ->where('prediction.horizon', 20)->where('prediction.variant', 'standard')
                ->orderByDesc('prediction.as_of')->orderByDesc('prediction.id')
                ->get(['instrument.symbol', 'prediction.expected_return'])->unique('symbol');
            if ($serviceForecasts->isNotEmpty()) {
                $minimum = (float) $serviceForecasts->min('expected_return');
                $maximum = (float) $serviceForecasts->max('expected_return');
                $range = max(0.000001, $maximum - $minimum);
                $forecastBySymbol = $serviceForecasts->mapWithKeys(fn (object $row): array => [
                    (string) $row->symbol => (((float) $row->expected_return - $minimum) / $range) * 10,
                ]);
                $indexAverages = $marketIndices->mapWithKeys(function (object $index) use ($forecastBySymbol): array {
                    $symbol = $index->symbol === '^GDAXI' ? 'DAX' : (string) $index->symbol;

                    return $forecastBySymbol->has($symbol) ? [(int) $index->id => (float) $forecastBySymbol->get($symbol)] : [];
                });
            }
        }

        $candidates = $candidates->sortByDesc(function (object $row) use ($strategy, $combinedAreaPriority, $sectorRotation, $indexRotation, $sectorAverages, $memberships, $indexAverages): string|float {
            $sectorScore = $sectorRotation ? (float) $sectorAverages->get((string) ($row->sector ?: 'Other'), 0) : 0;
            $indexScore = $indexRotation
                ? (float) collect($memberships->get($row->instrument_id, collect()))
                    ->map(fn (object $membership): float => (float) $indexAverages->get($membership->market_index_id, 0))->max()
                : 0;
            $rotationScores = array_filter([
                $sectorRotation ? $sectorScore : null,
                $indexRotation ? $indexScore : null,
            ], fn ($score): bool => $score !== null);
            $rotationScore = $rotationScores === [] ? 0 : array_sum($rotationScores) / count($rotationScores);

            if ($combinedAreaPriority) {
                $stockWeight = (float) data_get($strategy->filters, 'stock_forecast_weight', .20);
                $sectorWeight = (float) data_get($strategy->filters, 'sector_forecast_weight', .30);
                $indexWeight = (float) data_get($strategy->filters, 'index_forecast_weight', .50);
                $drawdownPenalty = (float) data_get($strategy->filters, 'drawdown_penalty_weight', .30);
                $drawdown = max(0.0, min(100.0, (float) ($row->drawdown_percent ?? 0)));

                return ($stockWeight * (float) $row->score_10)
                    + ($sectorWeight * $sectorScore)
                    + ($indexWeight * $indexScore)
                    - ($drawdownPenalty * ($drawdown / 10));
            }

            // The stock score remains primary. Rotation only resolves equal scores.
            return sprintf('%09.4f:%09.4f:%09.4f', (float) $row->score_10, $rotationScore, (float) $row->confidence_percent);
        })->values();

        $purchases = 0;
        $skipped = 0;
        foreach ($candidates as $candidate) {
            $candidateIndexScore = $indexRotation
                ? collect($memberships->get($candidate->instrument_id, collect()))
                    ->map(fn (object $membership): float => (float) $indexAverages->get($membership->market_index_id, 0))->max()
                : null;
            $bought = DB::transaction(fn (): bool => $this->buyCandidate(
                $strategy,
                $portfolio,
                $candidate,
                (float) $sectorAverages->get((string) ($candidate->sector ?: 'Other'), 0),
                $candidateIndexScore !== null ? (float) $candidateIndexScore : null,
            ), 3);
            $bought ? $purchases++ : $skipped++;
        }

        return ['candidates' => $candidates->count(), 'purchases' => $purchases, 'skipped' => $skipped];
    }

    /**
     * Rewritten 2026-09-18 to read the serving pipeline (serving_predictions,
     * 10/20/40T, Standard-Ensemble/Pure TCN) instead of the retired local
     * walk-forward pipeline (predictions/trained_models, 5/10/15/20T).
     * serving and the default connection are separate physical Postgres
     * databases (aktienki_serving_next vs aktienki) - no SQL JOIN is
     * possible across them, so this runs as two queries (serving BUY-signal
     * candidates, then local instrument/exchange/fundamentals/quote
     * filtering) merged in PHP, the same two-phase pattern execute()'s
     * indexRotation block already used for serving data.
     *
     * Several strategy filter fields have no serving equivalent and are no
     * longer applied: model (local trained_model IDs), quality_tier and
     * model_quality_min (local model_quality_rankings has no serving
     * counterpart), ai_type (local-only prediction field). heatmap_selection
     * still works for the cells backed by serving's own oos_metrics
     * (profit_factor, drawdown, volatility); cells needing signal_quality or
     * model_quality_score never exclude anything now (no serving analog).
     */
    private function candidates(SavedPredictionFilter $strategy): Collection
    {
        $filters = (array) $strategy->filters;
        $profileLimits = match ($this->signals->riskLevel($strategy->user)) {
            'cautious' => ['risk' => 35.0, 'volatility' => 45.0, 'drawdown' => 25.0, 'confidence' => 65.0, 'trades' => 20],
            'opportunity_oriented' => ['risk' => 80.0, 'volatility' => 100.0, 'drawdown' => 50.0, 'confidence' => 45.0, 'trades' => 10],
            default => ['risk' => 60.0, 'volatility' => 65.0, 'drawdown' => 40.0, 'confidence' => 55.0, 'trades' => 15],
        };

        $servingRows = $this->servingBuySignalCandidates($strategy, $filters, $profileLimits);
        if ($servingRows->isEmpty()) {
            return collect();
        }

        $fundamentalNumber = static fn (string $key): string => match ($key) {
            'trailingPE' => "COALESCE(fundamental.trailing_pe, CASE WHEN NULLIF(fundamental.data::jsonb->>'trailingPE', '') ~ '^-?[0-9]+([.][0-9]+)?$' THEN (fundamental.data::jsonb->>'trailingPE')::numeric END)",
            'dividendYield' => "COALESCE(fundamental.dividend_yield, CASE WHEN NULLIF(fundamental.data::jsonb->>'dividendYield', '') ~ '^-?[0-9]+([.][0-9]+)?$' THEN (fundamental.data::jsonb->>'dividendYield')::numeric END)",
            'marketCap' => "COALESCE(fundamental.market_cap, CASE WHEN NULLIF(fundamental.data::jsonb->>'marketCap', '') ~ '^-?[0-9]+([.][0-9]+)?$' THEN (fundamental.data::jsonb->>'marketCap')::numeric END)",
            'revenueGrowth' => "COALESCE(fundamental.revenue_growth, CASE WHEN NULLIF(fundamental.data::jsonb->>'revenueGrowth', '') ~ '^-?[0-9]+([.][0-9]+)?$' THEN (fundamental.data::jsonb->>'revenueGrowth')::numeric END)",
        };
        $latestQuoteIds = DB::table('current_stock_quotes')
            ->where('status', 'ok')
            ->selectRaw('instrument_id, MAX(id) AS quote_id')
            ->groupBy('instrument_id');
        $latestTechnicalIds = DB::table('technical_indicators')
            ->where('interval', '1d')
            ->selectRaw('instrument_id, MAX(id) AS technical_id')
            ->groupBy('instrument_id');
        $latestFundamentalIds = DB::table('instrument_fundamentals')
            ->selectRaw('instrument_id, MAX(id) AS fundamental_id')
            ->groupBy('instrument_id');

        $localRows = DB::table('instruments as instrument')
            ->whereIn('instrument.id', $servingRows->keys())
            ->leftJoin('exchanges as exchange', 'exchange.id', '=', 'instrument.exchange_id')
            ->leftJoinSub($latestTechnicalIds, 'latest_technical', fn ($join) => $join->on('latest_technical.instrument_id', '=', 'instrument.id'))
            ->leftJoin('technical_indicators as technical', 'technical.id', '=', 'latest_technical.technical_id')
            ->leftJoinSub($latestFundamentalIds, 'latest_fundamental', fn ($join) => $join->on('latest_fundamental.instrument_id', '=', 'instrument.id'))
            ->leftJoin('instrument_fundamentals as fundamental', 'fundamental.id', '=', 'latest_fundamental.fundamental_id')
            ->leftJoinSub($latestQuoteIds, 'latest_quote', fn ($join) => $join->on('latest_quote.instrument_id', '=', 'instrument.id'))
            ->leftJoin('current_stock_quotes as quote', 'quote.id', '=', 'latest_quote.quote_id')
            ->where('instrument.type', 'stock')
            ->where('instrument.is_active', true)
            ->where('instrument.is_german_tradeable', true)
            ->whereNull('instrument.deleted_at')
            // is_german_tradeable only means a EUR cross-listing EXISTS
            // somewhere - it does not mean this specific instrument row
            // trades in EUR itself (ADI/1024.HK/2318.HK all have it true
            // while instrument.currency is USD/HKD). A strategy whose
            // portfolio settles in EUR needs the row actually bought to be
            // EUR-denominated, so this is a separate, explicit filter.
            ->when((string) ($filters['currency'] ?? '') !== '', fn ($query) => $query
                ->whereRaw('UPPER(instrument.currency) = ?', [strtoupper((string) $filters['currency'])]))
            // Quotes must come from a specific venue (e.g. Xetra/Frankfurt
            // only, not Paris/Amsterdam, even though those also settle in
            // EUR) - a list of allowed exchange MICs, same empty-means-
            // unrestricted convention as every other filter here.
            ->when(collect((array) ($filters['exchange_mics'] ?? []))->filter()->isNotEmpty(), function ($query) use ($filters): void {
                $mics = collect((array) $filters['exchange_mics'])->filter()->map(fn ($mic) => strtoupper((string) $mic))->values()->all();
                $query->whereRaw('UPPER(exchange.mic) IN ('.implode(',', array_fill(0, count($mics), '?')).')', $mics);
            })
            ->when(($filters['q'] ?? '') !== '', function ($query) use ($filters): void {
                $term = '%'.strtolower(trim((string) $filters['q'])).'%';
                $query->where(fn ($nested) => $nested
                    ->whereRaw('LOWER(instrument.symbol) LIKE ?', [$term])
                    ->orWhereRaw('LOWER(instrument.name) LIKE ?', [$term]));
            })
            ->when(($filters['country'] ?? '') !== '', fn ($query) => $query->where('instrument.country', strtoupper((string) $filters['country'])))
            ->when(($filters['sector'] ?? '') !== '', fn ($query) => $query->where('instrument.sector', (string) $filters['sector']))
            ->when(($filters['exchange'] ?? '') !== '', fn ($query) => $query->where('exchange.code', strtoupper((string) $filters['exchange'])))
            ->when((float) ($filters['volatility_max'] ?? 100) < 100, fn ($query) => $query->whereRaw('(technical.volatility_20 IS NULL OR technical.volatility_20 * 100 <= ?)', [(float) $filters['volatility_max']]))
            ->when((float) ($filters['pe_max'] ?? 100) < 100, fn ($query) => $query->whereRaw($fundamentalNumber('trailingPE').' <= ?', [(float) $filters['pe_max']]))
            ->when(($filters['dividend_yield_operator'] ?? 'gte') === 'lte' || (float) ($filters['dividend_yield_min'] ?? 0) > 0, function ($query) use ($filters, $fundamentalNumber) {
                $operator = ($filters['dividend_yield_operator'] ?? 'gte') === 'lte' ? '<=' : '>=';

                return $query->whereRaw($fundamentalNumber('dividendYield').' '.$operator.' ?', [(float) ($filters['dividend_yield_min'] ?? 0) / 100]);
            })
            ->when((float) ($filters['market_cap_min'] ?? 0) > 0, fn ($query) => $query->whereRaw($fundamentalNumber('marketCap').' >= ?', [(float) $filters['market_cap_min'] * 1_000_000_000]))
            ->when(in_array($filters['market_cap_group'] ?? 'all', ['small', 'mid', 'large'], true), function ($query) use ($filters, $fundamentalNumber) {
                $value = $fundamentalNumber('marketCap');

                return match ($filters['market_cap_group']) {
                    'small' => $query->whereRaw($value.' < ?', [2_000_000_000]),
                    'mid' => $query->whereRaw($value.' >= ? AND '.$value.' < ?', [2_000_000_000, 10_000_000_000]),
                    'large' => $query->whereRaw($value.' >= ?', [10_000_000_000]),
                };
            })
            ->when((float) ($filters['revenue_growth_min'] ?? -50) > -50, fn ($query) => $query->whereRaw($fundamentalNumber('revenueGrowth').' >= ?', [(float) $filters['revenue_growth_min'] / 100]))
            ->select(['instrument.id as instrument_id', 'instrument.symbol', 'instrument.name', 'instrument.sector', 'instrument.currency', 'quote.price as quote_price'])
            ->selectRaw('COALESCE(technical.volatility_20, 0) * 100 AS volatility_percent')
            ->get()
            ->keyBy('instrument_id');

        $candidates = $servingRows->map(function (object $row) use ($localRows): ?object {
            $local = $localRows->get($row->instrument_id);
            if (! $local) {
                return null;
            }
            $merged = (object) array_merge((array) $row, (array) $local);
            // The serving row already covers one specific horizon - unlike
            // the old pipeline's 4 simultaneous predicted_price_Nd columns,
            // only this one is ever populated; buyCandidate()/
            // processEntryReservations() read whichever of 10/20/40 is set
            // and safely default the others to 0/null via data_get().
            $merged->{'predicted_price_'.$row->horizon.'d'} = $row->target_price;
            $merged->current_price = is_numeric($local->quote_price ?? null) ? (float) $local->quote_price : 0.0;
            $merged->score_10 = (float) $row->confidence * 10;
            $merged->confidence_percent = (float) $row->confidence * 100;
            $merged->drawdown_percent = abs((float) ($row->oos_max_drawdown ?? 0)) * 100;
            $merged->profit_factor = (float) ($row->oos_profit_factor ?? 0);
            $merged->hit_rate = (float) ($row->oos_hit_rate ?? 0) * 100;
            $merged->average_net_return = (float) ($row->oos_average_net_trade ?? 0) * 100;
            $merged->composite_score = $merged->score_10 * 10;
            $merged->signal_quality = null;
            $merged->model_quality_score = null;

            return $merged;
        })->filter()->values();

        $selections = json_decode((string) ($filters['heatmap_selection'] ?? ''), true);
        if (! is_array($selections) || collect($selections)->flatten()->isEmpty()) {
            return $candidates->unique('instrument_id')->values();
        }
        $cellFor = static function (string $map, object $row): ?string {
            [$x, $y, $xMin, $xMax, $xStep, $yMin, $yMax, $yStep] = match ($map) {
                'score_risk' => [$row->composite_score, ($row->risk_score - 1) * 25, 0, 100, 10, 0, 100, 10],
                'score_drawdown' => [$row->composite_score, $row->drawdown_percent, 0, 100, 10, 0, 50, 5],
                'score_profit_factor' => [$row->composite_score, $row->profit_factor, 0, 100, 10, 0, 3, .3],
                'score_volatility' => [$row->composite_score, $row->volatility_percent, 0, 100, 10, 0, 100, 10],
                'profit_factor_hit_rate' => [$row->profit_factor, $row->hit_rate, 0, 3, .3, 0, 100, 10],
                'signal_risk' => [$row->signal_quality, $row->drawdown_percent, 0, 100, 10, 0, 50, 5],
                'volatility_drawdown' => [(float) $row->model_quality_score * 100, $row->drawdown_percent, 0, 100, 10, 0, 50, 5],
                'trades_return' => [$row->confidence_percent, $row->average_net_return, 0, 100, 10, -5, 15, 2],
                default => [null, null, 0, 1, 1, 0, 1, 1],
            };
            if (! is_numeric($x) || ! is_numeric($y)) {
                return null;
            }
            $xb = (int) max(0, min(9, floor((max($xMin, min($xMax, (float) $x)) - $xMin) / $xStep)));
            $yb = (int) max(0, min(9, floor((max($yMin, min($yMax, (float) $y)) - $yMin) / $yStep)));

            return $xb.'-'.$yb;
        };

        return $candidates->filter(function (object $candidate) use ($selections, $cellFor): bool {
            foreach ($selections as $map => $cells) {
                if (is_array($cells) && $cells !== [] && in_array($cellFor((string) $map, $candidate), $cells, true)) {
                    return false;
                }
            }

            return true;
        })->unique('instrument_id')->values();
    }

    /**
     * Selects the serving pipeline's champion-release BUY-signal transitions
     * (new BUY, previous prediction at the same instrument/horizon/variant
     * wasn't already BUY) for the given strategy, keyed by instrument_id. A
     * strategy pinned to specific serving_model_configurations (created from
     * the model overview) only ever considers those exact stock/horizon/
     * variant combinations; everything else uses the strategy's own
     * serving_horizon/serving_variant (default 20T/standard).
     *
     * @return Collection<int, object>
     */
    private function servingBuySignalCandidates(SavedPredictionFilter $strategy, array $filters, array $profileLimits): Collection
    {
        $servingConfigurations = collect((array) ($filters['serving_model_configurations'] ?? []))
            ->filter(fn (mixed $configuration): bool => is_array($configuration)
                && filled($configuration['symbol'] ?? null)
                && in_array((int) ($configuration['horizon_days'] ?? 0), [10, 20, 40], true)
                && in_array((string) ($configuration['variant'] ?? ''), ['standard', 'pure_tcn'], true))
            ->values();
        // Real strategy filters already carry quality_horizons (e.g.
        // [10, 20, 40]) from the strategy-builder UI - an allow-list of
        // horizons to consider, not a single pin. Any BUY transition on any
        // listed horizon (always variant=standard; nothing in the existing
        // filter schema selects pure_tcn outside serving_model_configurations)
        // qualifies; duplicates per instrument are resolved below by
        // preferring the highest-confidence match.
        $defaultHorizons = collect((array) ($filters['quality_horizons'] ?? [20]))
            ->map(fn ($horizon) => (int) $horizon)
            ->filter(fn (int $horizon): bool => in_array($horizon, [10, 20, 40], true))
            ->unique()->values();
        if ($defaultHorizons->isEmpty()) {
            $defaultHorizons = collect([20]);
        }
        $defaultVariant = 'standard';

        $previousSignalSql = "(SELECT UPPER(pp.signal) FROM serving_predictions pp
            WHERE pp.instrument_id = prediction.instrument_id AND pp.horizon = prediction.horizon AND pp.variant = prediction.variant
              AND pp.id < prediction.id ORDER BY pp.as_of DESC, pp.id DESC LIMIT 1)";
        $columns = [
            'prediction.id as prediction_id', 'prediction.instrument_id', 'prediction.as_of',
            'prediction.horizon', 'prediction.variant', 'prediction.expected_return', 'prediction.target_price',
            'prediction.risk_score', 'prediction.confidence', 'prediction.compact_context',
        ];

        $base = fn () => DB::connection('serving')->table('serving_predictions as prediction')
            ->join('serving_active_models as active', fn ($join) => $join
                ->on('active.instrument_id', '=', 'prediction.instrument_id')
                ->on('active.release_id', '=', 'prediction.release_id'))
            ->whereRaw("UPPER(prediction.signal) = 'BUY'")
            // An automated model strategy reacts to the transition into BUY,
            // not to every newly persisted prediction while BUY remains active.
            ->whereRaw("COALESCE({$previousSignalSql}, 'NONE') <> 'BUY'")
            // Hard pre-selection by the user's risk profile (risk_score is a
            // 1-5 scale here, not 0-100 - (risk-1)*25 is the same conversion
            // the dashboard's chart-pattern cards and heatmap cellFor() use).
            ->whereRaw('((prediction.risk_score - 1) * 25) <= ?', [$profileLimits['risk']])
            ->whereRaw('(prediction.confidence * 100) >= ?', [$profileLimits['confidence']])
            ->when((float) ($filters['risk_max'] ?? 100) < 100, fn ($q) => $q->whereRaw('((prediction.risk_score - 1) * 25) <= ?', [(float) $filters['risk_max']]))
            ->when((float) ($filters['confidence_min'] ?? 0) > 0, fn ($q) => $q->whereRaw('(prediction.confidence * 100) >= ?', [(float) $filters['confidence_min']]))
            ->when((float) ($filters['predicted_return_min'] ?? -50) > -50, fn ($q) => $q->whereRaw('(prediction.expected_return * 100) >= ?', [(float) $filters['predicted_return_min']]))
            ->when((float) ($filters['drawdown_max'] ?? 50) < 50, fn ($q) => $q->whereRaw("ABS(COALESCE((prediction.compact_context::jsonb->'oos_metrics'->>'max_drawdown')::numeric, 0)) * 100 <= ?", [(float) $filters['drawdown_max']]))
            ->when((float) ($filters['profit_per_trade_min'] ?? 0) > 0, fn ($q) => $q->whereRaw("COALESCE((prediction.compact_context::jsonb->'oos_metrics'->>'average_net_trade')::numeric, 0) * 100 >= ?", [(float) $filters['profit_per_trade_min']]))
            ->when(is_numeric($filters['median_return_min'] ?? null), fn ($q) => $q->whereRaw("COALESCE((prediction.compact_context::jsonb->'oos_metrics'->>'median_net_trade')::numeric, 0) * 100 >= ?", [(float) $filters['median_return_min']]))
            ->when((float) ($filters['profit_factor_min'] ?? 0) > 0, fn ($q) => $q->whereRaw("COALESCE((prediction.compact_context::jsonb->'oos_metrics'->>'profit_factor')::numeric, 0) >= ?", [(float) $filters['profit_factor_min']]))
            ->when((float) ($filters['hit_rate_min'] ?? 0) > 0, fn ($q) => $q->whereRaw("COALESCE((prediction.compact_context::jsonb->'oos_metrics'->>'hit_rate')::numeric, 0) * 100 >= ?", [(float) $filters['hit_rate_min']]))
            // Scopes with few backtested trades produce unstable per-trade
            // ratios (profit_factor can swing into the hundreds off a single
            // lucky trade) - minimum_trades is the existing strategy-builder
            // filter field for this, just not yet applied to the serving
            // pipeline's own oos_metrics.trades count.
            ->when((int) ($filters['minimum_trades'] ?? 0) > 0, fn ($q) => $q->whereRaw("COALESCE((prediction.compact_context::jsonb->'oos_metrics'->>'trades')::numeric, 0) >= ?", [(int) $filters['minimum_trades']]));

        if ($servingConfigurations->isEmpty()) {
            $rows = $defaultHorizons->flatMap(fn (int $horizon): array => $base()
                ->where('prediction.horizon', $horizon)->where('prediction.variant', $defaultVariant)
                ->get($columns)->all());
        } else {
            // A strategy created from the model overview is tied to the exact
            // stock/horizon/variant selected there - it must never fall back
            // to another model of the same stock. serving's own horizon/
            // variant columns make this a direct match now, unlike the old
            // bridge's fuzzy model-name string comparison against the local
            // pipeline's differently-formatted model_definitions.name.
            $symbols = $servingConfigurations->pluck('symbol')->map(fn ($s) => strtoupper(trim((string) $s)))->unique()->values();
            $placeholders = implode(',', array_fill(0, $symbols->count(), '?'));
            $instrumentIdBySymbol = DB::table('instruments')
                ->whereRaw("UPPER(symbol) IN ({$placeholders})", $symbols->all())
                ->get(['id', 'symbol'])
                ->mapWithKeys(fn (object $row): array => [strtoupper((string) $row->symbol) => (int) $row->id]);

            $rows = $servingConfigurations->flatMap(function (array $configuration) use ($base, $columns, $instrumentIdBySymbol): array {
                $instrumentId = $instrumentIdBySymbol->get(strtoupper(trim((string) $configuration['symbol'])));
                if (! $instrumentId) {
                    return [];
                }

                return $base()->where('prediction.instrument_id', $instrumentId)
                    ->where('prediction.horizon', (int) $configuration['horizon_days'])
                    ->where('prediction.variant', (string) $configuration['variant'])
                    ->orderByDesc('prediction.as_of')->orderByDesc('prediction.id')
                    ->limit(1)->get($columns)->all();
            });
        }

        $alreadyExecuted = DB::table('portfolio_automation_executions')
            ->where('saved_prediction_filter_id', $strategy->id)
            ->pluck('prediction_id')->map(fn ($id) => (int) $id)->all();

        return collect($rows)
            ->reject(fn (object $row): bool => in_array((int) $row->prediction_id, $alreadyExecuted, true))
            ->map(function (object $row): object {
                $context = json_decode((string) $row->compact_context, true) ?? [];
                $row->oos_max_drawdown = data_get($context, 'oos_metrics.max_drawdown');
                $row->oos_profit_factor = data_get($context, 'oos_metrics.profit_factor');
                $row->oos_hit_rate = data_get($context, 'oos_metrics.hit_rate');
                $row->oos_average_net_trade = data_get($context, 'oos_metrics.average_net_trade');

                return $row;
            })
            // A strategy checking multiple quality_horizons can find a BUY
            // transition for the same instrument on more than one of them -
            // keep the most confident one, not just whichever horizon
            // happened to be queried first.
            ->sortByDesc(fn (object $row): float => (float) $row->confidence)
            ->unique('instrument_id')
            ->keyBy('instrument_id');
    }

    /**
     * A EUR depot buying a non-EUR instrument (e.g. the USA-Strategie
     * targeting USD stocks) must not record the raw native-currency quote
     * as if it were EUR - that silently misprices the trade (the bug
     * behind ADI/1024.HK/2318.HK needing manual cleanup earlier). Instead,
     * mirror DepotController::addInstrument()'s manual-add path: resolve
     * the instrument's German Xetra/Frankfurt cross-listing and buy at
     * its real EUR quote. Returns null - the candidate must be skipped -
     * when the depot's own currency doesn't match and no EUR listing
     * quote is available.
     *
     * @return array{price: float, currency: string, listing: ?array, primary_currency: string}|null
     */
    private function resolvePurchasePrice(object $candidate, Portfolio $portfolio): ?array
    {
        // For instruments in the portfolio's native currency, always use the
        // price at the time the prediction was generated (candidate->current_price),
        // not a later quote. For FX-requiring conversions, the listing quote
        // (candidate->quote_price) is fetched fresh since we need the current
        // EUR rate to execute the cross-listing trade.
        $nativePrice = (float) $candidate->current_price;
        if ($nativePrice <= 0) {
            return null;
        }

        $instrument = DB::table('instruments')->where('id', $candidate->instrument_id)
            ->first(['currency', 'isin', 'name', 'symbol', 'german_listing_symbol', 'german_listing_exchange', 'german_listing_mic', 'german_listing_currency']);
        if (! $instrument) {
            return null;
        }

        $instrumentCurrency = strtoupper((string) $instrument->currency);
        $portfolioCurrency = strtoupper((string) $portfolio->currency);
        if ($instrumentCurrency === $portfolioCurrency) {
            return ['price' => $nativePrice, 'currency' => $portfolioCurrency, 'listing' => null, 'primary_currency' => $instrumentCurrency];
        }

        // No FX conversion path exists for any depot currency other than
        // EUR - same restriction DepotController::addInstrument() enforces.
        if ($portfolioCurrency !== 'EUR') {
            return null;
        }

        $listing = $instrument->german_listing_symbol ? [
            'symbol' => $instrument->german_listing_symbol,
            'exchange' => $instrument->german_listing_exchange,
            'mic_code' => $instrument->german_listing_mic,
            'currency' => $instrument->german_listing_currency,
        ] : $this->marketData->germanListing($instrument->isin, (string) $instrument->name, (string) $instrument->symbol);
        if (! $listing || strtoupper((string) ($listing['currency'] ?? '')) !== 'EUR') {
            return null;
        }

        if (! $instrument->german_listing_symbol) {
            DB::table('instruments')->where('id', $candidate->instrument_id)->update([
                'german_listing_symbol' => $listing['symbol'], 'german_listing_exchange' => $listing['exchange'] ?: null,
                'german_listing_mic' => $listing['mic_code'] ?: null, 'german_listing_currency' => 'EUR',
                'german_listing_verified_at' => now(), 'updated_at' => now(),
            ]);
        }

        $listingQuote = $this->marketData->listingQuote($listing['symbol'], $listing['exchange'] ?: null);
        if (! is_numeric($listingQuote['price'] ?? null) || (float) $listingQuote['price'] <= 0
            || strtoupper((string) ($listingQuote['currency'] ?? 'EUR')) !== 'EUR') {
            return null;
        }

        return ['price' => (float) $listingQuote['price'], 'currency' => 'EUR', 'listing' => $listing, 'primary_currency' => $instrumentCurrency];
    }

    private function buyCandidate(SavedPredictionFilter $strategy, Portfolio $assignedPortfolio, object $candidate, float $sectorAverage, ?float $indexAverage = null, bool $reservationReleased = false): bool
    {
        if (DB::table('portfolio_automation_executions')->where('saved_prediction_filter_id', $strategy->id)->where('prediction_id', $candidate->prediction_id)->exists()) {
            return false;
        }

        // candidate->prediction_id is now a serving_predictions.id - the real
        // row, not a reconstructed one, so this calls assessSource()
        // directly instead of the legacy local-prediction bridge
        // (assessLegacyPrediction(), which resolved a serving row from a
        // local prediction's metadata.serving_prediction_id link).
        $servingSource = DB::connection('serving')->table('serving_predictions')->where('id', (int) $candidate->prediction_id)->first();
        $indicator = $servingSource
            ? app(IndicatorEntryGateService::class)->assessSource(array_merge((array) $servingSource, [
                'compact_context' => json_decode((string) $servingSource->compact_context, true),
            ]))
            : ['passed' => false, 'reason_codes' => ['SERVING_SOURCE_MISSING']];
        if (($indicator['passed'] ?? false) !== true) {
            Log::info('portfolio_indicator_entry_rejected', [
                'prediction_id' => (int) $candidate->prediction_id,
                'strategy_id' => (int) $strategy->id,
                'assessment' => $indicator,
            ]);

            return false;
        }

        /** @var Portfolio|null $portfolio */
        $portfolio = Portfolio::query()->lockForUpdate()->find($assignedPortfolio->id);
        if (! $portfolio || ! $portfolio->active || ! data_get($portfolio->meta, 'automation.live_enabled', false)) {
            return false;
        }

        // A strategy may hold an instrument only once. Locking the portfolio
        // above serializes concurrent automation runs for the same portfolio.
        // A new BUY is permitted only after the existing position was sold.
        $existingPosition = PortfolioPosition::query()
            ->where('portfolio_id', $portfolio->id)
            ->where('instrument_id', $candidate->instrument_id)
            ->lockForUpdate()
            ->first();
        if ($existingPosition) {
            return false;
        }

        $meta = (array) $portfolio->meta;
        $initialCapital = max(1000.0, (float) data_get(
            $strategy->filters,
            'initial_capital',
            data_get($meta, 'automation.initial_capital', 10000),
        ));
        $maxUnits = max(1, min(50, (int) data_get($strategy->filters, 'max_positions', 5)));
        $tradeCost = max(0.0, (float) data_get(
            $strategy->filters,
            'trade_cost',
            data_get($meta, 'automation.trade_cost', 10),
        ));
        $baseCapital = $initialCapital / $maxUnits;
        $cashAccount = DB::table('portfolio_cash_accounts')
            ->where('portfolio_id', $portfolio->id)
            ->where('currency', $portfolio->currency)
            ->lockForUpdate()->first();
        if (! $cashAccount) {
            return false;
        }
        $cash = max(0.0, (float) $cashAccount->balance - (float) $cashAccount->reserved_balance);
        $usedUnits = PortfolioPosition::query()->where('portfolio_id', $portfolio->id)->get()
            ->sum(fn (PortfolioPosition $position): int => max(1, (int) data_get($position->meta, 'automation.position_factor', 1)));
        $usedUnits += (int) DB::table('portfolio_strategy_reservations')->where('portfolio_id', $portfolio->id)
            ->where('status', 'active')->where('instrument_id', '<>', $candidate->instrument_id)->sum('position_factor');
        $availableUnits = max(0, $maxUnits - $usedUnits);
        $maximumFactor = max(1, min($maxUnits, (int) data_get($strategy->filters, 'position_factor', 1)));
        $affordableUnits = (int) floor(max(0, $cash - $tradeCost) / $baseCapital);
        $factor = min($maximumFactor, $availableUnits, $affordableUnits);
        if ($factor < 1) {
            return false;
        }

        $purchase = $this->resolvePurchasePrice($candidate, $portfolio);
        if ($purchase === null) {
            return false;
        }
        $price = $purchase['price'];
        $exitStrategy = (bool) data_get($strategy->filters, 'dynamic_horizon_exit_enabled', false)
            ? $this->exitStrategies->resolveForPrediction((int) $candidate->instrument_id, $candidate)
            : $this->exitStrategies->resolve((int) $candidate->instrument_id);
        $allocated = min($cash - $tradeCost, $baseCapital * $factor);
        if (! $reservationReleased && (bool) data_get($strategy->filters, 'entry_wait_5d_enabled', false)) {
            // A serving candidate only ever has one of these three populated
            // (its own horizon - see candidates()); the old 5/10/15/20d
            // pipeline had all four simultaneously, hence "highest of them".
            $targets = collect([10, 20, 40])->map(fn (int $days): float => (float) data_get($candidate, 'predicted_price_'.$days.'d', 0))->filter(fn (float $target): bool => $target > 0);
            $highestTarget = (float) ($targets->max() ?? 0);
            if ($highestTarget > 0 && $price > $highestTarget) {
                $reservedCapital = min((float) $cashAccount->balance - (float) $cashAccount->reserved_balance, $allocated + $tradeCost);
                if ($reservedCapital <= 0 || DB::table('portfolio_strategy_reservations')->where('saved_prediction_filter_id', $strategy->id)
                    ->where('instrument_id', $candidate->instrument_id)->where('status', 'active')->exists()) {
                    return false;
                }
                DB::table('portfolio_strategy_reservations')->insert([
                    'saved_prediction_filter_id' => $strategy->id, 'portfolio_id' => $portfolio->id,
                    'prediction_id' => $candidate->prediction_id, 'instrument_id' => $candidate->instrument_id,
                    'reserved_capital' => $reservedCapital, 'position_factor' => $factor, 'status' => 'active',
                    'expires_at' => now()->addDays(5),
                    'details' => json_encode(['reason' => 'current_performance_above_prediction', 'quote_price' => $price,
                        'highest_target_price' => $highestTarget, 'symbol' => $candidate->symbol], JSON_THROW_ON_ERROR),
                    'created_at' => now(), 'updated_at' => now(),
                ]);
                DB::table('portfolio_cash_accounts')->where('id', $cashAccount->id)->update([
                    'reserved_balance' => (float) $cashAccount->reserved_balance + $reservedCapital, 'updated_at' => now(),
                ]);

                return false;
            }
        }
        $quantity = floor($allocated / $price);
        if ($quantity < 1) {
            return false;
        }
        $allocated = $quantity * $price;

        $position = PortfolioPosition::query()->make([
            'portfolio_id' => $portfolio->id,
            'instrument_id' => $candidate->instrument_id,
        ]);
        $positionMeta = [];
        data_set($positionMeta, 'automation.position_factor', $factor);
        data_set($positionMeta, 'automation.strategy_id', $strategy->id);
        data_set($positionMeta, 'automation.exit_strategy', 'variable_instrument_horizon');
        data_set($positionMeta, 'automation.exit_holding_days', $exitStrategy['holding_days']);
        data_set($positionMeta, 'automation.exit_profile_id', $exitStrategy['profile_id']);
        data_set($positionMeta, 'automation.exit_model_signature', $exitStrategy['model_signature']);
        data_set($positionMeta, 'automation.exit_target_price', $exitStrategy['target_price'] ?? null);
        $position->fill([
            'quantity' => $quantity,
            'average_buy_price' => $price,
            'current_price' => $price,
            'opened_at_date' => $position->opened_at_date ?: now()->toDateString(),
            'meta' => $positionMeta,
        ])->save();

        $transaction = PortfolioTransaction::query()->create([
            'portfolio_id' => $portfolio->id,
            'instrument_id' => $candidate->instrument_id,
            'type' => 'buy',
            'transaction_date' => now()->toDateString(),
            'quantity' => $quantity,
            'price' => $price,
            'fees' => $tradeCost,
            'currency' => $purchase['currency'],
            'meta' => [
                'source' => 'strategy_automation', 'strategy_id' => $strategy->id,
                'prediction_id' => $candidate->prediction_id, 'sector' => $candidate->sector,
                'sector_average_score' => round($sectorAverage, 4), 'position_factor' => $factor,
                'index_average_score' => $indexAverage !== null ? round($indexAverage, 4) : null,
                'base_position_capital' => round($baseCapital, 2),
                'target_position_capital' => round($baseCapital * $factor, 2),
                'calculated_quantity' => (int) $quantity,
                'exit_strategy' => 'variable_instrument_horizon',
                'exit_holding_days' => $exitStrategy['holding_days'],
                'exit_profile_id' => $exitStrategy['profile_id'],
                'exit_profile_source' => $exitStrategy['source'],
                'pricing_source' => $purchase['listing'] !== null ? 'german_listing' : 'primary_listing',
                'pricing_listing' => $purchase['listing'],
                'primary_currency' => $purchase['primary_currency'],
            ],
        ]);
        DB::afterCommit(fn () => app(PublicPortfolioFollowerNotifier::class)->send((int) $transaction->id));

        $balanceAfterPurchase = (float) $cashAccount->balance - $allocated;
        $balanceAfterFee = $balanceAfterPurchase - $tradeCost;
        if ($balanceAfterFee < 0) {
            throw new \RuntimeException('Verrechnungskonto reicht für Kauf und Gebühren nicht aus.');
        }
        DB::table('portfolio_cash_accounts')->where('id', $cashAccount->id)->update([
            'balance' => $balanceAfterFee, 'updated_at' => now(),
        ]);
        DB::table('portfolio_cash_ledger')->insert([
            [
                'portfolio_cash_account_id' => $cashAccount->id, 'portfolio_transaction_id' => $transaction->id,
                'type' => 'purchase_debit', 'amount' => -$allocated, 'balance_after' => $balanceAfterPurchase,
                'currency' => $cashAccount->currency, 'occurred_at' => now(),
                'meta' => json_encode(['source' => 'laravel_strategy_automation', 'prediction_id' => $candidate->prediction_id], JSON_THROW_ON_ERROR),
                'created_at' => now(), 'updated_at' => now(),
            ],
            [
                'portfolio_cash_account_id' => $cashAccount->id, 'portfolio_transaction_id' => $transaction->id,
                'type' => 'fee', 'amount' => -$tradeCost, 'balance_after' => $balanceAfterFee,
                'currency' => $cashAccount->currency, 'occurred_at' => now(),
                'meta' => json_encode(['source' => 'laravel_strategy_automation', 'prediction_id' => $candidate->prediction_id], JSON_THROW_ON_ERROR),
                'created_at' => now(), 'updated_at' => now(),
            ],
        ]);

        DB::table('portfolio_automation_executions')->insert([
            'saved_prediction_filter_id' => $strategy->id,
            'portfolio_id' => $portfolio->id,
            'prediction_id' => $candidate->prediction_id,
            'instrument_id' => $candidate->instrument_id,
            'portfolio_transaction_id' => $transaction->id,
            'action' => 'buy',
            'sector_average_score' => $sectorAverage,
            'position_factor' => $factor,
            'allocated_capital' => $allocated,
            'details' => json_encode([
                'symbol' => $candidate->symbol,
                'score' => $candidate->score_10,
                'confidence' => $candidate->confidence_percent,
                'index_average_score' => $indexAverage !== null ? round($indexAverage, 4) : null,
                'base_position_capital' => round($baseCapital, 2),
                'target_position_capital' => round($baseCapital * $factor, 2),
                'calculated_quantity' => (int) $quantity,
                'exit_holding_days' => $exitStrategy['holding_days'],
                'exit_profile_id' => $exitStrategy['profile_id'],
            ], JSON_THROW_ON_ERROR),
            'created_at' => now(), 'updated_at' => now(),
        ]);

        return true;
    }

    private function processDynamicExits(SavedPredictionFilter $strategy, Portfolio $portfolio): void
    {
        $fixed20dExit = (bool) data_get($strategy->filters, 'fixed_20d_exit_enabled', false);
        $dynamicHorizonExit = (bool) data_get($strategy->filters, 'dynamic_horizon_exit_enabled', false);
        $supportStop = (bool) data_get($strategy->filters, 'support_stop_enabled', false);
        $resistanceExit = (bool) data_get($strategy->filters, 'resistance_trailing_stop_enabled', false);
        $forecastBelowPriceExit = (string) data_get($strategy->filters, 'exit_strategy', '') === 'forecast_below_price'
            || (bool) data_get($strategy->filters, 'forecast_below_price_exit_enabled', false);
        PortfolioPosition::query()->where('portfolio_id', $portfolio->id)->get()
            ->filter(fn (PortfolioPosition $position): bool => (int) data_get($position->meta, 'automation.strategy_id', 0) === (int) $strategy->id)
            ->each(function (PortfolioPosition $position) use ($strategy, $portfolio, $fixed20dExit, $dynamicHorizonExit, $supportStop, $resistanceExit, $forecastBelowPriceExit): void {
                $quote = DB::table('current_stock_quotes')->where('instrument_id', $position->instrument_id)
                    ->whereIn('status', ['ok', 'current'])->orderByDesc('quote_time')->orderByDesc('id')->value('price');
                $price = is_numeric($quote) ? (float) $quote : (float) $position->current_price;
                if ($price <= 0 || $position->quantity <= 0) {
                    return;
                }

                $levels = $this->priceLevels->levels((int) $position->instrument_id);
                $tradingDaysHeld = $position->opened_at_date
                    ? DB::table('price_bars')->where('instrument_id', $position->instrument_id)->where('interval', '1d')
                        ->whereDate('bar_time', '>=', $position->opened_at_date->toDateString())->distinct('bar_time')->count('bar_time')
                    : 0;
                $fixedExitTrigger = $fixed20dExit && $tradingDaysHeld >= 20;
                $dynamicExitDays = max(1, (int) data_get($position->meta, 'automation.exit_holding_days', 20));
                $dynamicExitTrigger = $dynamicHorizonExit && $tradingDaysHeld >= $dynamicExitDays;
                // Every automated entry stores the exit horizon resolved from
                // its concrete serving model. A saved strategy therefore does
                // not need (and must not override) a separate exit selection.
                $modelExitTrigger = data_get($position->meta, 'automation.exit_strategy') === 'variable_instrument_horizon'
                    && $tradingDaysHeld >= $dynamicExitDays;
                $supportTrigger = $supportStop && is_numeric($levels['support']) && $price < (float) $levels['support'] * .99;
                $latestForecast = $forecastBelowPriceExit
                    ? DB::connection('serving')->table('serving_predictions')
                        ->where('instrument_id', $position->instrument_id)
                        ->where('horizon', 20)->where('variant', 'standard')
                        ->orderByDesc('as_of')->orderByDesc('id')->value('target_price')
                    : null;
                $forecastBelowPriceTrigger = $forecastBelowPriceExit && is_numeric($latestForecast)
                    && (float) $latestForecast > 0 && (float) $latestForecast < $price;
                $positionMeta = (array) $position->meta;
                $trailingStop = data_get($positionMeta, 'automation.resistance_trailing_stop');
                $profitable = $price > (float) $position->average_buy_price;
                if ($resistanceExit && $profitable && is_numeric($levels['broken_resistance'])) {
                    $newStop = (float) $levels['broken_resistance'] * .99;
                    if (! is_numeric($trailingStop) || $newStop > (float) $trailingStop) {
                        data_set($positionMeta, 'automation.resistance_trailing_stop', $newStop);
                        data_set($positionMeta, 'automation.resistance_broken_at', now()->toIso8601String());
                        $position->forceFill(['meta' => $positionMeta, 'current_price' => $price])->save();
                        $trailingStop = $newStop;
                    }
                }
                $trailingTrigger = is_numeric($trailingStop) && $price < (float) $trailingStop;
                if (! $modelExitTrigger && ! $fixedExitTrigger && ! $dynamicExitTrigger && ! $supportTrigger && ! $trailingTrigger && ! $forecastBelowPriceTrigger) {
                    return;
                }

                $reason = $modelExitTrigger ? 'model_prediction_horizon'
                    : ($fixedExitTrigger ? 'fixed_20_trading_days'
                    : ($dynamicExitTrigger ? 'dynamic_prediction_horizon'
                    : ($supportTrigger ? 'support_stop_1_percent'
                    : ($trailingTrigger ? 'resistance_trailing_stop' : 'forecast_below_current_price'))));
                DB::transaction(function () use ($strategy, $position, $portfolio, $price, $levels, $reason): void {
                    $locked = PortfolioPosition::query()->lockForUpdate()->find($position->id);
                    if (! $locked) {
                        return;
                    }
                    $quantity = (float) $locked->quantity;
                    $proceeds = $quantity * $price;
                    $costBasis = $quantity * (float) $locked->average_buy_price;
                    $account = DB::table('portfolio_cash_accounts')->where('portfolio_id', $portfolio->id)
                        ->where('currency', $portfolio->currency)->lockForUpdate()->first();
                    if (! $account) {
                        return;
                    }
                    $transactionId = DB::table('portfolio_transactions')->insertGetId([
                        'portfolio_id' => $portfolio->id, 'instrument_id' => $locked->instrument_id,
                        'type' => 'sell', 'transaction_date' => now()->toDateString(), 'quantity' => $quantity,
                        'price' => $price, 'fees' => 0, 'currency' => $portfolio->currency,
                        'meta' => json_encode(['source' => 'strategy_automation', 'exit_reason' => $reason,
                            'support' => $levels['support'], 'resistance' => $levels['resistance'],
                            'realized_profit' => $proceeds - $costBasis], JSON_THROW_ON_ERROR),
                        'created_at' => now(), 'updated_at' => now(),
                    ]);
                    DB::afterCommit(fn () => app(PublicPortfolioFollowerNotifier::class)->send((int) $transactionId));
                    $balance = (float) $account->balance + $proceeds;
                    DB::table('portfolio_cash_accounts')->where('id', $account->id)->update(['balance' => $balance, 'updated_at' => now()]);
                    DB::table('portfolio_cash_ledger')->insert([
                        'portfolio_cash_account_id' => $account->id, 'portfolio_transaction_id' => $transactionId,
                        'type' => 'sale_credit', 'amount' => $proceeds, 'balance_after' => $balance,
                        'currency' => $portfolio->currency, 'occurred_at' => now(),
                        'meta' => json_encode(['source' => 'strategy_automation', 'exit_reason' => $reason], JSON_THROW_ON_ERROR),
                        'created_at' => now(), 'updated_at' => now(),
                    ]);
                    $predictionId = (int) DB::table('predictions')->where('instrument_id', $locked->instrument_id)->latest('id')->value('id');
                    if ($predictionId > 0) {
                        DB::table('portfolio_automation_executions')->insert([
                            'saved_prediction_filter_id' => $strategy->id, 'portfolio_id' => $portfolio->id,
                            'prediction_id' => $predictionId, 'instrument_id' => $locked->instrument_id,
                            'portfolio_transaction_id' => $transactionId, 'action' => 'sell',
                            'position_factor' => max(1, (int) data_get($locked->meta, 'automation.position_factor', 1)),
                            'allocated_capital' => $proceeds, 'details' => json_encode(['exit_reason' => $reason,
                                'support' => $levels['support'], 'resistance' => $levels['resistance']], JSON_THROW_ON_ERROR),
                            'created_at' => now(), 'updated_at' => now(),
                        ]);
                    }
                    $locked->delete();
                }, 3);
            });
    }

    private function processEntryReservations(SavedPredictionFilter $strategy, Portfolio $portfolio, Collection $candidates): Collection
    {
        $active = DB::table('portfolio_strategy_reservations')->where('saved_prediction_filter_id', $strategy->id)
            ->where('portfolio_id', $portfolio->id)->where('status', 'active')->orderBy('id')->get();
        $remaining = collect();
        foreach ($active as $reservation) {
            $alreadyHeld = PortfolioPosition::query()
                ->where('portfolio_id', $portfolio->id)
                ->where('instrument_id', $reservation->instrument_id)
                ->exists();
            if ($alreadyHeld) {
                $this->releaseReservation($reservation, 'already_held');

                continue;
            }
            $candidate = $candidates->firstWhere('instrument_id', $reservation->instrument_id);
            $expired = now()->greaterThanOrEqualTo($reservation->expires_at);
            if ($expired || ! $candidate) {
                $this->releaseReservation($reservation, $expired ? 'expired' : 'signal_invalid');

                continue;
            }
            $price = (float) ($candidate->quote_price ?: $candidate->current_price);
            $highestTarget = (float) collect([10, 20, 40])->map(fn (int $days): float => (float) data_get($candidate, 'predicted_price_'.$days.'d', 0))->max();
            if ($price > 0 && $highestTarget > 0 && $price <= $highestTarget) {
                $this->releaseReservation($reservation, 'converted');
                DB::transaction(fn (): bool => $this->buyCandidate($strategy, $portfolio, $candidate, 0.0, null, true), 3);

                continue;
            }
            $remaining->push((int) $reservation->instrument_id);
        }

        return $remaining;
    }

    private function releaseReservation(object $reservation, string $status): void
    {
        DB::transaction(function () use ($reservation, $status): void {
            $locked = DB::table('portfolio_strategy_reservations')->where('id', $reservation->id)->where('status', 'active')->lockForUpdate()->first();
            if (! $locked) {
                return;
            }
            $account = DB::table('portfolio_cash_accounts')->where('portfolio_id', $locked->portfolio_id)->lockForUpdate()->first();
            if ($account) {
                DB::table('portfolio_cash_accounts')->where('id', $account->id)->update([
                    'reserved_balance' => max(0, (float) $account->reserved_balance - (float) $locked->reserved_capital), 'updated_at' => now(),
                ]);
            }
            DB::table('portfolio_strategy_reservations')->where('id', $locked->id)->update([
                'status' => $status, 'released_at' => now(), 'updated_at' => now(),
            ]);
        }, 3);
    }
}
