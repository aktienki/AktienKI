<?php

namespace App\Http\Controllers;

use App\Services\PersonalizedSignalService;
use App\Services\TwelveDataService;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Throwable;

class StockController extends Controller
{
    public function chartAnalysis(string $symbol): View
    {
        $instrument = $this->instrument($symbol);
        $exchange = $instrument->exchange_id
            ? DB::table('exchanges')->where('id', $instrument->exchange_id)->first()
            : null;
        $indicatorCards = $this->indicatorCards($instrument);

        return view('stocks.chart-analysis', compact(
            'instrument', 'exchange', 'indicatorCards'
        ));
    }

    public function show(
        Request $request,
        string $symbol,
        PersonalizedSignalService $personalizedSignals,
        TwelveDataService $yahooFinance,
    ): View
    {
        $instrument = $this->instrument($symbol);

        $signalSql = $personalizedSignals->sql('prediction', auth()->user());
        $requestedPredictionId = $request->integer('prediction');
        $predictionQuery = DB::table('predictions as prediction')
            ->where('prediction.instrument_id', $instrument->id)
            ->select('prediction.*')
            ->selectRaw("{$signalSql} AS personalized_signal");

        if ($requestedPredictionId > 0) {
            $predictionQuery->where('prediction.id', $requestedPredictionId);
        } else {
            $predictionQuery
                ->orderByDesc('prediction.prediction_time')
                ->orderByDesc('prediction.id');
        }

        $prediction = $predictionQuery->first();
        abort_if($requestedPredictionId > 0 && ! $prediction, 404);
        if ($prediction && $requestedPredictionId === 0) {
            $currentQuote = DB::table('current_stock_quotes')
                ->where('instrument_id', $instrument->id)
                ->where('status', 'current')
                ->orderByDesc('quote_time')
                ->orderByDesc('id')
                ->first(['price', 'quote_time']);
            if ($currentQuote && is_numeric($currentQuote->price)) {
                $prediction->current_price = (float) $currentQuote->price;
                $prediction->current_quote_time = $currentQuote->quote_time;
            }
        }
        $signalChangedAt = null;
        if ($requestedPredictionId > 0 && $prediction?->prediction_time) {
            $signalHistory = DB::table('predictions as prediction')
                ->where('prediction.instrument_id', $instrument->id)
                ->where(function ($query) use ($prediction): void {
                    $query
                        ->where('prediction.prediction_time', '<', $prediction->prediction_time)
                        ->orWhere(function ($sameTime) use ($prediction): void {
                            $sameTime
                                ->where('prediction.prediction_time', '=', $prediction->prediction_time)
                                ->where('prediction.id', '<=', $prediction->id);
                        });
                })
                ->select(['prediction.id', 'prediction.prediction_time'])
                ->selectRaw("{$signalSql} AS personalized_signal")
                ->orderByDesc('prediction.prediction_time')
                ->orderByDesc('prediction.id')
                ->limit(2000)
                ->get();

            $selectedSignal = strtoupper((string) ($prediction->personalized_signal ?: 'HOLD'));
            $phaseStartedAt = null;
            foreach ($signalHistory as $signalPoint) {
                if (strtoupper((string) ($signalPoint->personalized_signal ?: 'HOLD')) !== $selectedSignal) {
                    $signalChangedAt = $phaseStartedAt;
                    break;
                }

                $phaseStartedAt = CarbonImmutable::parse($signalPoint->prediction_time);
            }
        }
        $modelQuality = $prediction?->trained_model_id
            ? DB::table('trained_models as trained_model')
                ->leftJoin('model_definitions as model_definition', 'model_definition.id', '=', 'trained_model.model_definition_id')
                ->leftJoin('model_quality_rankings as model_quality', function ($join): void {
                    $join->on('model_quality.trained_model_id', '=', 'trained_model.id')
                        ->whereRaw('model_quality.id = (
                            SELECT MAX(latest_model_quality.id)
                            FROM model_quality_rankings AS latest_model_quality
                            WHERE latest_model_quality.trained_model_id = trained_model.id
                        )');
                })
                ->leftJoin('model_quality_tiers as quality_tier', 'quality_tier.id', '=', 'model_quality.tier_id')
                ->where('trained_model.id', $prediction->trained_model_id)
                ->first([
                    'model_definition.public_alias as model_alias',
                    'trained_model.trained_at',
                    'model_quality.quality_score',
                    'model_quality.profit_factor',
                    'model_quality.sharpe',
                    'model_quality.direction_accuracy',
                    'model_quality.trade_count',
                    'model_quality.maximum_drawdown',
                    'model_quality.eligible',
                    'quality_tier.code as tier_code',
                    'quality_tier.name as tier_name',
                ])
            : null;
        $qualityGateTier = DB::table('model_quality_tiers')
            ->where('code', 'top')
            ->where('enabled', true)
            ->first();
        $modelQualityGateReasons = collect();
        if ($modelQuality && $qualityGateTier && $modelQuality->tier_code !== 'top') {
            $qualityChecks = [
                [__('Qualitätsscore'), $modelQuality->quality_score, $qualityGateTier->minimum_quality_score, 'min', 100, '%'],
                [__('Profit-Faktor'), $modelQuality->profit_factor, $qualityGateTier->minimum_profit_factor, 'min', 1, ''],
                [__('Sharpe Ratio'), $modelQuality->sharpe, $qualityGateTier->minimum_sharpe, 'min', 1, ''],
                [__('Richtungsgenauigkeit'), $modelQuality->direction_accuracy, $qualityGateTier->minimum_direction_accuracy, 'min', 100, '%'],
                [__('Validierte Trades'), $modelQuality->trade_count, $qualityGateTier->minimum_trade_count, 'min', 1, ''],
                [__('Maximaler Drawdown'), $modelQuality->maximum_drawdown, $qualityGateTier->maximum_drawdown, 'max', 100, '%'],
            ];
            foreach ($qualityChecks as [$name, $actual, $threshold, $direction, $multiplier, $unit]) {
                if (! is_numeric($actual) || ! is_numeric($threshold)) {
                    continue;
                }
                $failed = $direction === 'min'
                    ? (float) $actual < (float) $threshold
                    : (float) $actual > (float) $threshold;
                if ($failed) {
                    $modelQualityGateReasons->push([
                        'name' => $name,
                        'actual' => (float) $actual * $multiplier,
                        'threshold' => (float) $threshold * $multiplier,
                        'direction' => $direction,
                        'unit' => $unit,
                    ]);
                }
            }
        }
        $modelChallenger = $prediction?->trained_model_id
            ? DB::table('model_challengers as challenger')
                ->join('trained_models as trained_model', 'trained_model.id', '=', 'challenger.trained_model_id')
                ->leftJoin('model_definitions as model_definition', 'model_definition.id', '=', 'trained_model.model_definition_id')
                ->leftJoin('model_quality_rankings as model_quality', function ($join): void {
                    $join->on('model_quality.trained_model_id', '=', 'trained_model.id')
                        ->whereRaw('model_quality.id = (
                            SELECT MAX(latest_model_quality.id)
                            FROM model_quality_rankings AS latest_model_quality
                            WHERE latest_model_quality.trained_model_id = trained_model.id
                        )');
                })
                ->leftJoin('model_quality_tiers as quality_tier', 'quality_tier.id', '=', 'model_quality.tier_id')
                ->where('challenger.champion_model_id', $prediction->trained_model_id)
                ->where(fn ($query) => $query
                    ->where('challenger.instrument_id', $instrument->id)
                    ->orWhereNull('challenger.instrument_id'))
                ->orderByRaw('CASE WHEN challenger.instrument_id = ? THEN 0 ELSE 1 END', [$instrument->id])
                ->orderByDesc('challenger.elo_rating')
                ->orderByDesc('challenger.id')
                ->first([
                    'challenger.status',
                    'challenger.elo_rating',
                    'model_definition.public_alias as model_alias',
                    'model_quality.quality_score',
                    'model_quality.eligible',
                    'quality_tier.code as tier_code',
                    'quality_tier.name as tier_name',
                ])
            : null;
        $aiAssessment = DB::table('stock_ai_assessments')
            ->where('instrument_id', $instrument->id)
            ->when($requestedPredictionId > 0, fn ($query) => $query->where('prediction_id', $requestedPredictionId))
            ->orderByDesc('assessment_date')
            ->orderByDesc('id')
            ->first();
        $aiAssessmentOpportunities = $this->decodeJson($aiAssessment?->opportunities);
        $aiAssessmentRisks = $this->decodeJson($aiAssessment?->risks);
        $aiAssessmentFactors = $this->decodeJson($aiAssessment?->key_factors);
        $topStockAnalysis = DB::table('daily_top_stock_selections')
            ->where('instrument_id', $instrument->id)
            ->when($requestedPredictionId > 0, fn ($query) => $query->where('prediction_id', $requestedPredictionId))
            ->orderByDesc('selection_date')
            ->orderBy('rank')
            ->first();
        $topStockAnalysisDetails = $this->decodeJson($topStockAnalysis?->selection_details);
        $predictionFactorRatings = $this->decodeJson($prediction?->factor_ratings);
        $factorRatings = $predictionFactorRatings !== []
            ? $predictionFactorRatings
            : ($topStockAnalysisDetails['factor_ratings'] ?? []);
        $factorLabels = [
            'r2' => 'R²',
            'cagr' => __('Wachstum'),
            'error' => __('Prognosefehler'),
            'sharpe' => __('Sharpe Ratio'),
            'stability' => __('Stabilität'),
            'drawdown_risk' => __('Drawdown-Risiko'),
            'profit_factor' => __('Profit-Faktor'),
            'direction_accuracy' => __('Trefferquote Richtung'),
            'statistical_reliability' => __('Statistische Basis'),
        ];
        $topStockFactorRatings = collect($factorLabels)
            ->map(function (string $label, string $key) use ($factorRatings): array {
                $factor = is_array($factorRatings[$key] ?? null) ? $factorRatings[$key] : [];

                return [
                    'key' => $key,
                    'label' => $label,
                    'rating' => is_numeric($factor['rating'] ?? null) ? (int) $factor['rating'] : null,
                ];
            })
            ->values();
        $chartFocusAt = $requestedPredictionId > 0 && $prediction?->prediction_time
            ? CarbonImmutable::parse($prediction->prediction_time)
            : null;
        $returnTo = $request->query('return_to');
        $returnTo = is_string($returnTo)
            && Str::startsWith($returnTo, '/')
            && ! Str::startsWith($returnTo, '//')
                ? $returnTo
                : null;
        $returnLabel = $returnTo && Str::startsWith($returnTo, '/watchlists')
            ? __('Zurück zur Watchlist')
            : ($returnTo && Str::startsWith($returnTo, '/predictions')
                ? __('Zurück zu Prognosen')
                : ($returnTo && (Str::startsWith($returnTo, '/depots') || Str::startsWith($returnTo, '/paper-depots'))
                    ? __('Zurück zum Musterdepot')
                    : null));

        $fundamental = DB::table('instrument_fundamentals')
            ->where('instrument_id', $instrument->id)
            ->orderByDesc('snapshot_date')
            ->orderByDesc('id')
            ->first();

        $fundamentalData = array_replace(
            $this->decodeJson($fundamental?->data),
            array_filter([
                'marketCap' => $fundamental?->market_cap,
                'enterpriseValue' => $fundamental?->enterprise_value,
                'trailingPE' => $fundamental?->trailing_pe,
                'forwardPE' => $fundamental?->forward_pe,
                'pegRatio' => $fundamental?->peg_ratio,
                'priceToBook' => $fundamental?->price_to_book,
                'priceToSalesTrailing12Months' => $fundamental?->price_to_sales,
                'dividendRate' => $fundamental?->dividend_rate,
                'dividendYield' => $fundamental?->dividend_yield,
                'payoutRatio' => $fundamental?->payout_ratio,
                'profitMargins' => $fundamental?->profit_margin,
                'operatingMargins' => $fundamental?->operating_margin,
                'returnOnAssets' => $fundamental?->return_on_assets,
                'returnOnEquity' => $fundamental?->return_on_equity,
                'totalRevenue' => $fundamental?->revenue,
                'revenueGrowth' => $fundamental?->revenue_growth,
                'grossProfits' => $fundamental?->gross_profit,
                'ebitda' => $fundamental?->ebitda,
                'netIncomeToCommon' => $fundamental?->net_income,
                'totalCash' => $fundamental?->total_cash,
                'totalDebt' => $fundamental?->total_debt,
                'debtToEquity' => $fundamental?->debt_to_equity,
                'currentRatio' => $fundamental?->current_ratio,
                'quickRatio' => $fundamental?->quick_ratio,
                'operatingCashflow' => $fundamental?->operating_cash_flow,
                'freeCashflow' => $fundamental?->free_cash_flow,
                'sharesOutstanding' => $fundamental?->shares_outstanding,
                'floatShares' => $fundamental?->float_shares,
            ], fn (mixed $value): bool => $value !== null),
        );
        $instrumentMeta = $this->decodeJson($instrument->meta);
        $predictionExplanation = $this->decodeJson($prediction?->explanation);
        $predictionMetadata = $this->decodeJson($prediction?->metadata);
        $sectorRankings = $this->sectorRankings($instrument, $fundamentalData);

        ['candles' => $chartCandles, 'source' => $chartSource] = $this->chartSeries($instrument, $yahooFinance, $chartFocusAt);
        $chartStartAt = $chartCandles->isNotEmpty()
            ? CarbonImmutable::createFromTimestampMs((int) $chartCandles->first()['x'])->startOfDay()
            : null;
        $chartEndAt = $chartCandles->isNotEmpty()
            ? CarbonImmutable::createFromTimestampMs((int) $chartCandles->last()['x'])->endOfDay()
            : null;
        $historicalAiHistory = $chartStartAt && $chartEndAt
            ? DB::table('predictions as prediction')
                ->where('prediction.instrument_id', $instrument->id)
                ->whereBetween('prediction.prediction_time', [$chartStartAt, $chartEndAt])
                ->whereNotNull('prediction.prediction_score')
                ->orderByDesc('prediction.prediction_time')
                ->orderByDesc('prediction.id')
                ->select(['prediction.prediction_time', 'prediction.prediction_score', 'prediction.signal'])
                ->selectRaw("{$signalSql} AS personalized_signal")
                ->get()
                ->unique(fn (object $row): string => CarbonImmutable::parse($row->prediction_time)->format('Y-m-d'))
                ->reverse()
                ->values()
                ->map(fn (object $row): array => [
                    'x' => CarbonImmutable::parse($row->prediction_time)->getTimestampMs(),
                    'y' => \App\Support\AiScore::toTen($row->prediction_score),
                    'signal' => strtoupper((string) ($row->personalized_signal ?: $row->signal ?: 'HOLD')),
                ])
                ->filter(fn (array $point): bool => is_numeric($point['y']))
                ->values()
            : collect();
        $historicalAiScores = $historicalAiHistory->map(fn (array $point): array => [
            'x' => $point['x'],
            'y' => $point['y'],
        ]);
        $historicalSignalTransitions = $historicalAiHistory
            ->values()
            ->filter(fn (array $point, int $index): bool =>
                $index > 0 && $point['signal'] !== $historicalAiHistory->values()->get($index - 1)['signal'])
            ->map(function (array $point, int $index) use ($historicalAiHistory): array {
                $previous = $historicalAiHistory->values()->get($index - 1);

                return [
                    'x' => $point['x'],
                    'from' => $previous['signal'],
                    'to' => $point['signal'],
                    'score' => $point['y'],
                ];
            })
            ->values();
        $watchlistEntry = $this->watchlistEntry($instrument->id);
        $userWatchlists = DB::table('watchlists')
            ->where('user_id', auth()->id())
            ->where('active', true)
            ->orderByDesc('is_default')
            ->orderBy('name')
            ->get(['id', 'name', 'is_default']);
        $instrumentWatchlistIds = $userWatchlists->isEmpty()
            ? collect()
            : DB::table('watchlist_items')
                ->where('instrument_id', $instrument->id)
                ->whereIn('watchlist_id', $userWatchlists->pluck('id'))
                ->pluck('watchlist_id')
                ->map(fn ($id) => (int) $id);

        $predictionData = $prediction ? [
            'prediction_time' => $prediction->prediction_time,
            'prediction_score' => $prediction->prediction_score,
            'confidence' => $prediction->confidence,
            'risk_score' => $prediction->risk_score,
            'signal_strength' => $prediction->signal_strength,
            'trend_strength' => $prediction->trend_strength,
            'quality_gate_passed' => $prediction->quality_gate_passed,
            'quality_gate_score' => $prediction->quality_gate_score,
        ] : [];
        $ensembleQuality = $this->decodeJson($prediction?->ensemble_quality);
        $ensembleData = collect([
            __('Ensemble-Score') => $ensembleQuality['score'] ?? null,
            __('Qualitätsstufe') => match (strtolower((string) ($ensembleQuality['label'] ?? ''))) {
                'excellent' => __('Exzellent'),
                'strong' => __('Stark'),
                'solid' => __('Solide'),
                'weak' => __('Schwach'),
                default => $ensembleQuality['label'] ?? null,
            },
            __('Ensemble stabil') => $prediction?->ensemble_dispersion_stable,
            __('Relative Streuung') => is_numeric($prediction?->ensemble_relative_dispersion)
                ? (float) $prediction->ensemble_relative_dispersion
                : null,
            __('Modellübereinstimmung') => $ensembleQuality['agreement']
                ?? $ensembleQuality['agreement_score']
                ?? $ensembleQuality['direction_agreement']
                ?? $ensembleQuality['consensus']
                ?? null,
            __('Ensemble-Modelle') => $ensembleQuality['model_count']
                ?? $ensembleQuality['models_count']
                ?? $ensembleQuality['n_models']
                ?? $ensembleQuality['participating_models']
                ?? null,
            __('Ø Modellqualität') => $ensembleQuality['average_model_quality'] ?? null,
            __('Schwächste Modellqualität') => $ensembleQuality['weakest_model_quality'] ?? null,
            __('Ø Stabilität') => $ensembleQuality['average_stability'] ?? null,
            __('Ø Profit-Faktor') => $ensembleQuality['average_profit_factor'] ?? null,
            __('Statistische Zuverlässigkeit') => $ensembleQuality['statistical_reliability'] ?? null,
            __('Ensemble-Veto') => $prediction?->ensemble_dispersion_veto_used,
        ])->all();
        $indicatorCards = $this->indicatorCards($instrument);
        $chartDataUrl = route('stocks.chart-data', $requestedPredictionId > 0
            ? ['symbol' => $instrument->symbol, 'prediction' => $requestedPredictionId]
            : ['symbol' => $instrument->symbol]);
        $stockHeatmapQuery = DB::table('backtest_trades as backtest_trade')
            ->join('backtest_runs as backtest_run', 'backtest_run.id', '=', 'backtest_trade.backtest_run_id')
            ->where('backtest_trade.instrument_id', $instrument->id)
            ->where('backtest_trade.backtest_run_id', function ($query) use ($instrument): void {
                $query->from('backtest_runs as latest_backtest_run')
                    ->join('backtest_trades as latest_backtest_trade', 'latest_backtest_trade.backtest_run_id', '=', 'latest_backtest_run.id')
                    ->where('latest_backtest_trade.instrument_id', $instrument->id)
                    ->whereIn('status', ['completed', 'completed_with_errors'])
                    ->orderByDesc('latest_backtest_run.id')
                    ->limit(1)
                    ->select('latest_backtest_run.id');
            });
        $stockHeatmapSummary = (clone $stockHeatmapQuery)
            ->selectRaw('COUNT(*) AS trades')
            ->selectRaw('AVG(CASE WHEN backtest_trade.net_return > 0 THEN 1.0 ELSE 0.0 END) * 100 AS hit_rate')
            ->selectRaw('SUM(CASE WHEN backtest_trade.net_return > 0 THEN backtest_trade.net_return ELSE 0 END) / NULLIF(ABS(SUM(CASE WHEN backtest_trade.net_return < 0 THEN backtest_trade.net_return ELSE 0 END)), 0) AS profit_factor')
            ->selectRaw('MAX(backtest_trade.max_drawdown) * 100 AS drawdown')
            ->first();
        $stockHeatmap = $stockHeatmapQuery
            ->selectRaw('LEAST(9, GREATEST(0, FLOOR(backtest_trade.ki_score)))::integer AS score_bucket')
            ->selectRaw('LEAST(9, GREATEST(0, FLOOR(backtest_trade.confidence / 10)))::integer AS confidence_bucket')
            ->selectRaw('COUNT(*) AS samples')
            ->selectRaw('AVG(CASE WHEN backtest_trade.net_return > 0 THEN 1.0 ELSE 0.0 END) * 100 AS hit_rate')
            ->selectRaw('SUM(CASE WHEN backtest_trade.net_return > 0 THEN backtest_trade.net_return ELSE 0 END) / NULLIF(ABS(SUM(CASE WHEN backtest_trade.net_return < 0 THEN backtest_trade.net_return ELSE 0 END)), 0) AS profit_factor')
            ->selectRaw('MAX(backtest_trade.max_drawdown) * 100 AS drawdown')
            ->groupByRaw('LEAST(9, GREATEST(0, FLOOR(backtest_trade.ki_score)))::integer, LEAST(9, GREATEST(0, FLOOR(backtest_trade.confidence / 10)))::integer')
            ->get()
            ->keyBy(fn ($row) => $row->score_bucket.'-'.$row->confidence_bucket);
        return view('stocks.show', compact(
            'instrument',
            'prediction',
            'modelQuality',
            'modelQualityGateReasons',
            'modelChallenger',
            'aiAssessment',
            'aiAssessmentOpportunities',
            'aiAssessmentRisks',
            'aiAssessmentFactors',
            'topStockAnalysis',
            'topStockAnalysisDetails',
            'topStockFactorRatings',
            'predictionData',
            'ensembleData',
            'predictionExplanation',
            'predictionMetadata',
            'fundamental',
            'fundamentalData',
            'sectorRankings',
            'instrumentMeta',
            'chartCandles',
            'chartSource',
            'historicalAiScores',
            'historicalSignalTransitions',
            'chartFocusAt',
            'chartDataUrl',
            'requestedPredictionId',
            'signalChangedAt',
            'returnTo',
            'returnLabel',
            'watchlistEntry',
            'userWatchlists',
            'instrumentWatchlistIds',
            'indicatorCards',
            'stockHeatmap',
            'stockHeatmapSummary',
        ));
    }

    public function liveQuote(string $symbol, TwelveDataService $marketData): JsonResponse
    {
        $instrument = $this->instrument($symbol);
        $providerSymbol = (string) ($instrument->provider_symbol ?: $instrument->symbol);
        $streamQuote = Cache::get('twelve_data_stream_quote_'.sha1(strtoupper((string) $instrument->symbol)));

        try {
            $quote = is_numeric($streamQuote['price'] ?? null)
                ? $streamQuote
                : $marketData->liveQuote($providerSymbol);
        } catch (Throwable) {
            $quote = null;
        }

        if (! is_numeric($quote['price'] ?? null)) {
            return response()->json([
                'message' => __('Aktuell ist kein Livekurs verfügbar.'),
            ], 503);
        }

        return response()->json([
            'symbol' => (string) $instrument->symbol,
            'price' => (float) $quote['price'],
            'currency' => (string) (($quote['currency'] ?? null) ?: $instrument->currency ?: ''),
            'change_percent' => is_numeric($quote['change_percent'] ?? null)
                ? (float) $quote['change_percent']
                : null,
            'timestamp' => is_numeric($quote['timestamp'] ?? null)
                ? (int) $quote['timestamp']
                : now()->timestamp,
            'provider' => 'TwelveData',
        ]);
    }

    public function chartData(Request $request, string $symbol, TwelveDataService $yahooFinance): JsonResponse
    {
        $instrument = $this->instrument($symbol);
        $requestedPredictionId = $request->integer('prediction');
        $chartFocusAt = null;

        if ($requestedPredictionId > 0) {
            $predictionTime = DB::table('predictions')
                ->where('id', $requestedPredictionId)
                ->where('instrument_id', $instrument->id)
                ->value('prediction_time');
            abort_unless($predictionTime, 404);
            $chartFocusAt = CarbonImmutable::parse($predictionTime);
        }

        $series = $this->chartSeries($instrument, $yahooFinance, $chartFocusAt);

        return response()->json([
            'symbol' => $instrument->symbol,
            'candles' => $series['candles']->values(),
            'source' => $series['source'],
            'watchlist_entry' => $this->watchlistEntry($instrument->id),
            'updated_at' => now()->toIso8601String(),
        ]);
    }

    private function indicatorCards(object $instrument)
    {
        $indicators = DB::table('technical_indicators')
            ->where('instrument_id', $instrument->id)
            ->where('interval', '1d')
            ->where('bar_time', '>=', now()->subYears(3)->startOfDay())
            ->orderByDesc('bar_time')
            ->get([
                'bar_time', 'sma_20', 'sma_50', 'sma_200', 'ema_20', 'ema_50',
                'bollinger_upper', 'bollinger_middle', 'bollinger_lower', 'bollinger_width',
                'rsi_14', 'macd', 'macd_signal', 'macd_histogram', 'adx_14', 'atr_14',
                'stochastic_k', 'stochastic_d', 'volatility_20', 'momentum_10',
            ])
            ->reverse()
            ->values();
        $features = $indicators->isEmpty()
            ? collect()
            : DB::table('feature_store')
                ->where('instrument_id', $instrument->id)
                ->where('interval', '1d')
                ->whereIn('bar_time', $indicators->pluck('bar_time'))
                ->get(['bar_time', 'close', 'target_return_20d'])
                ->keyBy(fn (object $row): string => CarbonImmutable::parse($row->bar_time)->toIso8601String());
        $chartRows = $indicators->map(function (object $indicator) use ($features): array {
            $time = CarbonImmutable::parse($indicator->bar_time);
            $feature = $features->get($time->toIso8601String());

            return [
                'x' => $time->getTimestampMs(),
                'c' => $this->number($feature?->close),
                'rsi' => $this->number($indicator?->rsi_14),
                'adx' => $this->number($indicator?->adx_14),
                'stochK' => $this->number($indicator?->stochastic_k),
                'volatility' => $this->number($indicator?->volatility_20),
                'atr' => $this->number($indicator?->atr_14),
                'bbWidth' => $this->number($indicator?->bollinger_width),
                'macdHistogram' => $this->number($indicator?->macd_histogram),
                'momentum10' => $this->number($indicator?->momentum_10),
                'targetReturn20d' => $this->number($feature?->target_return_20d),
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
        $valueFor = function (?array $row, string $field): ?float {
            if (! $row) return null;
            $close = (float) ($row['c'] ?? 0);

            return match ($field) {
                'momentum10Pct' => abs($close - (float) ($row['momentum10'] ?? 0)) > 0.000001 && $row['momentum10'] !== null
                    ? $row['momentum10'] / ($close - $row['momentum10'])
                    : null,
                default => isset($row[$field]) && is_numeric($row[$field]) ? (float) $row[$field] : null,
            };
        };
        $currentRow = $chartRows->last();
        $fiveDayRow = $chartRows->count() >= 6 ? $chartRows->get($chartRows->count() - 6) : null;

        return collect($definitions)->map(function (array $definition) use ($chartRows, $currentRow, $fiveDayRow, $valueFor): array {
            $scale = $definition['unit'] === '%' ? 100.0 : 1.0;
            $currentRawValue = $valueFor($currentRow, $definition['field']);
            $fiveDayRawValue = $valueFor($fiveDayRow, $definition['field']);
            $fiveDayChange = $currentRawValue !== null && $fiveDayRawValue !== null
                ? ($currentRawValue - $fiveDayRawValue) * $scale
                : null;
            $points = $chartRows->map(function (array $row) use ($definition, $scale, $valueFor): ?array {
                $rawValue = $valueFor($row, $definition['field']);
                if ($rawValue === null || ! is_numeric($row['targetReturn20d'])) return null;
                $return = (float) $row['targetReturn20d'];

                return [
                    'x' => $rawValue * $scale,
                    'y' => $return * 100,
                    'up' => $return > 0,
                    'date' => CarbonImmutable::createFromTimestampMs($row['x'])->format('d.m.Y'),
                ];
            })->filter()->values();
            $nearby = $currentRawValue === null
                ? collect()
                : $points->sortBy(fn (array $point): float => abs($point['x'] - ($currentRawValue * $scale)))
                    ->take(min(40, $points->count()));
            $riseProbability = $nearby->isEmpty() ? null : ($nearby->where('up', true)->count() / $nearby->count()) * 100;

            return [
                ...$definition,
                'currentValue' => $currentRawValue === null ? null : $currentRawValue * $scale,
                'fiveDayChange' => $fiveDayChange,
                'fiveDayDirection' => $fiveDayChange === null ? null : (abs($fiveDayChange) < 0.000001 ? 'flat' : ($fiveDayChange > 0 ? 'up' : 'down')),
                'currentProbability' => $riseProbability,
                'currentFallProbability' => $riseProbability === null ? null : 100 - $riseProbability,
                'comparisonSamples' => $nearby->count(),
                'points' => $points,
            ];
        })->values();
    }

    private function instrument(string $symbol): object
    {
        $instrument = DB::table('instruments')
            ->where('type', 'stock')
            ->whereNull('deleted_at')
            ->whereRaw('UPPER(symbol) = ?', [strtoupper($symbol)])
            ->first();

        abort_unless($instrument, 404);

        return $instrument;
    }

    private function number(mixed $value): ?float
    {
        return is_numeric($value) ? (float) $value : null;
    }

    private function chartSeries(
        object $instrument,
        TwelveDataService $yahooFinance,
        ?CarbonImmutable $focusAt = null,
    ): array
    {
        $bars = $this->dailyBars((int) $instrument->id, $focusAt);

        if ($bars->count() < ($focusAt ? 50 : 100)) {
            try {
                $downloaded = $yahooFinance->dailyCandles(
                    $instrument->provider_symbol ?: $instrument->symbol,
                    $focusAt ? 140 : 110,
                );

                if ($downloaded) {
                    $now = now();
                    $rows = collect($downloaded)->map(fn (array $bar) => [
                        'instrument_id' => (int) $instrument->id,
                        'interval' => '1d',
                        'bar_time' => CarbonImmutable::createFromTimestampUTC($bar['timestamp']),
                        'open' => $bar['open'],
                        'high' => $bar['high'],
                        'low' => $bar['low'],
                        'close' => $bar['close'],
                        'adjusted_close' => $bar['adjusted_close'],
                        'volume' => $bar['volume'],
                        'source' => 'twelve_data',
                        'created_at' => $now,
                        'updated_at' => $now,
                    ])->all();

                    DB::table('price_bars')->upsert(
                        $rows,
                        ['instrument_id', 'interval', 'bar_time'],
                        ['open', 'high', 'low', 'close', 'adjusted_close', 'volume', 'source', 'updated_at'],
                    );
                    $bars = $this->dailyBars((int) $instrument->id, $focusAt);
                }
            } catch (Throwable $exception) {
                report($exception);
            }
        }

        return [
            'candles' => $bars->map(fn ($bar) => [
                'x' => CarbonImmutable::parse($bar->bar_time)->getTimestampMs(),
                'y' => [
                    (float) $bar->open,
                    (float) $bar->high,
                    (float) $bar->low,
                    (float) $bar->close,
                ],
            ]),
            'source' => $bars->isEmpty() ? 'unavailable' : ($bars->every(fn ($bar) => $bar->source === 'twelve_data') ? 'twelve_data' : 'price_bars'),
        ];
    }

    private function dailyBars(int $instrumentId, ?CarbonImmutable $focusAt = null)
    {
        $query = DB::table('price_bars')
            ->where('instrument_id', $instrumentId)
            ->where('interval', '1d');

        if ($focusAt) {
            return $query
                ->whereBetween('bar_time', [
                    $focusAt->subDays(50)->startOfDay(),
                    $focusAt->addDays(50)->endOfDay(),
                ])
                ->orderByDesc('bar_time')
                ->get()
                ->unique(fn ($bar) => CarbonImmutable::parse($bar->bar_time)->format('Y-m-d'))
                ->sortBy('bar_time')
                ->values();
        }

        return $query
            ->orderByDesc('bar_time')
            // A provider can persist the same trading day with a different
            // time component. Fetch enough rows, then keep exactly one OHLC
            // record per calendar/trading day.
            ->limit(160)
            ->get()
            ->unique(fn ($bar) => CarbonImmutable::parse($bar->bar_time)->format('Y-m-d'))
            ->take(100)
            ->reverse()
            ->values();
    }

    private function watchlistEntry(int $instrumentId): ?array
    {
        $entry = DB::table('watchlist_items as item')
            ->join('watchlists as watchlist', 'watchlist.id', '=', 'item.watchlist_id')
            ->where('watchlist.user_id', auth()->id())
            ->where('watchlist.active', true)
            ->where('item.instrument_id', $instrumentId)
            ->whereNotNull('item.entry_price')
            ->orderByDesc('watchlist.is_default')
            ->orderByDesc('item.added_at')
            ->select([
                'watchlist.name',
                'item.entry_price',
                'item.entry_price_at',
                'item.entry_currency',
            ])
            ->first();

        if (! $entry || ! is_numeric($entry->entry_price)) {
            return null;
        }

        return [
            'name' => $entry->name,
            'price' => (float) $entry->entry_price,
            'recorded_at' => $entry->entry_price_at,
            'currency' => $entry->entry_currency,
        ];
    }

    private function sectorRankings(object $instrument, array $fundamentalData): array
    {
        if (! $instrument->sector) {
            return [];
        }

        $latestFundamentalIds = DB::table('instrument_fundamentals')
            ->selectRaw('instrument_id, MAX(id) AS fundamental_id')
            ->groupBy('instrument_id');

        $sectorFundamentals = DB::table('instruments as peer')
            ->joinSub($latestFundamentalIds, 'latest_fundamental', fn ($join) =>
                $join->on('latest_fundamental.instrument_id', '=', 'peer.id'))
            ->join('instrument_fundamentals as fundamental', 'fundamental.id', '=', 'latest_fundamental.fundamental_id')
            ->where('peer.type', 'stock')
            ->whereNull('peer.deleted_at')
            ->where('peer.sector', $instrument->sector)
            ->pluck('fundamental.data')
            ->map(fn ($data) => $this->decodeJson($data));

        $definitions = [
            'pe' => ['key' => 'trailingPE', 'direction' => 'asc', 'positive_only' => true],
            'dividend' => ['key' => 'dividendYield', 'direction' => 'desc', 'positive_only' => false],
        ];

        return collect($definitions)
            ->mapWithKeys(function (array $definition, string $name) use ($sectorFundamentals, $fundamentalData): array {
                $current = $fundamentalData[$definition['key']] ?? null;
                if (! is_numeric($current) || ($definition['positive_only'] && (float) $current <= 0)) {
                    return [$name => null];
                }

                $current = (float) $current;
                $values = $sectorFundamentals
                    ->pluck($definition['key'])
                    ->filter(fn ($value) => is_numeric($value)
                        && (! $definition['positive_only'] || (float) $value > 0))
                    ->map(fn ($value) => (float) $value)
                    ->values();

                if ($values->isEmpty()) {
                    return [$name => null];
                }

                $better = $values->filter(fn (float $value) =>
                    $definition['direction'] === 'asc' ? $value < $current : $value > $current
                )->count();

                return [$name => [
                    'rank' => $better + 1,
                    'total' => $values->count(),
                ]];
            })
            ->all();
    }

    private function decodeJson(mixed $value): array
    {
        if (is_array($value)) {
            return $value;
        }

        if (! is_string($value) || $value === '') {
            return [];
        }

        return json_decode($value, true) ?: [];
    }

}
