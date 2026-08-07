<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Notifications\BetaTrialExpiryReminder;
use Illuminate\Console\Command;

class SendBetaTrialExpiryReminders extends Command
{
    protected $signature = 'beta:send-trial-reminders';
    protected $description = 'Sendet Beta-Nutzern sieben Tage vor Ablauf der Pro-Testphase eine E-Mail.';

    public function handle(): int
    {
        $windowStart = now()->addDays(7)->startOfDay();
        $windowEnd = now()->addDays(8)->startOfDay();
        $sent = 0;

        User::query()
            ->where('is_beta_tester', true)
            ->where('tariff_status', 'trialing')
            ->whereNotNull('tariff_ends_at')
            ->whereBetween('tariff_ends_at', [$windowStart, $windowEnd])
            ->chunkById(100, function ($users) use (&$sent): void {
                foreach ($users as $user) {
                    $meta = (array) ($user->meta ?? []);
                    $sentAt = data_get($meta, 'beta_trial.reminder_sent_at');
                    if ($sentAt) {
                        continue;
                    }

                    $user->notify(new BetaTrialExpiryReminder);
                    data_set($meta, 'beta_trial.reminder_sent_at', now()->toIso8601String());
                    $user->forceFill(['meta' => $meta])->save();
                    $sent++;
                }
            });

        $this->info("Beta-Trial-Erinnerungen versendet: {$sent}");

        return self::SUCCESS;
    }
}
