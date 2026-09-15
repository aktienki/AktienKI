<?php

namespace App\Console\Commands;

use App\Services\EarningsPriceReactionService;
use Illuminate\Console\Command;

class ComputeEarningsPriceReactions extends Command
{
    protected $signature = 'earnings:compute-price-reactions {--limit=2000}';

    protected $description = 'Compute/refresh earnings_price_reactions from corporate_events + price_bars for the post-earnings-drift study';

    public function handle(EarningsPriceReactionService $service): int
    {
        $result = $service->sync((int) $this->option('limit'));

        $this->info("Verarbeitet: {$result['processed']}, berechnet: {$result['computed']}, übersprungen (noch keine Kursdaten): {$result['skipped']}.");

        return self::SUCCESS;
    }
}
