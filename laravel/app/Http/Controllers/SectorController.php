<?php

namespace App\Http\Controllers;

use App\Services\PersonalizedSignalService;
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
            ->whereNull('instrument.deleted_at')
            ->whereNotNull('instrument.sector')
            ->where('instrument.sector', '<>', '')
            ->groupBy('instrument.sector')
            ->selectRaw('instrument.sector')
            ->selectRaw('COUNT(*) AS stocks_count')
            ->selectRaw('COUNT(prediction.id) AS analyzed_count')
            ->selectRaw('AVG(prediction.prediction_score) AS average_score')
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

        return view('sectors.index', compact('sectors'));
    }
}
