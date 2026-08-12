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
}
