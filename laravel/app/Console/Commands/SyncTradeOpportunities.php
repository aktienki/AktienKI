<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\TradeOpportunityService;
use Illuminate\Console\Command;

final class SyncTradeOpportunities extends Command
{
    protected $signature = 'opportunities:sync {--user= : Nur eine Benutzer-ID synchronisieren}';
    protected $description = 'Synchronisiert persönliche Pro-Handelschancen aus aktuellen Prognosen';

    public function handle(TradeOpportunityService $service): int
    {
        $service->purgeExpired();
        $query = User::query()->orderBy('id');
        if ($this->option('user')) $query->whereKey((int) $this->option('user'));
        $users = 0; $opportunities = 0;
        $query->chunkById(100, function ($items) use ($service, &$users, &$opportunities): void {
            foreach ($items as $user) {
                $count = $service->syncForUser($user);
                if ($count > 0) { $users++; $opportunities += $count; }
            }
        });
        $this->info("CHANCE-Synchronisierung: {$opportunities} Einträge für {$users} Nutzer.");
        return self::SUCCESS;
    }
}
