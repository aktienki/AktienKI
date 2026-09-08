<?php

namespace App\Console\Commands;

use App\Services\SgCertificateImporter;
use Illuminate\Console\Command;

final class SyncCertificates extends Command
{
    protected $signature = 'securities:sync-certificates {--provider=sg} {--page-size=1000} {--max-pages=}';
    protected $description = 'Synchronisiert aktuelle Zertifikate und Tageskurse der Emittenten';

    public function handle(SgCertificateImporter $sg): int
    {
        if ($this->option('provider') !== 'sg') {
            $this->error('Derzeit verfügbar: --provider=sg');
            return self::FAILURE;
        }

        $result = $sg->sync(
            (int) $this->option('page-size'),
            filled($this->option('max-pages')) ? (int) $this->option('max-pages') : null,
        );

        $this->info(sprintf(
            'SG: %d abgerufen, %d zugeordnet/importiert, %d ohne Aktienzuordnung.',
            $result['fetched'], $result['imported'], $result['unmatched']
        ));

        return self::SUCCESS;
    }
}
