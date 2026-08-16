<?php

namespace App\Http\Controllers;

use App\Enums\PlanLevel;
use App\Services\FreeRegionalStockUniverseService;
use App\Services\PersonalizedSignalService;
use App\Services\PlanAccessService;
use App\Support\AiScore;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class IndexScreenerController extends Controller
{
    public function __invoke(Request $request, PersonalizedSignalService $personalizedSignals, PlanAccessService $planAccess): View
    {
        $isFreeRegional = $planAccess->level($request->user()) === PlanLevel::Free;
        $regionalUniverse = app(FreeRegionalStockUniverseService::class);
        $allowedInstrumentIds = $isFreeRegional ? $regionalUniverse->instrumentIds($request->user())->all() : [];
        $regionalCountry = $regionalUniverse->country($request->user());
        $realtimeQuotes = $planAccess->allowsTariff($request->user(), PlanLevel::Pro);
        $signalSql = $personalizedSignals->sql('prediction', $request->user());
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

        $latestPredictions = DB::table('predictions')
            ->selectRaw('instrument_id, MAX(id) AS prediction_id')
            ->groupBy('instrument_id');

        $query = DB::table('market_indices as market_index')
            ->leftJoin('index_memberships as membership', function ($join) {
                $join->on('membership.market_index_id', '=', 'market_index.id')->whereNull('membership.removed_at');
            })
            ->leftJoinSub($latestPredictions, 'latest', fn ($join) => $join->on('latest.instrument_id', '=', 'membership.instrument_id'))
            ->leftJoin('predictions as prediction', 'prediction.id', '=', 'latest.prediction_id')
            ->leftJoinSub($walkForwardStats, 'walk_forward', fn ($join) => $join->on('walk_forward.instrument_id', '=', 'membership.instrument_id'))
            ->where('market_index.is_active', true)
            ->when($isFreeRegional, fn ($query) => $query->whereIn('membership.instrument_id', $allowedInstrumentIds))
            ->when($request->filled('q'), function ($query) use ($request) {
                $term = '%'.mb_strtolower(trim((string) $request->query('q'))).'%';
                $query->where(fn ($nested) => $nested->whereRaw('LOWER(market_index.name) LIKE ?', [$term])->orWhereRaw('LOWER(market_index.symbol) LIKE ?', [$term]));
            })
            ->when($request->filled('region'), fn ($query) => $query->where('market_index.region', $request->query('region')))
            ->groupBy('market_index.id')
            ->select('market_index.*')
            ->selectRaw('COUNT(DISTINCT membership.instrument_id) AS members_count')
            ->selectRaw('COUNT(prediction.id) AS analyzed_count')
            ->selectRaw('AVG(prediction.prediction_score) AS calculated_rating')
            ->selectRaw('AVG(prediction.confidence) AS average_confidence')
            ->selectRaw('AVG(walk_forward.hit_rate) AS average_hit_rate')
            ->selectRaw('AVG(walk_forward.profit_per_trade) AS average_profit_per_trade')
            ->selectRaw('AVG(prediction.horizon_fusion_stability_score) AS average_stability')
            ->selectRaw('AVG(prediction.risk_score) AS average_risk')
            ->selectRaw('SUM(COALESCE(walk_forward.historical_trades, 0)) AS historical_trades')
            ->selectRaw('AVG(((prediction.predicted_price_20d - prediction.current_price) / NULLIF(prediction.current_price, 0)) * 100) AS expected_return')
            ->havingRaw('COUNT(DISTINCT membership.instrument_id) > 0');

        $aggregateCacheKey = 'index_screener_aggregate_v2_'.sha1(json_encode([
            'q' => trim((string) $request->query('q')),
            'region' => trim((string) $request->query('region')),
            'universe' => $isFreeRegional ? $allowedInstrumentIds : 'all',
        ]));
        $indices = Cache::remember($aggregateCacheKey, now()->addMinutes(2), fn () => $query
            ->orderBy('market_index.global_rank')->get()->each(function ($index) {
                $index->rating_value = is_numeric($index->calculated_rating)
                    ? AiScore::toTen($index->calculated_rating)
                    : (is_numeric($index->rating) ? (float) $index->rating : null);
            }));

        $latestQuotes = DB::table('current_stock_quotes')
            ->where('status', 'current')
            ->whereRaw('LOWER(provider) = ?', ['twelvedata'])
            ->selectRaw('instrument_id, MAX(id) AS quote_id')
            ->groupBy('instrument_id');
        $indexMemberIds = DB::table('index_memberships')
            ->whereNull('removed_at')
            ->when($isFreeRegional, fn ($query) => $query->whereIn('instrument_id', $allowedInstrumentIds))
            ->select('instrument_id');
        $rankedDailyBars = DB::table('price_bars')
            ->where('interval', '1d')
            ->whereIn('instrument_id', $indexMemberIds)
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
        $rankedIndexStocks = DB::table('index_memberships as membership')
            ->join('market_indices as market_index', 'market_index.id', '=', 'membership.market_index_id')
            ->join('instruments as instrument', 'instrument.id', '=', 'membership.instrument_id')
            ->joinSub($latestPredictions, 'latest', fn ($join) => $join->on('latest.instrument_id', '=', 'instrument.id'))
            ->join('predictions as prediction', 'prediction.id', '=', 'latest.prediction_id')
            ->leftJoinSub($latestQuotes, 'latest_quote', fn ($join) => $join->on('latest_quote.instrument_id', '=', 'instrument.id'))
            ->leftJoin('current_stock_quotes as current_quote', 'current_quote.id', '=', 'latest_quote.quote_id')
            ->leftJoinSub($dailyCloses, 'daily_close', fn ($join) => $join->on('daily_close.instrument_id', '=', 'instrument.id'))
            ->whereNull('membership.removed_at')
            ->where('market_index.is_active', true)
            ->where('instrument.type', 'stock')
            ->where('instrument.is_active', true)
            ->whereNull('instrument.deleted_at')
            ->whereNotNull('prediction.prediction_score')
            ->when($isFreeRegional, fn ($query) => $query->whereIn('instrument.id', $allowedInstrumentIds))
            ->select([
                'market_index.id as market_index_id', 'instrument.symbol', 'instrument.name', 'instrument.country',
                'instrument.currency', 'prediction.id as prediction_id', 'prediction.current_price as prediction_price',
                'prediction.prediction_score as ai_score', 'current_quote.price as live_price', 'current_quote.quote_time',
                'daily_close.latest_daily_close', 'daily_close.previous_daily_close', 'daily_close.latest_daily_time',
            ])
            ->selectRaw("({$signalSql}) AS personalized_signal")
            ->selectRaw('ROW_NUMBER() OVER (PARTITION BY market_index.id ORDER BY prediction.prediction_score DESC NULLS LAST, instrument.symbol) AS sector_rank');
        $topIndexStocks = Cache::remember('indices_top_stocks_twelvedata_user_'.($request->user()?->id ?? 'guest').'_v1', now()->addSeconds(20), fn () => DB::query()
            ->fromSub($rankedIndexStocks, 'ranked_stock')
            ->where('sector_rank', '<=', 3)
            ->orderBy('market_index_id')->orderBy('sector_rank')
            ->get()->groupBy('market_index_id'));

        $indexCharts = Cache::remember('index_screener_charts_1y_v2', now()->addMinutes(10), fn () => DB::table('market_indices as market_index')
            ->join('instruments as index_instrument', function ($join) {
                $join->on('index_instrument.symbol', '=', 'market_index.symbol')->where('index_instrument.type', 'index');
            })
            ->join('price_bars as bar', 'bar.instrument_id', '=', 'index_instrument.id')
            ->where('bar.interval', '1d')
            ->where('bar.bar_time', '>=', now()->subYear())
            ->orderBy('bar.bar_time')
            ->get(['market_index.id as market_index_id', 'bar.bar_time', 'bar.close'])
            ->groupBy('market_index_id')
            ->map(fn ($bars) => $this->chartPointsWithoutIsolatedOutliers($bars)));
        $indices->each(function ($index) use ($topIndexStocks, $indexCharts): void {
            $index->top_stocks = $topIndexStocks->get($index->id, collect());
            $index->chart_points = $indexCharts->get($index->id, collect());
        });

        $dailyMarketInfos = ! $isFreeRegional && Schema::hasTable('daily_index_market_infos')
            ? DB::table('daily_index_market_infos')
                ->selectRaw('DISTINCT ON (market_index_id) market_index_id, analysis_date, model, market_info_de, market_info_en')
                ->orderBy('market_index_id')->orderByDesc('analysis_date')->orderByDesc('id')
                ->get()->keyBy('market_index_id')
            : collect();
        $indices->each(fn ($index) => $index->daily_market_info = $dailyMarketInfos->get($index->id));
        $regionsCacheKey = 'index_screener_regions_v2_'.($isFreeRegional ? sha1(implode(',', $allowedInstrumentIds)) : 'all');
        $regions = Cache::remember($regionsCacheKey, now()->addHour(), fn () => DB::table('market_indices as market_index')
            ->where('market_index.is_active', true)
            ->whereNotNull('market_index.region')
            ->when($isFreeRegional, fn ($query) => $query->whereExists(fn ($membership) => $membership
                ->selectRaw('1')->from('index_memberships as membership')
                ->whereColumn('membership.market_index_id', 'market_index.id')
                ->whereNull('membership.removed_at')
                ->whereIn('membership.instrument_id', $allowedInstrumentIds)))
            ->distinct()->orderBy('market_index.region')->pluck('market_index.region'));

        return view('indices.index', compact('indices', 'regions', 'realtimeQuotes', 'isFreeRegional', 'regionalCountry'));
    }

    private function chartPointsWithoutIsolatedOutliers($bars)
    {
        $points = $bars
            ->filter(fn ($bar) => is_numeric($bar->close) && (float) $bar->close > 0)
            ->map(fn ($bar) => ['date' => (string) $bar->bar_time, 'close' => (float) $bar->close])
            ->values();

        if ($points->count() < 7) {
            return $points;
        }

        return $points->filter(function (array $point, int $index) use ($points): bool {
            $window = $points->slice(max(0, $index - 3), 7)->pluck('close')->sort()->values();
            $count = $window->count();
            $median = $count % 2
                ? (float) $window->get(intdiv($count, 2))
                : ((float) $window->get(($count / 2) - 1) + (float) $window->get($count / 2)) / 2;

            return $median > 0 && $point['close'] >= $median * .65 && $point['close'] <= $median * 1.35;
        })->values();
    }
}
