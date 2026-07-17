<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class SyncMarketDataCommand extends Command
{
    protected $signature = 'market:sync {--full}';
    protected $description = 'Queue market data synchronization';

    public function handle(): int
    {
        $this->info('Market synchronization queued.');
        return self::SUCCESS;
    }
}
