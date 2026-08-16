<?php

namespace App\Http\Controllers;

use App\Models\SavedPredictionFilter;
use App\Enums\PlanLevel;
use App\Services\PlanAccessService;
use App\Services\SavedFilterLimitService;
use App\Services\YahooIndexService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

final class SavedPredictionFilterController extends Controller
{
    public const FILTER_KEYS = [
        'q', 'country', 'exchange', 'sector', 'ai_type', 'model', 'quality_tier', 'signal',
        'score_min', 'confidence_min', 'drawdown_max', 'profit_per_trade_min', 'volatility_max', 'minimum_trades',
        'predicted_return_min',
        'pe_max', 'dividend_yield_min', 'market_cap_min', 'revenue_growth_min', 'hit_rate_min',
        'gate_mode', 'sector_score_rotation', 'index_score_rotation', 'entry_strategy', 'entry_risk_style', 'automatic_strategy_comparison', 'automatic_selected_strategy', 'forecast_score_rotation_5d_enabled', 'strategy_priority', 'initial_capital', 'trade_cost',
        'max_positions', 'position_factor', 'exit_strategy',
        'fixed_20d_exit_enabled', 'dynamic_horizon_exit_enabled', 'support_stop_enabled', 'resistance_trailing_stop_enabled',
        'entry_wait_5d_enabled', 'signal_change_exit_enabled',
        'automatic_optimization', 'optimization_goal',
    ];

    public const FILTER_DEFAULTS = [
        'q' => '', 'country' => '', 'exchange' => '', 'sector' => '', 'ai_type' => '',
        'model' => '', 'quality_tier' => '', 'signal' => '',
        'score_min' => 0, 'confidence_min' => 0, 'drawdown_max' => 50,
        'profit_per_trade_min' => 0, 'volatility_max' => 100, 'minimum_trades' => 0,
        'predicted_return_min' => -50,
        'pe_max' => 100, 'dividend_yield_min' => 0, 'market_cap_min' => 0,
        'revenue_growth_min' => -50, 'hit_rate_min' => 0,
        'gate_mode' => 'system',
        'sector_score_rotation' => 0, 'index_score_rotation' => 0, 'entry_strategy' => 'direct_buy', 'entry_risk_style' => 'balanced', 'automatic_strategy_comparison' => 0, 'automatic_selected_strategy' => '', 'forecast_score_rotation_5d_enabled' => 0, 'strategy_priority' => 'rotation_first',
        'initial_capital' => 10000, 'trade_cost' => 10, 'max_positions' => 5, 'position_factor' => 1,
        'exit_strategy' => 'fixed_20d', 'fixed_20d_exit_enabled' => 0, 'dynamic_horizon_exit_enabled' => 0,
        'support_stop_enabled' => 0, 'resistance_trailing_stop_enabled' => 0,
        'entry_wait_5d_enabled' => 0, 'signal_change_exit_enabled' => 0,
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
            'automatic_selected_strategy' => ['nullable', 'in:selected_strategy,forecast_entry,sector_entry,index_entry,buy_and_hold,auto_exit_fixed_20d,auto_exit_dynamic_horizon,auto_exit_support_stop,auto_exit_resistance_trailing,auto_exit_signal_change,auto_entry_wait_5d'],
            'exit_strategy' => ['required', 'in:fixed_20d,signal_change,buy_and_hold'],
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
        $filters['profit_per_trade_min'] = max(0, min(10, (float) ($filters['profit_per_trade_min'] ?? 0)));
        $filters['display_icon'] = (string) ($validated['display_icon'] ?? data_get($editedFilter?->filters, 'display_icon', 'chart-bar'));
        $filters['display_color'] = strtoupper((string) ($validated['display_color'] ?? data_get($editedFilter?->filters, 'display_color', '#22D3EE')));
        if (! empty($validated['backtest_run'])) {
            $optimizedRun = DB::table('backtest_runs')->where('public_id', $validated['backtest_run'])
                ->whereRaw("(settings->>'initiated_by_user_id')::bigint = ?", [$user->id])
                ->whereIn('status', ['completed', 'completed_with_errors'])->first(['settings']);
            $optimizedSettings = is_string($optimizedRun?->settings) ? (json_decode($optimizedRun->settings, true) ?: []) : [];
            if (data_get($optimizedSettings, 'selection_filters.automatic_optimization', false)) {
                foreach (['fixed_20d_exit_enabled', 'dynamic_horizon_exit_enabled', 'support_stop_enabled', 'resistance_trailing_stop_enabled', 'entry_wait_5d_enabled', 'signal_change_exit_enabled'] as $rule) {
                    $filters[$rule] = (int) (bool) data_get($optimizedSettings, 'selection_filters.'.$rule, false);
                }
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
            'selected_strategy', 'forecast_entry', 'sector_entry', 'index_entry', 'buy_and_hold',
            'auto_exit_fixed_20d', 'auto_exit_dynamic_horizon', 'auto_exit_support_stop',
            'auto_exit_resistance_trailing', 'auto_exit_signal_change', 'auto_entry_wait_5d',
        ], true) ? $filters['automatic_selected_strategy'] : '';
        $filters['exit_strategy'] = in_array($filters['exit_strategy'] ?? null, ['fixed_20d', 'signal_change', 'buy_and_hold'], true)
            ? $filters['exit_strategy']
            : 'fixed_20d';
        foreach (['sector_score_rotation', 'index_score_rotation', 'automatic_strategy_comparison', 'forecast_score_rotation_5d_enabled', 'fixed_20d_exit_enabled', 'dynamic_horizon_exit_enabled', 'support_stop_enabled', 'resistance_trailing_stop_enabled', 'entry_wait_5d_enabled', 'signal_change_exit_enabled'] as $booleanFilter) {
            $filters[$booleanFilter] = $request->boolean($booleanFilter) ? 1 : 0;
        }
        $filters['forecast_score_rotation_5d_enabled'] = $filters['entry_strategy'] === 'forecast_score_rotation_5d' ? 1 : 0;
        $filters['entry_wait_5d_enabled'] = $filters['entry_strategy'] === 'wait_5d' ? 1 : 0;
        $filters['signal_change_exit_enabled'] = $filters['exit_strategy'] === 'signal_change' ? 1 : 0;
        foreach (['fixed_20d_exit_enabled', 'dynamic_horizon_exit_enabled', 'support_stop_enabled', 'resistance_trailing_stop_enabled'] as $disabledExitRule) {
            $filters[$disabledExitRule] = 0;
        }
        $filters['position_factor'] = max(1, (int) ($filters['position_factor'] ?? 1));
        $filters['max_positions'] = max(1, min(50, (int) ($filters['max_positions'] ?? 5)));
        $filters['position_factor'] = min($filters['position_factor'], $filters['max_positions']);
        $filters['initial_capital'] = round(max(1000, min(1000000, (float) ($filters['initial_capital'] ?? 10000))), 2);
        $filters['trade_cost'] = round(max(0, min(1000, (float) ($filters['trade_cost'] ?? 10))), 2);
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
