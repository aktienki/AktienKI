<?php

namespace App\Console\Commands;

use App\Services\TwelveDataCorporateEventImporter;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Throwable;

class SyncTwelveDataCorporateEvents extends Command
{
    protected $signature = 'events:sync-twelve-data {--days-back=100} {--days-forward=90}';
    protected $description = 'Synchronize Twelve Data earnings events for active German-tradeable universe stocks';

    public function handle(TwelveDataCorporateEventImporter $importer): int
    {
        $from = CarbonImmutable::today()->subDays(max(0, (int) $this->option('days-back')));
        $until = CarbonImmutable::today()->addDays(max(1, (int) $this->option('days-forward')));
        try {
            $result = $importer->syncEarnings($from, $until);
            $this->info("Quartalstermine synchronisiert: {$result['matched']} Universumsaktien, {$result['ignored']} externe Einträge ignoriert.");
            return self::SUCCESS;
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());
            return self::FAILURE;
        }
    }
}
