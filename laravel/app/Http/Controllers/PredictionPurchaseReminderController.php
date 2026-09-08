<?php

namespace App\Http\Controllers;

use App\Models\PredictionPurchaseReminder;
use App\Services\ServingReadService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class PredictionPurchaseReminderController extends Controller
{
    public function store(Request $request, int $instrument): RedirectResponse
    {
        $validated = $request->validate([
            'prediction_id' => ['nullable', 'integer'],
            'horizon_days' => ['required', Rule::in([5, 10, 15, 20, 40])],
            'remind_on' => ['required', 'date', 'after_or_equal:today'],
            'intent' => ['required', Rule::in(['purchased', 'interested'])],
            'purchase_price' => ['required', 'numeric', 'gt:0'],
            'fixed_20d_exit_enabled' => ['nullable', 'boolean'],
            'dynamic_horizon_exit_enabled' => ['nullable', 'boolean'],
            'support_stop_enabled' => ['nullable', 'boolean'],
            'resistance_trailing_stop_enabled' => ['nullable', 'boolean'],
        ]);

        $localInstrument = DB::table('instruments')->find($instrument, ['id', 'symbol']);
        abort_unless($localInstrument, 404);

        $localPrediction = null;
        $servingPrediction = null;
        if (! empty($validated['prediction_id'])) {
            $localPrediction = DB::table('predictions')->where('id', $validated['prediction_id'])
                ->where('instrument_id', $instrument)->first();
        } else {
            $servingStock = app(ServingReadService::class)->stock($localInstrument->symbol);
            abort_unless($servingStock, 404);
            $servingPrediction = collect($servingStock->horizons)
                ->get((int) $validated['horizon_days'])?->prediction;
        }
        abort_unless($localPrediction || $servingPrediction, 404);

        $rules = [
            'fixed_20d' => $request->boolean('fixed_20d_exit_enabled'),
            'dynamic_horizon' => $request->boolean('dynamic_horizon_exit_enabled'),
            'support_stop' => $request->boolean('support_stop_enabled'),
            'resistance_trailing_stop' => $request->boolean('resistance_trailing_stop_enabled'),
        ];

        PredictionPurchaseReminder::updateOrCreate([
            'user_id' => $request->user()->id,
            'instrument_id' => $instrument,
            'horizon_days' => $validated['horizon_days'],
        ], [
            'prediction_id' => $localPrediction?->id,
            'serving_prediction_id' => $servingPrediction?->id,
            'serving_batch_id' => $servingPrediction?->batch_id,
            'intent' => $validated['intent'],
            'purchase_price' => $validated['purchase_price'],
            'purchased_at' => now(),
            'remind_on' => $validated['remind_on'],
            'status' => 'active',
            'notified_at' => null,
            'exit_rules' => $validated['intent'] === 'purchased' ? $rules : [],
            'exit_state' => $validated['intent'] === 'purchased' ? 'monitoring' : 'scheduled',
        ]);

        return back()->with('status', $validated['intent'] === 'purchased'
            ? __('Kaufauswertung und Exit-Überwachung wurden gespeichert.')
            : __('E-Mail-Erinnerung wurde gespeichert.'));
    }

    public function disable(Request $request, PredictionPurchaseReminder $reminder): RedirectResponse
    {
        $this->authorizeReminder($request, $reminder);
        $reminder->update(['status' => 'disabled']);
        return back()->with('status', __('Kauferinnerung wurde deaktiviert.'));
    }

    public function enable(Request $request, PredictionPurchaseReminder $reminder): RedirectResponse
    {
        $this->authorizeReminder($request, $reminder);
        $reminder->update(['status' => 'active', 'notified_at' => null]);
        return back()->with('status', __('Kauferinnerung wurde aktiviert.'));
    }

    public function reschedule(Request $request, PredictionPurchaseReminder $reminder): RedirectResponse
    {
        $this->authorizeReminder($request, $reminder);
        $validated = $request->validate(['remind_on' => ['required', 'date', 'after_or_equal:today']]);
        $reminder->update(['remind_on' => $validated['remind_on'], 'notified_at' => null]);
        return back()->with('status', __('Termin der Erinnerung wurde verschoben.'));
    }

    public function destroy(Request $request, PredictionPurchaseReminder $reminder): RedirectResponse
    {
        $this->authorizeReminder($request, $reminder);
        $reminder->delete();
        return back()->with('status', __('Kauferinnerung wurde gelöscht.'));
    }

    private function authorizeReminder(Request $request, PredictionPurchaseReminder $reminder): void
    {
        abort_unless((int) $reminder->user_id === (int) $request->user()->id, 404);
    }
}
