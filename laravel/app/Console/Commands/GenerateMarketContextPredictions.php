<?php

namespace App\Console\Commands;

use App\Services\MarketContextPredictionService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

final class GenerateMarketContextPredictions extends Command
{
    protected $signature = 'predictions:market-context {--date=}';
    protected $description = 'Erzeugt die täglichen Prediction-Snapshots für Sektoren und Indizes.';

    public function handle(MarketContextPredictionService $service): int
    {
        $date = $this->option('date') ? Carbon::parse((string) $this->option('date')) : null;
        $result = $service->generate($date);
        $this->info("{$result['date']}: {$result['sectors']} Sektoren, {$result['indices']} Indizes");
        return self::SUCCESS;
    }
}
