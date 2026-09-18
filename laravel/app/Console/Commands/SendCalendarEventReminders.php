<?php

namespace App\Console\Commands;

use App\Models\CalendarEventReminder;
use App\Notifications\CalendarEventReminderNotification;
use Illuminate\Console\Command;
use Throwable;

final class SendCalendarEventReminders extends Command
{
    protected $signature = 'reminders:send-calendar-events';

    protected $description = 'Sends due Quartalszahlen/Verkauf reminders from calendar_event_reminders';

    public function handle(): int
    {
        $due = CalendarEventReminder::query()
            ->with(['user', 'instrument'])
            ->where('enabled', true)
            ->whereNull('sent_at')
            ->where('send_at', '<=', now()->toDateString())
            ->get();

        $sent = 0;
        $failed = 0;
        foreach ($due as $reminder) {
            if (! $reminder->user || ! $reminder->instrument) {
                continue;
            }
            try {
                $reminder->user->notify(new CalendarEventReminderNotification($reminder, $reminder->instrument));
                $reminder->update(['sent_at' => now()]);
                $sent++;
            } catch (Throwable $exception) {
                report($exception);
                $failed++;
            }
        }

        $this->info("Erinnerungen: {$sent} versendet, {$failed} fehlgeschlagen.");

        return self::SUCCESS;
    }
}
