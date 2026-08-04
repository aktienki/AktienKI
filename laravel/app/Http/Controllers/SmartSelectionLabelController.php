<?php

namespace App\Http\Controllers;

use App\Models\SmartSelectionLabel;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class SmartSelectionLabelController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'color' => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'icon' => ['required', 'in:sparkles,bolt,trophy,shield-check,chart-bar,rocket-launch'],
            'backtest_run' => ['nullable', 'uuid'],
            'score_min' => ['nullable', 'numeric', 'between:0,10'],
            'confidence_min' => ['nullable', 'numeric', 'between:0,100'],
            'drawdown_max' => ['nullable', 'numeric', 'between:0,50'],
            'profit_factor_min' => ['nullable', 'numeric', 'between:0,3'],
            'volatility_max' => ['nullable', 'numeric', 'between:0,100'],
            'predicted_return_min' => ['nullable', 'numeric', 'between:-50,100'],
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

        SmartSelectionLabel::query()->updateOrCreate(
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

        return redirect()->route('setup.quality', array_merge($criteria, [
            'backtest_run' => $validated['backtest_run'] ?? null,
        ]))->with('status', __('Smart-Selection-Label gespeichert.'));
    }
}
