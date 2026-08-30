<?php

namespace App\Http\Controllers;

use App\Services\ServingReadService;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\View\View;

final class ServingTradeOpportunityController extends Controller
{
    public function __invoke(Request $request, ServingReadService $serving): View
    {
        $all = $serving->activeStocks()
            ->filter(fn (object $stock): bool => $stock->prediction_count > 0)
            ->map(function (object $stock): object {
                $predictions = $stock->horizons->pluck('prediction')->filter();
                $best = $predictions->sortByDesc('expected_return_percent')->first();

                return (object) [
                    'instrument_id' => $stock->instrument_id,
                    'symbol' => $stock->symbol,
                    'name' => $stock->name,
                    'country' => $stock->country_code,
                    'sector' => $stock->sector_code,
                    'currency' => $stock->currency,
                    'quality_class' => $stock->quality_class,
                    'prediction' => $best,
                    'predictions' => $predictions->keyBy('horizon'),
                ];
            })
            ->filter(fn (object $row): bool => $row->prediction && (float) $row->prediction->expected_return_percent > 0)
            ->sortByDesc(fn (object $row): float => (float) $row->prediction->expected_return_percent)
            ->values();

        $page = max(1, (int) $request->query('page', 1));
        $perPage = 30;
        $opportunities = new LengthAwarePaginator(
            $all->forPage($page, $perPage)->values(),
            $all->count(),
            $perPage,
            $page,
            ['path' => $request->url(), 'query' => $request->query()]
        );

        return view('opportunities.serving-index', compact('opportunities'));
    }
}
