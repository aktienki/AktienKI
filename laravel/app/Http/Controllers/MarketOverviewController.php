<?php

namespace App\Http\Controllers;

use App\Services\PersonalizedSignalService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class MarketOverviewController extends Controller
{
    public function __invoke(PersonalizedSignalService $signals): View
    {
        $latestPredictions = DB::table('predictions as prediction')
            ->selectRaw('DISTINCT ON (prediction.instrument_id)
                prediction.id,
                prediction.instrument_id,
                prediction.ai_score,
                prediction.prediction_score,
                prediction.confidence,
                prediction.risk_score,
                prediction.drawdown_risk_factor,
                prediction.current_price,
                prediction.predicted_price_5d,
                prediction.predicted_price_20d,
                prediction.recommendation_class,
                prediction.signal,
                prediction.prediction_time')
            ->orderBy('prediction.instrument_id')
            ->orderByDesc('prediction.prediction_time')
            ->orderByDesc('prediction.id');

        $personalizedSignalSql = $signals->sql('prediction', auth()->user());
        $marketCacheKey = 'markets_overview_user_'.auth()->id();
        $exchanges = Cache::remember($marketCacheKey, now()->addSeconds(30), fn () => DB::query()
                ->fromSub($latestPredictions, 'prediction')
                ->join('instruments as instrument', 'instrument.id', '=', 'prediction.instrument_id')
                ->join('exchanges as exchange', 'exchange.id', '=', 'instrument.exchange_id')
                ->where('instrument.is_active', true)
                ->whereNull('instrument.deleted_at')
                ->selectRaw("
                exchange.code,
                exchange.name,
                exchange.country,
                exchange.currency,
                COUNT(*) AS instrument_count,
                AVG(COALESCE(prediction.prediction_score, prediction.ai_score)) AS average_score,
                AVG(prediction.confidence) AS average_confidence,
                AVG(COALESCE(prediction.risk_score, prediction.drawdown_risk_factor)) AS average_risk,
                PERCENTILE_CONT(0.75) WITHIN GROUP (
                    ORDER BY COALESCE(prediction.risk_score, prediction.drawdown_risk_factor)
                ) AS risk_p75,
                MAX(prediction.prediction_time) AS latest_prediction,
                SUM(CASE WHEN ({$personalizedSignalSql}) = 'BUY' THEN 1 ELSE 0 END) AS buy_count,
                SUM(CASE WHEN ({$personalizedSignalSql}) = 'WATCH' THEN 1 ELSE 0 END) AS watch_count,
                SUM(CASE WHEN ({$personalizedSignalSql}) = 'HOLD' THEN 1 ELSE 0 END) AS hold_count,
                SUM(CASE WHEN ({$personalizedSignalSql}) = 'SELL' THEN 1 ELSE 0 END) AS sell_count
                ")
                ->groupBy('exchange.id', 'exchange.code', 'exchange.name', 'exchange.country', 'exchange.currency')
                ->orderByDesc('instrument_count')
                ->get());

        $latestQuotes = DB::table('current_stock_quotes')
            ->where('status', 'current')
            ->whereRaw('LOWER(provider) = ?', ['twelvedata'])
            ->selectRaw('instrument_id, MAX(id) AS quote_id')
            ->groupBy('instrument_id');
        $rankedStocks = DB::query()
            ->fromSub(clone $latestPredictions, 'prediction')
            ->join('instruments as instrument', 'instrument.id', '=', 'prediction.instrument_id')
            ->join('exchanges as exchange', 'exchange.id', '=', 'instrument.exchange_id')
            ->leftJoinSub($latestQuotes, 'latest_quote', fn ($join) =>
                $join->on('latest_quote.instrument_id', '=', 'instrument.id'))
            ->leftJoin('current_stock_quotes as current_quote', 'current_quote.id', '=', 'latest_quote.quote_id')
            ->where('instrument.type', 'stock')
            ->where(fn ($query) => $query->whereNull('instrument.risk_status')->orWhere('instrument.risk_status', '<>', 'sleep'))
            ->where('instrument.is_active', true)
            ->whereNull('instrument.deleted_at')
            ->whereNotNull(DB::raw('COALESCE(prediction.prediction_score, prediction.ai_score)'))
            ->select([
                'exchange.code as exchange_code',
                'instrument.id as instrument_id',
                'instrument.symbol',
                'instrument.name',
                'instrument.currency',
                'prediction.current_price as prediction_price',
                'current_quote.price as live_price',
                'current_quote.quote_time',
            ])
            ->selectRaw('COALESCE(prediction.prediction_score, prediction.ai_score) AS ai_score')
            ->selectRaw('COALESCE(prediction.risk_score, prediction.drawdown_risk_factor) AS risk_score')
            ->selectRaw("({$personalizedSignalSql}) AS personalized_signal")
            ->selectRaw('ROW_NUMBER() OVER (
                PARTITION BY exchange.code
                ORDER BY COALESCE(prediction.prediction_score, prediction.ai_score) DESC NULLS LAST, instrument.symbol
            ) AS high_rank');
        $extremeStocks = Cache::remember('markets_top_stocks_twelvedata_user_'.auth()->id().'_v2', now()->addSeconds(20), fn () => DB::query()
            ->fromSub($rankedStocks, 'ranked_stock')
            ->where('high_rank', 1)
            ->get()
            ->groupBy('exchange_code'));

        $exchanges->each(function ($exchange) use ($extremeStocks): void {
            $stocks = $extremeStocks->get($exchange->code, collect());
            $exchange->highest_score_stock = $stocks->firstWhere('high_rank', 1);
        });

        $referenceIndices = MarketQuotesController::REFERENCE_INDICES;
        $indexBars = MarketQuotesController::referenceQuotes();
        $exchanges->transform(function ($exchange) use ($referenceIndices, $indexBars) {
            [$symbol, $name] = $referenceIndices[$exchange->code] ?? [null, null];
            $quote = $symbol ? $indexBars->get($symbol) : null;
            $exchange->reference_symbol = $symbol;
            $exchange->reference_name = $name;
            $exchange->market_price = $quote['price'] ?? null;
            $exchange->market_change = $quote['change_percent'] ?? null;
            $exchange->market_currency = $quote['currency'] ?? $exchange->currency;

            return $exchange;
        });

        $marketAnalysis = Cache::remember('markets_latest_ai_analysis_v2', now()->addMinute(), fn () =>
            DB::table('daily_market_ai_analyses')
                ->select('analysis_date', 'headline', 'executive_summary', 'breadth_analysis', 'market_outlook', 'confidence', 'index_analysis')
                ->orderByDesc('analysis_date')
                ->orderByDesc('id')
                ->first());

        $indexAnalyses = collect(json_decode((string) ($marketAnalysis?->index_analysis ?? '[]'), true))
            ->filter(fn (mixed $analysis): bool => is_array($analysis));
        $exchanges->each(function (object $exchange) use ($indexAnalyses): void {
            $exchange->index_analysis = $indexAnalyses->first(fn (array $analysis): bool =>
                strtoupper(trim((string) ($analysis['symbol'] ?? ''))) === strtoupper(trim((string) $exchange->reference_symbol))
            ) ?? $indexAnalyses->first(fn (array $analysis): bool =>
                mb_strtolower(trim((string) ($analysis['name'] ?? ''))) === mb_strtolower(trim((string) $exchange->reference_name))
            );
        });

        // Cross-asset context for the market overview.  The series are read
        // from the same persisted price-bar store used by the training
        // pipeline, so the cards remain useful even when no AI commentary is
        // available.
        $macroBars = DB::table('instruments as instrument')
            ->join('price_bars as bar', 'bar.instrument_id', '=', 'instrument.id')
            ->whereIn('instrument.symbol', ['US2Y', 'US10Y'])
            ->whereIn('bar.interval', ['1d', '1h'])
            ->orderByDesc('bar.bar_time')
            ->orderByDesc('bar.id')
            ->limit(180)
            ->get(['instrument.symbol', 'bar.close', 'bar.bar_time'])
            ->groupBy('symbol')
            ->map(fn ($rows) => $rows->sortBy('bar_time')->values());

        $macroSeries = static function ($rows): array {
            return collect($rows ?? [])->map(fn (object $row): array => [
                'label' => Carbon::parse($row->bar_time)->format('d.m.'),
                'value' => is_numeric($row->close) ? (float) $row->close : null,
            ])->filter(fn (array $point): bool => $point['value'] !== null)->values()->all();
        };
        $us2y = $macroSeries($macroBars->get('US2Y', collect()));
        $us10y = $macroSeries($macroBars->get('US10Y', collect()));

        // Use the actual VDAX-NEW index (ISIN A0DMX9) here.  The previous
        // implementation used realised S&P 500 volatility, which is a
        // different measure and therefore did not match the VDAX detail page.
        $vdaxId = DB::table('instruments')
            ->where('isin', 'A0DMX9')
            ->orWhere('symbol', 'VDAX')
            ->value('id');
        $volatilitySeries = $vdaxId
            ? DB::table('price_bars')
                ->where('instrument_id', $vdaxId)
                ->where('interval', '1d')
                ->orderByDesc('bar_time')
                ->limit(260)
                ->get(['bar_time', 'close'])
                ->sortBy('bar_time')
                ->map(fn (object $row): array => [
                    'label' => Carbon::parse($row->bar_time)->format('d.m.'),
                    'value' => is_numeric($row->close) ? (float) $row->close : null,
                ])->filter(fn (array $point): bool => $point['value'] !== null)->values()->all()
            : [];

        $macroCards = [
            [
                'key' => 'rates', 'title' => __('Zinsen'),
                'subtitle' => __('US Treasury Renditen · 2J und 10J'),
                'series' => [['name' => 'US 2J', 'color' => '#22d3ee', 'points' => $us2y], ['name' => 'US 10J', 'color' => '#fbbf24', 'points' => $us10y]],
                'unit' => '%',
            ],
            [
                'key' => 'vdax', 'title' => __('Volatilität'),
                'subtitle' => __('VDAX-NEW · A0DMX9 · letzter Jahresverlauf'),
                'series' => [['name' => __('VDAX'), 'color' => '#fb7185', 'points' => $volatilitySeries]],
                'unit' => '%',
            ],
            [
                'key' => 'bonds', 'title' => __('Anleihen'),
                'subtitle' => __('US 10J · historische Renditereihe'),
                'series' => [['name' => 'US 10J', 'color' => '#34d399', 'points' => $us10y]],
                'unit' => '%',
            ],
        ];

        return view('markets.index', compact('exchanges', 'marketAnalysis', 'macroCards'));
    }
}
