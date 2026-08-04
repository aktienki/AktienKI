<?php

namespace App\Http\Controllers;

use App\Models\Prediction;
use App\Models\SavedPredictionFilter;
use App\Notifications\SignalChangedNotification;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class SignalEmailPreviewController
{
    public function __invoke(Request $request): Response
    {
        $prediction = Prediction::query()
            ->with('instrument')
            ->whereHas('instrument', fn ($query) => $query->where('is_active', true))
            ->latest('prediction_time')
            ->firstOrFail();

        $previousSignal = Prediction::query()
            ->where('instrument_id', $prediction->instrument_id)
            ->where('prediction_time', '<', $prediction->prediction_time)
            ->latest('prediction_time')
            ->value('signal') ?: 'HOLD';

        $strategy = $request->user()->savedPredictionFilters()->first()
            ?? new SavedPredictionFilter(['name' => __('Quality Wachstum')]);

        $mail = (new SignalChangedNotification($prediction, $strategy, $previousSignal, 0))
            ->toMail($request->user());

        return new Response((string) $mail->render(), 200, ['Content-Type' => 'text/html; charset=UTF-8']);
    }
}
