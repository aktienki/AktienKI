<?php

namespace App\Console\Commands;

use App\Services\TradeOpportunityService;
use Illuminate\Console\Command;

final class PurgeTradeOpportunities extends Command
{
    protected $signature = 'opportunities:purge';
    protected $description = 'Löscht abgelaufene persönliche Handelschancen endgültig';

    public function handle(TradeOpportunityService $service): int
    {
        $this->info($service->purgeExpired().' abgelaufene Chancen gelöscht.');
        return self::SUCCESS;
    }
}
