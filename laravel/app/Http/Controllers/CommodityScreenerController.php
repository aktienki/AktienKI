<?php

namespace App\Http\Controllers;

use App\Support\DirectionalSignalRating;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

final class CommodityScreenerController extends Controller
{
    public function __invoke(Request $request): View
    {
        $query = DB::connection('serving')->table('serving_instruments as instrument')
            ->join('serving_active_models as active', 'active.instrument_id', '=', 'instrument.id')
            ->where('instrument.instrument_type', 'commodity')->where('instrument.is_active', true);

        if ($term = trim((string) $request->query('q'))) {
            $query->where(fn ($q) => $q->where('instrument.symbol', 'ilike', "%{$term}%")
                ->orWhere('instrument.name', 'ilike', "%{$term}%"));
        }

        $instruments = $query->orderBy('instrument.name')->get([
            'instrument.id', 'instrument.symbol', 'instrument.name', 'instrument.currency', 'active.release_id',
        ]);
        $predictions = DB::connection('serving')->table('serving_predictions')
            ->whereIn('instrument_id', $instruments->pluck('id'))->whereIn('horizon', [10, 20, 40])
            ->where('variant', 'standard')->orderByDesc('as_of')->orderByDesc('id')->get()
            ->groupBy('instrument_id');

        $commodities = $instruments->map(function (object $commodity) use ($predictions): object {
            $rows = collect($predictions->get($commodity->id, collect()))
                ->filter(fn (object $row) => $row->release_id === $commodity->release_id);
            foreach ([10, 20, 40] as $horizon) {
                $row = $rows->first(fn (object $item) => (int) $item->horizon === $horizon);
                $commodity->{"return_{$horizon}d"} = is_numeric($row?->expected_return) ? (float) $row->expected_return * 100 : null;
                $commodity->{"signal_{$horizon}d"} = $row?->signal;
            }
            $commodity->confidence = $rows->whereNotNull('confidence')->avg('confidence');
            $commodity->risk = $rows->whereNotNull('risk_score')->avg('risk_score');
            $rating = DirectionalSignalRating::calculate([
                5 => $commodity->return_10d, 10 => $commodity->return_20d, 20 => $commodity->return_40d,
            ], is_numeric($commodity->confidence) ? $commodity->confidence * 10 : null);
            $commodity->score_percent = $rating['percent'];
            $commodity->score_label = $rating['label'];
            $commodity->as_of = $rows->max('as_of');
            $commodity->signal = $rows->contains(fn (object $row) => $row->signal === 'BUY') ? 'BUY'
                : ($rows->contains(fn (object $row) => $row->signal === 'SELL') ? 'SELL' : 'WATCH');

            return $commodity;
        })->filter(fn (object $commodity) => collect([10, 20, 40])
            ->contains(fn (int $horizon) => is_numeric($commodity->{"return_{$horizon}d"})))->values();

        $localInstruments = DB::table('instruments')
            ->where('type', 'commodity')
            ->whereIn(DB::raw('UPPER(symbol)'), $commodities->pluck('symbol')->map(fn ($symbol) => strtoupper((string) $symbol)))
            ->get(['id', 'symbol'])
            ->keyBy(fn (object $instrument): string => strtoupper((string) $instrument->symbol));
        $chartBars = $localInstruments->isEmpty() ? collect() : DB::table('price_bars')
            ->whereIn('instrument_id', $localInstruments->pluck('id'))
            ->where('interval', '1d')
            ->where('bar_time', '>=', now()->subMonths(6))
            ->orderBy('bar_time')
            ->get(['instrument_id', 'bar_time', 'open', 'high', 'low', 'close'])
            ->groupBy('instrument_id');
        $commodities->each(function (object $commodity) use ($localInstruments, $chartBars): void {
            $instrument = $localInstruments->get(strtoupper((string) $commodity->symbol));
            $commodity->local_instrument_id = $instrument?->id;
            $commodity->chart_points = $instrument
                ? collect($chartBars->get($instrument->id, collect()))->take(-132)->values()
                : collect();
        });

        $userWatchlists = DB::table('watchlists')
            ->where('user_id', $request->user()->id)
            ->where('active', true)
            ->orderByDesc('is_default')
            ->orderBy('name')
            ->get(['id', 'name', 'is_default']);
        $watchlistMemberships = $userWatchlists->isEmpty()
            ? collect()
            : DB::table('watchlist_items')
                ->whereIn('watchlist_id', $userWatchlists->pluck('id'))
                ->whereIn('instrument_id', $localInstruments->pluck('id'))
                ->get(['instrument_id', 'watchlist_id'])
                ->groupBy('instrument_id')
                ->map(fn ($items) => $items->pluck('watchlist_id')->map(fn ($id) => (int) $id));

        return view('commodities.index', compact('commodities', 'userWatchlists', 'watchlistMemberships'));
    }
}
