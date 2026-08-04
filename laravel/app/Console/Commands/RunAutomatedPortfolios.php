<?php

namespace App\Console\Commands;

use App\Services\AutomatedPortfolioService;
use Illuminate\Console\Command;

final class RunAutomatedPortfolios extends Command
{
    protected $signature = 'portfolios:run-automation';
    protected $description = 'Apply linked BUY strategies to virtual portfolios';

    public function handle(AutomatedPortfolioService $service): int
    {
        $stats = $service->scan();
        $this->info(sprintf(
            'Strategies: %d, candidates: %d, purchases: %d, skipped: %d',
            $stats['strategies'], $stats['candidates'], $stats['purchases'], $stats['skipped'],
        ));

        return self::SUCCESS;
    }
}
