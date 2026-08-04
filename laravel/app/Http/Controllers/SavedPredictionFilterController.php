<?php

namespace App\Http\Controllers;

use App\Models\SavedPredictionFilter;
use App\Enums\PlanLevel;
use App\Services\PlanAccessService;
use App\Services\SavedFilterLimitService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

final class SavedPredictionFilterController extends Controller
{
    public const FILTER_KEYS = [
        'q', 'country', 'exchange', 'sector', 'ai_type', 'model', 'quality_tier', 'signal',
        'score_min', 'confidence_min', 'drawdown_max', 'profit_factor_min', 'volatility_max',
        'predicted_return_min',
        'pe_max', 'dividend_yield_min', 'market_cap_min', 'revenue_growth_min', 'hit_rate_min',
        'gate_mode', 'sector_score_rotation', 'index_score_rotation', 'max_positions', 'position_factor', 'exit_strategy',
    ];

    public const FILTER_DEFAULTS = [
        'q' => '', 'country' => '', 'exchange' => '', 'sector' => '', 'ai_type' => '',
        'model' => '', 'quality_tier' => '', 'signal' => '',
        'score_min' => 0, 'confidence_min' => 0, 'drawdown_max' => 50,
        'profit_factor_min' => 0, 'volatility_max' => 100,
        'predicted_return_min' => -50,
        'pe_max' => 100, 'dividend_yield_min' => 0, 'market_cap_min' => 0,
        'revenue_growth_min' => -50, 'hit_rate_min' => 0,
        'gate_mode' => 'system',
        'sector_score_rotation' => 0, 'index_score_rotation' => 0, 'max_positions' => 5, 'position_factor' => 1,
        'exit_strategy' => 'fixed_20d',
    ];

    public function index(Request $request, SavedFilterLimitService $limits): View
    {
        $savedFilters = SavedPredictionFilter::query()
            ->availableTo($request->user())
            ->with(['portfolios' => fn ($query) => $query->where('user_id', $request->user()->id)])
            ->orderByRaw("CASE WHEN visibility = 'pro_public' THEN 0 ELSE 1 END")
            ->orderByDesc('updated_at')
            ->get();
        $ownedSavedFilterCount = $savedFilters->where('user_id', $request->user()->id)->count();
        $savedFilterLimit = $limits->limitFor($request->user());
        $emailServiceEnabled = (bool) data_get($request->user()->preferences, 'email_service', true);
        $returnFilters = (array) $request->session()->get('setup_filter_state', self::FILTER_DEFAULTS);
        $modelAliases = DB::table('model_definitions')->pluck('public_alias', 'id');
        $runByFilterSignature = DB::table('backtest_runs')
            ->whereIn('status', ['completed', 'completed_with_errors'])
            ->whereRaw("settings->>'run_type' = 'user_filter'")
            ->whereRaw("(settings->>'initiated_by_user_id')::bigint = ?", [$request->user()->id])
            ->orderByDesc('finished_at')
            ->get(['id', 'public_id', 'settings', 'updated_at'])
            ->reduce(function ($runs, object $run) {
                $settings = is_string($run->settings) ? (json_decode($run->settings, true) ?: []) : (array) $run->settings;
                $signature = $this->filterSignature((array) data_get($settings, 'selection_filters', []));
                if (! $runs->has($signature)) $runs->put($signature, $run);

                return $runs;
            }, collect());
        $filterMetrics = $savedFilters->mapWithKeys(function (SavedPredictionFilter $savedFilter) use ($request, $runByFilterSignature): array {
            $run = $runByFilterSignature->get($this->filterSignature((array) $savedFilter->filters));
            if ($run === null) return [$savedFilter->id => null];

            $metrics = Cache::remember(
                'saved-filter-metrics:'.$run->id.':'.strtotime((string) $run->updated_at),
                now()->addMinutes(10),
                function () use ($request, $run): array {
                    $result = app(PredictionController::class)
                        ->filteredBacktestResult($request, (string) $run->public_id)
                        ->getData(true);
                    $years = max(1 / 12, (float) ($result['backtest_months'] ?? 36) / 12);
                    $totalPerformance = (float) ($result['strategy_performance'] ?? 0);
                    $annualPerformance = $totalPerformance > -100
                        ? (pow(1 + ($totalPerformance / 100), 1 / $years) - 1) * 100
                        : -100;

                    return [
                        'performance_year' => round($annualPerformance, 2),
                        'profit_factor' => $result['profit_factor'] ?? null,
                        'trades_month' => round((float) ($result['trades_per_month'] ?? 0), 2),
                        'drawdown' => round((float) ($result['portfolio_max_drawdown'] ?? 0), 2),
                    ];
                },
            );

            return [$savedFilter->id => $metrics];
        });

        return view('setup.saved-filters', compact('savedFilters', 'ownedSavedFilterCount', 'savedFilterLimit', 'emailServiceEnabled', 'returnFilters', 'modelAliases', 'filterMetrics'));
    }

    public function store(Request $request, SavedFilterLimitService $limits): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'saved_filter' => ['nullable', 'integer', 'min:1'],
            'exit_strategy' => ['required', 'in:fixed_20d,winner_runner,prediction_target,buy_and_hold'],
            'visibility' => ['required', 'in:private,pro_public'],
            'description' => ['nullable', 'string', 'max:1000'],
        ]);
        $user = $request->user();
        $editedFilter = isset($validated['saved_filter'])
            ? $user->savedPredictionFilters()->whereKey($validated['saved_filter'])->firstOrFail()
            : null;
        $existing = SavedPredictionFilter::query()
            ->where('user_id', $user->id)
            ->where('name', trim($validated['name']))
            ->when($editedFilter, fn ($query) => $query->whereKeyNot($editedFilter->id))
            ->first();

        // Saving under an existing name means updating that user's filter.
        // This keeps the save flow predictable when the user opens a stored
        // setup without the saved_filter query parameter.
        if ($editedFilter === null && $existing !== null) $editedFilter = $existing;

        if ($editedFilter === null && $user->savedPredictionFilters()->count() >= $limits->limitFor($user)) {
            return back()->withErrors(['saved_filter' => __('Das Limit für gespeicherte Filter in deinem Tarif ist erreicht.')]);
        }

        $filters = collect(self::FILTER_DEFAULTS)
            ->mapWithKeys(fn ($default, string $key) => [$key => $request->input($key, $default)])
            ->all();
        $filters['model'] = collect((array) ($filters['model'] ?? []))
            ->filter(fn ($id) => is_numeric($id) && (int) $id > 0)
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();
        $filters['exit_strategy'] = in_array($filters['exit_strategy'] ?? null, ['fixed_20d', 'winner_runner', 'prediction_target', 'buy_and_hold'], true)
            ? $filters['exit_strategy']
            : 'fixed_20d';
        foreach (['sector_score_rotation', 'index_score_rotation'] as $booleanFilter) {
            $filters[$booleanFilter] = $request->boolean($booleanFilter) ? 1 : 0;
        }
        $filters['position_factor'] = max(1, (int) ($filters['position_factor'] ?? 1));
        $filters['max_positions'] = max(1, min(50, (int) ($filters['max_positions'] ?? 5)));
        $filters['position_factor'] = min($filters['position_factor'], $filters['max_positions']);
        if ($filters['exit_strategy'] === 'buy_and_hold') $filters['position_factor'] = 1;
        if ($editedFilter) {
            $editedFilter->update([
                'name' => trim($validated['name']),
                'filters' => $filters,
                'visibility' => $validated['visibility'],
                'description' => filled($validated['description'] ?? null) ? trim($validated['description']) : null,
                'published_at' => $validated['visibility'] === 'pro_public' ? ($editedFilter->published_at ?? now()) : null,
            ]);
            $savedFilter = $editedFilter->fresh();
        } else {
            $savedFilter = $user->savedPredictionFilters()->create([
                'name' => trim($validated['name']),
                'filters' => $filters,
                'visibility' => $validated['visibility'],
                'description' => filled($validated['description'] ?? null) ? trim($validated['description']) : null,
                'published_at' => $validated['visibility'] === 'pro_public' ? now() : null,
            ]);
        }
        $request->session()->put('setup_filter_state', $filters);

        return redirect()->route('setup.saved-filters.index', ['highlight' => $savedFilter->id])
            ->with('status', __('Filter gespeichert.'));
    }

    public function destroy(Request $request, SavedPredictionFilter $savedFilter): RedirectResponse
    {
        abort_unless((int) $savedFilter->user_id === (int) $request->user()->id, 404);
        if ($savedFilter->portfolios()->exists()) {
            return back()->withErrors(['strategy_delete' => __('Die Strategie ist noch einem Depot zugeordnet. Entferne sie zuerst im Musterdepot.')]);
        }
        $savedFilter->delete();

        return back()->with('status', __('Filter gelöscht.'));
    }

    public function update(Request $request, SavedPredictionFilter $savedFilter): RedirectResponse
    {
        abort_unless((int) $savedFilter->user_id === (int) $request->user()->id, 404);
        $validated = $request->validate(['name' => ['required', 'string', 'max:80']]);
        $name = trim($validated['name']);
        $duplicate = SavedPredictionFilter::query()
            ->where('user_id', $request->user()->id)
            ->where('name', $name)
            ->whereKeyNot($savedFilter->id)
            ->exists();
        if ($duplicate) {
            return back()->withErrors(['name_'.$savedFilter->id => __('Ein Filter mit diesem Namen existiert bereits.')]);
        }
        $savedFilter->update(['name' => $name]);

        return back()->with('status', __('Filter umbenannt.'));
    }

    public function link(Request $request, SavedPredictionFilter $savedFilter): RedirectResponse
    {
        abort_unless((int) $savedFilter->user_id === (int) $request->user()->id, 404);
        $validated = $request->validate([
            'email_notification_enabled' => ['required', 'boolean'],
        ]);
        if ((bool) $validated['email_notification_enabled'] && ! (bool) data_get($request->user()->preferences, 'email_service', true)) {
            return back()->withErrors(['email_notification' => __('Aktiviere zuerst den E-Mail-Service in deinem Profil.')]);
        }
        $savedFilter->update([
            'watchlist_id' => null,
            'email_notification_enabled' => (bool) $validated['email_notification_enabled'],
        ]);

        return back()->with('status', __('Benachrichtigung gespeichert.'));
    }

    public function import(Request $request, SavedPredictionFilter $savedFilter, SavedFilterLimitService $limits, PlanAccessService $access): RedirectResponse
    {
        abort_unless($savedFilter->visibility === 'pro_public', 404);
        abort_unless($access->allows($request->user(), PlanLevel::Pro), 403);
        if ($request->user()->savedPredictionFilters()->count() >= $limits->limitFor($request->user())) {
            return back()->withErrors(['strategy_import' => __('Das Limit für gespeicherte Strategien in deinem Tarif ist erreicht.')]);
        }
        $baseName = trim($savedFilter->name);
        $name = $baseName;
        $suffix = 2;
        while ($request->user()->savedPredictionFilters()->where('name', $name)->exists()) {
            $name = $baseName.' ('.$suffix++.')';
        }
        $copy = $request->user()->savedPredictionFilters()->create([
            'name' => $name,
            'filters' => $savedFilter->filters,
            'description' => $savedFilter->description,
            'visibility' => 'private',
            'source_strategy_id' => $savedFilter->id,
            'published_at' => null,
        ]);

        return redirect()->route('setup.saved-filters.index', ['highlight' => $copy->id])
            ->with('status', __('Strategie importiert. Du kannst deine private Kopie jetzt bearbeiten.'));
    }

    public function updateVisibility(Request $request, SavedPredictionFilter $savedFilter): RedirectResponse
    {
        abort_unless((int) $savedFilter->user_id === (int) $request->user()->id, 404);
        $validated = $request->validate(['visibility' => ['required', 'in:private,pro_public']]);
        $savedFilter->update([
            'visibility' => $validated['visibility'],
            'published_at' => $validated['visibility'] === 'pro_public' ? ($savedFilter->published_at ?? now()) : null,
        ]);

        return back()->with('status', $validated['visibility'] === 'pro_public'
            ? __('Die Strategie steht Pro-Nutzern jetzt als importierbare Vorlage zur Verfügung.')
            : __('Die Strategie ist jetzt privat. Bereits importierte Kopien bleiben erhalten.'));
    }

    private function filterSignature(array $filters): string
    {
        $normalized = collect(self::FILTER_DEFAULTS)->mapWithKeys(function ($default, string $key) use ($filters): array {
            $value = $filters[$key] ?? $default;
            if ($key === 'model') {
                $value = collect((array) $value)->map(fn ($id) => (int) $id)->filter()->sort()->values()->all();
            } elseif (in_array($key, ['sector_score_rotation', 'index_score_rotation', 'max_positions', 'position_factor'], true)) {
                $value = (int) $value;
            } else {
                $value = (string) $value;
            }

            return [$key => $value];
        })->all();

        return hash('sha256', json_encode($normalized, JSON_THROW_ON_ERROR));
    }
}
