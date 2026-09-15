<?php

namespace App\Console\Commands;

use App\Services\TwelveDataCorporateEventImporter;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Throwable;

/**
 * One-time (re-runnable) multi-year backfill of Twelve Data's earnings
 * calendar, chunked by month to stay well under the API's per-minute
 * credit budget shared with live price polling. Not scheduled - run
 * manually when a wider history is needed (e.g. for the drift study).
 *
 * TwelveDataCorporateEventImporter::syncEarnings() already upserts by a
 * stable provider_event_key, so re-running any chunk is safe.
 */
class BackfillEarningsHistory extends Command
{
    protected $signature = 'events:backfill-earnings-history
        {--years=5 : How many years back to backfill}
        {--sleep=3 : Seconds to sleep between monthly chunks, to stay under the shared API credit budget}';

    protected $description = 'Backfill years of historical Twelve Data earnings events into corporate_events, one month at a time';

    public function handle(TwelveDataCorporateEventImporter $importer): int
    {
        $years = max(1, (int) $this->option('years'));
        $sleepSeconds = max(0, (int) $this->option('sleep'));

        $until = CarbonImmutable::today();
        $from = $until->subYears($years);

        $chunkStart = $from;
        $totalReceived = 0;
        $totalMatched = 0;
        $failedChunks = [];

        $this->info("Backfilling earnings von {$from->toDateString()} bis {$until->toDateString()}, monatsweise.");

        while ($chunkStart->lt($until)) {
            $chunkEnd = $chunkStart->addMonth()->min($until);

            try {
                $result = $importer->syncEarnings($chunkStart, $chunkEnd);
                $totalReceived += $result['received'];
                $totalMatched += $result['matched'];
                $this->line("  {$chunkStart->toDateString()} bis {$chunkEnd->toDateString()}: {$result['matched']} von {$result['received']} zugeordnet.");
            } catch (Throwable $exception) {
                $failedChunks[] = $chunkStart->toDateString();
                $this->error("  {$chunkStart->toDateString()} bis {$chunkEnd->toDateString()}: {$exception->getMessage()}");
            }

            $chunkStart = $chunkEnd;

            if ($sleepSeconds > 0 && $chunkStart->lt($until)) {
                sleep($sleepSeconds);
            }
        }

        $this->info("Fertig: {$totalMatched} von {$totalReceived} historischen Einträgen unserem Universum zugeordnet.");

        if ($failedChunks !== []) {
            $this->warn('Fehlgeschlagene Monate (erneut versuchen): '.implode(', ', $failedChunks));

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
