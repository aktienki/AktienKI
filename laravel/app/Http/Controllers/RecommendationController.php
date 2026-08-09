<?php

namespace App\Http\Controllers;

use App\Services\PersonalizedSignalService;
use App\Services\TwelveDataService;
use App\Support\AiScore;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
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
            ->get(['symbol', 'provider_symbol', 'currency']);

        $quotes = $instruments->mapWithKeys(function (object $instrument) use ($marketData): array {
            $streamQuote = Cache::get('twelve_data_stream_quote_'.sha1(strtoupper((string) $instrument->symbol)));
            try {
                $referenceQuote = $marketData->quote((string) ($instrument->provider_symbol ?: $instrument->symbol));
                $quote = is_numeric($streamQuote['price'] ?? null)
                    ? [...($referenceQuote ?? []), ...$streamQuote]
                    : $marketData->liveQuote((string) ($instrument->provider_symbol ?: $instrument->symbol));
            } catch (Throwable) {
                $quote = null;
            }

            if (! is_numeric($quote['price'] ?? null)) {
                return [];
            }

            return [(string) $instrument->symbol => [
                'price' => (float) $quote['price'],
                'currency' => (string) (($quote['currency'] ?? null) ?: $instrument->currency ?: ''),
                'change_percent' => is_numeric($quote['change_percent'] ?? null)
                    ? (float) $quote['change_percent']
                    : null,
                'timestamp' => is_numeric($quote['timestamp'] ?? null)
                    ? (int) $quote['timestamp']
                    : now()->timestamp,
            ]];
        });

        return response()->json(['quotes' => $quotes]);
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
        $expectedReturn = match (true) {
            (float) $row->current_price !== 0.0 && is_numeric($row->predicted_price_20d) =>
                (((float) $row->predicted_price_20d - (float) $row->current_price) / (float) $row->current_price) * 100,
            (float) $row->current_price !== 0.0 && is_numeric($row->predicted_price_5d) =>
                (((float) $row->predicted_price_5d - (float) $row->current_price) / (float) $row->current_price) * 100,
            default => $this->returnPercent($row->economic_edge_return),
        };

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

    private function returnPercent(mixed $value): float
    {
        if (! is_numeric($value)) {
            return 0.0;
        }

        $number = (float) $value;

        return abs($number) <= 1.0 ? $number * 100.0 : $number;
    }
}
