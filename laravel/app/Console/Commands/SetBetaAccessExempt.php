<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

class SetBetaAccessExempt extends Command
{
    protected $signature = 'beta:set-exempt {user : Nutzer-ID oder E-Mail-Adresse} {--revoke : Dauerhafte Freistellung entfernen}';
    protected $description = 'Markiert einen Beta-Nutzer als dauerhaft kostenfrei oder entfernt diese Markierung.';

    public function handle(): int
    {
        $identifier = (string) $this->argument('user');
        $user = User::query()
            ->when(ctype_digit($identifier), fn ($query) => $query->whereKey((int) $identifier))
            ->when(! ctype_digit($identifier), fn ($query) => $query->where('email', $identifier))
            ->first();

        if (! $user) {
            $this->error('Nutzer nicht gefunden.');
            return self::FAILURE;
        }

        $exempt = ! $this->option('revoke');
        $user->forceFill([
            'is_beta_tester' => true,
            'beta_access_exempt' => $exempt,
        ])->save();

        $this->info($exempt
            ? "Dauerhafte Beta-Freistellung für {$user->email} aktiviert."
            : "Dauerhafte Beta-Freistellung für {$user->email} entfernt.");

        return self::SUCCESS;
    }
}
