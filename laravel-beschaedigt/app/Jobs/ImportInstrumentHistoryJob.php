<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;

class ImportInstrumentHistoryJob implements ShouldQueue
{
    public function __construct(public int $instrumentId, public string $interval='1d')
    {
    }

    public function handle(): void
    {
        // Übergibt den Auftrag später an die Python-Engine.
    }
}
