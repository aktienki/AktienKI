<?php

namespace App\Console\Commands;

use App\Models\PredictionPurchaseReminder;
use App\Models\User;
use App\Notifications\PredictionPurchaseReminderNotification;
use App\Services\ServingReadService;
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
            if (! $user || ! $instrument) {
                return;
            }
            $stock = app(ServingReadService::class)->stock($instrument->symbol);
            if (! $stock) {
                return;
            }
            $latest = $stock->latest_prediction;
            $price = $latest?->current_price;
            if (! is_numeric($price)) {
                return;
            }
            $instrument->currency = $stock->currency;
            $instrument->name = $stock->name;
            $instrument->sector = $stock->sector_code;
            $user->notifyNow(new PredictionPurchaseReminderNotification($reminder, $instrument, (float) $price, strtoupper((string) ($latest?->signal ?: $stock->recommended_signal ?: 'HOLD'))));
            $reminder->update(['status' => 'sent', 'notified_at' => now()]);
            $sent++;
        });
        $deleted = PredictionPurchaseReminder::query()
            ->where('status', 'sent')
            ->whereDate('remind_on', '<', today())
            ->delete();

        $this->info("{$sent} Erinnerungen versendet, {$deleted} abgelaufene Erinnerungen gelöscht.");

        return self::SUCCESS;
    }
}
