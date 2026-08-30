<?php

namespace App\Http\Controllers;

use App\Services\ServingReadService;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\View\View;

final class ServingPredictionTableController extends Controller
{
    public function __invoke(Request $request, ServingReadService $serving): View
    {
        $allStocks = $serving->activeStocks();
        $search = mb_strtolower(trim((string) $request->query('q', '')));
        $country = strtoupper(trim((string) $request->query('country', '')));
        $exchange = strtoupper(trim((string) $request->query('exchange', '')));
        $sector = trim((string) $request->query('sector', ''));
        $quality = strtolower(trim((string) $request->query('quality', '')));
        $status = strtolower(trim((string) $request->query('status', '')));
        $signal = strtoupper(trim((string) $request->query('signal', '')));

        $filtered = $allStocks->filter(function (object $stock) use ($search, $country, $exchange, $sector, $quality, $status, $signal): bool {
            if ($search !== '' && ! str_contains(mb_strtolower(implode(' ', [
                $stock->name, $stock->symbol, $stock->isin, $stock->industry,
            ])), $search)) {
                return false;
            }
            if ($country !== '' && strtoupper((string) $stock->country_code) !== $country) return false;
            if ($exchange !== '' && strtoupper((string) $stock->exchange) !== $exchange) return false;
            if ($sector !== '' && (string) $stock->sector_code !== $sector) return false;
            if ($quality !== '' && (string) $stock->quality_class !== $quality) return false;
            if ($status === 'eligible' && $stock->eligible_horizon_count < 1) return false;
            if ($status === 'blocked' && $stock->eligible_horizon_count > 0) return false;
            if ($status === 'published' && $stock->prediction_count < 1) return false;
            if ($status === 'waiting' && $stock->prediction_count > 0) return false;
            if ($signal !== '' && strtoupper((string) ($stock->latest_prediction?->signal ?? '')) !== $signal) return false;

            return true;
        });

        $sort = (string) $request->query('sort', 'released');
        $direction = strtolower((string) $request->query('direction', 'desc')) === 'asc' ? 'asc' : 'desc';
        $sorted = $this->sort($filtered, $sort, $direction);
        $page = max(1, (int) $request->query('page', 1));
        $perPage = 50;
        $stocks = new LengthAwarePaginator(
            $sorted->forPage($page, $perPage)->values(),
            $sorted->count(),
            $perPage,
            $page,
            ['path' => $request->url(), 'query' => $request->query()]
        );

        $qualityCounts = collect(['quality', 'solid', 'basic', 'underperform', 'open'])
            ->mapWithKeys(fn (string $class): array => [$class => $allStocks->where('quality_class', $class)->count()]);
        $summary = (object) [
            'active_stocks' => $allStocks->count(),
            'eligible_horizons' => $allStocks->sum('eligible_horizon_count'),
            'published_predictions' => $serving->latestPredictions()->count(),
            'stocks_with_predictions' => $allStocks->where('prediction_count', '>', 0)->count(),
            'last_release_at' => $allStocks->max('released_at'),
            'quality_counts' => $qualityCounts,
        ];

        $countries = $allStocks->pluck('country_code')->filter()->unique()->sort()->values();
        $exchanges = $allStocks->pluck('exchange')->filter()->unique()->sort()->values();
        $sectors = $allStocks->pluck('sector_code')->filter()->unique()->sort()->values();

        return view('predictions.serving-index', compact(
            'stocks', 'summary', 'countries', 'exchanges', 'sectors', 'sort', 'direction'
        ));
    }

    private function sort(Collection $stocks, string $sort, string $direction): Collection
    {
        $qualityRank = ['quality' => 5, 'solid' => 4, 'basic' => 3, 'open' => 2, 'underperform' => 1];
        $value = match ($sort) {
            'stock' => fn (object $stock): string => mb_strtolower((string) $stock->name),
            'quality' => fn (object $stock): int => $qualityRank[$stock->quality_class] ?? 0,
            'eligible' => fn (object $stock): int => (int) $stock->eligible_horizon_count,
            'predictions' => fn (object $stock): int => (int) $stock->prediction_count,
            default => fn (object $stock): string => (string) $stock->released_at,
        };

        return ($direction === 'asc' ? $stocks->sortBy($value) : $stocks->sortByDesc($value))->values();
    }
}
