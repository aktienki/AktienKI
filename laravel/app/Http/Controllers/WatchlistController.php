<?php

namespace App\Http\Controllers;

use App\Enums\PlanLevel;
use App\Models\Watchlist;
use App\Services\PlanAccessService;
use App\Services\PersonalCollectionLimitService;
use App\Services\PersonalizedSignalService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class WatchlistController extends Controller
{
    public function menu(Request $request): JsonResponse
    {
        $watchlists = DB::table('watchlists as watchlist')
            ->leftJoin('watchlist_items as item', 'item.watchlist_id', '=', 'watchlist.id')
            ->leftJoin('instruments as instrument', function ($join): void {
                $join->on('instrument.id', '=', 'item.instrument_id')
                    ->where('instrument.is_active', true)
                    ->whereNull('instrument.deleted_at');
            })
            ->where('watchlist.user_id', $request->user()->id)
            ->where('watchlist.active', true)
            ->groupBy('watchlist.id', 'watchlist.name', 'watchlist.is_default')
            ->selectRaw('watchlist.id, watchlist.name, watchlist.is_default, COUNT(instrument.id) AS stocks_count')
            ->orderByDesc('watchlist.is_default')
            ->orderBy('watchlist.name')
            ->get()
            ->map(fn ($watchlist) => [
                'id' => (int) $watchlist->id,
                'name' => $watchlist->name,
                'is_default' => (bool) $watchlist->is_default,
                'stocks_count' => (int) $watchlist->stocks_count,
                'url' => route('watchlists.show', $watchlist->id),
            ]);

        return response()
            ->json(['watchlists' => $watchlists])
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0');
    }

    public function index(Request $request): View
    {
        $watchlists = Watchlist::query()
            ->where('user_id', $request->user()->id)
            ->with(['items' => fn ($query) => $query
                ->whereHas('instrument', fn ($instrument) => $instrument
                    ->where('is_active', true)
                    ->whereNull('deleted_at'))
                ->with('instrument')
                ->latest('added_at')])
            ->orderByDesc('is_default')
            ->latest()
            ->get();

        $instrumentIds = $watchlists
            ->flatMap(fn (Watchlist $watchlist) => $watchlist->items->pluck('instrument_id'))
            ->unique()
            ->values();
        $latestQuotes = DB::table('current_stock_quotes')
            ->whereIn('instrument_id', $instrumentIds)
            ->where('status', 'current')
            ->selectRaw('instrument_id, MAX(id) AS quote_id')
            ->groupBy('instrument_id');
        $currentPrices = $instrumentIds->isEmpty()
            ? collect()
            : DB::table('current_stock_quotes as quote')
                ->joinSub($latestQuotes, 'latest', fn ($join) =>
                    $join->on('latest.quote_id', '=', 'quote.id'))
                ->get([
                    'quote.instrument_id',
                    'quote.price as current_price',
                    'quote.quote_time as prediction_time',
                ])
                ->keyBy('instrument_id');
        $latestPredictionIds = $instrumentIds->isEmpty()
            ? collect()
            : DB::table('predictions')
                ->whereIn('instrument_id', $instrumentIds)
                ->selectRaw('instrument_id, MAX(id) AS prediction_id')
                ->groupBy('instrument_id')
                ->pluck('prediction_id', 'instrument_id');

        $watchlistPerformance = $watchlists->mapWithKeys(function (Watchlist $watchlist) use ($currentPrices): array {
            $returns = $watchlist->items
                ->map(function ($item) use ($currentPrices): ?float {
                    $currentPrice = $currentPrices->get($item->instrument_id)?->current_price;

                    if (! is_numeric($item->entry_price) || (float) $item->entry_price <= 0 || ! is_numeric($currentPrice)) {
                        return null;
                    }

                    return (((float) $currentPrice - (float) $item->entry_price) / (float) $item->entry_price) * 100;
                })
                ->filter(fn ($return) => $return !== null)
                ->values();

            return [$watchlist->id => [
                'percent' => $returns->isEmpty() ? null : $returns->avg(),
                'evaluated' => $returns->count(),
                'total' => $watchlist->items->count(),
            ]];
        });
        $instrumentIndices = $this->instrumentIndices($instrumentIds);

        $watchlistLimit = app(PersonalCollectionLimitService::class)->watchlists($request->user());

        return view('watchlists.index', compact('watchlists', 'currentPrices', 'latestPredictionIds', 'watchlistPerformance', 'instrumentIndices', 'watchlistLimit'));
    }

    public function show(Request $request, int $watchlist): View
    {
        $watchlist = Watchlist::query()
            ->where('id', $watchlist)
            ->where('user_id', $request->user()->id)
            ->where('active', true)
            ->with(['items' => fn ($query) => $query
                ->whereHas('instrument', fn ($instrument) => $instrument
                    ->where('is_active', true)
                    ->whereNull('deleted_at'))
                ->with('instrument')
                ->latest('added_at')])
            ->firstOrFail();

        $instrumentIds = $watchlist->items->pluck('instrument_id')->unique()->values();
        $latestPredictionIds = DB::table('predictions')
            ->whereIn('instrument_id', $instrumentIds)
            ->selectRaw('instrument_id, MAX(id) AS prediction_id')
            ->groupBy('instrument_id');
        $latestQuoteIds = DB::table('current_stock_quotes')
            ->whereIn('instrument_id', $instrumentIds)
            ->where('status', 'current')
            ->selectRaw('instrument_id, MAX(id) AS quote_id')
            ->groupBy('instrument_id');

        $latestPredictions = $instrumentIds->isEmpty()
            ? collect()
            : DB::table('predictions as prediction')
                ->joinSub($latestPredictionIds, 'latest', fn ($join) =>
                    $join->on('latest.prediction_id', '=', 'prediction.id'))
                ->leftJoinSub($latestQuoteIds, 'latest_quote', fn ($join) =>
                    $join->on('latest_quote.instrument_id', '=', 'prediction.instrument_id'))
                ->leftJoin('current_stock_quotes as current_quote', 'current_quote.id', '=', 'latest_quote.quote_id')
                ->select([
                    'prediction.id',
                    'prediction.instrument_id',
                    'prediction.prediction_score',
                    'prediction.confidence',
                    'prediction.horizon_fusion_stability_score',
                    'prediction.risk_score',
                    'prediction.drawdown_risk_factor',
                    'prediction.prediction_time',
                    DB::raw('COALESCE(current_quote.price, prediction.current_price) AS current_price'),
                ])
                ->selectRaw(app(PersonalizedSignalService::class)->sql('prediction', $request->user()).' AS personalized_signal')
                ->get()
                ->keyBy('instrument_id');

        $canViewSignalChanges = app(PlanAccessService::class)->allowsTariff($request->user(), PlanLevel::Pro);
        $signalChanges = collect();
        if ($canViewSignalChanges) {
            $signalSql = app(PersonalizedSignalService::class)->sql('prediction', $request->user());
            $signalChanges = $instrumentIds->mapWithKeys(function ($instrumentId) use ($signalSql): array {
                $history = DB::table('predictions as prediction')
                    ->where('prediction.instrument_id', $instrumentId)
                    ->select(['prediction.id', 'prediction.prediction_time'])
                    ->selectRaw($signalSql.' AS personalized_signal')
                    ->orderByDesc('prediction.prediction_time')->orderByDesc('prediction.id')->limit(30)->get();
                $latest = $history->first();
                if (! $latest) return [$instrumentId => null];
                $to = strtoupper((string) ($latest->personalized_signal ?: 'HOLD'));
                $previous = $history->skip(1)->first(fn ($row) => strtoupper((string) ($row->personalized_signal ?: 'HOLD')) !== $to);

                return [$instrumentId => $previous ? [
                    'from' => strtoupper((string) ($previous->personalized_signal ?: 'HOLD')),
                    'to' => $to,
                    'date' => $latest->prediction_time,
                ] : null];
            });
        }

        $latestWalkForwardRunIds = DB::table('walk_forward_backtest_runs')
            ->where('status', 'completed')->whereIn('horizon_days', [5, 10, 15, 20])
            ->groupBy('horizon_days')->selectRaw('MAX(id) AS id')->pluck('id');
        $walkForwardStats = $instrumentIds->isEmpty() || $latestWalkForwardRunIds->isEmpty()
            ? collect()
            : DB::table('walk_forward_backtest_trades')
                ->whereIn('instrument_id', $instrumentIds)->whereIn('run_id', $latestWalkForwardRunIds)
                ->groupBy('instrument_id')
                ->selectRaw('instrument_id, AVG(CASE WHEN net_return > 0 THEN 1.0 ELSE 0.0 END) * 100 AS hit_rate')
                ->selectRaw('AVG(net_return) * 100 AS average_profit_per_trade_percent')
                ->get()->keyBy('instrument_id');

        $profits = $watchlist->items
            ->map(function ($item) use ($latestPredictions): ?float {
                $currentPrice = $latestPredictions->get($item->instrument_id)?->current_price;

                if (! is_numeric($item->entry_price) || (float) $item->entry_price <= 0 || ! is_numeric($currentPrice)) {
                    return null;
                }

                return (((float) $currentPrice - (float) $item->entry_price) / (float) $item->entry_price) * 100;
            })
            ->filter(fn ($profit) => $profit !== null);

        $averageProfit = $profits->isEmpty() ? null : (float) $profits->avg();
        $instrumentIndices = $this->instrumentIndices($instrumentIds);
        $performanceSeries = $watchlist->items->mapWithKeys(function ($item) use ($latestPredictions): array {
            $entryPrice = is_numeric($item->entry_price) && (float) $item->entry_price > 0
                ? (float) $item->entry_price : null;
            if ($entryPrice === null) return [$item->instrument_id => collect()];

            $points = DB::table('price_bars')
                ->where('instrument_id', $item->instrument_id)
                ->where('interval', '1d')->where('close', '>', 0)
                ->when($item->entry_price_at ?: $item->added_at, fn ($query, $from) => $query->where('bar_time', '>=', $from))
                ->orderByDesc('bar_time')->limit(60)->get(['bar_time', 'close'])->reverse()->values()
                ->map(fn ($bar): array => [
                    'date' => (string) $bar->bar_time,
                    'value' => (((float) $bar->close - $entryPrice) / $entryPrice) * 100,
                ]);
            $current = $latestPredictions->get($item->instrument_id)?->current_price;
            if (is_numeric($current)) {
                $points->push(['date' => now()->toIso8601String(), 'value' => (((float) $current - $entryPrice) / $entryPrice) * 100]);
            }

            return [$item->instrument_id => $points];
        });

        return view('watchlists.show', compact(
            'watchlist',
            'latestPredictions',
            'walkForwardStats',
            'averageProfit',
            'instrumentIndices',
            'performanceSeries',
            'canViewSignalChanges',
            'signalChanges',
        ));
    }

    public function store(Request $request): RedirectResponse
    {
        $limit = app(PersonalCollectionLimitService::class)->watchlists($request->user());
        $currentCount = Watchlist::query()
            ->where('user_id', $request->user()->id)
            ->where('active', true)
            ->count();
        if ($limit !== null && $currentCount >= $limit) {
            return back()->withErrors(['name' => __('Dein Tarif erlaubt maximal :count aktive Watchlists.', ['count' => $limit])]);
        }

        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:80',
                Rule::unique('watchlists')->where('user_id', $request->user()->id),
            ],
            'description' => ['nullable', 'string', 'max:500'],
        ]);

        DB::transaction(function () use ($request, $validated): void {
            $hasWatchlist = Watchlist::query()
                ->where('user_id', $request->user()->id)
                ->exists();

            Watchlist::query()->create([
                'user_id' => $request->user()->id,
                'name' => $validated['name'],
                'description' => $validated['description'] ?? null,
                'is_default' => ! $hasWatchlist,
                'active' => true,
            ]);
        });

        return redirect()->route('watchlists.index')->with('status', 'watchlist-created');
    }

    public function destroyItem(Request $request, int $watchlist, int $instrument): RedirectResponse
    {
        $ownedWatchlist = Watchlist::query()
            ->where('id', $watchlist)
            ->where('user_id', $request->user()->id)
            ->firstOrFail();

        $ownedWatchlist->items()
            ->where('instrument_id', $instrument)
            ->delete();

        return back()->with('status', 'watchlist-item-removed');
    }

    public function moveItem(Request $request, int $watchlist, int $instrument): JsonResponse|RedirectResponse
    {
        $validated = $request->validate([
            'target_watchlist_id' => ['required', 'integer'],
        ]);
        abort_if((int) $validated['target_watchlist_id'] === $watchlist, 422);

        $ownedWatchlists = Watchlist::query()
            ->where('user_id', $request->user()->id)
            ->where('active', true)
            ->whereIn('id', [$watchlist, (int) $validated['target_watchlist_id']])
            ->pluck('id');

        abort_unless($ownedWatchlists->contains($watchlist)
            && $ownedWatchlists->contains((int) $validated['target_watchlist_id']), 404);

        DB::transaction(function () use ($watchlist, $instrument, $validated): void {
            $sourceItem = DB::table('watchlist_items')
                ->where('watchlist_id', $watchlist)
                ->where('instrument_id', $instrument)
                ->lockForUpdate()
                ->first();

            abort_unless($sourceItem, 404);

            $alreadyInTarget = DB::table('watchlist_items')
                ->where('watchlist_id', (int) $validated['target_watchlist_id'])
                ->where('instrument_id', $instrument)
                ->lockForUpdate()
                ->exists();

            if ($alreadyInTarget) {
                DB::table('watchlist_items')->where('id', $sourceItem->id)->delete();

                return;
            }

            DB::table('watchlist_items')
                ->where('id', $sourceItem->id)
                ->update([
                    'watchlist_id' => (int) $validated['target_watchlist_id'],
                    'updated_at' => now(),
                ]);
        });

        if ($request->expectsJson()) {
            return response()->json(['status' => 'watchlist-item-moved']);
        }

        return redirect()
            ->route('watchlists.index')
            ->with('status', 'watchlist-item-moved');
    }

    public function toggleItem(Request $request, int $watchlist, int $instrument): RedirectResponse
    {
        $validated = $request->validate([
            'prediction_id' => ['nullable', 'integer'],
        ]);
        $ownedWatchlist = Watchlist::query()
            ->where('id', $watchlist)
            ->where('user_id', $request->user()->id)
            ->where('active', true)
            ->firstOrFail();

        $instrumentRow = DB::table('instruments')
            ->where('id', $instrument)
            ->where('type', 'stock')
            ->where('is_active', true)
            ->whereNull('deleted_at')
            ->first(['id', 'currency']);

        abort_unless($instrumentRow, 404);

        $removed = false;
        DB::transaction(function () use ($ownedWatchlist, $instrumentRow, $validated, &$removed): void {
            $item = DB::table('watchlist_items')
                ->where('watchlist_id', $ownedWatchlist->id)
                ->where('instrument_id', $instrumentRow->id);

            if ($item->exists()) {
                $item->delete();
                $removed = true;

                return;
            }

            $entryPredictionQuery = DB::table('predictions')
                ->where('instrument_id', $instrumentRow->id)
                ->whereNotNull('current_price');

            if (! empty($validated['prediction_id'])) {
                $entryPredictionQuery->where('id', (int) $validated['prediction_id']);
            } else {
                $entryPredictionQuery
                    ->orderByDesc('prediction_time')
                    ->orderByDesc('id');
            }

            $entryPrediction = $entryPredictionQuery->first(['id', 'current_price']);
            abort_if(! empty($validated['prediction_id']) && ! $entryPrediction, 422);

            DB::table('watchlist_items')->insert([
                'watchlist_id' => $ownedWatchlist->id,
                'instrument_id' => $instrumentRow->id,
                'prediction_id' => $entryPrediction?->id,
                'added_at' => now(),
                'entry_price' => $entryPrediction?->current_price,
                'entry_price_at' => $entryPrediction !== null ? now() : null,
                'entry_currency' => $instrumentRow->currency,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        });

        return back()->with('status', $removed ? 'watchlist-item-removed' : 'watchlist-item-added');
    }

    public function destroy(Request $request, int $watchlist): RedirectResponse
    {
        DB::transaction(function () use ($request, $watchlist): void {
            $ownedWatchlist = Watchlist::query()
                ->where('id', $watchlist)
                ->where('user_id', $request->user()->id)
                ->lockForUpdate()
                ->firstOrFail();

            $wasDefault = $ownedWatchlist->is_default;
            $ownedWatchlist->delete();

            if ($wasDefault) {
                $nextWatchlistId = Watchlist::query()
                    ->where('user_id', $request->user()->id)
                    ->where('active', true)
                    ->orderBy('id')
                    ->value('id');

                if ($nextWatchlistId) {
                    Watchlist::query()
                        ->where('id', $nextWatchlistId)
                        ->update(['is_default' => true, 'updated_at' => now()]);
                }
            }
        });

        return redirect()->route('watchlists.index')->with('status', 'watchlist-deleted');
    }

    private function instrumentIndices($instrumentIds)
    {
        if ($instrumentIds->isEmpty()) {
            return collect();
        }

        return DB::table('index_memberships as membership')
            ->join('market_indices as market_index', 'market_index.id', '=', 'membership.market_index_id')
            ->whereIn('membership.instrument_id', $instrumentIds)
            ->where('market_index.is_active', true)
            ->orderBy('market_index.symbol')
            ->get([
                'membership.instrument_id',
                'market_index.symbol',
                'market_index.name',
            ])
            ->groupBy('instrument_id');
    }
}
