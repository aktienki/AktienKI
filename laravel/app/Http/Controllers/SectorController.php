<?php

namespace App\Http\Controllers;

use App\Enums\PlanLevel;
use App\Services\FreeRegionalStockUniverseService;
use App\Services\PlanAccessService;
use App\Services\PersonalizedSignalService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SectorController extends Controller
{
    public function index(Request $request, PersonalizedSignalService $personalizedSignals, PlanAccessService $planAccess): View
    {
        $isFreeRegional = $planAccess->level($request->user()) === PlanLevel::Free;
        $regionalUniverse = app(FreeRegionalStockUniverseService::class);
        $allowedInstrumentIds = $isFreeRegional ? $regionalUniverse->instrumentIds($request->user())->all() : [];
        $regionalCountry = $regionalUniverse->country($request->user());
        $realtimeQuotes = $planAccess->allowsTariff($request->user(), PlanLevel::Pro);
        $signalSql = $personalizedSignals->sql('prediction', auth()->user());
        $latestPredictions = DB::table('predictions')
            ->selectRaw('instrument_id, MAX(id) AS prediction_id')
            ->groupBy('instrument_id');

        $latestCompletedRuns = DB::table('walk_forward_backtest_trades as candidate_trade')
            ->join('walk_forward_backtest_runs as candidate_run', 'candidate_run.id', '=', 'candidate_trade.run_id')
            ->where('candidate_run.status', 'completed')
            ->whereIn('candidate_trade.horizon_days', [5, 10, 15, 20])
            ->groupBy('candidate_trade.instrument_id', 'candidate_trade.horizon_days')
            ->select('candidate_trade.instrument_id', 'candidate_trade.horizon_days')
            ->selectRaw('MAX(candidate_trade.run_id) AS run_id');

        $walkForwardStats = DB::table('walk_forward_backtest_trades as trade')
            ->joinSub($latestCompletedRuns, 'latest_run', function ($join) {
                $join->on('latest_run.instrument_id', '=', 'trade.instrument_id')
                    ->on('latest_run.horizon_days', '=', 'trade.horizon_days')
                    ->on('latest_run.run_id', '=', 'trade.run_id');
            })
            ->groupBy('trade.instrument_id')
            ->select('trade.instrument_id')
            ->selectRaw('AVG(CASE WHEN net_return > 0 THEN 1.0 ELSE 0.0 END) * 100 AS hit_rate')
            ->selectRaw('AVG(net_return) * 100 AS profit_per_trade')
            ->selectRaw('COUNT(*) AS historical_trades');

        $fiveDayBaselines = DB::table('predictions')
            ->where('prediction_time', '>=', now()->subDays(5))
            ->selectRaw('instrument_id, MIN(id) AS prediction_id')
            ->groupBy('instrument_id');

        $latestFundamentals = DB::table('instrument_fundamentals')
            ->selectRaw('instrument_id, MAX(id) AS fundamental_id')
            ->groupBy('instrument_id');

        $sectors = DB::table('instruments as instrument')
            ->leftJoin('market_sectors as market_sector', fn ($join) => $join->whereRaw('LOWER(market_sector.name) = LOWER(instrument.sector)'))
            ->joinSub($latestPredictions, 'latest', fn ($join) =>
                $join->on('latest.instrument_id', '=', 'instrument.id'))
            ->join('predictions as prediction', 'prediction.id', '=', 'latest.prediction_id')
            ->leftJoinSub($walkForwardStats, 'walk_forward', fn ($join) =>
                $join->on('walk_forward.instrument_id', '=', 'instrument.id'))
            ->leftJoinSub($fiveDayBaselines, 'baseline', fn ($join) =>
                $join->on('baseline.instrument_id', '=', 'instrument.id'))
            ->leftJoin('predictions as baseline_prediction', 'baseline_prediction.id', '=', 'baseline.prediction_id')
            ->leftJoinSub($latestFundamentals, 'latest_fundamental', fn ($join) =>
                $join->on('latest_fundamental.instrument_id', '=', 'instrument.id'))
            ->leftJoin('instrument_fundamentals as fundamental', 'fundamental.id', '=', 'latest_fundamental.fundamental_id')
            ->where('instrument.type', 'stock')
            ->where(fn ($query) => $query->whereNull('instrument.risk_status')->orWhere('instrument.risk_status', '<>', 'sleep'))
            ->where('instrument.is_active', true)
            ->whereNull('instrument.deleted_at')
            ->whereNotNull('instrument.sector')
            ->where('instrument.sector', '<>', '')
            ->whereNotNull('prediction.prediction_score')
            ->when($isFreeRegional, fn ($query) => $query->whereIn('instrument.id', $allowedInstrumentIds))
            ->when($request->filled('q'), function ($query) use ($request): void {
                $term = '%'.mb_strtolower(trim((string) $request->query('q'))).'%';
                $query->whereRaw('LOWER(instrument.sector) LIKE ?', [$term]);
            })
            ->groupBy('instrument.sector', 'market_sector.description', 'market_sector.rating', 'market_sector.assessment')
            ->selectRaw('instrument.sector')
            ->selectRaw('market_sector.description AS description')
            ->selectRaw('market_sector.rating AS stored_rating')
            ->selectRaw('market_sector.assessment AS assessment')
            ->selectRaw('COUNT(*) AS stocks_count')
            ->selectRaw('COUNT(prediction.id) AS analyzed_count')
            ->selectRaw('AVG(prediction.prediction_score) AS average_score')
            ->selectRaw('AVG(
                ((prediction.predicted_price_20d - prediction.current_price)
                / NULLIF(prediction.current_price, 0)) * 100
            ) AS average_expected_return_20d')
            ->selectRaw('AVG(prediction.confidence) AS average_confidence')
            ->selectRaw('AVG(walk_forward.hit_rate) AS average_hit_rate')
            ->selectRaw('AVG(walk_forward.profit_per_trade) AS average_profit_per_trade')
            ->selectRaw('AVG(prediction.horizon_fusion_stability_score) AS average_stability')
            ->selectRaw('SUM(COALESCE(walk_forward.historical_trades, 0)) AS historical_trades')
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
        $rankedDailyBars = DB::table('price_bars')
            ->where('interval', '1d')
            ->select(['instrument_id', 'close', 'bar_time'])
            ->selectRaw('ROW_NUMBER() OVER (PARTITION BY instrument_id ORDER BY bar_time DESC, id DESC) AS bar_rank');
        $dailyCloses = DB::query()
            ->fromSub($rankedDailyBars, 'ranked_bar')
            ->where('bar_rank', '<=', 2)
            ->groupBy('instrument_id')
            ->select('instrument_id')
            ->selectRaw('MAX(close) FILTER (WHERE bar_rank = 1) AS latest_daily_close')
            ->selectRaw('MAX(close) FILTER (WHERE bar_rank = 2) AS previous_daily_close')
            ->selectRaw('MAX(bar_time) FILTER (WHERE bar_rank = 1) AS latest_daily_time');
        $rankedSectorStocks = DB::table('instruments as instrument')
            ->joinSub($latestPredictions, 'latest', fn ($join) =>
                $join->on('latest.instrument_id', '=', 'instrument.id'))
            ->join('predictions as prediction', 'prediction.id', '=', 'latest.prediction_id')
            ->leftJoinSub($latestQuotes, 'latest_quote', fn ($join) =>
                $join->on('latest_quote.instrument_id', '=', 'instrument.id'))
            ->leftJoin('current_stock_quotes as current_quote', 'current_quote.id', '=', 'latest_quote.quote_id')
            ->leftJoinSub($dailyCloses, 'daily_close', fn ($join) =>
                $join->on('daily_close.instrument_id', '=', 'instrument.id'))
            ->where('instrument.type', 'stock')
            ->where(fn ($query) => $query->whereNull('instrument.risk_status')->orWhere('instrument.risk_status', '<>', 'sleep'))
            ->where('instrument.is_active', true)
            ->whereNull('instrument.deleted_at')
            ->whereNotNull('instrument.sector')
            ->where('instrument.sector', '<>', '')
            ->whereNotNull('prediction.prediction_score')
            ->when($isFreeRegional, fn ($query) => $query->whereIn('instrument.id', $allowedInstrumentIds))
            ->select([
                'instrument.sector',
                'instrument.id as instrument_id',
                'instrument.symbol',
                'instrument.name',
                'instrument.country',
                'instrument.currency',
                'prediction.id as prediction_id',
                'prediction.current_price as prediction_price',
                'prediction.prediction_score as ai_score',
                'current_quote.price as live_price',
                'current_quote.quote_time',
                'daily_close.latest_daily_close',
                'daily_close.previous_daily_close',
                'daily_close.latest_daily_time',
            ])
            ->selectRaw('COALESCE(prediction.risk_score, prediction.drawdown_risk_factor) AS risk_score')
            ->selectRaw("({$signalSql}) AS personalized_signal")
            ->selectRaw('ROW_NUMBER() OVER (
                PARTITION BY instrument.sector
                ORDER BY prediction.prediction_score DESC NULLS LAST, instrument.symbol
            ) AS sector_rank');
        $topSectorStocks = Cache::remember('sectors_top_stocks_twelvedata_user_'.auth()->id().'_v4_'.($isFreeRegional ? sha1(implode(',', $allowedInstrumentIds)) : 'all'), now()->addSeconds(20), fn () => DB::query()
            ->fromSub($rankedSectorStocks, 'ranked_stock')
            ->where('sector_rank', '<=', 3)
            ->orderBy('sector')
            ->orderBy('sector_rank')
            ->get()
            ->groupBy('sector'));
        $sectors->each(fn ($sector) => $sector->top_stocks = $topSectorStocks->get($sector->sector, collect()));

        $sectorEtfCharts = DB::table('market_sectors as market_sector')
            ->join('price_bars as bar', 'bar.instrument_id', '=', 'market_sector.reference_instrument_id')
            ->where('bar.interval', '1d')
            ->where('bar.bar_time', '>=', now()->subYear())
            ->orderBy('bar.bar_time')
            ->get(['market_sector.name as sector', 'bar.bar_time', 'bar.close'])
            ->groupBy('sector')
            ->map(fn ($bars) => $bars->map(fn ($bar) => [
                'date' => (string) $bar->bar_time,
                'close' => (float) $bar->close,
            ])->values());
        $sectors->each(fn ($sector) => $sector->etf_chart_points = $sectorEtfCharts->get($sector->sector, collect()));

        $dailySectorScores = DB::table('instruments as score_instrument')
            ->join('predictions as score_prediction', 'score_prediction.instrument_id', '=', 'score_instrument.id')
            ->where('score_instrument.type', 'stock')
            ->where(fn ($query) => $query->whereNull('score_instrument.risk_status')->orWhere('score_instrument.risk_status', '<>', 'sleep'))
            ->where('score_instrument.is_active', true)
            ->whereNull('score_instrument.deleted_at')
            ->whereNotNull('score_instrument.sector')
            ->where('score_instrument.sector', '<>', '')
            ->where('score_prediction.prediction_time', '>=', now()->subDays(60))
            ->whereNotNull('score_prediction.prediction_score')
            ->when($isFreeRegional, fn ($query) => $query->whereIn('score_instrument.id', $allowedInstrumentIds))
            ->selectRaw('DISTINCT ON (score_instrument.sector, score_instrument.id, DATE(score_prediction.prediction_time)) score_instrument.sector, score_instrument.id AS instrument_id, DATE(score_prediction.prediction_time) AS score_date')
            ->selectRaw('CASE WHEN score_prediction.prediction_score <= 1 THEN score_prediction.prediction_score * 100 WHEN score_prediction.prediction_score <= 10 THEN score_prediction.prediction_score * 10 ELSE score_prediction.prediction_score END AS normalized_score')
            ->orderBy('score_instrument.sector')
            ->orderBy('score_instrument.id')
            ->orderByRaw('DATE(score_prediction.prediction_time)')
            ->orderByDesc('score_prediction.prediction_time')
            ->orderByDesc('score_prediction.id');
        $sectorScoreTrends = Cache::remember(
            'sector_screener_score_trends_v1_'.($isFreeRegional ? sha1(implode(',', $allowedInstrumentIds)) : 'all'),
            now()->addMinutes(10),
            fn () => DB::query()->fromSub($dailySectorScores, 'daily_sector_score')
                ->groupBy('sector', 'score_date')
                ->orderBy('sector')->orderBy('score_date')
                ->get(['sector', 'score_date', DB::raw('AVG(normalized_score) AS average_score')])
                ->groupBy('sector')
                ->map(fn ($points) => $points->take(-30)->values())
        );
        $sectors->each(fn ($sector) => $sector->score_trend = $sectorScoreTrends->get($sector->sector, collect()));

        $latestAnalysis = $isFreeRegional ? null : DB::table('daily_market_ai_analyses')
            ->orderByDesc('analysis_date')
            ->orderByDesc('id')
            ->first(['analysis_date', 'sector_analysis']);
        $sectorComments = json_decode((string) ($latestAnalysis?->sector_analysis ?? '[]'), true);
        $sectorComments = is_array($sectorComments) ? collect($sectorComments) : collect();
        $sectorAnalysisDate = $latestAnalysis?->analysis_date;

        return view('sectors.index', compact('sectors', 'sectorComments', 'sectorAnalysisDate', 'realtimeQuotes', 'isFreeRegional', 'regionalCountry'));
    }
}
