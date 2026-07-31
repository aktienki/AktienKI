<?php

namespace App\Console\Commands;

use App\Http\Controllers\MarketQuotesController;
use App\Services\YahooIndexService;
use Illuminate\Console\Command;

class RefreshMarketIndices extends Command
{
    protected $signature = 'markets:refresh-indices';

    protected $description = 'Fetch reference indices in the background and persist them to price_bars';

    public function handle(YahooIndexService $indices): int
    {
        $symbols = collect(MarketQuotesController::REFERENCE_INDICES)->pluck(0)->all();
        $quotes = $indices->quotes($symbols);

        $this->info(sprintf('Stored %d of %d reference indices.', count($quotes), count($symbols)));

        return self::SUCCESS;
    }
}
