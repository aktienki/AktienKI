<?php

namespace App\Http\Controllers;

use App\Enums\PlanLevel;
use App\Models\SavedPredictionFilter;
use App\Services\PlanAccessService;
use App\Services\SavedFilterLimitService;
use App\Services\YahooIndexService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

final class SavedPredictionFilterController extends Controller
{
    public const FILTER_KEYS = [
        'q', 'country', 'exchange', 'sector', 'ai_type', 'model', 'quality_tier',
        'service_quality_gate', 'quality_horizons_present', 'quality_horizons', 'signal',
        'score_min', 'confidence_min', 'drawdown_max', 'risk_max', 'profit_per_trade_min', 'median_return_min', 'volatility_max', 'minimum_trades', 'sector_score_min',
        'predicted_return_min', 'noise_score_min', 'profit_factor_min', 'signal_quality_min', 'model_quality_min', 'heatmap_selection',
        'pe_max', 'dividend_yield_min', 'dividend_yield_operator', 'market_cap_min', 'market_cap_group', 'revenue_growth_min', 'hit_rate_min',
        'gate_mode', 'sector_score_rotation', 'index_score_rotation', 'entry_strategy', 'entry_risk_style', 'automatic_strategy_comparison', 'automatic_selected_strategy', 'forecast_score_rotation_5d_enabled', 'strategy_priority', 'initial_capital', 'trade_cost',
        'combined_area_forecast_priority', 'stock_forecast_weight', 'sector_forecast_weight', 'index_forecast_weight', 'drawdown_penalty_weight',
        'max_positions', 'position_factor', 'dynamic_capital_weighting', 'entry_wait_5d_enabled',
        'automatic_optimization', 'optimization_goal',
    ];

    public const FILTER_DEFAULTS = [
        'q' => '', 'country' => '', 'exchange' => '', 'sector' => '', 'ai_type' => '',
        'model' => '', 'quality_tier' => '', 'service_quality_gate' => '',
        'quality_horizons_present' => 1, 'quality_horizons' => [10, 20, 40], 'signal' => '',
        'score_min' => 0, 'confidence_min' => 0, 'drawdown_max' => 50, 'risk_max' => 100,
        'profit_per_trade_min' => 0, 'median_return_min' => null, 'volatility_max' => 100, 'minimum_trades' => 0, 'sector_score_min' => -1,
        'predicted_return_min' => 0.5, 'noise_score_min' => 0, 'profit_factor_min' => 0, 'signal_quality_min' => 0, 'model_quality_min' => 0, 'heatmap_selection' => '',
        'pe_max' => 100, 'dividend_yield_min' => 0, 'dividend_yield_operator' => 'gte', 'market_cap_min' => 0, 'market_cap_group' => 'all',
        'revenue_growth_min' => -50, 'hit_rate_min' => 0,
        'gate_mode' => 'system',
        'sector_score_rotation' => 0, 'index_score_rotation' => 0, 'entry_strategy' => 'direct_buy', 'entry_risk_style' => 'balanced', 'automatic_strategy_comparison' => 0, 'automatic_selected_strategy' => '', 'forecast_score_rotation_5d_enabled' => 0, 'strategy_priority' => 'rotation_first',
        'combined_area_forecast_priority' => 0, 'stock_forecast_weight' => .20, 'sector_forecast_weight' => .30, 'index_forecast_weight' => .50, 'drawdown_penalty_weight' => .30,
        'initial_capital' => 10000, 'trade_cost' => 10, 'max_positions' => 5, 'position_factor' => 1, 'dynamic_capital_weighting' => 0,
        'entry_wait_5d_enabled' => 0,
        'automatic_optimization' => 0, 'optimization_goal' => '',
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
                if (! $runs->has($signature)) {
                    $runs->put($signature, $run);
                }

                return $runs;
            }, collect());
        $filterMetrics = $savedFilters->mapWithKeys(function (SavedPredictionFilter $savedFilter) use ($request, $runByFilterSignature): array {
            if (collect(data_get($savedFilter->filters, 'serving_model_configurations', []))->isNotEmpty()) {
                return [$savedFilter->id => null];
            }
            $run = $runByFilterSignature->get($this->filterSignature((array) $savedFilter->filters));
            if ($run === null) {
                return [$savedFilter->id => null];
            }

            $metrics = Cache::remember(
                'saved-filter-metrics:'.$run->id.':'.strtotime((string) $run->updated_at),
                now()->addMinutes(10),
                function () use ($request, $run): array {
                    $result = app(PredictionController::class)
                        ->filteredBacktestResult($request, (string) $run->public_id, app(YahooIndexService::class))
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
            'entry_strategy' => ['nullable', 'in:direct_buy,wait_5d,forecast_score_rotation_5d'],
            'entry_risk_style' => ['nullable', 'in:conservative,balanced,chance'],
            'automatic_strategy_comparison' => ['nullable', 'boolean'],
            'automatic_selected_strategy' => ['nullable', 'in:selected_strategy,forecast_entry,sector_entry,index_entry,auto_entry_wait_5d'],
            'visibility' => ['required', 'in:private,pro_public'],
            'description' => ['nullable', 'string', 'max:1000'],
            'display_icon' => ['nullable', 'in:chart-bar,bolt,shield-check,arrow-path,trophy,rocket-launch'],
            'display_color' => ['nullable', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'automation_enabled' => ['nullable', 'boolean'],
            'portfolio_id' => ['nullable', 'integer'],
            'automation_initial_capital' => ['nullable', 'numeric', 'between:1000,1000000'],
            'automation_trade_cost' => ['nullable', 'numeric', 'between:0,1000'],
            'transaction_email_enabled' => ['nullable', 'boolean'],
            'backtest_run' => ['nullable', 'uuid'],
            'return_to_models' => ['nullable', 'boolean'],
            'models_return_token' => ['nullable', 'string', 'size:40', 'regex:/^[A-Za-z0-9]+$/'],
            'heatmap_selection' => ['nullable', 'string', 'max:4000'],
            'score_min' => ['nullable', 'numeric', 'between:0,10'],
            'confidence_min' => ['nullable', 'numeric', 'between:0,100'],
            'drawdown_max' => ['nullable', 'numeric', 'between:0,100'],
            'risk_max' => ['nullable', 'numeric', 'between:0,100'],
            'profit_per_trade_min' => ['nullable', 'numeric', 'between:-5,15'],
            'median_return_min' => ['nullable', 'numeric', 'between:-5,15'],
            'profit_factor_min' => ['nullable', 'numeric', 'between:0,3'],
            'signal_quality_min' => ['nullable', 'numeric', 'between:0,100'],
            'model_quality_min' => ['nullable', 'numeric', 'between:0,100'],
            'volatility_max' => ['nullable', 'numeric', 'between:0,1000000'],
            'minimum_trades' => ['nullable', 'integer', 'between:0,10000'],
            'sector_score_min' => ['nullable', 'numeric', 'between:-1,10'],
            'predicted_return_min' => ['nullable', 'numeric', 'between:0.5,10'],
            'noise_score_min' => ['nullable', 'numeric', 'between:0,100'],
            'pe_max' => ['nullable', 'numeric', 'between:0,1000000'],
            'dividend_yield_min' => ['nullable', 'numeric', 'between:0,5'],
            'dividend_yield_operator' => ['nullable', 'in:gte,lte'],
            'market_cap_min' => ['nullable', 'numeric', 'between:0,1000000000'],
            'market_cap_group' => ['nullable', 'in:all,small,mid,large'],
            'revenue_growth_min' => ['nullable', 'numeric', 'between:-1000000,1000000'],
            'hit_rate_min' => ['nullable', 'numeric', 'between:0,100'],
        ]);
        $user = $request->user();
        $automationEnabled = $request->boolean('automation_enabled');
        $portfolio = $automationEnabled && $request->filled('portfolio_id')
            ? $user->portfolios()->where('type', 'paper')->where('active', true)
                ->whereKey($request->integer('portfolio_id'))->firstOrFail()
            : null;
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
        if ($editedFilter === null && $existing !== null) {
            $editedFilter = $existing;
        }

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
        $filters['profit_per_trade_min'] = max(-5, min(15, (float) ($filters['profit_per_trade_min'] ?? 0)));
        $filters['median_return_min'] = is_numeric($filters['median_return_min'] ?? null)
            ? max(-5, min(15, (float) $filters['median_return_min']))
            : null;
        $filters['score_min'] = max(0, min(10, (float) ($filters['score_min'] ?? 0)));
        $filters['confidence_min'] = max(0, min(100, (float) ($filters['confidence_min'] ?? 0)));
        $filters['drawdown_max'] = max(0, min(100, (float) ($filters['drawdown_max'] ?? 50)));
        $filters['risk_max'] = max(0, min(100, (float) ($filters['risk_max'] ?? 100)));
        $filters['profit_factor_min'] = max(0, min(3, (float) ($filters['profit_factor_min'] ?? 0)));
        $filters['minimum_trades'] = max(0, min(10000, (int) ($filters['minimum_trades'] ?? 0)));
        $filters['predicted_return_min'] = max(.5, min(10, (float) ($filters['predicted_return_min'] ?? .5)));
        $filters['display_icon'] = (string) ($validated['display_icon'] ?? data_get($editedFilter?->filters, 'display_icon', 'chart-bar'));
        $filters['display_color'] = strtoupper((string) ($validated['display_color'] ?? data_get($editedFilter?->filters, 'display_color', '#22D3EE')));
        $servingModelConfigurations = data_get($editedFilter?->filters, 'serving_model_configurations', []);
        if (is_array($servingModelConfigurations) && $servingModelConfigurations !== []) {
            // Model configurations originate from the Serving release and are
            // intentionally not editable in the legacy filter form. Preserve
            // them while the user changes the surrounding strategy rules.
            $filters['serving_model_configurations'] = $servingModelConfigurations;
        }
        if (! empty($validated['backtest_run'])) {
            $sourceRun = DB::table('backtest_runs')->where('public_id', $validated['backtest_run'])
                ->whereRaw("(settings->>'initiated_by_user_id')::bigint = ?", [$user->id])
                ->whereIn('status', ['completed', 'completed_with_errors'])->first(['settings']);
            $sourceSettings = is_string($sourceRun?->settings) ? (json_decode($sourceRun->settings, true) ?: []) : [];
            $runServingConfigurations = data_get($sourceSettings, 'selection_filters.serving_model_configurations', []);
            if (is_array($runServingConfigurations) && $runServingConfigurations !== []) {
                // New strategies created from /setup/models inherit the exact
                // immutable model selection used by their completed backtest.
                $filters['serving_model_configurations'] = $runServingConfigurations;
            }
            if (data_get($sourceSettings, 'selection_filters.automatic_optimization', false)) {
                $filters['entry_wait_5d_enabled'] = (int) (bool) data_get($sourceSettings, 'selection_filters.entry_wait_5d_enabled', false);
                $filters['optimized_backtest_run'] = $validated['backtest_run'];
            }
        }
        $filters['entry_strategy'] = in_array($filters['entry_strategy'] ?? null, ['direct_buy', 'wait_5d', 'forecast_score_rotation_5d'], true)
            ? $filters['entry_strategy']
            : 'direct_buy';
        $filters['entry_risk_style'] = in_array($filters['entry_risk_style'] ?? null, ['conservative', 'balanced', 'chance'], true)
            ? $filters['entry_risk_style']
            : 'balanced';
        $filters['automatic_selected_strategy'] = in_array($filters['automatic_selected_strategy'] ?? null, [
            'selected_strategy', 'forecast_entry', 'sector_entry', 'index_entry', 'auto_entry_wait_5d',
        ], true) ? $filters['automatic_selected_strategy'] : '';
        foreach (['sector_score_rotation', 'index_score_rotation', 'automatic_strategy_comparison', 'forecast_score_rotation_5d_enabled', 'entry_wait_5d_enabled', 'dynamic_capital_weighting'] as $booleanFilter) {
            $filters[$booleanFilter] = $request->boolean($booleanFilter) ? 1 : 0;
        }
        $filters['forecast_score_rotation_5d_enabled'] = $filters['entry_strategy'] === 'forecast_score_rotation_5d' ? 1 : 0;
        $filters['entry_wait_5d_enabled'] = $filters['entry_strategy'] === 'wait_5d' ? 1 : 0;
        $filters['position_factor'] = max(1, (int) ($filters['position_factor'] ?? 1));
        $filters['max_positions'] = max(1, min(50, (int) ($filters['max_positions'] ?? 5)));
        $filters['position_factor'] = min($filters['position_factor'], $filters['max_positions']);
        $filters['initial_capital'] = round(max(1000, min(1000000, (float) ($filters['initial_capital'] ?? 10000))), 2);
        $filters['trade_cost'] = round(max(0, min(1000, (float) ($filters['trade_cost'] ?? 10))), 2);
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
        if ($automationEnabled) {
            $initialCapital = round((float) ($validated['automation_initial_capital'] ?? $filters['initial_capital']), 2);
            $tradeCost = round((float) ($validated['automation_trade_cost'] ?? $filters['trade_cost']), 2);
            $filters['initial_capital'] = $initialCapital;
            $filters['trade_cost'] = $tradeCost;
            $savedFilter->forceFill(['filters' => $filters])->save();
            if ($portfolio === null) {
                $baseName = trim($validated['name']).' Depot';
                $portfolioName = $baseName;
                $suffix = 2;
                while ($user->portfolios()->whereRaw('LOWER(name) = ?', [mb_strtolower($portfolioName)])->exists()) {
                    $portfolioName = $baseName.' '.$suffix++;
                }
                $isFirst = ! $user->portfolios()->where('active', true)->exists();
                $portfolio = $user->portfolios()->create([
                    'name' => $portfolioName,
                    'type' => 'paper',
                    'currency' => 'EUR',
                    'description' => __('Automatisches Depot für :strategy', ['strategy' => $savedFilter->name]),
                    'is_default' => $isFirst,
                    'active' => true,
                    'meta' => ['automation' => ['initial_capital' => $initialCapital, 'trade_cost' => $tradeCost]],
                ]);
                $accountId = DB::table('portfolio_cash_accounts')->insertGetId([
                    'portfolio_id' => $portfolio->id,
                    'currency' => 'EUR',
                    'balance' => $initialCapital,
                    'reserved_balance' => 0,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                DB::table('portfolio_cash_ledger')->insert([
                    'portfolio_cash_account_id' => $accountId,
                    'type' => 'initial_deposit',
                    'amount' => $initialCapital,
                    'balance_after' => $initialCapital,
                    'currency' => 'EUR',
                    'occurred_at' => now(),
                    'meta' => json_encode(['source' => 'automatic_strategy'], JSON_THROW_ON_ERROR),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
            $meta = (array) $portfolio->meta;
            data_set($meta, 'automation.initial_capital', $initialCapital);
            data_set($meta, 'automation.trade_cost', $tradeCost);
            data_set($meta, 'automation.live_enabled', true);
            data_set($meta, 'automation.transaction_email_enabled', $request->boolean('transaction_email_enabled'));
            data_set($meta, 'automation.label', 'Strategie');
            data_set($meta, 'automation.activated_at', now()->toIso8601String());
            $portfolio->forceFill(['meta' => $meta])->save();

            DB::table('portfolio_strategy_assignments')->where('saved_prediction_filter_id', $savedFilter->id)->delete();
            DB::table('portfolio_strategy_assignments')->insert([
                'portfolio_id' => $portfolio->id,
                'saved_prediction_filter_id' => $savedFilter->id,
                'enabled' => true,
                'priority' => 10,
                'capital_weight' => 1,
                'settings' => json_encode(['source' => 'automatic_optimization'], JSON_THROW_ON_ERROR),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $savedFilter->forceFill(['automatic_portfolio_enabled' => true])->save();
        }
        $request->session()->put('setup_filter_state', $filters);

        if ($request->boolean('return_to_models')) {
            $returnParameters = ! empty($validated['models_return_token'])
                ? ['restore_selection' => $validated['models_return_token']]
                : [];

            return redirect()->route('setup.models', $returnParameters)
                ->with('status', __('Strategie gespeichert. Du bist zurück in der Modellübersicht.'));
        }

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
            } elseif (is_array($value)) {
                $value = array_values($value);
            } elseif (in_array($key, ['sector_score_rotation', 'index_score_rotation', 'max_positions', 'position_factor', 'dynamic_capital_weighting'], true)) {
                $value = (int) $value;
            } else {
                $value = (string) $value;
            }

            return [$key => $value];
        })->all();

        return hash('sha256', json_encode($normalized, JSON_THROW_ON_ERROR));
    }
}
