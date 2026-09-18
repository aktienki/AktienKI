<?php

namespace App\Console\Commands;

use App\Services\TwelveDataCorporateEventImporter;
use Illuminate\Console\Command;

/**
 * Per-symbol alternative to events:sync-twelve-data. That command's global
 * earnings_calendar call hard-caps at ~1200 records, so for a wide window
 * our universe only randomly appears in the page - stocks can end up with
 * multi-quarter gaps (see the missing COST 2025/2026 reports found while
 * investigating the pattern-analysis tab). This queries Twelve Data's
 * per-symbol /earnings endpoint once per instrument instead, at the cost of
 * one request per stock (~250ms apart, well under the plan's rate limit).
 */
class SyncTwelveDataEarningsPerSymbol extends Command
{
    protected $signature = 'events:sync-twelve-data-per-symbol
        {--outputsize=20 : How many past reports Twelve Data returns per symbol}
        {--sleep-ms=250 : Milliseconds to sleep between symbols}
        {--limit= : Only process the first N instruments (for testing)}';

    protected $description = 'Synchronize Twelve Data earnings events per instrument (complete history, one API call per stock)';

    public function handle(TwelveDataCorporateEventImporter $importer): int
    {
        $limit = $this->option('limit') !== null ? (int) $this->option('limit') : null;

        $result = $importer->syncEarningsPerInstrument(
            outputsize: (int) $this->option('outputsize'),
            sleepMs: (int) $this->option('sleep-ms'),
            limit: $limit,
        );

        $this->info("Aktien abgefragt: {$result['instruments']}, Termine übernommen: {$result['matched']} von {$result['received']} (verworfen als unplausibel: {$result['filtered_out']}).");

        if ($result['failed'] !== []) {
            $this->warn('Fehlgeschlagen ('.count($result['failed']).'): '.implode(', ', array_slice($result['failed'], 0, 30)).(count($result['failed']) > 30 ? ', ...' : ''));
        }

        return self::SUCCESS;
    }
}
