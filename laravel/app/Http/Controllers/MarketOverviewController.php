<?php

namespace App\Http\Controllers;

use App\Services\YahooFinanceService;
use App\Services\PersonalizedSignalService;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class MarketOverviewController extends Controller
{
    public function __invoke(YahooFinanceService $yahoo, PersonalizedSignalService $signals): View
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

        $exchanges = DB::query()
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
            ->get();

        $referenceIndices = [
            'AMS' => ['^AEX', 'AEX'],
            'BTS' => ['^GSPC', 'S&P 500'],
            'EBS' => ['^SSMI', 'SMI'],
            'GER' => ['^GDAXI', 'DAX'],
            'LSE' => ['^FTSE', 'FTSE 100'],
            'PAR' => ['^FCHI', 'CAC 40'],
            'XASX' => ['^AXJO', 'ASX 200'],
            'XHKG' => ['^HSI', 'Hang Seng'],
            'XJSE' => ['^J203.JO', 'FTSE/JSE All Share'],
            'XNAS' => ['^IXIC', 'NASDAQ Composite'],
            'XNYS' => ['^NYA', 'NYSE Composite'],
            'XTKS' => ['^N225', 'Nikkei 225'],
        ];
        $quotes = $yahoo->quotes(collect($referenceIndices)->pluck(0)->all());
        $exchanges->transform(function ($exchange) use ($referenceIndices, $quotes) {
            [$symbol, $name] = $referenceIndices[$exchange->code] ?? [null, null];
            $quote = $symbol ? ($quotes[$symbol] ?? null) : null;
            $exchange->reference_symbol = $symbol;
            $exchange->reference_name = $name;
            $exchange->market_price = $quote['price'] ?? null;
            $exchange->market_change = $quote['change_percent'] ?? null;
            $exchange->market_currency = $quote['currency'] ?? $exchange->currency;

            return $exchange;
        });

        $marketAnalysis = DB::table('daily_market_ai_analyses')
            ->select('analysis_date', 'headline', 'executive_summary', 'breadth_analysis', 'market_outlook', 'confidence')
            ->orderByDesc('analysis_date')
            ->orderByDesc('id')
            ->first();

        return view('markets.index', compact('exchanges', 'marketAnalysis'));
    }
}
