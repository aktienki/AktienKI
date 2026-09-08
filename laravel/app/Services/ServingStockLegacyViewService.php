<?php

namespace App\Services;

use App\Enums\PlanLevel;
use App\Models\ExternalBuyReview;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Adapts the compact serving schema to the established stock-detail design.
 *
 * The adapter deliberately supplies empty collections for content that is not
 * published in the serving database (news, certificates, historic AI rows,
 * legacy assessments). The generated BUY research report is the deliberate
 * exception: it is stored once in the application database and reused by the
 * serving detail page while the remote signal remains BUY.
 */
final class ServingStockLegacyViewService
{
    public function __construct(
        private readonly ServingModelOverviewService $models,
        private readonly ServingChartCacheService $charts,
        private readonly PlanAccessService $plans,
    ) {}

    /** @return array<string, mixed> */
    public function data(Request $request, object $stock): array
    {
        $overview = $this->models->data((string) $stock->symbol);
        /** @var Collection<int, array<string, mixed>> $horizons */
        $horizons = $overview['horizons'];
        $displayHorizons = $horizons->pluck('days')->map(fn ($days): int => (int) $days)->values()->all();
        $primaryHorizon = $horizons->firstWhere('days', 20)
            ?? $horizons->firstWhere('active_prediction_enabled', true)
            ?? $horizons->first();
        $primaryModel = $primaryHorizon
            ? ($primaryHorizon['variants'][$primaryHorizon['active_variant']] ?? null)
            : null;
        $primaryPrediction = $primaryModel['prediction'] ?? null;
        $currentSignal = $overview['currentSignal'];
        $signal = strtoupper((string) ($currentSignal?->signal
            ?? $overview['release']['recommended_signal']
            ?? $primaryPrediction['signal']
            ?? 'HOLD'));
        if ($signal === 'NEUTRAL') {
            $signal = 'HOLD';
        }
        $currentPrice = $this->currentPrice($primaryPrediction, $stock);
        $dashboardRating = (string) (($currentSignal?->buy_rating ?? null)
            ?: ($currentSignal?->underlying_buy_rating ?? null)
            ?: '');
        $qualityPercent = $dashboardRating !== ''
            ? $this->ratingPercent($dashboardRating)
            : $this->qualityPercent((string) ($primaryModel['quality_class'] ?? $stock->quality_class));
        $riskLevel = is_numeric($currentSignal?->risk_score ?? null)
            ? max(1, min(5, (int) $currentSignal->risk_score))
            : 3;
        $riskPercent = $riskLevel * 20;

        $instrument = (object) [
            'id' => (int) $stock->instrument_id,
            'symbol' => (string) $stock->symbol,
            'provider_symbol' => (string) (($stock->provider_symbol ?? null) ?: $stock->symbol),
            'name' => (string) (($stock->name ?? null) ?: $stock->symbol),
            'short_name' => (string) (($stock->short_name ?? null) ?: $stock->name ?: $stock->symbol),
            'currency' => (string) (($stock->german_listing_currency ?? null) ?: $stock->currency ?: 'EUR'),
            'country' => (string) (($stock->country_code ?? null) ?: '—'),
            'country_code' => (string) (($stock->country_code ?? null) ?: ''),
            'sector' => (string) (($stock->sector_code ?? null) ?: 'Keine Branche'),
            'industry' => (string) (($stock->industry ?? null) ?: ''),
            'exchange' => (string) (($stock->exchange ?? null) ?: ''),
            'exchange_id' => null,
            'isin' => $stock->isin ?? null,
            'market_cap' => $stock->market_cap ?? null,
            'risk_status' => (string) (($stock->risk_status ?? null) ?: 'unknown'),
            'risk_profit_factor' => $stock->risk_profit_factor ?? null,
            'risk_confidence' => $stock->risk_confidence ?? null,
            'risk_max_drawdown' => $stock->risk_max_drawdown ?? null,
            'meta' => json_encode($stock->display_metadata, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'display_price_factor' => 1.0,
            'german_listing_symbol' => $stock->german_listing_symbol ?? null,
            'german_listing_exchange' => $stock->german_listing_exchange ?? null,
            'german_listing_currency' => $stock->german_listing_currency ?? null,
        ];

        $prediction = $primaryPrediction ? (object) [
            'id' => $stock->latest_prediction?->id,
            'prediction_time' => $primaryPrediction['as_of'],
            'current_quote_time' => $primaryPrediction['as_of'],
            'prediction_score' => $qualityPercent / 10,
            'display_score_10' => $qualityPercent / 10,
            'confidence' => is_numeric($primaryPrediction['confidence_percent'] ?? null)
                ? (float) $primaryPrediction['confidence_percent'] / 100
                : null,
            'risk_score' => $riskPercent,
            'drawdown_risk_factor' => null,
            'signal' => $signal,
            'personalized_signal' => $signal,
            'signal_strength' => null,
            'trend_strength' => null,
            'current_price' => $currentPrice,
            'actual_price' => null,
            'actual_return' => null,
            'direction_correct' => null,
            'quality_gate_passed' => (bool) ($primaryModel['quality_gate_passed'] ?? false),
            'quality_gate_score' => ($primaryModel['quality_gate_passed'] ?? false) ? 1.0 : 0.0,
            'horizon_fusion_stability_score' => $this->stability($primaryModel),
            'horizon_fusion_stability_passed' => (bool) ($primaryHorizon['active_prediction_enabled'] ?? false),
            'higher_timeframe_trend' => 'neutral',
            'interval' => '1d',
            'predicted_price_5d' => null,
            'predicted_price_10d' => null,
            'predicted_price_15d' => null,
            'predicted_price_20d' => null,
            'predicted_price_40d' => null,
        ] : null;

        $horizonTargets = [];
        $horizonStability = [];
        foreach ($horizons as $horizon) {
            $days = (int) $horizon['days'];
            $model = $horizon['variants'][$horizon['active_variant']] ?? null;
            $modelPrediction = $model['prediction'] ?? null;
            $target = is_numeric($modelPrediction['target_price'] ?? null) ? (float) $modelPrediction['target_price'] : null;
            $expectedReturn = is_numeric($modelPrediction['expected_return_percent'] ?? null)
                ? (float) $modelPrediction['expected_return_percent']
                : ($target !== null && $currentPrice !== null && $currentPrice !== 0.0
                    ? (($target / $currentPrice) - 1) * 100
                    : null);
            $horizonTargets[$days] = [
                'price' => $target,
                'return' => $expectedReturn,
                'confidence' => is_numeric($modelPrediction['confidence_percent'] ?? null)
                    ? max(0.0, min(100.0, (float) $modelPrediction['confidence_percent']))
                    : null,
            ];
            $horizonStability[$days] = [
                'price' => $target,
                'return' => $expectedReturn,
                'direction' => $expectedReturn === null ? null : ($expectedReturn > 0 ? 'up' : ($expectedReturn < 0 ? 'down' : 'flat')),
                'stability_score' => $this->stability($model),
                'direction_consistency' => null,
                'dispersion' => data_get($model, 'metrics.stddev_net_trade'),
                'noise_passed' => (bool) ($model['prediction_enabled'] ?? false),
                'stability_passed' => (bool) ($model['prediction_enabled'] ?? false),
            ];
            if ($prediction) {
                $property = "predicted_price_{$days}d";
                $prediction->{$property} = $target;
            }
        }

        $modelMetrics = (array) ($primaryModel['metrics'] ?? []);
        $tradeChart = (array) ($overview['tradeChart'] ?? []);
        $tradeChartInitial = (array) ($tradeChart['initial'] ?? []);
        $signalTradeChart = data_get(
            $tradeChart,
            'series.'.((int) ($tradeChartInitial['days'] ?? 0)).'.'.((string) ($tradeChartInitial['variant'] ?? '')),
            [],
        );
        $modelQuality = $primaryModel ? (object) [
            'model_alias' => (string) ($primaryModel['model_name'] ?? $primaryModel['label'] ?? '—'),
            'trained_at' => $overview['release']['created_at'],
            'quality_score' => $qualityPercent / 100,
            'profit_factor' => $modelMetrics['profit_factor'] ?? null,
            'sharpe' => null,
            'direction_accuracy' => $modelMetrics['hit_rate'] ?? null,
            'trade_count' => is_numeric($modelMetrics['trades'] ?? null) ? (int) $modelMetrics['trades'] : 0,
            'maximum_drawdown' => $modelMetrics['max_drawdown'] ?? null,
            'model_stability' => $this->stability($primaryModel),
            'eligible' => (bool) ($primaryModel['prediction_enabled'] ?? false),
            'tier_code' => $this->tierCode((string) ($primaryModel['quality_class'] ?? 'unknown')),
            'tier_name' => (string) ($primaryModel['quality_label'] ?? 'Nicht qualifiziert'),
        ] : null;
        $detailWalkForwardStats = $primaryModel ? (object) [
            'trade_count' => is_numeric($modelMetrics['trades'] ?? null) ? (int) $modelMetrics['trades'] : 0,
            'hit_rate' => is_numeric($modelMetrics['hit_rate'] ?? null) ? (float) $modelMetrics['hit_rate'] * 100 : null,
            'average_profit_per_trade_percent' => is_numeric($modelMetrics['average_net_trade'] ?? null)
                ? (float) $modelMetrics['average_net_trade'] * 100
                : null,
            'return_volatility_percent' => is_numeric($modelMetrics['stddev_net_trade'] ?? null)
                ? (float) $modelMetrics['stddev_net_trade'] * 100
                : null,
        ] : null;
        $backtestMetricPercentiles = $primaryModel
            ? $this->backtestMetricPercentiles((int) ($primaryHorizon['days'] ?? 20), $modelMetrics)
            : [];
        $challenger = $primaryHorizon
            ? collect($primaryHorizon['variants'])->first(fn (array $variant): bool => ! $variant['selected'])
            : null;
        $modelChallenger = $challenger ? (object) [
            'status' => 'challenger',
            'elo_rating' => null,
            'model_alias' => (string) ($challenger['model_name'] ?? $challenger['label'] ?? '—'),
            'quality_score' => $this->qualityPercent((string) ($challenger['quality_class'] ?? 'unknown')) / 100,
            'eligible' => (bool) ($challenger['prediction_enabled'] ?? false),
            'tier_code' => $this->tierCode((string) ($challenger['quality_class'] ?? 'unknown')),
            'tier_name' => (string) ($challenger['quality_label'] ?? 'Nicht qualifiziert'),
        ] : null;

        $chart = $this->charts->load(
            (int) $stock->instrument_id,
            $this->charts->providerSymbol($stock),
            (string) $instrument->currency,
        );
        $chartCandles = collect($chart['points'] ?? [])->map(function (array $point): array {
            $close = (float) $point['close'];

            return [
                'x' => (int) $point['timestamp'] * 1000,
                'y' => [
                    (float) ($point['open'] ?? $close),
                    (float) ($point['high'] ?? $close),
                    (float) ($point['low'] ?? $close),
                    $close,
                ],
                'volume' => is_numeric($point['volume'] ?? null) ? (float) $point['volume'] : null,
            ];
        })->values();
        $chartPatternData = $this->chartPatternData($chartCandles);

        $fundamental = (object) [
            'snapshot_date' => $stock->fundamental_snapshot_date ?? null,
            'market_cap' => $stock->market_cap ?? null,
            'trailing_pe' => $stock->trailing_pe ?? null,
            'forward_pe' => $stock->forward_pe ?? null,
            'peg_ratio' => $stock->peg_ratio ?? null,
            'price_to_book' => $stock->price_to_book ?? null,
            'price_to_sales' => $stock->price_to_sales ?? null,
            'dividend_yield' => $stock->dividend_yield ?? null,
            'payout_ratio' => $stock->payout_ratio ?? null,
            'profit_margin' => $stock->profit_margin ?? null,
            'operating_margin' => $stock->operating_margin ?? null,
            'return_on_assets' => $stock->return_on_assets ?? null,
            'return_on_equity' => $stock->return_on_equity ?? null,
            'revenue' => $stock->revenue ?? null,
            'revenue_growth' => $stock->revenue_growth ?? null,
            'ebitda' => $stock->ebitda ?? null,
            'net_income' => $stock->net_income ?? null,
            'total_cash' => $stock->total_cash ?? null,
            'total_debt' => $stock->total_debt ?? null,
            'debt_to_equity' => $stock->debt_to_equity ?? null,
            'current_ratio' => $stock->current_ratio ?? null,
            'quick_ratio' => $stock->quick_ratio ?? null,
            'operating_cash_flow' => $stock->operating_cash_flow ?? null,
            'free_cash_flow' => $stock->free_cash_flow ?? null,
        ];
        $fundamentalData = [
            'marketCap' => $fundamental->market_cap,
            'trailingPE' => $fundamental->trailing_pe,
            'forwardPE' => $fundamental->forward_pe,
            'pegRatio' => $fundamental->peg_ratio,
            'priceToBook' => $fundamental->price_to_book,
            'priceToSalesTrailing12Months' => $fundamental->price_to_sales,
            'dividendYield' => $fundamental->dividend_yield,
            'payoutRatio' => $fundamental->payout_ratio,
            'profitMargins' => $fundamental->profit_margin,
            'operatingMargins' => $fundamental->operating_margin,
            'returnOnAssets' => $fundamental->return_on_assets,
            'returnOnEquity' => $fundamental->return_on_equity,
            'totalRevenue' => $fundamental->revenue,
            'revenueGrowth' => $fundamental->revenue_growth,
            'ebitda' => $fundamental->ebitda,
            'netIncomeToCommon' => $fundamental->net_income,
            'totalCash' => $fundamental->total_cash,
            'totalDebt' => $fundamental->total_debt,
            'debtToEquity' => $fundamental->debt_to_equity,
            'currentRatio' => $fundamental->current_ratio,
            'quickRatio' => $fundamental->quick_ratio,
            'operatingCashflow' => $fundamental->operating_cash_flow,
            'freeCashflow' => $fundamental->free_cash_flow,
        ];

        $returnTo = $request->query('return_to');
        $returnTo = is_string($returnTo) && Str::startsWith($returnTo, '/') && ! Str::startsWith($returnTo, '//')
            ? $returnTo
            : null;

        $externalBuyReview = null;
        if ($signal === 'BUY'
            && $this->plans->allowsTariff($request->user(), PlanLevel::Pro)
            && Schema::hasTable('external_buy_reviews')) {
            $externalBuyReview = ExternalBuyReview::query()
                ->where('instrument_id', $instrument->id)
                ->latest('triggered_at')
                ->latest('id')
                ->first();
        }

        return [
            'instrument' => $instrument,
            'prediction' => $prediction,
            'modelQuality' => $modelQuality,
            'detailWalkForwardStats' => $detailWalkForwardStats,
            'backtestMetricPercentiles' => $backtestMetricPercentiles,
            'modelQualityGateReasons' => collect(),
            'modelChallenger' => $modelChallenger,
            'aiAssessment' => null,
            'aiAssessmentOpportunities' => [],
            'aiAssessmentRisks' => [],
            'aiAssessmentFactors' => [],
            'externalBuyReview' => $externalBuyReview,
            'externalBuyReviewPositiveFactors' => $externalBuyReview?->positive_factors ?? [],
            'externalBuyReviewRiskFactors' => $externalBuyReview?->risk_factors ?? [],
            'externalBuyReviewFindings' => $externalBuyReview?->key_findings ?? [],
            'externalBuyReviewLimitations' => $externalBuyReview?->research_limitations ?? [],
            'externalBuyReviewSources' => $externalBuyReview?->sources ?? [],
            'topStockAnalysis' => null,
            'topStockAnalysisDetails' => [],
            'topStockFactorRatings' => collect(),
            'predictionData' => $prediction ? [
                'prediction_time' => $prediction->prediction_time,
                'prediction_score' => $prediction->prediction_score,
                'confidence' => $prediction->confidence,
                'risk_score' => $riskLevel,
                'quality_gate_passed' => $prediction->quality_gate_passed,
            ] : [],
            'ensembleData' => [
                __('Champion') => $primaryModel['label'] ?? null,
                __('Horizont') => $primaryHorizon ? $primaryHorizon['days'].' Tage' : null,
                __('Prediction freigegeben') => (bool) ($primaryModel['prediction_enabled'] ?? false),
            ],
            'predictionExplanation' => [],
            'predictionMetadata' => ['trend' => 'neutral', 'trend_timeframe' => '1d'],
            'fundamental' => $fundamental,
            'fundamentalData' => $fundamentalData,
            'sectorRankings' => [],
            'instrumentMeta' => (array) $stock->display_metadata,
            'chartCandles' => $chartCandles,
            'chartSource' => ($chart['cache_hit'] ?? false) ? 'serving_file_cache' : 'twelve_data',
            'chartPatterns' => $chartPatternData['recent'],
            'chartPatternStats' => $chartPatternData['statistics'],
            'historicalAiScores' => collect(),
            'historicalSignalTransitions' => collect(),
            'latestSignalTransition' => null,
            'chartFocusAt' => $prediction?->prediction_time ? CarbonImmutable::parse($prediction->prediction_time) : null,
            'chartDataUrl' => route('stocks.serving-chart-data', ['symbol' => $instrument->symbol]),
            'requestedPredictionId' => 0,
            'signalChangedAt' => null,
            'returnTo' => $returnTo,
            'returnLabel' => $returnTo ? __('Zurück') : null,
            'watchlistEntry' => null,
            'userWatchlists' => collect(),
            'instrumentWatchlistIds' => collect(),
            'paperPortfolios' => collect(),
            'indicatorCards' => $this->indicatorCards($instrument, (array) ($chart['points'] ?? [])),
            'stockHeatmap' => collect(),
            'stockHeatmapSummary' => null,
            'stockNews' => collect(),
            'stockNewsCount' => 0,
            'stockEtfs' => collect(),
            'linkedSecurities' => collect(),
            'canViewRealtime' => true,
            'canUseChartIndicators' => true,
            'canViewChartPatterns' => true,
            'canUseChartZoom' => true,
            'marketSession' => ['open' => false, 'timezone' => 'Europe/Berlin'],
            'historicalChartAllowed' => true,
            'historicalChartRestrictionReason' => null,
            'horizonTargets' => $horizonTargets,
            'horizonStability' => $horizonStability,
            'signalTradeChart' => is_array($signalTradeChart) ? $signalTradeChart : [],
            'displayHorizons' => $displayHorizons,
            'servingMode' => true,
            'servingReleaseId' => (string) $stock->release_id,
            'canViewModelOverview' => true,
        ];
    }

    /** @return array{recent: array<int, array<string, mixed>>, statistics: array<int, array<string, mixed>>} */
    public function chartPatternData(Collection $candles): array
    {
        $candles = $candles->values();
        $definitions = [
            'bullish-engulfing' => [__('Bullish Engulfing'), 'bullish'],
            'bearish-engulfing' => [__('Bearish Engulfing'), 'bearish'],
            'bullish-pin-bar' => [__('Bullish Pin Bar'), 'bullish'],
            'bearish-pin-bar' => [__('Bearish Pin Bar'), 'bearish'],
            'upside-breakout' => [__('Ausbruch nach oben'), 'bullish'],
            'downside-breakout' => [__('Ausbruch nach unten'), 'bearish'],
        ];
        $occurrences = collect($definitions)->mapWithKeys(fn ($definition, string $key): array => [$key => []])->all();

        for ($index = 1; $index < $candles->count(); $index++) {
            $bar = $candles->get($index);
            $previous = $candles->get($index - 1);
            if (! is_array($bar) || ! is_array($previous) || count($bar['y'] ?? []) < 4 || count($previous['y'] ?? []) < 4) continue;
            [$open, $high, $low, $close] = array_map('floatval', $bar['y']);
            [$previousOpen, , , $previousClose] = array_map('floatval', $previous['y']);
            $found = [];
            if ($close > $open && $previousClose < $previousOpen && $open <= $previousClose && $close >= $previousOpen) $found[] = 'bullish-engulfing';
            if ($close < $open && $previousClose > $previousOpen && $open >= $previousClose && $close <= $previousOpen) $found[] = 'bearish-engulfing';
            $body = abs($close - $open);
            $range = $high - $low;
            if ($range > 0) {
                $lowerWick = min($open, $close) - $low;
                $upperWick = $high - max($open, $close);
                if ($lowerWick >= 2 * max($body, $range * .05) && $upperWick <= $body) $found[] = 'bullish-pin-bar';
                if ($upperWick >= 2 * max($body, $range * .05) && $lowerWick <= $body) $found[] = 'bearish-pin-bar';
            }
            if ($index >= 20) {
                $prior = $candles->slice($index - 20, 20);
                if ($close > (float) $prior->max(fn (array $row): float => (float) ($row['y'][1] ?? 0))) $found[] = 'upside-breakout';
                if ($close < (float) $prior->min(fn (array $row): float => (float) ($row['y'][2] ?? INF))) $found[] = 'downside-breakout';
            }

            foreach (array_unique($found) as $key) {
                $future = $candles->get($index + 20);
                $futureClose = is_array($future) ? data_get($future, 'y.3') : null;
                $return = is_numeric($futureClose) && $close !== 0.0 ? (((float) $futureClose / $close) - 1) * 100 : null;
                $example = $candles->slice(max(0, $index - 3), 7)->map(fn (array $exampleBar): array => [
                    'open' => (float) data_get($exampleBar, 'y.0', 0),
                    'high' => (float) data_get($exampleBar, 'y.1', 0),
                    'low' => (float) data_get($exampleBar, 'y.2', 0),
                    'close' => (float) data_get($exampleBar, 'y.3', 0),
                ])->values()->all();
                $occurrences[$key][] = ['at' => (int) $bar['x'], 'return' => $return, 'example' => $example, 'index' => $index];
            }
        }

        $recentCutoff = max(0, $candles->count() - 5);
        $recent = collect($definitions)->map(function (array $definition, string $key) use ($occurrences, $candles, $recentCutoff): ?array {
            $latest = collect($occurrences[$key])->last();
            if (! $latest || $latest['index'] < $recentCutoff) return null;
            $bar = $candles->get($latest['index']);
            $previous = $candles->get(max(0, $latest['index'] - 1));
            $range = collect([...($previous['y'] ?? []), ...($bar['y'] ?? [])])->filter(fn ($value): bool => is_numeric($value));

            return [
                'name' => $definition[0], 'direction' => $definition[1],
                'from' => (int) ($previous['x'] ?? $bar['x']), 'to' => (int) $bar['x'],
                'low' => (float) $range->min(), 'high' => (float) $range->max(),
            ];
        })->filter()->values()->all();
        $statistics = collect($definitions)->map(function (array $definition, string $key) use ($occurrences): array {
            [$name, $direction] = $definition;
            $items = collect($occurrences[$key]);
            $validated = $items->whereNotNull('return');
            $returns = $validated->pluck('return')->map(fn ($return): float => (float) $return * ($direction === 'bearish' ? -1 : 1));
            $latest = $items->last();

            return [
                'key' => $key, 'name' => $name, 'direction' => $direction,
                'latest_at' => isset($latest['at']) ? CarbonImmutable::createFromTimestampMs((int) $latest['at']) : null,
                'example' => $latest['example'] ?? [], 'samples' => $validated->count(),
                'hit_rate' => $returns->isEmpty() ? null : $returns->filter(fn (float $return): bool => $return > 0)->count() / $returns->count() * 100,
                'average_performance' => $returns->isEmpty() ? null : $returns->avg(),
            ];
        })->values()->all();

        return ['recent' => $recent, 'statistics' => $statistics];
    }

    /**
     * Build the same historical indicator data used by the established stock
     * detail view. Serving owns the current prediction, while the application
     * database remains the source for the three-year indicator history.
     *
     * @return Collection<int, array<string, mixed>>
     */
    private function indicatorCards(object $instrument, array $chartPoints = []): Collection
    {
        $indicators = DB::table('technical_indicators')
            ->where('instrument_id', $instrument->id)
            ->where('interval', '1d')
            ->where('bar_time', '>=', now()->subYears(3)->startOfDay())
            ->orderBy('bar_time')
            ->get([
                'bar_time', 'rsi_14', 'adx_14', 'stochastic_k',
                'volatility_20', 'atr_14', 'bollinger_width',
                'macd_histogram', 'momentum_10',
            ]);

        if ($indicators->isEmpty()) {
            return $this->chartIndicatorCards($chartPoints);
        }

        $features = DB::table('feature_store')
            ->where('instrument_id', $instrument->id)
            ->where('interval', '1d')
            ->whereIn('bar_time', $indicators->pluck('bar_time'))
            ->get(['bar_time', 'close', 'target_return_20d'])
            ->keyBy(fn (object $row): string => CarbonImmutable::parse($row->bar_time)->toIso8601String());

        $rows = $indicators->map(function (object $indicator) use ($features): array {
            $time = CarbonImmutable::parse($indicator->bar_time);
            $feature = $features->get($time->toIso8601String());

            return [
                'date' => $time->format('d.m.Y'),
                'close' => $this->number($feature?->close),
                'target' => $this->number($feature?->target_return_20d),
                'rsi' => $this->number($indicator->rsi_14),
                'adx' => $this->number($indicator->adx_14),
                'stochK' => $this->number($indicator->stochastic_k),
                'volatility' => $this->number($indicator->volatility_20),
                'atr' => $this->number($indicator->atr_14),
                'bbWidth' => $this->number($indicator->bollinger_width),
                'macdHistogram' => $this->number($indicator->macd_histogram),
                'momentum10' => $this->number($indicator->momentum_10),
            ];
        });

        $definitions = [
            ['label' => 'RSI 14', 'field' => 'rsi', 'unit' => ''],
            ['label' => 'ADX 14', 'field' => 'adx', 'unit' => ''],
            ['label' => 'Stochastik %K', 'field' => 'stochK', 'unit' => ''],
            ['label' => __('Volatilität 20T'), 'field' => 'volatility', 'unit' => '%'],
            ['label' => 'ATR 14', 'field' => 'atr', 'unit' => $instrument->currency ?: ''],
            ['label' => __('Bollinger-Bandbreite'), 'field' => 'bbWidth', 'unit' => '%'],
            ['label' => 'MACD Histogramm', 'field' => 'macdHistogram', 'unit' => ''],
            ['label' => __('Momentum 10T'), 'field' => 'momentum10Pct', 'unit' => '%'],
        ];
        $valueFor = static function (?array $row, string $field): ?float {
            if (! $row) return null;
            if ($field !== 'momentum10Pct') {
                return is_numeric($row[$field] ?? null) ? (float) $row[$field] : null;
            }
            if (! is_numeric($row['momentum10'] ?? null) || ! is_numeric($row['close'] ?? null)) return null;
            $priorClose = (float) $row['close'] - (float) $row['momentum10'];

            return abs($priorClose) > 0.000001 ? (float) $row['momentum10'] / $priorClose : null;
        };
        $current = $rows->last();
        $fiveDaysAgo = $rows->count() >= 6 ? $rows->get($rows->count() - 6) : null;

        return collect($definitions)->map(function (array $definition) use ($rows, $current, $fiveDaysAgo, $valueFor): array {
            $scale = $definition['unit'] === '%' ? 100.0 : 1.0;
            $currentRaw = $valueFor($current, $definition['field']);
            $previousRaw = $valueFor($fiveDaysAgo, $definition['field']);
            $points = $rows->map(function (array $row) use ($definition, $scale, $valueFor): ?array {
                $value = $valueFor($row, $definition['field']);
                if ($value === null || ! is_numeric($row['target'])) return null;

                return [
                    'x' => $value * $scale,
                    'y' => (float) $row['target'] * 100,
                    'up' => (float) $row['target'] > 0,
                    'date' => $row['date'],
                ];
            })->filter()->values();
            $nearby = $currentRaw === null
                ? collect()
                : $points->sortBy(fn (array $point): float => abs($point['x'] - ($currentRaw * $scale)))
                    ->take(min(40, $points->count()));
            $probability = $nearby->isEmpty() ? null : $nearby->where('up', true)->count() / $nearby->count() * 100;
            $change = $currentRaw !== null && $previousRaw !== null ? ($currentRaw - $previousRaw) * $scale : null;

            return [
                ...$definition,
                'currentValue' => $currentRaw === null ? null : $currentRaw * $scale,
                'fiveDayChange' => $change,
                'fiveDayDirection' => $change === null ? null : (abs($change) < .000001 ? 'flat' : ($change > 0 ? 'up' : 'down')),
                'currentProbability' => $probability,
                'currentFallProbability' => $probability === null ? null : 100 - $probability,
                'comparisonSamples' => $nearby->count(),
                'points' => $points,
            ];
        })->values();
    }

    /**
     * The remote serving host intentionally carries no legacy
     * technical_indicators rows. Derive the four matrix inputs from the same
     * cached OHLC history that is rendered in the stock chart instead of
     * falling back to legacy predictions or displaying empty heatmaps.
     *
     * @return Collection<int, array<string, mixed>>
     */
    private function chartIndicatorCards(array $chartPoints): Collection
    {
        $prices = collect($chartPoints)
            ->filter(fn ($point): bool => is_array($point) && is_numeric($point['close'] ?? null))
            ->sortBy('timestamp')
            ->values();
        if ($prices->count() < 45) return collect();

        $ema12 = $ema26 = $macdSignal = null;
        $rows = $prices->map(function (array $point, int $index) use ($prices, &$ema12, &$ema26, &$macdSignal): array {
            $close = (float) $point['close'];
            $ema12 = $ema12 === null ? $close : ($close * (2 / 13)) + ($ema12 * (11 / 13));
            $ema26 = $ema26 === null ? $close : ($close * (2 / 27)) + ($ema26 * (25 / 27));
            $macd = $ema12 - $ema26;
            $macdSignal = $macdSignal === null ? $macd : ($macd * .2) + ($macdSignal * .8);
            $window = $prices->slice(max(0, $index - 13), min(14, $index + 1));
            $closes = $window->pluck('close')->map(fn ($value): float => (float) $value);
            $high = (float) $window->max(fn (array $row): float => (float) ($row['high'] ?? $row['close']));
            $low = (float) $window->min(fn (array $row): float => (float) ($row['low'] ?? $row['close']));
            $dailyMoves = $closes->values()->map(function (float $value, int $offset) use ($closes): ?float {
                if ($offset === 0) return null;
                $previous = (float) $closes->get($offset - 1);

                return $previous !== 0.0 ? abs(($value / $previous) - 1) : null;
            })->filter();
            $periodStart = (float) ($closes->first() ?: $close);
            $direction = $periodStart !== 0.0 ? abs(($close / $periodStart) - 1) : 0.0;
            $adx = $dailyMoves->avg() > 0 ? min(100.0, ($direction / ($dailyMoves->avg() * 14)) * 100) : null;
            $targetClose = data_get($prices->get($index + 20), 'close');

            return [
                'date' => isset($point['timestamp']) ? CarbonImmutable::createFromTimestamp((int) $point['timestamp'])->format('d.m.Y') : (string) $index,
                'target' => is_numeric($targetClose) && $close !== 0.0 ? ((float) $targetClose / $close) - 1 : null,
                'momentum' => $index >= 10 && (float) $prices->get($index - 10)['close'] !== 0.0
                    ? ($close / (float) $prices->get($index - 10)['close']) - 1 : null,
                'adx' => $adx,
                'macd' => $macd - $macdSignal,
                'stochastic' => $high > $low ? (($close - $low) / ($high - $low)) * 100 : null,
            ];
        })->values();

        $definitions = [
            ['label' => __('Momentum 10T'), 'field' => 'momentum', 'unit' => '%', 'scale' => 100.0],
            ['label' => 'ADX 14', 'field' => 'adx', 'unit' => '', 'scale' => 1.0],
            ['label' => 'MACD Histogramm', 'field' => 'macd', 'unit' => '', 'scale' => 1.0],
            ['label' => 'Stochastik %K', 'field' => 'stochastic', 'unit' => '', 'scale' => 1.0],
        ];

        return collect($definitions)->map(function (array $definition) use ($rows): array {
            $points = $rows->filter(fn (array $row): bool => is_numeric($row[$definition['field']] ?? null) && is_numeric($row['target']))
                ->map(fn (array $row): array => [
                    'x' => (float) $row[$definition['field']] * $definition['scale'],
                    'y' => (float) $row['target'] * 100,
                    'up' => (float) $row['target'] > 0,
                    'date' => $row['date'],
                ])->values();
            $latestRawValue = data_get($rows->last(), $definition['field']);
            $currentValue = is_numeric($latestRawValue) ? (float) $latestRawValue * $definition['scale'] : null;
            $nearby = $currentValue === null ? collect() : $points
                ->sortBy(fn (array $point): float => abs($point['x'] - $currentValue))
                ->take(min(40, $points->count()));
            $probability = $nearby->isEmpty() ? null : $nearby->where('up', true)->count() / $nearby->count() * 100;

            return [
                'label' => $definition['label'], 'field' => $definition['field'], 'unit' => $definition['unit'],
                'currentValue' => $currentValue, 'fiveDayChange' => null, 'fiveDayDirection' => null,
                'currentProbability' => $probability,
                'currentFallProbability' => $probability === null ? null : 100 - $probability,
                'comparisonSamples' => $nearby->count(), 'points' => $points,
            ];
        })->values();
    }

    private function number(mixed $value): ?float
    {
        return is_numeric($value) ? (float) $value : null;
    }

    private function currentPrice(?array $prediction, object $stock): ?float
    {
        if (is_numeric($stock->latest_prediction?->current_price ?? null)) {
            return (float) $stock->latest_prediction->current_price;
        }
        $target = $prediction['target_price'] ?? null;
        $return = $prediction['expected_return_percent'] ?? null;
        if (is_numeric($target) && is_numeric($return)) {
            $factor = 1 + ((float) $return / 100);

            return $factor !== 0.0 ? (float) $target / $factor : null;
        }

        return null;
    }

    private function qualityPercent(string $quality): float
    {
        return match (strtolower($quality)) {
            'quality', 'top', 'strong' => 90,
            'solid' => 75,
            'basic', 'test' => 55,
            'underperform', 'not_qualified' => 30,
            default => 45,
        };
    }

    private function ratingPercent(string $rating): float
    {
        return match (str_replace('−', '-', trim($rating))) {
            '1++' => 99, '1+' => 95, '1' => 90, '1-' => 85,
            '2+' => 78, '2' => 72, '2-' => 65,
            '3+' => 58, '3' => 52, '3-' => 45,
            '4+' => 38, '4' => 32, '4-' => 25,
            '5+' => 18, '5' => 12, '5-' => 5,
            default => 50,
        };
    }

    private function tierCode(string $quality): string
    {
        return match (strtolower($quality)) {
            'quality', 'top', 'strong' => 'top',
            'solid' => 'solid',
            'basic', 'test' => 'test',
            default => 'unqualified',
        };
    }

    private function stability(?array $model): ?float
    {
        $stddev = data_get($model, 'metrics.stddev_net_trade');
        if (! is_numeric($stddev)) {
            return null;
        }

        return max(0.0, min(1.0, 1.0 - abs((float) $stddev)));
    }

    /** @return array<string, array{percentile: float|null, models: int}> */
    private function backtestMetricPercentiles(int $horizon, array $currentMetrics): array
    {
        $distributions = Cache::remember(
            "serving.backtest-metric-distributions.{$horizon}",
            now()->addMinutes(15),
            function () use ($horizon): array {
                $metrics = ['trades', 'hit_rate', 'profit_factor', 'average_net_trade', 'max_drawdown'];
                $values = array_fill_keys($metrics, []);

                DB::connection('serving')->table('serving_model_horizon_status')
                    ->where('horizon', $horizon)
                    ->where('selected_for_prediction', true)
                    ->pluck('performance')
                    ->each(function ($performance) use (&$values, $metrics): void {
                        $row = is_array($performance)
                            ? $performance
                            : (json_decode((string) $performance, true) ?: []);
                        foreach ($metrics as $metric) {
                            if (is_numeric($row[$metric] ?? null)) {
                                $values[$metric][] = $metric === 'max_drawdown'
                                    ? abs((float) $row[$metric])
                                    : (float) $row[$metric];
                            }
                        }
                    });

                return $values;
            },
        );

        return collect($distributions)->mapWithKeys(function (array $values, string $metric) use ($currentMetrics): array {
            $current = $currentMetrics[$metric] ?? null;
            if (! is_numeric($current) || $values === []) {
                return [$metric => ['percentile' => null, 'models' => count($values)]];
            }

            $current = $metric === 'max_drawdown' ? abs((float) $current) : (float) $current;
            $below = collect($values)->filter(fn (float $value): bool => $value < $current)->count();
            $equal = collect($values)->filter(fn (float $value): bool => abs($value - $current) < 0.0000001)->count();
            $percentile = (($below + (($equal + 1) / 2)) / count($values)) * 100;
            if ($metric === 'max_drawdown') {
                $percentile = 100 - $percentile;
            }

            return [$metric => [
                'percentile' => round(max(0, min(100, $percentile)), 1),
                'models' => count($values),
            ]];
        })->all();
    }
}
