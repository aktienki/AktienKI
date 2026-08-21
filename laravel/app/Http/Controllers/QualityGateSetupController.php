<?php

namespace App\Http\Controllers;

use App\Models\UserQualityGateProfile;
use App\Services\QualityGateAccessService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class QualityGateSetupController extends Controller
{
    public const DEFAULTS = [
        'minimum_tier' => 'strong',
        'score_min' => 6.5,
        'confidence_min' => 65,
        'risk_max' => 40,
        'predicted_return_min' => 2,
        'drawdown_max' => 20,
        'profit_factor_min' => 1.1,
        'hit_rate_min' => 52,
        'minimum_trades' => 20,
        'positive_prediction_required' => true,
        'ensemble_veto_required' => true,
    ];

    public function edit(Request $request, QualityGateAccessService $access): View
    {
        $profile = UserQualityGateProfile::query()->where('user_id', $request->user()->id)->first();
        return view('setup.quality-gate', [
            'profile' => $profile,
            'rules' => array_merge(self::DEFAULTS, (array) $profile?->rules),
            'canConfigure' => $access->allowed($request->user()),
        ]);
    }

    public function update(Request $request, QualityGateAccessService $access): RedirectResponse
    {
        abort_unless($access->allowed($request->user()), 403);
        [$validated, $rules] = $this->validatedConfiguration($request);
        $this->save($request, $validated, $rules);

        return back()->with('status', __('Quality Gate gespeichert.'));
    }

    public function backtest(Request $request, QualityGateAccessService $access): RedirectResponse
    {
        abort_unless($access->allowed($request->user()), 403);
        [$validated, $rules] = $this->validatedConfiguration($request);
        $this->save($request, $validated, $rules);

        return redirect()->route('setup.filter', [
            'quality_tier' => $rules['minimum_tier'],
            'score_min' => $rules['score_min'],
            'confidence_min' => $rules['confidence_min'],
            'drawdown_max' => $rules['drawdown_max'],
            'profit_factor_min' => $rules['profit_factor_min'],
            'hit_rate_min' => $rules['hit_rate_min'],
            'risk_max' => $rules['risk_max'],
            'predicted_return_min' => $rules['predicted_return_min'],
            'minimum_trades' => $rules['minimum_trades'],
            'positive_prediction_required' => $rules['positive_prediction_required'] ? 1 : 0,
            'ensemble_veto_required' => $rules['ensemble_veto_required'] ? 1 : 0,
            'quality_gate_profile' => 1,
        ])->with('status', __('Quality-Gate-Regeln wurden in den Strategietester übernommen.'));
    }

    private function validatedConfiguration(Request $request): array
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'minimum_tier' => ['required', 'in:test,solid,strong,top'],
            'score_min' => ['required', 'numeric', 'between:0,10'],
            'confidence_min' => ['required', 'numeric', 'between:0,100'],
            'risk_max' => ['required', 'numeric', 'between:0,100'],
            'predicted_return_min' => ['required', 'numeric', 'between:-50,100'],
            'drawdown_max' => ['required', 'numeric', 'between:0,100'],
            'profit_factor_min' => ['required', 'numeric', 'between:0,3'],
            'hit_rate_min' => ['required', 'numeric', 'between:0,100'],
            'minimum_trades' => ['required', 'integer', 'between:1,10000'],
        ]);
        $rules = collect(self::DEFAULTS)->mapWithKeys(fn ($default, string $key): array => [$key => match ($key) {
            'positive_prediction_required', 'ensemble_veto_required' => $request->boolean($key),
            'minimum_tier' => $validated[$key],
            'minimum_trades' => (int) $validated[$key],
            default => (float) $validated[$key],
        }])->all();

        return [$validated, $rules];
    }

    private function save(Request $request, array $validated, array $rules): void
    {
        UserQualityGateProfile::query()->updateOrCreate(
            ['user_id' => $request->user()->id],
            [
                'tariff_plan_id' => $request->user()->tariff_plan_id,
                'name' => trim($validated['name']),
                'is_active' => $request->boolean('is_active'),
                'rules' => $rules,
                'access_bound_at' => now(),
            ],
        );
    }
}
