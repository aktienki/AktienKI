<?php

namespace App\Http\Controllers;

use App\Models\CalendarEventReminder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

final class CalendarEventReminderController extends Controller
{
    public function toggle(Request $request): JsonResponse
    {
        $data = $request->validate([
            'event_type' => ['required', 'in:earnings,sell'],
            'reference_id' => ['required', 'integer'],
            'instrument_id' => ['required', 'integer', 'exists:instruments,id'],
            'event_date' => ['required', 'date'],
        ]);

        $user = $request->user();
        $eventDate = Carbon::parse($data['event_date']);
        // Earnings reminders fire the day before; sell reminders fire on
        // the exit day itself.
        $sendAt = $data['event_type'] === 'earnings' ? $eventDate->copy()->subDay() : $eventDate->copy();

        $reminder = CalendarEventReminder::query()->firstOrNew([
            'user_id' => $user->id,
            'event_type' => $data['event_type'],
            'reference_id' => $data['reference_id'],
        ]);

        if ($reminder->exists) {
            $reminder->enabled = ! $reminder->enabled;
        } else {
            $reminder->instrument_id = $data['instrument_id'];
            $reminder->event_date = $eventDate->toDateString();
            $reminder->send_at = $sendAt->toDateString();
            $reminder->enabled = true;
        }
        $reminder->save();

        return response()->json(['enabled' => $reminder->enabled]);
    }
}
