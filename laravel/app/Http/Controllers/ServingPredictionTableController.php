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
        $allModels = $allStocks->flatMap(fn (object $stock): Collection => collect($stock->horizons));
        $metricRanges = (object) [
            'profit_per_trade' => $this->metricRange($allModels, 'average_return', -10.0, 10.0, 0.1, false, 0.05),
            'drawdown' => $this->metricRange($allModels, 'max_drawdown', 0.0, 100.0, 0.1, true),
            'hit_rate' => $this->metricRange($allModels, 'hit_rate', 0.0, 100.0, 0.1),
        ];
        $search = mb_strtolower(trim((string) $request->query('q', '')));
        $country = strtoupper(trim((string) $request->query('country', '')));
        $exchange = strtoupper(trim((string) $request->query('exchange', '')));
        $sector = trim((string) $request->query('sector', ''));
        $quality = strtolower(trim((string) $request->query('quality', '')));
        $status = strtolower(trim((string) $request->query('status', '')));
        $signal = strtoupper(trim((string) $request->query('signal', '')));
        $horizon = in_array($request->integer('horizon'), [10, 20, 40], true)
            ? $request->integer('horizon')
            : null;
        $profitPerTradeMin = $this->optionalNumber($request, 'profit_per_trade_min', $metricRanges->profit_per_trade->min, $metricRanges->profit_per_trade->max);
        $drawdownMax = $this->optionalNumber($request, 'drawdown_max', $metricRanges->drawdown->min, $metricRanges->drawdown->max);
        $hitRateMin = $this->optionalNumber($request, 'hit_rate_min', $metricRanges->hit_rate->min, $metricRanges->hit_rate->max);
        $profitPerTradeMin = $profitPerTradeMin !== null && $profitPerTradeMin > $metricRanges->profit_per_trade->min ? $profitPerTradeMin : null;
        $drawdownMax = $drawdownMax !== null && $drawdownMax < $metricRanges->drawdown->max ? $drawdownMax : null;
        $hitRateMin = $hitRateMin !== null && $hitRateMin > $metricRanges->hit_rate->min ? $hitRateMin : null;
        $modelFilterActive = $signal !== '' || $horizon !== null || $profitPerTradeMin !== null || $drawdownMax !== null || $hitRateMin !== null;

        $allStocks->each(function (object $stock) use ($modelFilterActive, $signal, $horizon, $profitPerTradeMin, $drawdownMax, $hitRateMin): void {
            collect($stock->horizons)->each(function (object $model) use ($modelFilterActive, $signal, $horizon, $profitPerTradeMin, $drawdownMax, $hitRateMin): void {
                $model->matches_active_filter = ! $modelFilterActive || $this->modelMatchesFilters(
                    $model,
                    $signal,
                    $horizon,
                    $profitPerTradeMin,
                    $drawdownMax,
                    $hitRateMin
                );
            });
        });

        $filtered = $allStocks->filter(function (object $stock) use (
            $search, $country, $exchange, $sector, $quality, $status, $signal,
            $modelFilterActive
        ): bool {
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
            if ($modelFilterActive && ! collect($stock->horizons)->contains(
                fn (object $model): bool => $model->matches_active_filter
            )) {
                return false;
            }

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
        $filteredModels = $filtered
            ->flatMap(fn (object $stock): Collection => collect($stock->horizons))
            ->filter(fn (object $model): bool => ! $modelFilterActive || $model->matches_active_filter);
        $summary = (object) [
            'active_stocks' => $filtered->count(),
            'eligible_horizons' => $filteredModels->where('prediction_enabled', true)->count(),
            'published_predictions' => $filteredModels->pluck('prediction')->filter()->count(),
            'stocks_with_predictions' => $filtered->filter(fn (object $stock): bool => collect($stock->horizons)->contains(
                fn (object $model): bool => (! $modelFilterActive || $model->matches_active_filter) && $model->prediction !== null
            ))->count(),
            'last_release_at' => $allStocks->max('released_at'),
            'quality_counts' => $qualityCounts,
        ];

        $countries = $allStocks->pluck('country_code')->filter()->unique()->sort()->values();
        $exchanges = $allStocks->pluck('exchange')->filter()->unique()->sort()->values();
        $sectors = $allStocks->pluck('sector_code')->filter()->unique()->sort()->values();

        return view('predictions.serving-index', compact(
            'stocks', 'summary', 'countries', 'exchanges', 'sectors', 'sort', 'direction', 'metricRanges', 'modelFilterActive'
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

    private function optionalNumber(Request $request, string $key, float $minimum, float $maximum): ?float
    {
        $value = $request->query($key);

        if ($value === null || $value === '' || ! is_numeric($value)) {
            return null;
        }

        return max($minimum, min($maximum, (float) $value));
    }

    private function metricRange(
        Collection $models,
        string $metric,
        float $fallbackMin,
        float $fallbackMax,
        float $step,
        bool $absolute = false,
        float $trimPercent = 0.0
    ): object {
        $values = $models
            ->map(fn (object $model): mixed => $model->metrics->{$metric} ?? null)
            ->filter(fn (mixed $value): bool => is_numeric($value) && is_finite((float) $value))
            ->map(fn (mixed $value): float => $absolute ? abs((float) $value) : (float) $value)
            ->sort()
            ->values();

        if ($values->isEmpty()) {
            $minimum = $fallbackMin;
            $maximum = $fallbackMax;
        } else {
            $lastIndex = $values->count() - 1;
            $lowerIndex = (int) floor($lastIndex * max(0.0, min(0.49, $trimPercent)));
            $upperIndex = (int) ceil($lastIndex * (1.0 - max(0.0, min(0.49, $trimPercent))));
            $minimum = floor((float) $values[$lowerIndex] / $step) * $step;
            $maximum = ceil((float) $values[$upperIndex] / $step) * $step;
        }

        if ($maximum <= $minimum) {
            $maximum = $minimum + $step;
        }

        return (object) ['min' => $minimum, 'max' => $maximum, 'step' => $step];
    }

    private function modelMatchesFilters(
        object $model,
        string $signal,
        ?int $horizon,
        ?float $profitPerTradeMin,
        ?float $drawdownMax,
        ?float $hitRateMin
    ): bool {
        $requiresReleasedPrediction = $signal !== ''
            || $profitPerTradeMin !== null
            || $drawdownMax !== null
            || $hitRateMin !== null;
        if ($requiresReleasedPrediction && ! $model->prediction_enabled) return false;
        if ($signal !== '' && strtoupper((string) ($model->prediction?->signal ?? '')) !== $signal) return false;
        if ($horizon !== null && (int) $model->horizon !== $horizon) return false;

        $metrics = $model->metrics;
        if ($profitPerTradeMin !== null && (! is_numeric($metrics->average_return) || (float) $metrics->average_return < $profitPerTradeMin)) return false;
        if ($drawdownMax !== null && (! is_numeric($metrics->max_drawdown) || abs((float) $metrics->max_drawdown) > $drawdownMax)) return false;
        if ($hitRateMin !== null && (! is_numeric($metrics->hit_rate) || (float) $metrics->hit_rate < $hitRateMin)) return false;

        return true;
    }
}
