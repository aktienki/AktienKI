<?php

namespace App\Http\Controllers;

use App\Models\SmartSelectionLabel;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class SmartSelectionLabelController extends Controller
{
    public function index(Request $request): \Illuminate\Contracts\View\View
    {
        $labels = SmartSelectionLabel::query()
            ->where('user_id', $request->user()->id)
            ->where('tariff_plan_id', $request->user()->tariff_plan_id)
            ->orderByDesc('is_active')->orderBy('name')->get();

        return view('setup.label-manager', compact('labels'));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'color' => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'icon' => ['required', 'in:sparkles,bolt,trophy,shield-check,chart-bar,rocket-launch'],
            'backtest_run' => ['nullable', 'uuid'],
            'score_min' => ['nullable', 'numeric', 'between:0,10'],
            'confidence_min' => ['nullable', 'numeric', 'between:0,100'],
            'drawdown_max' => ['nullable', 'numeric', 'between:0,100'],
            'profit_factor_min' => ['nullable', 'numeric', 'between:0,10'],
            'volatility_max' => ['nullable', 'numeric', 'between:0,1000000'],
            'predicted_return_min' => ['nullable', 'numeric', 'between:-50,100'],
            'email_notification_enabled' => ['nullable', 'boolean'],
        ]);

        $backtestRunId = null;
        if (! empty($validated['backtest_run'])) {
            $backtestRunId = DB::table('backtest_runs')
                ->where('public_id', $validated['backtest_run'])
                ->whereRaw("settings->>'run_type' = 'user_filter'")
                ->whereRaw("(settings->>'initiated_by_user_id')::bigint = ?", [$request->user()->id])
                ->whereIn('status', ['completed', 'completed_with_errors'])
                ->value('id');
        }

        $criteria = collect([
            'score_min' => 0,
            'confidence_min' => 0,
            'drawdown_max' => 50,
            'profit_factor_min' => 0,
            'volatility_max' => 100,
            'predicted_return_min' => -20,
        ])->mapWithKeys(fn ($default, string $key): array => [
            $key => (float) ($validated[$key] ?? $default),
        ])->all();
        $criteria['email_notification_enabled'] = (bool) ($validated['email_notification_enabled'] ?? false);

        $label = SmartSelectionLabel::query()->updateOrCreate(
            ['user_id' => $request->user()->id, 'name' => trim($validated['name'])],
            [
                'tariff_plan_id' => $request->user()->tariff_plan_id,
                'backtest_run_id' => $backtestRunId,
                'category' => 'smart_selection',
                'color' => strtolower($validated['color']),
                'icon' => $validated['icon'],
                'criteria' => $criteria,
                'is_active' => true,
            ],
        );

        return redirect()->route('predictions.index')
            ->with('status', __('Smart-Selection-Label „:name“ gespeichert.', ['name' => $label->name]));
    }

    public function update(Request $request, SmartSelectionLabel $label): RedirectResponse
    {
        abort_unless((int) $label->user_id === (int) $request->user()->id, 403);
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'color' => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'icon' => ['required', 'in:sparkles,bolt,trophy,shield-check,chart-bar,rocket-launch'],
            'email_notification_enabled' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
        ]);
        $criteria = (array) $label->criteria;
        $criteria['email_notification_enabled'] = (bool) ($validated['email_notification_enabled'] ?? false);
        $label->update([
            'name' => trim($validated['name']),
            'color' => strtolower($validated['color']),
            'icon' => $validated['icon'],
            'criteria' => $criteria,
            'is_active' => (bool) ($validated['is_active'] ?? false),
        ]);

        return back()->with('status', __('Label aktualisiert.'));
    }

    public function destroy(Request $request, SmartSelectionLabel $label): RedirectResponse
    {
        abort_unless((int) $label->user_id === (int) $request->user()->id, 403);
        $label->delete();
        return back()->with('status', __('Label gelöscht.'));
    }
}
