<?php

namespace App\Http\Controllers;

use App\Enums\PlanLevel;
use App\Services\FreeRegionalStockUniverseService;
use App\Services\PlanAccessService;
use App\Services\PersonalizedSignalService;
use App\Services\TwelveDataService;
use App\Support\AiScore;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;
use Throwable;

final class RecommendationController extends Controller
{
    public function liveQuotes(Request $request, TwelveDataService $marketData): JsonResponse
    {
        $symbols = collect(explode(',', (string) $request->query('symbols')))
            ->map(fn (string $symbol): string => strtoupper(trim($symbol)))
            ->filter()
            ->unique()
            ->take(3);

        $instruments = DB::table('instruments')
            ->whereIn(DB::raw('UPPER(symbol)'), $symbols)
            ->where('type', 'stock')
            ->where('is_active', true)
            ->whereNull('deleted_at')
            ->get(['symbol', 'provider_symbol', 'currency', 'german_listing_symbol', 'german_listing_exchange', 'german_listing_currency']);

        $quotes = $instruments->mapWithKeys(function (object $instrument) use ($marketData): array {
            $streamQuote = Cache::get('twelve_data_stream_quote_'.sha1(strtoupper((string) $instrument->symbol)));
            try {
                $usesGermanListing = filled($instrument->german_listing_symbol)
                    && strtoupper((string) $instrument->german_listing_currency) === 'EUR';
                $referenceQuote = $usesGermanListing
                    ? $marketData->listingQuote((string) $instrument->german_listing_symbol, $instrument->german_listing_exchange ?: null)
                    : $marketData->quote((string) ($instrument->provider_symbol ?: $instrument->symbol));
                $quote = is_numeric($streamQuote['price'] ?? null)
                    ? [...($referenceQuote ?? []), ...$streamQuote]
                    : ($usesGermanListing
                        ? $marketData->listingQuote((string) $instrument->german_listing_symbol, $instrument->german_listing_exchange ?: null)
                        : $marketData->liveQuote((string) ($instrument->provider_symbol ?: $instrument->symbol)));
            } catch (Throwable) {
                $quote = null;
            }

            if (! is_numeric($quote['price'] ?? null)) {
                return [];
            }

            $timestamp = is_numeric($quote['timestamp'] ?? null)
                ? (int) $quote['timestamp']
                : now()->timestamp;
            $ageSeconds = max(0, now()->timestamp - $timestamp);

            return [(string) $instrument->symbol => [
                'price' => (float) $quote['price'],
                'currency' => $usesGermanListing ? 'EUR' : (string) (($quote['currency'] ?? null) ?: $instrument->currency ?: ''),
                'change_percent' => is_numeric($quote['change_percent'] ?? null)
                    ? (float) $quote['change_percent']
                    : null,
                'timestamp' => $timestamp,
                'age_seconds' => $ageSeconds,
                'realtime' => $ageSeconds < 120,
            ]];
        });

        return response()->json(['quotes' => $quotes]);
    }

    /**
     * Beginner-friendly screener with only the decision-relevant fields.
     */
    public function screener(Request $request): View
    {
        $isFreeRegional = app(PlanAccessService::class)->level($request->user()) === PlanLevel::Free;
        $regionalUniverse = app(FreeRegionalStockUniverseService::class);
        $allowedInstrumentIds = $isFreeRegional ? $regionalUniverse->instrumentIds($request->user())->all() : [];
        $regionalCountry = $regionalUniverse->country($request->user());
        // Keep older installations usable until the optional description
        // columns have been added by the accompanying migration.
        $hasBusinessSummary = Schema::hasColumn('instruments', 'business_summary');
        $hasBusinessSummaryEn = Schema::hasColumn('instruments', 'business_summary_en');
        $hasBusinessDescription = Schema::hasColumn('instruments', 'business_description');
        $hasBusinessDescriptionEn = Schema::hasColumn('instruments', 'business_description_en');
        $personalizedSignals = app(PersonalizedSignalService::class);
        $signalSql = $personalizedSignals->sql('prediction', $request->user());
        $sectorVolatilityPercentileSql = $personalizedSignals->sectorVolatilityPercentileSql('prediction');
        $requestedLimit = strtolower((string) $request->query('limit', '10'));
        $resultLimit = match ($requestedLimit) {
            '25' => 25,
            '50' => 50,
            '100' => 100,
            'all' => null,
            default => 10,
        };
        $transitionDays = in_array((int) $request->query('transition_days'), [1, 5, 10, 20], true)
            ? (int) $request->query('transition_days')
            : null;
        $selectedIndexSymbol = trim((string) $request->query('index'));
        $selectedMarketIndexId = null;
        $selectedIndexCountry = null;
        if ($selectedIndexSymbol !== '') {
            if (Schema::hasTable('market_indices') && Schema::hasTable('index_memberships')) {
                $selectedMarketIndexId = DB::table('market_indices')
                    ->where('symbol', $selectedIndexSymbol)
                    ->value('id');
            }
            $selectedIndexCountry = DB::table('instruments')
                ->where('type', 'index')
                ->where('symbol', $selectedIndexSymbol)
                ->value('country');
        }
        $latestIds = DB::table('predictions as latest')
            ->selectRaw('DISTINCT ON (latest.instrument_id) latest.id')
            ->orderBy('latest.instrument_id')
            ->orderByDesc('latest.prediction_time')
            ->orderByDesc('latest.id');
        $walkForwardRunIds = DB::table('walk_forward_backtest_runs')
            ->where('status', 'completed')
            ->whereIn('horizon_days', [5, 10, 15, 20])
            ->orderByDesc('id')
            ->get(['id', 'horizon_days'])
            ->unique('horizon_days')
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->values();
        $profitFactorByInstrument = $walkForwardRunIds->isNotEmpty()
            ? DB::table('walk_forward_backtest_trades as walk_forward_trade')
                ->join('walk_forward_backtest_runs as walk_forward_run', 'walk_forward_run.id', '=', 'walk_forward_trade.run_id')
                ->whereIn('walk_forward_trade.run_id', $walkForwardRunIds)
                ->where('walk_forward_trade.signal_date', '>=', now()->subYears(3)->toDateString())
                ->groupBy('walk_forward_trade.instrument_id', 'walk_forward_trade.run_id', 'walk_forward_run.horizon_days')
                ->select('walk_forward_trade.instrument_id', 'walk_forward_trade.run_id', 'walk_forward_run.horizon_days')
                ->selectRaw('COUNT(*) AS trade_count')
                ->selectRaw("SUM(CASE WHEN net_return > 0 THEN net_return ELSE 0 END) / NULLIF(ABS(SUM(CASE WHEN net_return < 0 THEN net_return ELSE 0 END)), 0) AS profit_factor")
                ->selectRaw('AVG(walk_forward_trade.net_return) * 100 AS average_profit_per_trade_percent')
                ->selectRaw('AVG(CASE WHEN net_return > 0 THEN 1.0 ELSE 0.0 END) * 100 AS hit_rate')
                ->get()
                ->groupBy('instrument_id')
            : collect();
        $drawdownByInstrument = $walkForwardRunIds->isNotEmpty()
            ? DB::table('walk_forward_backtest_year_stats')
                ->whereIn('run_id', $walkForwardRunIds)
                ->whereNotNull('maximum_drawdown')
                ->groupBy('instrument_id', 'run_id')
                ->select('instrument_id', 'run_id')
                ->selectRaw('PERCENTILE_CONT(0.90) WITHIN GROUP (ORDER BY ABS(maximum_drawdown)) * 100 AS drawdown_p90')
                ->get()->groupBy('instrument_id')
            : collect();
        $latestQualityRankings = DB::table('model_quality_rankings')
            ->selectRaw('trained_model_id, MAX(id) AS ranking_id')
            ->groupBy('trained_model_id');
        $globalRanking = DB::table('predictions as ranked_prediction')
            ->join('instruments as ranked_instrument', 'ranked_instrument.id', '=', 'ranked_prediction.instrument_id')
            ->leftJoinSub($latestQualityRankings, 'latest_ranking_quality', fn ($join) => $join
                ->on('latest_ranking_quality.trained_model_id', '=', 'ranked_prediction.trained_model_id'))
            ->leftJoin('model_quality_rankings as ranking_quality', 'ranking_quality.id', '=', 'latest_ranking_quality.ranking_id')
            ->whereIn('ranked_prediction.id', clone $latestIds)
            ->where('ranked_instrument.type', 'stock')
            ->where('ranked_instrument.is_active', true)
            ->whereNull('ranked_instrument.deleted_at')
            ->when($isFreeRegional, fn ($query) => $query->whereIn('ranked_instrument.id', $allowedInstrumentIds))
            ->get([
                'ranked_prediction.instrument_id', 'ranked_prediction.prediction_score',
                'ranked_prediction.confidence', 'ranked_prediction.risk_score',
                'ranked_prediction.drawdown_risk_factor', 'ranked_prediction.current_price',
                'ranked_prediction.predicted_price_20d', 'ranked_prediction.signal',
                'ranked_prediction.horizon_fusion_noise_passed',
                'ranked_prediction.horizon_fusion_stability_passed',
                'ranked_prediction.horizon_fusion_stability_score',
                'ranked_instrument.sector',
                'ranking_quality.quality_score as model_quality_score',
            ])
            ->map(function (object $row) use ($profitFactorByInstrument, $drawdownByInstrument): object {
                $grossReturn = (float) $row->current_price !== 0.0
                    ? (((float) $row->predicted_price_20d - (float) $row->current_price) / (float) $row->current_price) * 100
                    : 0.0;
                $return = max(-20.0, min(20.0, $grossReturn - max(0.0, (float) config('aktienki.signals.round_trip_cost_percent', 0.5))));
                $returnScore = max(0, min(100, 50 + ($return * 5)));
                $confidence = (float) $row->confidence;
                $confidence = max(0, min(100, $confidence <= 1 ? $confidence * 100 : $confidence));
                $allHorizonStats = collect($profitFactorByInstrument->get($row->instrument_id, collect()))
                    ->filter(fn (object $stat): bool => (int) ($stat->trade_count ?? 0) >= 10);
                $horizonStats = $allHorizonStats
                    ->filter(fn (object $stat): bool => is_numeric($stat->profit_factor ?? null));
                $tradeCount = (int) $horizonStats->sum(fn (object $stat): int => (int) $stat->trade_count);
                $profitFactors = $horizonStats->map(function (object $stat): float {
                    $reliability = (int) $stat->trade_count / ((int) $stat->trade_count + 20);
                    return 1 + ((max(0.0, min(2.5, (float) $stat->profit_factor)) - 1) * $reliability);
                });
                $hitRates = $horizonStats->filter(fn (object $stat): bool => is_numeric($stat->hit_rate ?? null))->map(function (object $stat): float {
                    $reliability = (int) $stat->trade_count / ((int) $stat->trade_count + 20);
                    return 50 + (((float) $stat->hit_rate - 50) * $reliability);
                });
                $profitTradeStats = $allHorizonStats
                    ->filter(fn (object $stat): bool => is_numeric($stat->average_profit_per_trade_percent ?? null));
                $profitTradeCount = (int) $profitTradeStats->sum(fn (object $stat): int => (int) $stat->trade_count);
                $profitFactor = $profitFactors->isNotEmpty() ? (float) $profitFactors->avg() : null;
                $profitPerTrade = $profitTradeCount > 0
                    ? (float) $profitTradeStats->sum(fn (object $stat): float => (float) $stat->average_profit_per_trade_percent * (int) $stat->trade_count) / $profitTradeCount
                    : null;
                $displayProfitPerTrade = $profitPerTrade;
                $hitRate = $hitRates->isNotEmpty() ? (float) $hitRates->avg() : null;
                $drawdowns = collect($drawdownByInstrument->get($row->instrument_id, collect()))
                    ->filter(fn (object $stat): bool => is_numeric($stat->drawdown_p90 ?? null))
                    ->pluck('drawdown_p90')->map(fn ($value): float => (float) $value);
                $drawdown = $drawdowns->isNotEmpty() ? (float) $drawdowns->avg() : null;
                $modelQuality = is_numeric($row->model_quality_score)
                    ? max(0, min(100, (float) $row->model_quality_score * 100))
                    : null;
                $noiseAvailable = $row->horizon_fusion_noise_passed !== null;
                $stabilityAvailable = $row->horizon_fusion_stability_passed !== null;
                $noisePassed = $row->horizon_fusion_noise_passed === true;
                $stabilityPassed = $row->horizon_fusion_stability_passed === true;
                $noiseScore = $noisePassed ? 100.0 : 0.0;
                $stabilityScore = $stabilityPassed && is_numeric($row->horizon_fusion_stability_score)
                    ? max(0, min(100, (float) $row->horizon_fusion_stability_score * 100))
                    : 0.0;
                $components = collect([
                    ['value' => $profitFactor !== null ? max(0, min(100, (($profitFactor - 0.5) / 2.0) * 100)) : null, 'weight' => 20],
                    ['value' => $profitPerTrade !== null ? max(0, min(100, 50 + ($profitPerTrade * 12.5))) : null, 'weight' => 10],
                    ['value' => $confidence, 'weight' => 20],
                    ['value' => $returnScore, 'weight' => 15],
                    ['value' => $drawdown !== null ? max(0, min(100, 100 - (($drawdown / 50) * 100))) : null, 'weight' => 15],
                    ['value' => $hitRate, 'weight' => 10],
                    ['value' => $modelQuality, 'weight' => 5],
                    ['value' => $noiseAvailable ? $noiseScore : null, 'weight' => 2.5],
                    ['value' => $stabilityAvailable ? $stabilityScore : null, 'weight' => 2.5],
                ])->filter(fn (array $component): bool => $component['value'] !== null);
                $availableWeight = (float) $components->sum('weight');
                $row->ranking_score = round($availableWeight > 0
                    ? (float) $components->sum(fn (array $component): float => $component['value'] * $component['weight']) / $availableWeight
                    : max(0, min(100, (float) $row->prediction_score)), 2);
                $row->expected_return_20d = $return;
                $row->profit_factor = $profitFactor;
                $row->normalized_profit_per_trade = $profitPerTrade;
                $row->average_profit_per_trade_percent = $displayProfitPerTrade;
                $row->profit_factor_trade_count = $tradeCount;
                $row->backtest_hit_rate = $hitRate;
                $row->backtest_drawdown = $drawdown;
                $row->model_quality_percent = $modelQuality;
                $row->noise_passed = $noisePassed;
                $row->stability_passed = $stabilityPassed;
                $row->stability_percent = $stabilityScore;
                $row->noise_available = $noiseAvailable;
                $row->stability_available = $stabilityAvailable;

                return $row;
            })
            ->filter(fn (object $row): bool => $row->expected_return_20d >= 0)
            ->sortByDesc('ranking_score')
            ->values();
        $globalRankByInstrument = $globalRanking
            ->mapWithKeys(fn (object $row, int $index): array => [(int) $row->instrument_id => $index + 1]);
        $sectorRankByInstrument = collect();
        $globalRanking
            ->groupBy(fn (object $row): string => filled($row->sector) ? (string) $row->sector : '__without_sector__')
            ->each(function ($sectorStocks) use ($sectorRankByInstrument): void {
                $sectorStocks->values()->each(
                    fn (object $row, int $index) => $sectorRankByInstrument->put((int) $row->instrument_id, $index + 1)
                );
            });
        $globalScoreByInstrument = $globalRanking
            ->mapWithKeys(fn (object $row): array => [(int) $row->instrument_id => (float) $row->ranking_score]);
        $globalProfitFactorByInstrument = $globalRanking
            ->mapWithKeys(fn (object $row): array => [(int) $row->instrument_id => $row->profit_factor]);
        $globalProfitFactorTradesByInstrument = $globalRanking
            ->mapWithKeys(fn (object $row): array => [(int) $row->instrument_id => $row->profit_factor_trade_count]);
        $globalProfitPerTradeByInstrument = $globalRanking
            ->mapWithKeys(fn (object $row): array => [(int) $row->instrument_id => $row->normalized_profit_per_trade]);
        $globalDisplayedProfitPerTradeByInstrument = $globalRanking
            ->mapWithKeys(fn (object $row): array => [(int) $row->instrument_id => $row->average_profit_per_trade_percent]);
        $globalMetricsByInstrument = $globalRanking->mapWithKeys(fn (object $row): array => [(int) $row->instrument_id => [
            'hit_rate' => $row->backtest_hit_rate,
            'drawdown' => $row->backtest_drawdown,
            'model_quality' => $row->model_quality_percent,
            'noise_passed' => $row->noise_passed,
            'stability_passed' => $row->stability_passed,
            'stability_percent' => $row->stability_percent,
            'noise_available' => $row->noise_available,
            'stability_available' => $row->stability_available,
        ]]);

        $latestFundamentals = DB::table('instrument_fundamentals as latest_fundamental')
            ->selectRaw('DISTINCT ON (latest_fundamental.instrument_id) latest_fundamental.instrument_id, latest_fundamental.trailing_pe, latest_fundamental.forward_pe, latest_fundamental.dividend_yield')
            ->orderBy('latest_fundamental.instrument_id')
            ->orderByDesc('latest_fundamental.snapshot_date')
            ->orderByDesc('latest_fundamental.id');

        $query = DB::table('predictions as prediction')
            ->join('instruments as instrument', 'instrument.id', '=', 'prediction.instrument_id')
            ->leftJoin('exchanges as exchange', 'exchange.id', '=', 'instrument.exchange_id')
            ->leftJoinSub($latestFundamentals, 'fundamental', fn ($join) => $join->on('fundamental.instrument_id', '=', 'instrument.id'))
            ->whereIn('prediction.id', $latestIds)
            ->where('instrument.type', 'stock')
            ->where('instrument.is_active', true)
            ->where('instrument.is_german_tradeable', true)
            ->whereNull('instrument.deleted_at')
            ->select([
                'prediction.id', 'prediction.instrument_id', 'prediction.prediction_time',
                'prediction.current_price', 'prediction.predicted_price_20d',
                'prediction.signal as model_signal',
                'prediction.timeframe', 'prediction.ai_type', 'prediction.model_scope',
                'prediction.prediction_horizon_minutes',
                'prediction.prediction_score', 'prediction.confidence',
                'prediction.risk_score', 'prediction.drawdown_risk_factor',
                'prediction.quality_gate_blockers',
                'instrument.symbol', 'instrument.name', 'instrument.isin', 'instrument.country', 'instrument.currency', 'instrument.sector',
                'instrument.german_listing_symbol', 'instrument.german_listing_exchange',
                'instrument.german_listing_currency', 'instrument.german_listing_verified_at',
                'exchange.name as exchange_name', 'exchange.code as exchange_code',
                'fundamental.trailing_pe', 'fundamental.forward_pe', 'fundamental.dividend_yield',
            ])
            ->when($hasBusinessSummary, fn ($builder) => $builder->addSelect('instrument.business_summary'))
            ->when($hasBusinessSummaryEn, fn ($builder) => $builder->addSelect('instrument.business_summary_en'))
            ->when($hasBusinessDescription, fn ($builder) => $builder->addSelect('instrument.business_description'))
            ->when($hasBusinessDescriptionEn, fn ($builder) => $builder->addSelect('instrument.business_description_en'))
            ->selectRaw("{$signalSql} AS personalized_signal")
            ->selectRaw("{$sectorVolatilityPercentileSql} * 100 AS sector_volatility_percentile")
            ->selectRaw($personalizedSignals->annualizedVolatilitySql('prediction').' AS annualized_volatility')
            ->selectRaw("CASE WHEN prediction.prediction_score <= 1 THEN prediction.prediction_score * 10 WHEN prediction.prediction_score <= 10 THEN prediction.prediction_score ELSE prediction.prediction_score / 10 END AS score_10")
            ->selectRaw("CASE WHEN prediction.confidence <= 1 THEN prediction.confidence * 100 ELSE prediction.confidence END AS confidence_percent")
            // Keep the screener consistent with score(): a model drawdown of
            // zero does not mean that holding an individual stock is risk-free.
            ->selectRaw("LEAST(100, GREATEST(20, COALESCE(CASE WHEN COALESCE(prediction.risk_score, prediction.drawdown_risk_factor) <= 1 THEN COALESCE(prediction.risk_score, prediction.drawdown_risk_factor) * 100 ELSE COALESCE(prediction.risk_score, prediction.drawdown_risk_factor) END, 50))) AS risk_percent")
            ->selectRaw('((prediction.predicted_price_20d - prediction.current_price) / NULLIF(prediction.current_price, 0)) * 100 AS expected_return_20d')
            ->whereIn('prediction.instrument_id', $globalRankByInstrument->keys()->all())
            ->when($request->filled('q'), function ($builder) use ($request): void {
                $term = '%'.strtolower(trim((string) $request->query('q'))).'%';
                $builder->where(function ($nested) use ($term): void {
                    $nested->whereRaw('LOWER(instrument.symbol) LIKE ?', [$term])
                        ->orWhereRaw('LOWER(instrument.name) LIKE ?', [$term]);
                });
            })
            ->when($request->filled('country'), fn ($builder) => $builder->where('instrument.country', strtoupper((string) $request->query('country'))))
            ->when($request->filled('sector'), fn ($builder) => $builder->where('instrument.sector', (string) $request->query('sector')))
            ->when($selectedIndexSymbol !== '', function ($builder) use ($selectedMarketIndexId, $selectedIndexCountry): void {
                if ($selectedMarketIndexId) {
                    $builder->whereExists(function ($membership) use ($selectedMarketIndexId): void {
                        $membership->selectRaw('1')
                            ->from('index_memberships as membership')
                            ->whereColumn('membership.instrument_id', 'instrument.id')
                            ->where('membership.market_index_id', $selectedMarketIndexId)
                            ->whereNull('membership.removed_at');
                    });
                } elseif (filled($selectedIndexCountry)) {
                    $builder->where('instrument.country', strtoupper((string) $selectedIndexCountry));
                } else {
                    $builder->whereRaw('1 = 0');
                }
            })
            // Filter and visible badge must use the same signal. Otherwise a
            // personalized BUY could appear on the BUY page as a HOLD card.
            ->when(in_array(strtoupper((string) $request->query('signal')), ['BUY', 'WAIT', 'WATCH', 'HOLD', 'SELL'], true), fn ($builder) => $builder->whereRaw("UPPER({$signalSql}) = ?", [strtoupper((string) $request->query('signal'))]))
            ->orderByRaw("CASE UPPER({$signalSql}) WHEN 'BUY' THEN 1 WHEN 'WAIT' THEN 2 WHEN 'WATCH' THEN 3 WHEN 'HOLD' THEN 4 ELSE 5 END")
            ->orderByDesc('expected_return_20d');

        $stocks = $query->get()
            ->sortBy(fn (object $stock): int => (int) ($globalRankByInstrument->get($stock->instrument_id) ?? PHP_INT_MAX));
        if ($transitionDays !== null && $stocks->isNotEmpty()) {
            $candidateSignalHistory = DB::table('predictions')
                ->whereIn('instrument_id', $stocks->pluck('instrument_id')->all())
                ->where('prediction_time', '>=', now()->subYear())
                ->orderBy('prediction_time')
                ->orderBy('id')
                ->get(['instrument_id', 'prediction_time', 'signal', 'timeframe', 'ai_type', 'model_scope', 'prediction_horizon_minutes'])
                ->groupBy('instrument_id');
            $transitionCutoff = now()->subDays($transitionDays);
            $stocks = $stocks->filter(function (object $stock) use ($candidateSignalHistory, $transitionCutoff): bool {
                $previousSignal = null;
                $transitionAt = null;
                foreach ($candidateSignalHistory->get($stock->instrument_id, collect()) as $prediction) {
                    if ((string) $prediction->timeframe !== (string) $stock->timeframe
                        || (string) $prediction->ai_type !== (string) $stock->ai_type
                        || (string) $prediction->model_scope !== (string) $stock->model_scope
                        || (int) $prediction->prediction_horizon_minutes !== (int) $stock->prediction_horizon_minutes) {
                        continue;
                    }
                    $historySignal = strtoupper((string) $prediction->signal);
                    if ($previousSignal !== null && $historySignal !== $previousSignal) {
                        $transitionAt = $prediction->prediction_time;
                    }
                    $previousSignal = $historySignal;
                }

                return $transitionAt !== null
                    && \Illuminate\Support\Carbon::parse($transitionAt)->greaterThanOrEqualTo($transitionCutoff);
            });
        }
        if ($resultLimit !== null) {
            $stocks = $stocks->take($resultLimit);
        }
        $stocks = $stocks->values();

        // German listings are the canonical customer-facing prices. Preserve
        // the model's forecast return when translating its target from the
        // original listing into the current German EUR quote.
        $marketData = app(TwelveDataService::class);
        $euCountries = ['AT','BE','BG','HR','CY','CZ','DK','EE','FI','FR','DE','GR','HU','IE','IT','LV','LT','LU','MT','NL','PL','PT','RO','SK','SI','ES','SE'];
        $userCountry = strtoupper((string) data_get($request->user()?->preferences, 'country_code', 'DE'));
        $preferEuro = in_array($userCountry, $euCountries, true);
        $stocks->each(function (object $stock) use ($marketData, $preferEuro): void {
            $stock->original_price = is_numeric($stock->current_price) ? (float) $stock->current_price : null;
            $stock->original_currency = strtoupper((string) $stock->currency);

            if (! $preferEuro && strtoupper((string) $stock->currency) === 'USD') {
                return;
            }

            $preferredListing = $preferEuro
                ? (filled($stock->german_listing_symbol) && strtoupper((string) $stock->german_listing_currency) === 'EUR'
                    ? ['symbol' => $stock->german_listing_symbol, 'exchange' => $stock->german_listing_exchange, 'currency' => 'EUR'] : null)
                : $marketData->usListing($stock->isin ?? null, (string) $stock->name, (string) $stock->symbol);

            if (! $preferredListing) return;

            try {
                $quote = $marketData->listingQuote(
                    (string) $preferredListing['symbol'],
                    filled($preferredListing['exchange'] ?? null) ? (string) $preferredListing['exchange'] : null,
                );
            } catch (Throwable) {
                $quote = null;
            }

            if (! is_numeric($quote['price'] ?? null) || (float) $quote['price'] <= 0) {
                return;
            }

            $originalPrice = is_numeric($stock->current_price) ? (float) $stock->current_price : null;
            $forecastRatio = $originalPrice && $originalPrice > 0 && is_numeric($stock->predicted_price_20d)
                ? (float) $stock->predicted_price_20d / $originalPrice
                : null;
            $stock->original_symbol = $stock->symbol;
            $stock->display_symbol = $preferredListing['symbol'];
            $stock->current_price = (float) $quote['price'];
            $stock->predicted_price_20d = $forecastRatio !== null ? $stock->current_price * $forecastRatio : null;
            $stock->currency = $preferredListing['currency'];
            $stock->expected_return_20d = $forecastRatio !== null ? ($forecastRatio - 1) * 100 : $stock->expected_return_20d;
        });
        $latestAssessments = collect();
        if (Schema::hasTable('stock_ai_assessments')) {
            $latestAssessments = DB::table('stock_ai_assessments')
                ->select([
                    'instrument_id', 'prediction_id', 'summary', 'opportunities', 'risks',
                    'assessment_date', 'recommendation', 'confidence', 'model',
                ])
                ->whereIn('instrument_id', $stocks->pluck('instrument_id')->all())
                ->orderBy('instrument_id')
                ->orderByDesc('assessment_date')
                ->orderByDesc('id')
                ->get()
                ->unique('instrument_id')
                ->keyBy('instrument_id');
        }
        $decodeAssessmentItems = static function ($value): array {
            if (is_array($value)) {
                return array_values($value);
            }
            $decoded = is_string($value) ? json_decode($value, true) : null;

            return is_array($decoded) ? array_values($decoded) : [];
        };
        $stocks->each(function (object $stock) use ($latestAssessments, $decodeAssessmentItems, $personalizedSignals, $request): void {
            $assessment = $latestAssessments->get($stock->instrument_id);
            $stock->assessment_pros = $decodeAssessmentItems($assessment?->opportunities);
            $stock->assessment_cons = $decodeAssessmentItems($assessment?->risks);
            $stock->assessment_summary = $assessment?->summary;
            $stock->assessment_date = $assessment?->assessment_date;
            $stock->assessment_recommendation = $assessment?->recommendation;
            $stock->assessment_confidence = $assessment?->confidence;
            $stock->assessment_model = $assessment?->model;
            $stock->personal_risk_profile = $personalizedSignals->profileLabel($request->user());
            $stock->personal_signal_explanation = $personalizedSignals->explanation($stock, $request->user());
            $stock->personal_signal_breakdown = $personalizedSignals->breakdown($stock, $request->user());
            $stock->assessment_is_detailed_buy = $assessment !== null
                && (int) $assessment->prediction_id === (int) $stock->id
                && strtoupper((string) $stock->personalized_signal) === 'BUY';
        });
        if (! $hasBusinessSummary) {
            $stocks->each(fn (object $stock) => $stock->business_summary = null);
        }
        if (! $hasBusinessSummaryEn) {
            $stocks->each(fn (object $stock) => $stock->business_summary_en = null);
        }
        if (! $hasBusinessDescription) {
            $stocks->each(fn (object $stock) => $stock->business_description = null);
        }
        if (! $hasBusinessDescriptionEn) {
            $stocks->each(fn (object $stock) => $stock->business_description_en = null);
        }

        // One year of daily closes for the compact screener sparkline. Keep
        // the payload small by reducing long histories to at most 64 points.
        $signalHistoryByInstrument = DB::table('predictions')
            ->whereIn('instrument_id', $stocks->pluck('instrument_id')->all())
            ->where('prediction_time', '>=', now()->subYear())
            ->orderBy('prediction_time')
            ->orderBy('id')
            ->get(['instrument_id', 'prediction_time', 'signal', 'timeframe', 'ai_type', 'model_scope', 'prediction_horizon_minutes'])
            ->groupBy('instrument_id');
        $barsByInstrument = DB::table('price_bars')
            ->whereIn('instrument_id', $stocks->pluck('instrument_id')->all())
            ->where('interval', '1d')
            ->where('bar_time', '>=', now()->subYear())
            ->orderBy('bar_time')
            ->get(['instrument_id', 'bar_time', 'close'])
            ->groupBy('instrument_id');
        $stocks->each(function (object $stock) use ($barsByInstrument, $signalHistoryByInstrument): void {
            $bars = collect($barsByInstrument->get($stock->instrument_id, collect()))
                ->filter(fn (object $bar): bool => is_numeric($bar->close))
                ->values();
            $closes = $bars->map(fn (object $bar): float => (float) $bar->close);
            $transitionAt = null;
            $transitionFrom = null;
            $previousSignal = null;
            $comparableHistory = $signalHistoryByInstrument
                ->get($stock->instrument_id, collect())
                ->filter(fn (object $prediction): bool =>
                    (string) $prediction->timeframe === (string) $stock->timeframe
                    && (string) $prediction->ai_type === (string) $stock->ai_type
                    && (string) $prediction->model_scope === (string) $stock->model_scope
                    && (int) $prediction->prediction_horizon_minutes === (int) $stock->prediction_horizon_minutes
                );
            foreach ($comparableHistory as $prediction) {
                $historySignal = strtoupper((string) $prediction->signal);
                if ($previousSignal !== null && $historySignal !== $previousSignal) {
                    $transitionAt = $prediction->prediction_time;
                    $transitionFrom = $previousSignal;
                }
                $previousSignal = $historySignal;
            }
            $stock->signal_transition_x = null;
            $stock->signal_transition_at = $transitionAt;
            $stock->signal_transition_from = $transitionFrom;
            if ($transitionAt !== null && $bars->count() > 1) {
                $transitionTimestamp = \Illuminate\Support\Carbon::parse($transitionAt)->getTimestamp();
                $transitionIndex = $bars->search(
                    fn (object $bar): bool => \Illuminate\Support\Carbon::parse($bar->bar_time)->getTimestamp() >= $transitionTimestamp
                );
                if ($transitionIndex !== false) {
                    $stock->signal_transition_x = ((int) $transitionIndex / ($bars->count() - 1)) * 600;
                }
            }
            if ($closes->count() > 64) {
                $last = $closes->count() - 1;
                $closes = collect(range(0, 63))->map(fn (int $index): float => $closes->get((int) round($index * $last / 63)))->values();
            }
            $stock->chart_points = $closes->all();
        });
        $stocks->each(function (object $stock) use ($globalRankByInstrument, $sectorRankByInstrument, $globalScoreByInstrument, $globalProfitFactorByInstrument, $globalProfitFactorTradesByInstrument, $globalProfitPerTradeByInstrument, $globalDisplayedProfitPerTradeByInstrument, $globalMetricsByInstrument): void {
            $stock->screening_rank = $globalRankByInstrument->get($stock->instrument_id);
            $stock->sector_rank = $sectorRankByInstrument->get($stock->instrument_id);
            $stock->ranking_score = $globalScoreByInstrument->get($stock->instrument_id);
            $stock->score_10 = is_numeric($stock->ranking_score) ? (float) $stock->ranking_score / 10 : null;
            $stock->ranking_profit_factor = $globalProfitFactorByInstrument->get($stock->instrument_id);
            $stock->ranking_profit_factor_trades = $globalProfitFactorTradesByInstrument->get($stock->instrument_id);
            $stock->ranking_profit_per_trade = $globalProfitPerTradeByInstrument->get($stock->instrument_id);
            $stock->display_profit_per_trade_percent = $globalDisplayedProfitPerTradeByInstrument->get($stock->instrument_id);
            $metrics = $globalMetricsByInstrument->get($stock->instrument_id, []);
            $stock->ranking_hit_rate = $metrics['hit_rate'] ?? null;
            $stock->ranking_drawdown = $metrics['drawdown'] ?? null;
            $stock->ranking_model_quality = $metrics['model_quality'] ?? null;
            $stock->ranking_noise_passed = $metrics['noise_passed'] ?? false;
            $stock->ranking_stability_passed = $metrics['stability_passed'] ?? false;
            $stock->ranking_stability_percent = $metrics['stability_percent'] ?? 0.0;
            $stock->ranking_noise_available = $metrics['noise_available'] ?? false;
            $stock->ranking_stability_available = $metrics['stability_available'] ?? false;
            $simplePros = [];
            $simpleCons = [];
            if ((float) $stock->expected_return_20d > 0) {
                $simplePros[] = __('Positive erwartete 20-Tage-Rendite von :value %.', ['value' => number_format((float) $stock->expected_return_20d, 2, ',', '.')]);
            }
            if (is_numeric($stock->display_profit_per_trade_percent) && (float) $stock->display_profit_per_trade_percent > 0) {
                $simplePros[] = __('Durchschnittlicher Netto-Profit je Trade: :value %.', ['value' => number_format((float) $stock->display_profit_per_trade_percent, 2, ',', '.')]);
            } else {
                $simpleCons[] = is_numeric($stock->display_profit_per_trade_percent)
                    ? __('Durchschnittlicher Netto-Profit je Trade ist mit :value % nicht positiv.', ['value' => number_format((float) $stock->display_profit_per_trade_percent, 2, ',', '.')])
                    : __('Noch kein belastbarer Profit je Trade verfügbar.');
            }
            if ((float) $stock->confidence_percent >= 60) {
                $simplePros[] = __('Modellkonfidenz von :value %.', ['value' => number_format((float) $stock->confidence_percent, 0, ',', '.')]);
            } else {
                $simpleCons[] = __('Modellkonfidenz liegt nur bei :value %.', ['value' => number_format((float) $stock->confidence_percent, 0, ',', '.')]);
            }
            if ($stock->ranking_noise_available && $stock->ranking_noise_passed) {
                $simplePros[] = __('Noise-Filter bestanden.');
            } elseif ($stock->ranking_noise_available) {
                $simpleCons[] = __('Noise-Filter nicht bestanden.');
            }
            if ($stock->ranking_stability_available && $stock->ranking_stability_passed) {
                $simplePros[] = __('Stabilitätsfilter bestanden.');
            } elseif ($stock->ranking_stability_available) {
                $simpleCons[] = __('Stabilitätsfilter nicht bestanden.');
            }
            if (is_numeric($stock->ranking_drawdown) && (float) $stock->ranking_drawdown >= 25) {
                $simpleCons[] = __('Erhöhter möglicher Drawdown von :value %.', ['value' => number_format((float) $stock->ranking_drawdown, 1, ',', '.')]);
            }
            $storedPros = is_array($stock->assessment_pros ?? null) ? $stock->assessment_pros : [];
            $storedCons = is_array($stock->assessment_cons ?? null) ? $stock->assessment_cons : [];
            $stock->simple_pros = array_slice(
                $storedPros ?: ($simplePros ?: [__('Keine eindeutigen quantitativen Vorteile.')]),
                0,
                5
            );
            $stock->simple_cons = array_slice(
                $storedCons ?: ($simpleCons ?: [__('Keine zusätzlichen quantitativen Warnsignale.')]),
                0,
                5
            );
            $stock->simple_assessment_is_stored = $storedPros !== [] || $storedCons !== [];
        });
        $countries = DB::table('instruments')->where('type', 'stock')->where('is_active', true)->whereNull('deleted_at')->whereNotNull('country')->when($isFreeRegional, fn ($query) => $query->whereIn('id', $allowedInstrumentIds))->distinct()->orderBy('country')->pluck('country');
        $sectors = DB::table('instruments')->where('type', 'stock')->where('is_active', true)->whereNull('deleted_at')->whereNotNull('sector')->when($isFreeRegional, fn ($query) => $query->whereIn('id', $allowedInstrumentIds))->distinct()->orderBy('sector')->pluck('sector');
        $indices = DB::table('market_indices as market_index')
            ->where('market_index.is_active', true)
            ->whereExists(fn ($membership) => $membership->selectRaw('1')
                ->from('index_memberships as membership')
                ->whereColumn('membership.market_index_id', 'market_index.id')
                ->when($isFreeRegional, fn ($query) => $query->whereIn('membership.instrument_id', $allowedInstrumentIds))
                ->whereNull('membership.removed_at'))
            ->orderBy('market_index.global_rank')
            ->get(['market_index.symbol', 'market_index.name']);

        $userWatchlists = DB::table('watchlists')->where('user_id', $request->user()->id)
            ->where('active', true)->orderByDesc('is_default')->orderBy('name')->get(['id', 'name']);
        $paperPortfolios = DB::table('portfolios')->where('user_id', $request->user()->id)
            ->leftJoin('portfolio_cash_accounts as cash', 'cash.portfolio_id', '=', 'portfolios.id')
            ->where('portfolios.active', true)->where('portfolios.type', 'paper')->orderByDesc('portfolios.is_default')->orderBy('portfolios.name')->get(['portfolios.id', 'portfolios.name', 'portfolios.currency', 'portfolios.meta', DB::raw('COALESCE(cash.balance - cash.reserved_balance, 0) AS available_capital')]);
        $watchlistMemberships = DB::table('watchlist_items')->whereIn('watchlist_id', $userWatchlists->pluck('id'))
            ->whereIn('instrument_id', $stocks->pluck('instrument_id'))->get(['watchlist_id', 'instrument_id'])
            ->groupBy('instrument_id')->map(fn ($items) => $items->pluck('watchlist_id')->map(fn ($id) => (int) $id)->all());
        $paperPortfolioMemberships = DB::table('portfolio_positions')->whereIn('portfolio_id', $paperPortfolios->pluck('id'))
            ->whereIn('instrument_id', $stocks->pluck('instrument_id'))->get(['portfolio_id', 'instrument_id'])
            ->groupBy('instrument_id')->map(fn ($items) => $items->pluck('portfolio_id')->map(fn ($id) => (int) $id)->all());

        return view('screener.index', compact('stocks', 'countries', 'sectors', 'indices', 'userWatchlists', 'paperPortfolios', 'watchlistMemberships', 'paperPortfolioMemberships', 'isFreeRegional', 'regionalCountry'));
    }

    public function screeningHistory(Request $request): View
    {
        $runs = DB::table('stock_screening_runs as r')
            ->leftJoin('stock_screening_items as i', 'i.screening_run_id', '=', 'r.id')
            ->select('r.id', 'r.generated_at', 'r.model', 'r.item_count', 'r.estimated_cost_usd', DB::raw('MAX(i.created_at) as items_saved_at'))
            ->groupBy('r.id')->orderByDesc('r.generated_at')->limit(30)->get();

        $selected = null;
        if ($request->filled('run')) {
            $selected = DB::table('stock_screening_items as item')
                ->join('instruments as instrument', 'instrument.id', '=', 'item.instrument_id')
                ->where('item.screening_run_id', $request->integer('run'))
                ->orderBy('item.rank')->get(['item.*', 'instrument.symbol', 'instrument.name']);
        }

        return view('screener.history', compact('runs', 'selected'));
    }

    public function __invoke(Request $request): View
    {
        $signalSql = app(PersonalizedSignalService::class)->sql('prediction', $request->user());
        $country = strtoupper(substr(trim((string) $request->query('country')), 0, 2));
        $sector = trim((string) $request->query('sector'));
        $exchangeId = max(0, $request->integer('exchange'));
        $selectionDate = DB::table('daily_top_stock_selections')
            ->max('selection_date');
        $latestPredictions = DB::table('predictions as latest_prediction')
            ->selectRaw('DISTINCT ON (latest_prediction.instrument_id)
                latest_prediction.id,
                latest_prediction.instrument_id,
                latest_prediction.trained_model_id,
                latest_prediction.prediction_time,
                latest_prediction.predicted_price_5d,
                latest_prediction.predicted_price_20d,
                latest_prediction.economic_edge_return,
                latest_prediction.prediction_score,
                latest_prediction.confidence,
                latest_prediction.risk_score,
                latest_prediction.drawdown_risk_factor,
                latest_prediction.current_price,
                latest_prediction.recommendation_class,
                latest_prediction.signal')
            ->orderBy('latest_prediction.instrument_id')
            ->orderByDesc('latest_prediction.prediction_time')
            ->orderByDesc('latest_prediction.id');
        $latestQualityRankings = DB::table('model_quality_rankings')
            ->selectRaw('trained_model_id, MAX(id) AS ranking_id')
            ->groupBy('trained_model_id');
        $latestQuotes = DB::table('current_stock_quotes')
            ->where('status', 'current')
            ->selectRaw('instrument_id, MAX(id) AS quote_id')
            ->groupBy('instrument_id');
        $walkForwardRunId = (int) (DB::table('walk_forward_backtest_runs')
            ->where('id', 8)->where('status', 'completed')->exists()
            ? 8
            : (DB::table('walk_forward_backtest_runs')->where('status', 'completed')->orderByDesc('id')->value('id') ?? 0));
        $backtestRiskStats = DB::table('walk_forward_backtest_year_stats')
            ->where('run_id', $walkForwardRunId)
            ->whereNotNull('maximum_drawdown')
            ->select(['instrument_id'])
            ->selectRaw('SUM(trade_count) AS backtest_trade_count')
            ->selectRaw('PERCENTILE_CONT(0.90) WITHIN GROUP (ORDER BY ABS(maximum_drawdown)) * 100 AS backtest_drawdown_p90')
            ->groupBy('instrument_id');
        $recommendations = DB::table('predictions as prediction')
            ->join('daily_top_stock_selections as daily_selection', function ($join) use ($selectionDate): void {
                $join->on('daily_selection.prediction_id', '=', 'prediction.id')
                    ->where('daily_selection.selection_date', '=', $selectionDate);
            })
            ->join('instruments as instrument', 'instrument.id', '=', 'prediction.instrument_id')
            ->leftJoin('exchanges as instrument_exchange', 'instrument_exchange.id', '=', 'instrument.exchange_id')
            ->leftJoin('trained_models as trained_model', 'trained_model.id', '=', 'prediction.trained_model_id')
            ->leftJoin('model_definitions as model_definition', 'model_definition.id', '=', 'trained_model.model_definition_id')
            ->leftJoinSub($latestQualityRankings, 'latest_quality', fn ($join) =>
                $join->on('latest_quality.trained_model_id', '=', 'trained_model.id'))
            ->leftJoin('model_quality_rankings as model_quality', 'model_quality.id', '=', 'latest_quality.ranking_id')
            ->leftJoin('model_quality_tiers as model_tier', 'model_tier.id', '=', 'model_quality.tier_id')
            ->leftJoinSub($backtestRiskStats, 'backtest_risk', fn ($join) => $join
                ->on('backtest_risk.instrument_id', '=', 'prediction.instrument_id'))
            ->leftJoinSub($latestQuotes, 'latest_quote', fn ($join) =>
                $join->on('latest_quote.instrument_id', '=', 'instrument.id'))
            ->leftJoin('current_stock_quotes as current_quote', 'current_quote.id', '=', 'latest_quote.quote_id')
            ->where('instrument.type', 'stock')
            ->where('instrument.is_active', true)
            ->where('instrument.is_german_tradeable', true)
            ->whereNull('instrument.deleted_at')
            ->whereNotNull('prediction.prediction_score')
            ->whereNotNull('prediction.confidence')
            ->whereRaw('COALESCE(current_quote.price, prediction.current_price) > 0')
            ->where('prediction.quality_gate_passed', true)
            ->when($country !== '', fn ($query) => $query->where('instrument.country', $country))
            ->when($sector !== '', fn ($query) => $query->where('instrument.sector', $sector))
            ->when($exchangeId > 0, fn ($query) => $query->where('instrument.exchange_id', $exchangeId))
            ->select([
                'prediction.id as prediction_id',
                'daily_selection.rank as selection_rank',
                'daily_selection.selection_date',
                'daily_selection.recommendation_score as stored_recommendation_score',
                'daily_selection.risk_percent as stored_risk_percent',
                'prediction.instrument_id',
                'prediction.prediction_time',
                'prediction.predicted_price_5d',
                'prediction.predicted_price_20d',
                'prediction.economic_edge_return',
                'prediction.prediction_score',
                'prediction.confidence',
                'prediction.risk_score',
                'prediction.drawdown_risk_factor',
                'instrument.symbol',
                'instrument.provider_symbol',
                'instrument.name',
                'instrument.country',
                'instrument.sector',
                'instrument.currency',
                'instrument_exchange.code as exchange_code',
                'instrument_exchange.name as exchange_name',
                'model_definition.public_alias as model_alias',
                'model_quality.quality_score as model_quality_score',
                'model_quality.eligible as model_quality_eligible',
                'model_tier.code as model_tier_code',
                'model_tier.name as model_tier_name',
                'backtest_risk.backtest_trade_count',
                'backtest_risk.backtest_drawdown_p90',
            ])
            ->selectRaw('COALESCE(current_quote.price, prediction.current_price) AS current_price')
            ->addSelect('current_quote.quote_time as current_quote_time')
            ->selectRaw("{$signalSql} AS personalized_signal")
            ->get()
            ->map(fn (object $row): object => $this->score($row))
            ->sortBy('selection_rank')
            ->take(3)
            ->values();

        $recommendations->each(fn (object $recommendation) => $recommendation->is_test_candidate = false);

        if ($recommendations->count() < 3) {
            $missing = 3 - $recommendations->count();
            $fallbacks = DB::query()
                ->fromSub($latestPredictions, 'prediction')
                ->join('instruments as instrument', 'instrument.id', '=', 'prediction.instrument_id')
                ->leftJoin('exchanges as instrument_exchange', 'instrument_exchange.id', '=', 'instrument.exchange_id')
                ->leftJoin('trained_models as trained_model', 'trained_model.id', '=', 'prediction.trained_model_id')
                ->leftJoin('model_definitions as model_definition', 'model_definition.id', '=', 'trained_model.model_definition_id')
                ->leftJoinSub($latestQualityRankings, 'latest_quality', fn ($join) =>
                    $join->on('latest_quality.trained_model_id', '=', 'trained_model.id'))
                ->leftJoin('model_quality_rankings as model_quality', 'model_quality.id', '=', 'latest_quality.ranking_id')
                ->leftJoin('model_quality_tiers as model_tier', 'model_tier.id', '=', 'model_quality.tier_id')
                ->leftJoinSub($backtestRiskStats, 'backtest_risk', fn ($join) => $join
                    ->on('backtest_risk.instrument_id', '=', 'prediction.instrument_id'))
                ->leftJoinSub($latestQuotes, 'latest_quote', fn ($join) =>
                    $join->on('latest_quote.instrument_id', '=', 'instrument.id'))
                ->leftJoin('current_stock_quotes as current_quote', 'current_quote.id', '=', 'latest_quote.quote_id')
                ->where('instrument.type', 'stock')
                ->where('instrument.is_active', true)
                ->whereNull('instrument.deleted_at')
                ->whereNotNull('prediction.prediction_score')
                ->whereNotNull('prediction.confidence')
                ->where(function ($query): void {
                    $query->whereNull('model_tier.code')->orWhere('model_tier.code', '<>', 'test');
                })
                ->whereRaw('COALESCE(current_quote.price, prediction.current_price) > 0')
                ->when($recommendations->isNotEmpty(), fn ($query) =>
                    $query->whereNotIn('prediction.instrument_id', $recommendations->pluck('instrument_id')))
                ->when($country !== '', fn ($query) => $query->where('instrument.country', $country))
                ->when($sector !== '', fn ($query) => $query->where('instrument.sector', $sector))
                ->when($exchangeId > 0, fn ($query) => $query->where('instrument.exchange_id', $exchangeId))
                ->select([
                    'prediction.id as prediction_id',
                    'prediction.instrument_id',
                    'prediction.prediction_time',
                    'prediction.predicted_price_5d',
                    'prediction.predicted_price_20d',
                    'prediction.economic_edge_return',
                    'prediction.prediction_score',
                    'prediction.confidence',
                    'prediction.risk_score',
                    'prediction.drawdown_risk_factor',
                    'instrument.symbol',
                    'instrument.provider_symbol',
                    'instrument.name',
                    'instrument.country',
                    'instrument.sector',
                    'instrument.currency',
                    'instrument_exchange.code as exchange_code',
                    'instrument_exchange.name as exchange_name',
                    'model_definition.public_alias as model_alias',
                    'model_quality.quality_score as model_quality_score',
                    'model_quality.eligible as model_quality_eligible',
                    'model_tier.code as model_tier_code',
                    'model_tier.name as model_tier_name',
                    'backtest_risk.backtest_trade_count',
                    'backtest_risk.backtest_drawdown_p90',
                ])
                ->selectRaw('NULL::date AS selection_date')
                ->selectRaw('NULL::numeric AS stored_recommendation_score')
                ->selectRaw('NULL::numeric AS stored_risk_percent')
                ->selectRaw('COALESCE(current_quote.price, prediction.current_price) AS current_price')
                ->addSelect('current_quote.quote_time as current_quote_time')
                ->selectRaw("{$signalSql} AS personalized_signal")
                ->get()
                ->map(fn (object $row): object => $this->score($row))
                ->sortByDesc('recommendation_score')
                ->take($missing)
                ->values()
                ->map(function (object $row, int $index) use ($recommendations): object {
                    $row->selection_rank = $recommendations->count() + $index + 1;
                    // Fallbacks are real database-backed stocks, never demo/test cards.
                    $row->is_test_candidate = false;

                    return $row;
                });

            $recommendations = $recommendations->concat($fallbacks)->values();
        }

        $recommendations = $recommendations
            ->map(function (object $recommendation) use ($signalSql): object {
                $recommendation->candles = DB::table('price_bars')
                    ->where('instrument_id', $recommendation->instrument_id)
                    ->where('interval', '1d')
                    ->orderByDesc('bar_time')
                    ->limit(100)
                    ->get(['bar_time', 'open', 'high', 'low', 'close'])
                    ->unique(fn (object $bar): string => \Illuminate\Support\Carbon::parse($bar->bar_time)->format('Y-m-d'))
                    ->take(32)
                    ->reverse()
                    ->values()
                    ->map(fn (object $bar): array => [
                        'x' => \Illuminate\Support\Carbon::parse($bar->bar_time)->getTimestampMs(),
                        'y' => [
                            (float) $bar->open,
                            (float) $bar->high,
                            (float) $bar->low,
                            (float) $bar->close,
                        ],
                    ]);
                $signalHistory = DB::table('predictions as prediction')
                    ->where('prediction.instrument_id', $recommendation->instrument_id)
                    ->whereNotNull('prediction.prediction_time')
                    ->orderByDesc('prediction.prediction_time')
                    ->orderByDesc('prediction.id')
                    ->limit(200)
                    ->select('prediction.prediction_time', 'prediction.signal')
                    ->selectRaw("{$signalSql} AS personalized_signal")
                    ->get()
                    ->unique(fn (object $row): string => \Illuminate\Support\Carbon::parse($row->prediction_time)->format('Y-m-d'))
                    ->reverse()
                    ->values()
                    ->map(fn (object $row): array => [
                        'x' => \Illuminate\Support\Carbon::parse($row->prediction_time)->getTimestampMs(),
                        'signal' => strtoupper((string) ($row->personalized_signal ?: $row->signal ?: 'HOLD')),
                    ]);
                $recommendation->last_signal_transition = null;
                for ($index = 1; $index < $signalHistory->count(); $index++) {
                    $previous = $signalHistory->get($index - 1);
                    $current = $signalHistory->get($index);
                    if ($previous['signal'] !== $current['signal']) {
                        $recommendation->last_signal_transition = [
                            'x' => $current['x'],
                            'from' => $previous['signal'],
                            'to' => $current['signal'],
                        ];
                    }
                }

                return $recommendation;
            });

        $countries = DB::table('instruments')
            ->where('type', 'stock')
            ->where('is_active', true)
            ->whereNull('deleted_at')
            ->whereNotNull('country')
            ->where('country', '<>', '')
            ->distinct()
            ->orderBy('country')
            ->pluck('country');

        $sectors = DB::table('instruments')
            ->where('type', 'stock')
            ->where('is_active', true)
            ->whereNull('deleted_at')
            ->whereNotNull('sector')
            ->where('sector', '<>', '')
            ->distinct()
            ->orderBy('sector')
            ->pluck('sector');

        $exchanges = DB::table('exchanges as exchange')
            ->join('instruments as instrument', 'instrument.exchange_id', '=', 'exchange.id')
            ->where('exchange.is_active', true)
            ->where('exchange.code', '<>', 'UNKNOWN')
            ->where('instrument.type', 'stock')
            ->where('instrument.is_active', true)
            ->whereNull('instrument.deleted_at')
            ->select([
                'exchange.id',
                'exchange.code',
                'exchange.name',
                'exchange.country',
                DB::raw('COUNT(instrument.id) AS stocks_count'),
            ])
            ->groupBy('exchange.id', 'exchange.code', 'exchange.name', 'exchange.country')
            ->orderBy('exchange.name')
            ->get();

        $userWatchlists = DB::table('watchlists')
            ->where('user_id', $request->user()->id)
            ->where('active', true)
            ->orderByDesc('is_default')
            ->orderBy('name')
            ->get(['id', 'name', 'is_default']);

        $watchlistMemberships = $userWatchlists->isEmpty() || $recommendations->isEmpty()
            ? collect()
            : DB::table('watchlist_items')
                ->whereIn('watchlist_id', $userWatchlists->pluck('id'))
                ->whereIn('instrument_id', $recommendations->pluck('instrument_id'))
                ->get(['instrument_id', 'watchlist_id'])
                ->groupBy('instrument_id')
                ->map(fn ($items) => $items->pluck('watchlist_id')->map(fn ($id) => (int) $id));

        return view('recommendations.index', [
            'recommendations' => $recommendations,
            'countries' => $countries,
            'sectors' => $sectors,
            'exchanges' => $exchanges,
            'country' => $country,
            'sector' => $sector,
            'exchangeId' => $exchangeId,
            'userWatchlists' => $userWatchlists,
            'watchlistMemberships' => $watchlistMemberships,
        ]);
    }

    private function score(object $row): object
    {
        $scorePercent = AiScore::toPercent($row->prediction_score) ?? 0.0;
        $confidencePercent = $this->percentage($row->confidence) ?? 0.0;
        $rawRiskPercent = is_numeric($row->stored_risk_percent ?? null)
            ? (float) $row->stored_risk_percent
            : ($this->percentage($row->risk_score ?? $row->drawdown_risk_factor) ?? 50.0);

        $backtestTradeCount = (int) ($row->backtest_trade_count ?? 0);
        $backtestRiskPercent = $backtestTradeCount >= 10 && is_numeric($row->backtest_drawdown_p90 ?? null)
            ? max(0.0, min(100.0, (float) $row->backtest_drawdown_p90))
            : null;

        // Conservative hybrid: never understate either the model estimate or
        // a sufficiently supported out-of-sample backtest drawdown.
        $riskPercent = min(100.0, max(20.0, $rawRiskPercent, $backtestRiskPercent ?? 0.0));
        $grossExpectedReturn = match (true) {
            (float) $row->current_price !== 0.0 && is_numeric($row->predicted_price_20d) =>
                (((float) $row->predicted_price_20d - (float) $row->current_price) / (float) $row->current_price) * 100,
            (float) $row->current_price !== 0.0 && is_numeric($row->predicted_price_5d) =>
                (((float) $row->predicted_price_5d - (float) $row->current_price) / (float) $row->current_price) * 100,
            default => $this->returnPercent($row->economic_edge_return),
        };
        $expectedReturn = is_numeric($grossExpectedReturn)
            ? max(-20.0, min(
                20.0,
                (float) $grossExpectedReturn - max(0.0, (float) config('aktienki.signals.round_trip_cost_percent', 0.5))
            ))
            : 0.0;

        // Renditen zwischen -10 % und +20 % werden auf eine robuste 0–100-Skala begrenzt.
        $returnScore = max(0.0, min(100.0, (($expectedReturn + 10.0) / 30.0) * 100.0));

        $row->score_percent = $scorePercent;
        $row->score_10 = $scorePercent / 10;
        $row->confidence_percent = $confidencePercent;
        $row->risk_percent = $riskPercent;
        $row->backtest_risk_percent = $backtestRiskPercent;
        $row->backtest_trade_count = $backtestTradeCount;
        $row->risk_source = $backtestRiskPercent !== null && $backtestRiskPercent >= max(20.0, $rawRiskPercent)
            ? 'backtest'
            : ($rawRiskPercent >= 20.0 ? 'model' : 'minimum');
        $row->expected_return_20d = $expectedReturn;
        $calculatedScore = round(
            ($scorePercent * 0.40)
            + ($confidencePercent * 0.25)
            + ((100.0 - $riskPercent) * 0.20)
            + ($returnScore * 0.15),
            1
        );
        $row->recommendation_score = is_numeric($row->stored_recommendation_score ?? null)
            ? round((float) $row->stored_recommendation_score, 1)
            : $calculatedScore;

        return $row;
    }

    /**
     * Combine the latest technical snapshot with its historical signal bucket.
     */
    private function combineIndicatorStatistics(
        ?object $technical,
        \Illuminate\Support\Collection $statistics,
        float $currentPrice,
    ): array
    {
        if (! $technical) {
            return [];
        }

        $trend = (float) $technical->sma_20 >= (float) $technical->sma_50 ? 'bull' : 'bear';
        $volatility = (float) $technical->volatility_20;
        $regime = $trend.'_'.($volatility >= 0.40 ? 'high_vol' : 'normal');
        $side = 'long';
        $momentumBase = $currentPrice - (float) $technical->momentum_10;
        $values = [
            'rsi_14' => $technical->rsi_14,
            'adx_14' => $technical->adx_14,
            'stochastic_k' => $technical->stochastic_k,
            'volatility_20' => $technical->volatility_20,
            'atr_14_pct' => $currentPrice > 0 ? (float) $technical->atr_14 / $currentPrice : null,
            'bollinger_width' => $technical->bollinger_width,
            'macd_histogram_pct' => $currentPrice > 0 ? (float) $technical->macd_histogram / $currentPrice : null,
            'momentum_10_pct' => abs($momentumBase) > 0.000001 ? (float) $technical->momentum_10 / $momentumBase : null,
        ];

        return collect($values)->mapWithKeys(function (mixed $rawValue, string $indicator) use ($statistics, $regime, $side): array {
            if (! is_numeric($rawValue)) {
                return [$indicator => null];
            }

            $value = (float) $rawValue;
            $bucket = $statistics->get($indicator.'|'.$regime.'|'.$side, collect())
                ->first(fn (object $row): bool =>
                    ($row->value_lower === null || $value >= (float) $row->value_lower)
                    && ($row->value_upper === null || $value < (float) $row->value_upper)
                );

            return [$indicator => [
                'value' => $value,
                'signal_score' => $bucket ? (float) $bucket->signal_score : null,
                'hit_rate' => $bucket ? (float) $bucket->hit_rate * 100 : null,
                'mean_return' => $bucket ? (float) $bucket->mean_return * 100 : null,
                'sample_size' => $bucket ? (int) $bucket->sample_size : null,
                'eligible' => $bucket ? (bool) $bucket->eligible : false,
            ]];
        })->all();
    }

    private function percentage(mixed $value): ?float
    {
        if (! is_numeric($value)) {
            return null;
        }

        $number = (float) $value;

        return max(0.0, min(100.0, $number <= 1.0 ? $number * 100.0 : $number));
    }

    private function entryAtrSubquery(string $tradeAlias, string $dateColumn): Builder
    {
        $recentBars = DB::table('price_bars as atr_source')
            ->whereColumn('atr_source.instrument_id', "{$tradeAlias}.instrument_id")
            ->where('atr_source.interval', '1d')
            ->whereRaw("atr_source.bar_time < {$tradeAlias}.{$dateColumn}::timestamp + interval '1 day'")
            ->orderByDesc('atr_source.bar_time')
            ->limit(15)
            ->select(['atr_source.bar_time', 'atr_source.high', 'atr_source.low', 'atr_source.close']);

        $barsWithPreviousClose = DB::query()
            ->fromSub($recentBars, 'recent_atr_bar')
            ->select(['recent_atr_bar.bar_time', 'recent_atr_bar.high', 'recent_atr_bar.low'])
            ->selectRaw('LAG(recent_atr_bar.close) OVER (ORDER BY recent_atr_bar.bar_time) AS previous_close');

        return DB::query()
            ->fromSub($barsWithPreviousClose, 'atr_bar')
            ->whereNotNull('atr_bar.previous_close')
            ->selectRaw('AVG(GREATEST(atr_bar.high - atr_bar.low, ABS(atr_bar.high - atr_bar.previous_close), ABS(atr_bar.low - atr_bar.previous_close))) AS atr_14');
    }

    private function returnPercent(mixed $value): float
    {
        if (! is_numeric($value)) {
            return 0.0;
        }

        $number = (float) $value;

        return abs($number) <= 1.0 ? $number * 100.0 : $number;
    }
}
