<?php

namespace App\Http\Controllers;

use App\Enums\PlanLevel;
use App\Livewire\Dashboard\MarketData;
use App\Services\FreeRegionalStockUniverseService;
use App\Services\PersonalizedSignalService;
use App\Services\PlanAccessService;
use App\Services\ServingCurrentSignalSource;
use App\Support\AiScore;
use App\Support\DirectionalSignalRating;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class IndexScreenerController extends Controller
{
    public function __invoke(Request $request, PersonalizedSignalService $personalizedSignals, PlanAccessService $planAccess): View
    {
        $isFreeRegional = ! $planAccess->allows($request->user(), PlanLevel::Plus);
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
            ->join('index_memberships as membership', function ($join) {
                $join->on('membership.market_index_id', '=', 'market_index.id')->whereNull('membership.removed_at');
            })
            ->joinSub($latestPredictions, 'latest', fn ($join) => $join->on('latest.instrument_id', '=', 'membership.instrument_id'))
            ->join('predictions as prediction', 'prediction.id', '=', 'latest.prediction_id')
            ->leftJoinSub($walkForwardStats, 'walk_forward', fn ($join) => $join->on('walk_forward.instrument_id', '=', 'membership.instrument_id'))
            ->where('market_index.is_active', true)
            ->whereNotNull('prediction.prediction_score')
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
            ->selectRaw('AVG(((prediction.predicted_price_5d - prediction.current_price) / NULLIF(prediction.current_price, 0)) * 100) AS expected_return_5d')
            ->selectRaw('AVG(((prediction.predicted_price_10d - prediction.current_price) / NULLIF(prediction.current_price, 0)) * 100) AS expected_return_10d')
            ->selectRaw('AVG(((prediction.predicted_price_15d - prediction.current_price) / NULLIF(prediction.current_price, 0)) * 100) AS expected_return_15d')
            ->selectRaw('AVG(((prediction.predicted_price_20d - prediction.current_price) / NULLIF(prediction.current_price, 0)) * 100) AS expected_return');

        // Direct index releases must not depend on legacy stock predictions or
        // populated member lists. Serving predictions decide visibility below.
        $query = DB::table('market_indices as market_index')
            ->where('market_index.is_active', true)
            ->when($request->filled('q'), function ($query) use ($request) {
                $term = '%'.mb_strtolower(trim((string) $request->query('q'))).'%';
                $query->where(fn ($nested) => $nested
                    ->whereRaw('LOWER(market_index.name) LIKE ?', [$term])
                    ->orWhereRaw('LOWER(market_index.symbol) LIKE ?', [$term]));
            })
            ->when($request->filled('region'), fn ($query) => $query->where('market_index.region', $request->query('region')))
            ->select('market_index.*')
            ->selectSub(fn ($members) => $members->from('index_memberships')
                ->whereColumn('market_index_id', 'market_index.id')->whereNull('removed_at')->selectRaw('COUNT(*)'), 'members_count')
            ->selectRaw('0 AS analyzed_count, NULL::numeric AS calculated_rating, NULL::numeric AS average_confidence, NULL::numeric AS average_hit_rate, NULL::numeric AS average_profit_per_trade, NULL::numeric AS average_stability, NULL::numeric AS average_risk, 0 AS historical_trades, NULL::numeric AS expected_return_5d, NULL::numeric AS expected_return_10d, NULL::numeric AS expected_return_15d, NULL::numeric AS expected_return');

        $aggregateCacheKey = 'index_screener_aggregate_v7_'.sha1(json_encode([
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
        $this->applyServingOutlooks($indices);
        $indices = $indices
            ->filter(fn (object $index): bool => collect([10, 20, 40])
                ->contains(fn (int $horizon): bool => is_numeric($index->{"expected_return_{$horizon}d"} ?? null)))
            ->values();
        $index60Date = Schema::hasTable('market_context_predictions')
            ? DB::table('market_context_predictions')->where('scope_type', 'index60')->max('prediction_date')
            : null;
        $index60Contexts = $index60Date
            ? DB::table('market_context_predictions')->where('scope_type', 'index60')
                ->where('prediction_date', $index60Date)->get()->keyBy('scope_key')
            : collect();
        $indices->each(function ($index) use ($index60Contexts): void {
            $context = $index60Contexts->get((string) $index->id);
            $index->pytorch_60t_probability = $context ? max(0.0, min(1.0, ((float) $context->score) / 10.0)) : null;
            $index->pytorch_60t_signal = $context?->signal;
            $index->pytorch_60t_confidence = $context ? (float) $context->confidence : null;
            $index->pytorch_60t_member_count = $context ? (int) $context->member_count : 0;
            $index->pytorch_60t_alignment = null;
            $index->pytorch_60t_stability_adjustment = 0.0;
            if ($index->pytorch_60t_probability !== null && is_numeric($index->expected_return)) {
                $decisive = abs($index->pytorch_60t_probability - 0.5) >= 0.05;
                $aligned = ($index->pytorch_60t_probability >= 0.5) === ((float) $index->expected_return >= 0.0);
                $index->pytorch_60t_alignment = $decisive ? $aligned : null;
                $index->pytorch_60t_stability_adjustment = ! $decisive ? 0.0 : ($aligned ? 0.05 : -0.10);
            }
            $baseStability = is_numeric($index->average_stability) ? (float) $index->average_stability : null;
            $index->context_adjusted_stability = $baseStability === null
                ? null
                : max(0.0, min(1.0, $baseStability + $index->pytorch_60t_stability_adjustment));
        });

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
            ->where(fn ($query) => $query->whereNull('instrument.risk_status')->orWhere('instrument.risk_status', '<>', 'sleep'))
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
        $dailyMemberScores = DB::table('index_memberships as score_membership')
            ->join('predictions as score_prediction', 'score_prediction.instrument_id', '=', 'score_membership.instrument_id')
            ->whereNull('score_membership.removed_at')
            ->where('score_prediction.prediction_time', '>=', now()->subDays(60))
            ->whereNotNull('score_prediction.prediction_score')
            ->when($isFreeRegional, fn ($query) => $query->whereIn('score_membership.instrument_id', $allowedInstrumentIds))
            ->selectRaw('DISTINCT ON (score_membership.market_index_id, score_membership.instrument_id, DATE(score_prediction.prediction_time)) score_membership.market_index_id, score_membership.instrument_id, DATE(score_prediction.prediction_time) AS score_date')
            ->selectRaw('CASE WHEN score_prediction.prediction_score <= 1 THEN score_prediction.prediction_score * 100 WHEN score_prediction.prediction_score <= 10 THEN score_prediction.prediction_score * 10 ELSE score_prediction.prediction_score END AS normalized_score')
            ->orderBy('score_membership.market_index_id')
            ->orderBy('score_membership.instrument_id')
            ->orderByRaw('DATE(score_prediction.prediction_time)')
            ->orderByDesc('score_prediction.prediction_time')
            ->orderByDesc('score_prediction.id');
        $indexScoreTrends = Cache::remember(
            'index_screener_score_trends_v1_'.($isFreeRegional ? sha1(implode(',', $allowedInstrumentIds)) : 'all'),
            now()->addMinutes(10),
            fn () => DB::query()->fromSub($dailyMemberScores, 'daily_member_score')
                ->groupBy('market_index_id', 'score_date')
                ->orderBy('market_index_id')->orderBy('score_date')
                ->get(['market_index_id', 'score_date', DB::raw('AVG(normalized_score) AS average_score')])
                ->groupBy('market_index_id')
                ->map(fn ($points) => $points->take(-30)->values())
        );
        $indices->each(function ($index) use ($topIndexStocks, $indexCharts, $indexScoreTrends): void {
            $index->top_stocks = $topIndexStocks->get($index->id, collect());
            $index->chart_points = $indexCharts->get($index->id, collect());
            $index->score_trend = $indexScoreTrends->get($index->id, collect());
        });

        $dailyMarketInfos = ! $isFreeRegional && Schema::hasTable('daily_index_market_infos')
            ? DB::table('daily_index_market_infos')
                ->selectRaw('DISTINCT ON (market_index_id) market_index_id, analysis_date, model, market_info_de, market_info_en')
                ->orderBy('market_index_id')->orderByDesc('analysis_date')->orderByDesc('id')
                ->get()->keyBy('market_index_id')
            : collect();
        $indices->each(fn ($index) => $index->daily_market_info = $dailyMarketInfos->get($index->id));
        $visibleIndexIds = $indices->pluck('id')->all();
        $marketData = app(MarketData::class);
        $indexAnalysisCards = method_exists($marketData, 'loadVisibleIndexComparisonCards')
            ? $marketData->loadVisibleIndexComparisonCards($indices, $isFreeRegional ? $allowedInstrumentIds : [])
            : [];
        $indices->each(fn ($index) => $index->analysis_card = $indexAnalysisCards[(int) $index->id] ?? null);
        $regionsCacheKey = 'index_screener_regions_v4_'.sha1(implode(',', $visibleIndexIds));
        $regions = Cache::remember($regionsCacheKey, now()->addHour(), fn () => DB::table('market_indices as market_index')
            ->where('market_index.is_active', true)
            ->whereNotNull('market_index.region')
            ->whereIn('market_index.id', $visibleIndexIds)
            ->distinct()->orderBy('market_index.region')->pluck('market_index.region'));
        $macroCards = method_exists($marketData, 'loadIndexComparisonCards')
            ? $marketData->loadIndexComparisonCards()
            : [];
        $view = $request->routeIs('indices.redesign') ? 'indices.redesign' : 'indices.index';

        return view($view, compact('indices', 'regions', 'realtimeQuotes', 'isFreeRegional', 'regionalCountry', 'macroCards'));
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

    private function applyServingOutlooks($indices): void
    {
        $serviceSymbolByMarketSymbol = [
            '^GDAXI' => 'DAX',
        ];
        $requestedSymbols = $indices->map(fn (object $index): string => $serviceSymbolByMarketSymbol[$index->symbol] ?? $index->symbol)->unique()->values();
        $serviceInstruments = DB::connection('serving')->table('serving_instruments')
            ->where('instrument_type', 'index')->where('is_active', true)
            ->whereIn('symbol', $requestedSymbols)->get(['id', 'symbol'])->keyBy('symbol');
        $servicePredictions = DB::connection('serving')->table('serving_predictions as prediction')
            ->join('serving_active_models as active', function ($join): void {
                $join->on('active.instrument_id', '=', 'prediction.instrument_id')
                    ->on('active.release_id', '=', 'prediction.release_id');
            })
            ->whereIn('prediction.instrument_id', $serviceInstruments->pluck('id'))
            ->whereIn('prediction.horizon', [10, 20, 40])
            ->where('prediction.variant', 'standard')
            ->orderByDesc('prediction.as_of')->orderByDesc('prediction.id')
            ->get(['prediction.*'])->groupBy('instrument_id');

        $indices->each(function (object $index) use ($serviceSymbolByMarketSymbol, $serviceInstruments, $servicePredictions): void {
            $serviceSymbol = $serviceSymbolByMarketSymbol[$index->symbol] ?? $index->symbol;
            $instrument = $serviceInstruments->get($serviceSymbol);
            $predictions = collect($instrument ? $servicePredictions->get($instrument->id, collect()) : collect());
            foreach ([10, 20, 40] as $horizon) {
                $prediction = $predictions->first(fn (object $row): bool => (int) $row->horizon === $horizon);
                $index->{"expected_return_{$horizon}d"} = is_numeric($prediction?->expected_return)
                    ? (float) $prediction->expected_return * 100
                    : null;
            }
            $index->expected_return = $index->expected_return_20d;
            if ($predictions->isNotEmpty()) {
                $index->average_risk = $predictions->whereNotNull('risk_score')->avg('risk_score') * 20;
                $index->average_confidence = $predictions->whereNotNull('confidence')->avg('confidence') * 100;

                // Index releases do not use the legacy market_indices.rating column.
                // Build the displayed directional grade from their actual 10/20/40-day
                // Serving predictions and use confidence only as an evidence weight.
                $directionalRating = DirectionalSignalRating::calculate([
                    5 => $index->expected_return_10d,
                    10 => $index->expected_return_20d,
                    20 => $index->expected_return_40d,
                ], $index->average_confidence / 10);
                $index->rating_value = $directionalRating['percent'] / 10;
            }
        });

        return;

        $indexIds = $indices->pluck('id')->map(fn ($id): int => (int) $id)->all();
        if ($indexIds === []) {
            return;
        }

        $memberSymbols = DB::table('index_memberships as membership')
            ->join('instruments as instrument', 'instrument.id', '=', 'membership.instrument_id')
            ->whereIn('membership.market_index_id', $indexIds)
            ->whereNull('membership.removed_at')
            ->get(['membership.market_index_id', 'instrument.symbol'])
            ->groupBy('market_index_id');
        $symbols = $memberSymbols->flatten(1)->pluck('symbol')->filter()->unique()->values()->all();
        if ($symbols === []) {
            return;
        }

        $servingInstruments = DB::connection('serving')->table('serving_instruments')
            ->whereIn('symbol', $symbols)
            ->where('instrument_type', 'stock')
            ->where('is_active', true)
            ->where('is_tradeable', true)
            ->get(['id', 'symbol'])
            ->keyBy('symbol');
        $instrumentIds = $servingInstruments->pluck('id')->map(fn ($id): int => (int) $id)->all();
        if ($instrumentIds === []) {
            return;
        }

        $currentBatches = DB::connection('serving')->table(ServingCurrentSignalSource::relation())
            ->whereIn('instrument_id', $instrumentIds)
            ->pluck('batch_id', 'instrument_id');
        $predictions = DB::connection('serving')->table('serving_predictions')
            ->whereIn('instrument_id', $instrumentIds)
            ->whereIn('horizon', [10, 20, 40])
            ->orderByDesc('as_of')->orderByDesc('id')->get()
            ->filter(fn (object $prediction): bool => (string) $prediction->batch_id === (string) $currentBatches->get($prediction->instrument_id))
            ->groupBy('instrument_id');
        $statuses = DB::connection('serving')->table('serving_model_horizon_status')
            ->whereIn('instrument_id', $instrumentIds)
            ->get()->groupBy('instrument_id');

        $indices->each(function (object $index) use ($memberSymbols, $servingInstruments, $predictions, $statuses): void {
            $serviceIds = collect($memberSymbols->get($index->id, collect()))
                ->map(fn (object $member) => $servingInstruments->get($member->symbol)?->id)
                ->filter()->map(fn ($id): int => (int) $id)->unique();

            foreach ([10, 20, 40] as $horizon) {
                $returns = $serviceIds->map(function (int $instrumentId) use ($predictions, $statuses, $horizon) {
                    $instrumentStatuses = collect($statuses->get($instrumentId, collect()));
                    $prediction = collect($predictions->get($instrumentId, collect()))
                        ->where('horizon', $horizon)
                        ->filter(function (object $candidate) use ($instrumentStatuses): bool {
                            $status = $instrumentStatuses->first(fn (object $item): bool => (int) $item->horizon === (int) $candidate->horizon
                                && (string) $item->variant === (string) $candidate->variant
                                && (string) $item->release_id === (string) $candidate->release_id);

                            return $status
                                && (bool) $status->prediction_enabled
                                && (bool) $status->selected_for_prediction
                                && is_numeric($candidate->expected_return);
                        })
                        ->sortByDesc(function (object $candidate) use ($instrumentStatuses): float {
                            $status = $instrumentStatuses->first(fn (object $item): bool => (int) $item->horizon === (int) $candidate->horizon
                                && (string) $item->variant === (string) $candidate->variant
                                && (string) $item->release_id === (string) $candidate->release_id);
                            $quality = match ((string) ($status->model_quality_class ?? '')) {
                                'quality' => 4, 'solid' => 3, 'basic' => 2, 'underperform' => 1, default => 0,
                            };

                            return ((bool) ($status->quality_gate_passed ?? false) ? 100_000 : 0)
                                + ($quality * 1_000)
                                + ((float) ($candidate->confidence ?? 0) * 100)
                                + ((float) ($candidate->expected_return ?? 0) * 10);
                        })->first();

                    return is_numeric($prediction?->expected_return) ? (float) $prediction->expected_return * 100 : null;
                })->filter(fn ($value): bool => is_numeric($value));
                $index->{"expected_return_{$horizon}d"} = $returns->isNotEmpty() ? $returns->avg() : null;
            }
            $index->expected_return = $index->expected_return_20d;
            unset($index->expected_return_5d, $index->expected_return_15d);
        });
    }
}
