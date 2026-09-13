<?php

namespace App\Http\Controllers;

use App\Services\ServingReadService;
use App\Services\ServingWalkForwardStatisticsService;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;

final class ServingPredictionTableController extends Controller
{
    private const MAX_SAVED_SELECTION_SNAPSHOTS = 100;

    private const RESTORABLE_FILTERS = [
        'q', 'country', 'exchange', 'sector', 'quality', 'status', 'signal', 'horizon', 'variant', 'panel',
        'profit_per_trade_min', 'drawdown_max', 'hit_rate_min', 'minimum_trades', 'sort', 'direction',
    ];

    public function __invoke(Request $request, ServingReadService $serving): View
    {
        $restoreToken = (string) $request->query('restore_selection', '');
        $storedRestoredSelection = preg_match('/^[A-Za-z0-9]{40}$/', $restoreToken) === 1
            ? $request->session()->get("serving_strategy_selections.{$restoreToken}")
            : null;
        $hasRestoredSelection = is_array($storedRestoredSelection) && $storedRestoredSelection !== [];
        $restoredConfigurationKeys = collect();
        if ($hasRestoredSelection) {
            $restored = (array) $request->session()->get("serving_strategy_selection_filters.{$restoreToken}", []);
            $restoredConfigurationKeys = collect($storedRestoredSelection)
                ->map(fn (array $configuration): string => $this->configurationKey($configuration));
            if ($restored !== []) {
                $request->query->replace(array_merge(
                    Arr::only($restored, self::RESTORABLE_FILTERS),
                    Arr::except($request->query(), ['restore_selection']),
                ));
            }
        }

        $allStocks = $serving->activeStocks();
        $this->attachPanelRatings($allStocks);
        $this->expandModelVariants($allStocks);
        $this->attachWalkForwardStatistics($allStocks);
        $executableModelKeys = $this->executableServingModelKeys();
        $allStocks->each(function (object $stock) use ($executableModelKeys): void {
            $this->stockModels($stock)->each(function (object $model) use ($stock, $executableModelKeys): void {
                $model->strategy_executable = $executableModelKeys->has($this->executionKey(
                    (string) $stock->release_id,
                    (string) $stock->symbol,
                    (int) $model->horizon,
                    (string) $model->variant,
                ));
            });
        });
        $allModels = $allStocks->flatMap(fn (object $stock): Collection => $this->stockModels($stock));
        $trainingModels = $allModels->filter(
            fn (object $model): bool => (int) ($model->statistics_metrics?->trades ?? 0) > 0,
        );
        $profitPerTradeRange = $this->metricRange($trainingModels, 'median_return', -10.0, 10.0, 0.1, false, 0.0, 'statistics_metrics');
        $profitPerTradeRange->min = min(-10.0, (float) $profitPerTradeRange->min);
        $profitPerTradeRange->max = max(10.0, (float) $profitPerTradeRange->max);
        $metricRanges = (object) [
            'profit_per_trade' => $profitPerTradeRange,
            'drawdown' => $this->metricRange($trainingModels, 'max_drawdown', 0.0, 100.0, 0.1, true, 0.0, 'statistics_metrics'),
            'hit_rate' => $this->metricRange($trainingModels, 'hit_rate', 0.0, 100.0, 0.1, false, 0.0, 'statistics_metrics'),
            'trades' => $this->metricRange($trainingModels, 'trades', 0.0, 100.0, 1.0, false, 0.0, 'statistics_metrics'),
        ];
        $search = mb_strtolower(trim((string) $request->query('q', '')));
        $country = strtoupper(trim((string) $request->query('country', '')));
        $exchange = strtoupper(trim((string) $request->query('exchange', '')));
        $sector = trim((string) $request->query('sector', ''));
        $quality = strtolower(trim((string) $request->query('quality', '')));
        $status = strtolower(trim((string) $request->query('status', '')));
        $signal = strtoupper(trim((string) $request->query('signal', '')));
        $variant = in_array($request->query('variant'), ['standard', 'pure_tcn'], true)
            ? (string) $request->query('variant')
            : '';
        $panelDecile = in_array($request->integer('panel'), range(1, 10), true)
            ? $request->integer('panel')
            : null;
        $horizon = in_array($request->integer('horizon'), [10, 20, 40], true)
            ? $request->integer('horizon')
            : null;
        $profitPerTradeMin = $this->optionalNumber($request, 'profit_per_trade_min', $metricRanges->profit_per_trade->min, $metricRanges->profit_per_trade->max);
        $drawdownMax = $this->optionalNumber($request, 'drawdown_max', $metricRanges->drawdown->min, $metricRanges->drawdown->max);
        $hitRateMin = $this->optionalNumber($request, 'hit_rate_min', $metricRanges->hit_rate->min, $metricRanges->hit_rate->max);
        $minimumTrades = $this->optionalNumber($request, 'minimum_trades', $metricRanges->trades->min, $metricRanges->trades->max);
        $profitPerTradeMin = $profitPerTradeMin !== null && $profitPerTradeMin > $metricRanges->profit_per_trade->min ? $profitPerTradeMin : null;
        $drawdownMax = $drawdownMax !== null && $drawdownMax < $metricRanges->drawdown->max ? $drawdownMax : null;
        $hitRateMin = $hitRateMin !== null && $hitRateMin > $metricRanges->hit_rate->min ? $hitRateMin : null;
        $minimumTrades = $minimumTrades !== null && $minimumTrades > $metricRanges->trades->min ? (int) round($minimumTrades) : null;
        $selectionFilterActive = $horizon !== null || $variant !== '' || $profitPerTradeMin !== null
            || $drawdownMax !== null || $hitRateMin !== null || $minimumTrades !== null;
        $modelFilterActive = $signal !== '' || $selectionFilterActive;

        $allStocks->each(function (object $stock) use ($modelFilterActive, $selectionFilterActive, $signal, $horizon, $variant, $profitPerTradeMin, $drawdownMax, $hitRateMin, $minimumTrades): void {
            $this->stockModels($stock)->each(function (object $model) use ($modelFilterActive, $selectionFilterActive, $signal, $horizon, $variant, $profitPerTradeMin, $drawdownMax, $hitRateMin, $minimumTrades): void {
                $model->matches_active_filter = ! $modelFilterActive || $this->modelMatchesFilters(
                    $model,
                    $signal,
                    $horizon,
                    $variant,
                    $profitPerTradeMin,
                    $drawdownMax,
                    $hitRateMin,
                    $minimumTrades
                );
                // A live signal belongs to the stock's currently active
                // serving variant. It may decide whether the stock row is
                // visible, but it must not disable another variant that has a
                // complete historical backtest and can therefore be tested.
                $model->matches_selection_filter = ! $selectionFilterActive || $this->modelMatchesFilters(
                    $model,
                    '',
                    $horizon,
                    $variant,
                    $profitPerTradeMin,
                    $drawdownMax,
                    $hitRateMin,
                    $minimumTrades
                );
            });
        });

        $filtered = $allStocks->filter(function (object $stock) use (
            $search, $country, $exchange, $sector, $quality, $status,
            $modelFilterActive, $selectionFilterActive, $signal, $panelDecile
        ): bool {
            // Availability is model-specific and based only on the two-year
            // training window. A stock stays visible when any one of its
            // Standard or TCN configurations has training trades.
            if (! $this->stockModels($stock)->contains(
                fn (object $model): bool => (bool) ($model->training_selectable ?? false)
            )) {
                return false;
            }
            if ($search !== '' && ! str_contains(mb_strtolower(implode(' ', [
                $stock->name, $stock->symbol, $stock->isin, $stock->industry,
            ])), $search)) {
                return false;
            }
            if ($country !== '' && strtoupper((string) $stock->country_code) !== $country) {
                return false;
            }
            if ($exchange !== '' && strtoupper((string) $stock->exchange) !== $exchange) {
                return false;
            }
            if ($sector !== '' && (string) $stock->sector_code !== $sector) {
                return false;
            }
            if ($quality !== '' && (string) $stock->quality_class !== $quality) {
                return false;
            }
            if ($status === 'eligible' && $stock->eligible_horizon_count < 1) {
                return false;
            }
            if ($status === 'blocked' && $stock->eligible_horizon_count > 0) {
                return false;
            }
            if ($status === 'published' && $stock->prediction_count < 1) {
                return false;
            }
            if ($status === 'waiting' && $stock->prediction_count > 0) {
                return false;
            }
            if ($panelDecile !== null && (int) ($stock->panel_decile ?? 0) !== $panelDecile) {
                return false;
            }
            if ($signal !== '' && ! $this->stockModels($stock)->contains(
                fn (object $model): bool => $model->prediction_enabled
                    && strtoupper((string) ($model->prediction?->signal ?? '')) === $signal
            )) {
                return false;
            }
            if ($selectionFilterActive && ! $this->stockModels($stock)->contains(
                fn (object $model): bool => $model->matches_selection_filter
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
            ->flatMap(fn (object $stock): Collection => $this->stockModels($stock))
            ->filter(fn (object $model): bool => ! $selectionFilterActive || $model->matches_selection_filter);
        $summary = (object) [
            'active_stocks' => $filtered->count(),
            'eligible_horizons' => $filteredModels->where('prediction_enabled', true)->count(),
            'published_predictions' => $filteredModels->pluck('prediction')->filter()->count(),
            'stocks_with_predictions' => $filtered->filter(fn (object $stock): bool => $this->stockModels($stock)->contains(
                fn (object $model): bool => (! $modelFilterActive || $model->matches_active_filter) && $model->prediction !== null
            ))->count(),
            'last_release_at' => $allStocks->max('released_at'),
            'quality_counts' => $qualityCounts,
        ];

        // Freeze the exact Service-DB model configurations represented by the
        // current result. Rebuilding this selection later from broad filters
        // could silently include a different variant or horizon.
        $strategyConfigurations = $filtered->flatMap(function (object $stock) use ($selectionFilterActive): Collection {
            return $this->stockModels($stock)
                ->filter(fn (object $model): bool => (! $selectionFilterActive || $model->matches_selection_filter)
                    && $model->training_selectable)
                ->map(fn (object $model): array => [
                    'source' => 'serving_prediction_table',
                    'symbol' => strtoupper((string) $stock->symbol),
                    'release_id' => (string) $stock->release_id,
                    'source_release_id' => (string) $stock->release_id,
                    'release_policy' => 'active',
                    'horizon' => (int) $model->horizon,
                    'horizon_days' => (int) $model->horizon,
                    'variant' => (string) $model->variant,
                    'selection_active' => (bool) $model->is_active,
                    'historical_trade_available' => (bool) ($model->strategy_executable ?? false),
                ]);
        })->unique(fn (array $configuration): string => implode('|', $configuration))->values();
        $strategySelectionToken = null;
        if ($strategyConfigurations->isNotEmpty()) {
            $selections = (array) $request->session()->get('serving_strategy_selections', []);
            $selectionFilters = (array) $request->session()->get('serving_strategy_selection_filters', []);
            $snapshotConfigurations = $strategyConfigurations->all();
            $snapshotFilters = Arr::only($request->query(), self::RESTORABLE_FILTERS);
            // Repeated GET requests (range sliders, pagination or browser
            // prefetching) must not evict the token that belongs to the table
            // the user is still looking at. Reuse an identical snapshot.
            foreach (array_reverse(array_keys($selections)) as $existingToken) {
                if (($selections[$existingToken] ?? null) === $snapshotConfigurations
                    && ($selectionFilters[$existingToken] ?? []) === $snapshotFilters) {
                    $strategySelectionToken = (string) $existingToken;
                    break;
                }
            }
            if ($strategySelectionToken === null) {
                $strategySelectionToken = Str::random(40);
                $selections[$strategySelectionToken] = $snapshotConfigurations;
                $selectionFilters[$strategySelectionToken] = $snapshotFilters;
            }
            while (count($selections) > self::MAX_SAVED_SELECTION_SNAPSHOTS) {
                $oldestToken = (string) array_key_first($selections);
                unset($selections[$oldestToken], $selectionFilters[$oldestToken]);
            }
            $request->session()->put('serving_strategy_selections', $selections);
            $selectionFilters = Arr::only($selectionFilters, array_keys($selections));
            $request->session()->put('serving_strategy_selection_filters', $selectionFilters);
        }
        $strategyUrl = $strategySelectionToken === null ? null : route('setup.filter', [
            'new_backtest' => 1,
            'serving_selection' => $strategySelectionToken,
        ]);
        $strategyModelCount = $strategyConfigurations->count();
        $selectableModelKeys = $strategyConfigurations
            ->map(fn (array $configuration): string => $this->configurationKey($configuration))
            ->values();
        // "All" means every selectable model remaining after the current
        // filters, not only the release's green champion variant. Otherwise a
        // filtered page containing only available challengers starts with zero
        // selected models and the strategy action can never be submitted.
        $bulkSelectableModelKeys = $selectableModelKeys;
        $initialSelectedModelKeys = ($hasRestoredSelection
            ? $restoredConfigurationKeys->intersect($selectableModelKeys)
            : $bulkSelectableModelKeys)
            ->values();
        $indexRoute = $request->routeIs('setup.models') ? 'setup.models' : 'predictions.index';

        $countries = $allStocks->pluck('country_code')->filter()->unique()->sort()->values();
        $exchanges = $allStocks->pluck('exchange')->filter()->unique()->sort()->values();
        $sectors = $allStocks->pluck('sector_code')->filter()->unique()->sort()->values();

        return view('predictions.serving-index', compact(
            'stocks', 'summary', 'countries', 'exchanges', 'sectors', 'sort', 'direction', 'metricRanges', 'modelFilterActive',
            'strategyUrl', 'strategyModelCount', 'strategySelectionToken', 'selectableModelKeys', 'bulkSelectableModelKeys', 'initialSelectedModelKeys', 'indexRoute'
        ));
    }

    public function storeStrategySelection(Request $request, ServingReadService $serving): RedirectResponse
    {
        $validated = $request->validate([
            'selection_token' => ['required', 'string', 'size:40', 'regex:/^[A-Za-z0-9]+$/'],
            'select_all' => ['nullable', 'boolean'],
            'models' => ['nullable', 'array', 'max:2000'],
            'models.*' => ['required', 'string', 'max:180'],
        ]);
        $sourceToken = $validated['selection_token'];
        $sourceAvailable = collect((array) $request->session()->get("serving_strategy_selections.{$sourceToken}", []));
        // Browser back/forward navigation can restore a table DOM from an
        // older page while retaining the newest opaque selection token. Use
        // all still-valid session configurations to resolve the stable model
        // keys actually submitted by the checkboxes. This preserves the
        // user's exact visible selection without trusting arbitrary symbols.
        $availableByKey = collect((array) $request->session()->get('serving_strategy_selections', []))
            ->flatMap(fn (mixed $configurations): array => is_array($configurations) ? $configurations : [])
            ->filter(fn (mixed $configuration): bool => is_array($configuration))
            ->keyBy(fn (array $configuration): string => $this->configurationKey($configuration));
        $submittedModelKeys = collect($validated['models'] ?? [])->unique()->values();
        $unresolvedModelKeys = $submittedModelKeys->reject(fn (string $key): bool => $availableByKey->has($key));
        if ($unresolvedModelKeys->isNotEmpty()) {
            $canonicalByKey = $this->canonicalStrategyConfigurations($serving)
                ->keyBy(fn (array $configuration): string => $this->configurationKey($configuration));
            $availableByKey = $availableByKey->union(
                $canonicalByKey->only($unresolvedModelKeys->all()),
            );
        }
        $selected = $request->boolean('select_all')
            ? $sourceAvailable->values()
            : ($submittedModelKeys->isNotEmpty()
                ? $submittedModelKeys
                ->unique()
                ->map(fn (string $key): ?array => $availableByKey->get($key))
                ->filter()
                ->values()
                : collect());
        $selected = $selected
            ->map(fn (array $configuration): array => Arr::except($configuration, ['selection_active']))
            ->values();

        if ($selected->isEmpty()) {
            return back()->withErrors(['models' => __('Bitte wähle mindestens ein freigegebenes Modell aus.')]);
        }

        $selectionToken = Str::random(40);
        $selections = (array) $request->session()->get('serving_strategy_selections', []);
        $selections[$selectionToken] = $selected->all();
        while (count($selections) > self::MAX_SAVED_SELECTION_SNAPSHOTS) {
            $oldestToken = (string) array_key_first($selections);
            unset($selections[$oldestToken]);
        }
        $request->session()->put('serving_strategy_selections', $selections);

        $selectionFilters = (array) $request->session()->get('serving_strategy_selection_filters', []);
        $selectionFilters[$selectionToken] = (array) ($selectionFilters[$sourceToken] ?? []);
        $selectionFilters = Arr::only($selectionFilters, array_keys($selections));
        $request->session()->put('serving_strategy_selection_filters', $selectionFilters);

        return redirect()->route('setup.filter', [
            'new_backtest' => 1,
            'serving_selection' => $selectionToken,
        ]);
    }

    private function configurationKey(array $configuration): string
    {
        return strtoupper((string) ($configuration['symbol'] ?? '')).'|'
            .(int) ($configuration['horizon_days'] ?? $configuration['horizon'] ?? 0).'|'
            .strtolower((string) ($configuration['variant'] ?? 'standard'));
    }

    private function canonicalStrategyConfigurations(ServingReadService $serving): Collection
    {
        $stocks = $serving->activeStocks();
        $this->expandModelVariants($stocks);
        $this->attachWalkForwardStatistics($stocks);

        return $stocks->flatMap(function (object $stock): Collection {
            return $this->stockModels($stock)
                ->filter(fn (object $model): bool => (bool) $model->training_selectable)
                ->map(fn (object $model): array => [
                    'source' => 'serving_prediction_table',
                    'symbol' => strtoupper((string) $stock->symbol),
                    'release_id' => (string) $stock->release_id,
                    'source_release_id' => (string) $stock->release_id,
                    'release_policy' => 'active',
                    'horizon' => (int) $model->horizon,
                    'horizon_days' => (int) $model->horizon,
                    'variant' => (string) $model->variant,
                    'selection_active' => (bool) $model->is_active,
                ]);
        })->values();
    }

    /** @return Collection<string, true> */
    private function executableServingModelKeys(): Collection
    {
        $serving = DB::connection('serving');
        $runs = $serving->table('serving_strategy_runs')
            ->where('status', 'complete')
            ->orderByDesc('finished_at')
            ->orderByDesc('calculation_date')
            ->orderByDesc('id')
            ->get(['id', 'strategy_version', 'source_metadata'])
            ->mapWithKeys(function (object $run): array {
                $metadata = is_array($run->source_metadata)
                    ? $run->source_metadata
                    : (json_decode((string) $run->source_metadata, true) ?: []);
                $releaseId = (string) ($metadata['release_id'] ?? '');
                $variant = (string) ($metadata['variant'] ?? '');
                if ($variant === '') {
                    $variant = str_contains((string) $run->strategy_version, 'pure-tcn') ? 'pure_tcn' : 'standard';
                }

                return $releaseId === '' ? [] : [(string) $run->id => [
                    'release_id' => $releaseId,
                    'variant' => $variant,
                ]];
            });

        if ($runs->isEmpty()) {
            return collect();
        }

        return $serving->table('serving_strategy_trades as trade')
            ->join('serving_instruments as instrument', 'instrument.id', '=', 'trade.instrument_id')
            ->whereIn('trade.strategy_run_id', $runs->keys())
            ->where('trade.entry_signal', 'BUY')
            ->whereNotNull('trade.entry_close_eur')
            ->whereNotNull('trade.exit_close_eur')
            ->whereNotNull('trade.net_return')
            ->distinct()
            ->get(['trade.strategy_run_id', 'trade.horizon', 'instrument.symbol'])
            ->mapWithKeys(function (object $trade) use ($runs): array {
                $run = $runs->get((string) $trade->strategy_run_id);
                if (! is_array($run)) {
                    return [];
                }

                return [$this->executionKey(
                    $run['release_id'],
                    (string) $trade->symbol,
                    (int) $trade->horizon,
                    $run['variant'],
                ) => true];
            });
    }

    private function executionKey(string $releaseId, string $symbol, int $horizon, string $variant): string
    {
        return implode('|', [
            $releaseId,
            strtoupper(trim($symbol)),
            $horizon,
            strtolower(trim($variant)),
        ]);
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
        float $trimPercent = 0.0,
        string $metricsProperty = 'metrics'
    ): object {
        $values = $models
            ->map(fn (object $model): mixed => data_get($model, $metricsProperty.'.'.$metric))
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
        string $variant,
        ?float $profitPerTradeMin,
        ?float $drawdownMax,
        ?float $hitRateMin,
        ?int $minimumTrades
    ): bool {
        if ($signal !== '' && ! $model->prediction_enabled) {
            return false;
        }
        if ($signal !== '' && strtoupper((string) ($model->prediction?->signal ?? '')) !== $signal) {
            return false;
        }
        if ($horizon !== null && (int) $model->horizon !== $horizon) {
            return false;
        }
        if ($variant !== '' && (string) $model->variant !== $variant) {
            return false;
        }

        // Model selection is fitted exclusively on the first two years. The
        // following year is displayed as an untouched validation result and
        // must never decide whether its checkbox is enabled.
        $metrics = $model->statistics_metrics;
        if ($metrics === null || (int) ($metrics->trades ?? 0) < 1) {
            return false;
        }
        if ($profitPerTradeMin !== null && (! is_numeric($metrics->median_return) || (float) $metrics->median_return < $profitPerTradeMin)) {
            return false;
        }
        if ($drawdownMax !== null && (! is_numeric($metrics->max_drawdown) || abs((float) $metrics->max_drawdown) > $drawdownMax)) {
            return false;
        }
        if ($hitRateMin !== null && (! is_numeric($metrics->hit_rate) || (float) $metrics->hit_rate < $hitRateMin)) {
            return false;
        }
        if ($minimumTrades !== null && (! is_numeric($metrics->trades) || (int) $metrics->trades < $minimumTrades)) {
            return false;
        }

        return true;
    }

    private function expandModelVariants(Collection $stocks): void
    {
        $stocks->each(function (object $stock): void {
            collect($stock->horizons)->each(function (object $scope): void {
                $scope->model_variants = collect([
                    'standard' => $scope->standard_metrics,
                    'pure_tcn' => $scope->tcn_metrics,
                ])->map(function (object $metrics, string $variant) use ($scope): object {
                    $isActive = (string) $scope->variant === $variant;
                    $hasBacktest = (int) ($metrics->trades ?? 0) > 0
                        || is_numeric($metrics->hit_rate ?? null)
                        || is_numeric($metrics->profit_factor ?? null)
                        || is_numeric($metrics->average_return ?? null);

                    return (object) [
                        'horizon' => (int) $scope->horizon,
                        'variant' => $variant,
                        'variant_label' => $variant === 'pure_tcn' ? 'TCN' : 'Standard',
                        'metrics' => $metrics,
                        'is_active' => $isActive,
                        'strategy_selectable' => $hasBacktest,
                        'prediction_enabled' => $isActive && (bool) $scope->prediction_enabled,
                        'prediction' => $isActive ? $scope->prediction : null,
                    ];
                });
            });
        });
    }

    private function attachWalkForwardStatistics(Collection $stocks): void
    {
        $statistics = app(ServingWalkForwardStatisticsService::class)->forActiveStocks($stocks);

        $stocks->each(function (object $stock) use ($statistics): void {
            $this->stockModels($stock)->each(function (object $model) use ($stock, $statistics): void {
                $key = implode('|', [(int) $stock->instrument_id, (int) $model->horizon, (string) $model->variant]);
                $split = $statistics->get($key);
                $model->full_period_metrics = $model->metrics;
                $model->statistics_metrics = $split['statistics'] ?? null;
                $model->walk_forward_metrics = $split['walk_forward'] ?? null;
                $model->walk_forward_passed = (bool) ($split['walk_forward_passed'] ?? false);
                $model->statistics_from = $split['statistics_from'] ?? null;
                $model->statistics_to = $split['statistics_to'] ?? null;
                $model->walk_forward_from = $split['walk_forward_from'] ?? null;
                $model->walk_forward_to = $split['walk_forward_to'] ?? null;

                if ($model->statistics_metrics !== null) {
                    // Slider ranges, filters and displayed selection metrics
                    // must all use the same two-year statistics window.
                    $model->metrics = $model->statistics_metrics;
                }
                $model->training_selectable = $model->statistics_metrics !== null
                    && $model->statistics_metrics->trades > 0;
                $model->strategy_selectable = $model->training_selectable;
            });
        });
    }

    private function stockModels(object $stock): Collection
    {
        return collect($stock->horizons)
            ->flatMap(fn (object $scope): Collection => collect($scope->model_variants ?? []));
    }

    private function attachPanelRatings(Collection $stocks): void
    {
        $stocks->each(function (object $stock): void {
            $stock->panel_decile = null;
            $stock->panel_percentile = null;
        });

        try {
            $panelVersion = DB::table('panel_predictions')
                ->orderByDesc('as_of_date')
                ->orderByDesc('id')
                ->value('model_version');
            if (! $panelVersion) {
                return;
            }
            $peakCount = (int) DB::table('panel_predictions')
                ->where('model_version', $panelVersion)
                ->groupBy('as_of_date')
                ->orderByDesc(DB::raw('count(*)'))
                ->value(DB::raw('count(*)'));
            $panelAsOf = DB::table('panel_predictions')
                ->where('model_version', $panelVersion)
                ->groupBy('as_of_date')
                ->havingRaw('count(*) >= ?', [max(50, (int) ($peakCount * .85))])
                ->orderByDesc('as_of_date')
                ->value('as_of_date');

            if (! $panelAsOf) {
                return;
            }

            $ratings = DB::table('panel_predictions as panel')
                ->join('instruments as instrument', 'instrument.id', '=', 'panel.instrument_id')
                ->where('panel.model_version', $panelVersion)
                ->where('panel.as_of_date', $panelAsOf)
                ->whereIn('instrument.symbol', $stocks->pluck('symbol')->all())
                ->get(['instrument.symbol', 'panel.xsec_pctile', 'panel.decile'])
                ->keyBy(fn (object $rating): string => strtoupper((string) $rating->symbol));

            $stocks->each(function (object $stock) use ($ratings): void {
                $rating = $ratings->get(strtoupper((string) $stock->symbol));
                if (! $rating) {
                    return;
                }

                $stock->panel_decile = is_numeric($rating->decile) ? (int) $rating->decile : null;
                $stock->panel_percentile = is_numeric($rating->xsec_pctile)
                    ? max(0.0, min(100.0, (float) $rating->xsec_pctile * 100.0))
                    : null;
            });
        } catch (\Throwable) {
            // The Service model overview remains available while panel data is rebuilding.
        }
    }
}
