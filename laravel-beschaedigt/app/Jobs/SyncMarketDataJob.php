<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;

class SyncMarketDataJob implements ShouldQueue
{
    public function handle(): void
    {
        // Trigger Python synchronization worker.
    }
}
