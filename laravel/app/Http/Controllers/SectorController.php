<?php

namespace App\Http\Controllers;

use App\Services\PersonalizedSignalService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class SectorController extends Controller
{
    public function index(PersonalizedSignalService $personalizedSignals): View
    {
        $signalSql = $personalizedSignals->sql('prediction', auth()->user());
        $latestPredictions = DB::table('predictions')
            ->selectRaw('instrument_id, MAX(id) AS prediction_id')
            ->groupBy('instrument_id');

        $fiveDayBaselines = DB::table('predictions')
            ->where('prediction_time', '>=', now()->subDays(5))
            ->selectRaw('instrument_id, MIN(id) AS prediction_id')
            ->groupBy('instrument_id');

        $latestFundamentals = DB::table('instrument_fundamentals')
            ->selectRaw('instrument_id, MAX(id) AS fundamental_id')
            ->groupBy('instrument_id');

        $sectors = DB::table('instruments as instrument')
            ->leftJoinSub($latestPredictions, 'latest', fn ($join) =>
                $join->on('latest.instrument_id', '=', 'instrument.id'))
            ->leftJoin('predictions as prediction', 'prediction.id', '=', 'latest.prediction_id')
            ->leftJoinSub($fiveDayBaselines, 'baseline', fn ($join) =>
                $join->on('baseline.instrument_id', '=', 'instrument.id'))
            ->leftJoin('predictions as baseline_prediction', 'baseline_prediction.id', '=', 'baseline.prediction_id')
            ->leftJoinSub($latestFundamentals, 'latest_fundamental', fn ($join) =>
                $join->on('latest_fundamental.instrument_id', '=', 'instrument.id'))
            ->leftJoin('instrument_fundamentals as fundamental', 'fundamental.id', '=', 'latest_fundamental.fundamental_id')
            ->where('instrument.type', 'stock')
            ->where('instrument.is_active', true)
            ->whereNull('instrument.deleted_at')
            ->whereNotNull('instrument.sector')
            ->where('instrument.sector', '<>', '')
            ->groupBy('instrument.sector')
            ->selectRaw('instrument.sector')
            ->selectRaw('COUNT(*) AS stocks_count')
            ->selectRaw('COUNT(prediction.id) AS analyzed_count')
            ->selectRaw('AVG(prediction.prediction_score) AS average_score')
            ->selectRaw('AVG(
                ((prediction.predicted_price_20d - prediction.current_price)
                / NULLIF(prediction.current_price, 0)) * 100
            ) AS average_expected_return_20d')
            ->selectRaw('AVG(prediction.confidence) AS average_confidence')
            ->selectRaw('PERCENTILE_CONT(0.75) WITHIN GROUP (
                ORDER BY COALESCE(prediction.risk_score, prediction.drawdown_risk_factor)
            ) AS risk_p75')
            ->selectRaw('AVG(baseline_prediction.prediction_score) AS five_day_baseline_score')
            ->selectRaw('AVG(prediction.prediction_score) - AVG(baseline_prediction.prediction_score) AS five_day_score_change')
            ->selectRaw("COUNT(*) FILTER (WHERE ({$signalSql}) = 'BUY') AS buy_count")
            ->selectRaw("COUNT(*) FILTER (WHERE ({$signalSql}) = 'HOLD') AS hold_count")
            ->selectRaw("COUNT(*) FILTER (WHERE ({$signalSql}) = 'WATCH') AS watch_count")
            ->selectRaw("COUNT(*) FILTER (WHERE ({$signalSql}) = 'SELL') AS sell_count")
            ->selectRaw('COUNT(fundamental.id) AS fundamental_count')
            ->selectRaw("AVG(NULLIF(fundamental.data->>'trailingPE', '')::numeric) AS average_pe")
            ->selectRaw("AVG(NULLIF(fundamental.data->>'profitMargins', '')::numeric) AS average_profit_margin")
            ->selectRaw("AVG(NULLIF(fundamental.data->>'revenueGrowth', '')::numeric) AS average_revenue_growth")
            ->selectRaw("AVG(NULLIF(fundamental.data->>'dividendYield', '')::numeric) AS average_dividend_yield")
            ->orderByRaw('AVG(prediction.prediction_score) DESC NULLS LAST')
            ->get();

        $latestQuotes = DB::table('current_stock_quotes')
            ->where('status', 'current')
            ->whereRaw('LOWER(provider) = ?', ['twelvedata'])
            ->selectRaw('instrument_id, MAX(id) AS quote_id')
            ->groupBy('instrument_id');
        $rankedSectorStocks = DB::table('instruments as instrument')
            ->joinSub($latestPredictions, 'latest', fn ($join) =>
                $join->on('latest.instrument_id', '=', 'instrument.id'))
            ->join('predictions as prediction', 'prediction.id', '=', 'latest.prediction_id')
            ->leftJoinSub($latestQuotes, 'latest_quote', fn ($join) =>
                $join->on('latest_quote.instrument_id', '=', 'instrument.id'))
            ->leftJoin('current_stock_quotes as current_quote', 'current_quote.id', '=', 'latest_quote.quote_id')
            ->where('instrument.type', 'stock')
            ->where('instrument.is_active', true)
            ->whereNull('instrument.deleted_at')
            ->whereNotNull('instrument.sector')
            ->where('instrument.sector', '<>', '')
            ->whereNotNull('prediction.prediction_score')
            ->select([
                'instrument.sector',
                'instrument.id as instrument_id',
                'instrument.symbol',
                'instrument.name',
                'instrument.currency',
                'prediction.current_price as prediction_price',
                'prediction.prediction_score as ai_score',
                'current_quote.price as live_price',
                'current_quote.quote_time',
            ])
            ->selectRaw('COALESCE(prediction.risk_score, prediction.drawdown_risk_factor) AS risk_score')
            ->selectRaw("({$signalSql}) AS personalized_signal")
            ->selectRaw('ROW_NUMBER() OVER (
                PARTITION BY instrument.sector
                ORDER BY prediction.prediction_score DESC NULLS LAST, instrument.symbol
            ) AS sector_rank');
        $topSectorStocks = Cache::remember('sectors_top_stocks_twelvedata_user_'.auth()->id().'_v1', now()->addSeconds(20), fn () => DB::query()
            ->fromSub($rankedSectorStocks, 'ranked_stock')
            ->where('sector_rank', 1)
            ->get()
            ->keyBy('sector'));
        $sectors->each(fn ($sector) => $sector->highest_score_stock = $topSectorStocks->get($sector->sector));

        $latestAnalysis = DB::table('daily_market_ai_analyses')
            ->orderByDesc('analysis_date')
            ->orderByDesc('id')
            ->first(['analysis_date', 'sector_analysis']);
        $sectorComments = json_decode((string) ($latestAnalysis?->sector_analysis ?? '[]'), true);
        $sectorComments = is_array($sectorComments) ? collect($sectorComments) : collect();
        $sectorAnalysisDate = $latestAnalysis?->analysis_date;

        return view('sectors.index', compact('sectors', 'sectorComments', 'sectorAnalysisDate'));
    }
}
