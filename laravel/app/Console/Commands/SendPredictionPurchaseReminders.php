<?php

namespace App\Console\Commands;

use App\Models\PredictionPurchaseReminder;
use App\Models\User;
use App\Notifications\PredictionPurchaseReminderNotification;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SendPredictionPurchaseReminders extends Command
{
    protected $signature = 'predictions:send-purchase-reminders';
    protected $description = 'Sendet fällige Erinnerungen zu Prognosekäufen und Kaufinteresse.';

    public function handle(): int
    {
        $sent = 0;
        PredictionPurchaseReminder::where('status', 'active')->whereDate('remind_on', '<=', today())->each(function ($reminder) use (&$sent): void {
            $user = User::find($reminder->user_id);
            $instrument = DB::table('instruments')->find($reminder->instrument_id);
            if (! $user || ! $instrument) return;
            $latest = DB::table('predictions')->where('instrument_id', $reminder->instrument_id)->latest('id')->first(['current_price', 'signal']);
            $price = DB::table('current_stock_quotes')->where('instrument_id', $reminder->instrument_id)->where('status', 'current')->latest('id')->value('price') ?? $latest?->current_price;
            if (! is_numeric($price)) return;
            $user->notifyNow(new PredictionPurchaseReminderNotification($reminder, $instrument, (float) $price, strtoupper((string) ($latest?->signal ?: 'HOLD'))));
            $reminder->update(['status' => 'sent', 'notified_at' => now()]);
            $sent++;
        });
        $this->info("{$sent} Erinnerungen versendet.");
        return self::SUCCESS;
    }
}
