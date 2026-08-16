<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

class StartPostBetaTrials extends Command
{
    protected $signature = 'beta:start-post-phase-trials';
    protected $description = 'Startet das kostenlose Pro-Jahr für Betatester nach Ende der Beta.';

    public function handle(): int
    {
        if (! (bool) config('aktienki.beta.phase_ended', false)) {
            $this->error('Die Beta-Phase ist laut AKTIENKI_BETA_PHASE_ENDED noch nicht beendet.');
            return self::FAILURE;
        }

        $proPlanId = \Illuminate\Support\Facades\DB::table('tariff_plans')->where('code', 'pro')->value('id');
        if (! $proPlanId) {
            $this->error('Der Pro-Tarif ist nicht in tariff_plans vorhanden.');
            return self::FAILURE;
        }

        $started = 0;
        User::query()
            ->where('is_beta_tester', true)
            ->where('beta_access_exempt', false)
            ->whereNull('tariff_ends_at')
            ->chunkById(100, function ($users) use (&$started, $proPlanId): void {
                foreach ($users as $user) {
                    $startsAt = now();
                    $endsAt = $startsAt->copy()->addYear();
                    $metadata = (array) ($user->subscription_metadata ?? []);
                    $metadata['trial_starts_after_beta'] = false;
                    $metadata['trial_started_at'] = $startsAt->toIso8601String();
                    $metadata['trial_ends_at'] = $endsAt->toIso8601String();
                    $metadata['trial_months'] = 12;
                    $user->forceFill([
                        'tariff_plan_id' => $proPlanId,
                        'tariff_status' => 'trialing',
                        'tariff_started_at' => $startsAt,
                        'tariff_ends_at' => $endsAt,
                        'subscription_metadata' => $metadata,
                    ])->save();
                    $started++;
                }
            });

        $this->info("Pro-Testphasen gestartet: {$started}");
        return self::SUCCESS;
    }
}
