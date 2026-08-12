<?php

namespace App\Console\Commands;

use App\Models\EntrySignalAlert;
use App\Models\Prediction;
use App\Notifications\EntrySignalBuyNotification;
use App\Services\PersonalizedSignalService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SendEntrySignalAlerts extends Command
{
    protected $signature = 'signals:send-entry-alerts';
    protected $description = 'Sendet einmalige Pro-Einstiegsalarme bei ABWARTEN zu BUY.';

    public function handle(PersonalizedSignalService $signals): int
    {
        $sent = 0;
        EntrySignalAlert::query()->where('status', 'active')->orderBy('id')->each(function (EntrySignalAlert $alert) use ($signals, &$sent): void {
            $user = DB::table('users')->where('id', $alert->user_id)->first();
            if (! $user) return;
            $userModel = \App\Models\User::find($alert->user_id);
            $signalSql = $signals->sql('prediction', $userModel);
            $row = DB::table('predictions as prediction')->where('instrument_id', $alert->instrument_id)
                ->where('id', '>', (int) $alert->source_prediction_id)->select('prediction.id')->selectRaw("{$signalSql} AS personalized_signal")
                ->orderByDesc('prediction_time')->orderByDesc('id')->first();
            if (! $row) return;
            $currentSignal = strtoupper((string) $row->personalized_signal);
            $allowedSignals = $alert->notification_mode === 'wait_or_buy' ? ['WAIT', 'BUY'] : ['BUY'];
            // HOLD and SELL are deliberately silent. The active alert remains
            // available for a later positive daily prediction.
            if (! in_array($currentSignal, $allowedSignals, true)) return;
            $prediction = Prediction::with('instrument')->find($row->id);
            if (! $prediction) return;
            $userModel->notifyNow(new EntrySignalBuyNotification($prediction, $currentSignal));
            $alert->update(['status' => 'sent', 'triggered_prediction_id' => $prediction->id, 'triggered_at' => now(), 'notified_at' => now()]);
            $sent++;
        });
        $this->info("{$sent} Einstiegsalarme versendet.");

        return self::SUCCESS;
    }
}
