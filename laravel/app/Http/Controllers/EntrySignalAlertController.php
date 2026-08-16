<?php

namespace App\Http\Controllers;

use App\Enums\PlanLevel;
use App\Models\EntrySignalAlert;
use App\Models\Instrument;
use App\Models\Prediction;
use App\Services\PlanAccessService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class EntrySignalAlertController extends Controller
{
    public function store(Request $request, Instrument $instrument, PlanAccessService $plans): RedirectResponse
    {
        abort_unless($plans->allowsTariff($request->user(), PlanLevel::Pro), 403);
        $validated = $request->validate([
            'prediction_id' => ['required', 'integer'],
            'notification_mode' => ['required', 'in:buy_only,wait_or_buy'],
        ]);
        $prediction = Prediction::query()->whereKey((int) $validated['prediction_id'])->where('instrument_id', $instrument->id)->firstOrFail();
        EntrySignalAlert::query()->updateOrCreate(
            ['user_id' => $request->user()->id, 'instrument_id' => $instrument->id, 'status' => 'active'],
            ['source_prediction_id' => $prediction->id, 'initial_signal' => 'WAIT', 'notification_mode' => $validated['notification_mode']]
        );

        return back()->with('status', __('Einstiegsalarm aktiviert. Du erhältst eine E-Mail, sobald der Status auf BUY wechselt.'));
    }

    public function disable(Request $request, EntrySignalAlert $alert): RedirectResponse
    {
        abort_unless((int) $alert->user_id === (int) $request->user()->id, 404);
        $alert->update(['status' => 'disabled']);

        return back()->with('status', __('Signalalarm wurde deaktiviert.'));
    }

    public function enable(Request $request, EntrySignalAlert $alert): RedirectResponse
    {
        abort_unless((int) $alert->user_id === (int) $request->user()->id, 404);
        $alert->update(['status' => 'active', 'notified_at' => null]);

        return back()->with('status', __('Signalalarm wurde aktiviert.'));
    }

    public function destroy(Request $request, EntrySignalAlert $alert): RedirectResponse
    {
        abort_unless((int) $alert->user_id === (int) $request->user()->id, 404);
        $alert->delete();

        return back()->with('status', __('Signalalarm wurde gelöscht.'));
    }
}
