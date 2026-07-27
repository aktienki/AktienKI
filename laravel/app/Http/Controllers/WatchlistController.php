<?php

namespace App\Http\Controllers;

use App\Models\Watchlist;
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
            ->where('watchlist.user_id', $request->user()->id)
            ->where('watchlist.active', true)
            ->groupBy('watchlist.id', 'watchlist.name', 'watchlist.is_default')
            ->selectRaw('watchlist.id, watchlist.name, watchlist.is_default, COUNT(item.id) AS stocks_count')
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
                ->with('instrument')
                ->latest('added_at')])
            ->orderByDesc('is_default')
            ->latest()
            ->get();

        $instrumentIds = $watchlists
            ->flatMap(fn (Watchlist $watchlist) => $watchlist->items->pluck('instrument_id'))
            ->unique()
            ->values();
        $latestPredictions = DB::table('predictions')
            ->whereIn('instrument_id', $instrumentIds)
            ->selectRaw('instrument_id, MAX(id) AS prediction_id')
            ->groupBy('instrument_id');
        $currentPrices = $instrumentIds->isEmpty()
            ? collect()
            : DB::table('predictions as prediction')
                ->joinSub($latestPredictions, 'latest', fn ($join) =>
                    $join->on('latest.prediction_id', '=', 'prediction.id'))
                ->get(['prediction.instrument_id', 'prediction.current_price', 'prediction.prediction_time'])
                ->keyBy('instrument_id');

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

        return view('watchlists.index', compact('watchlists', 'currentPrices', 'watchlistPerformance', 'instrumentIndices'));
    }

    public function show(Request $request, int $watchlist): View
    {
        $watchlist = Watchlist::query()
            ->where('id', $watchlist)
            ->where('user_id', $request->user()->id)
            ->where('active', true)
            ->with(['items' => fn ($query) => $query
                ->with('instrument')
                ->latest('added_at')])
            ->firstOrFail();

        $instrumentIds = $watchlist->items->pluck('instrument_id')->unique()->values();
        $latestPredictionIds = DB::table('predictions')
            ->whereIn('instrument_id', $instrumentIds)
            ->selectRaw('instrument_id, MAX(id) AS prediction_id')
            ->groupBy('instrument_id');

        $latestPredictions = $instrumentIds->isEmpty()
            ? collect()
            : DB::table('predictions as prediction')
                ->joinSub($latestPredictionIds, 'latest', fn ($join) =>
                    $join->on('latest.prediction_id', '=', 'prediction.id'))
                ->get([
                    'prediction.instrument_id',
                    'prediction.current_price',
                    'prediction.prediction_score',
                    'prediction.prediction_time',
                ])
                ->keyBy('instrument_id');

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

        return view('watchlists.show', compact(
            'watchlist',
            'latestPredictions',
            'averageProfit',
            'instrumentIndices',
        ));
    }

    public function store(Request $request): RedirectResponse
    {
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
