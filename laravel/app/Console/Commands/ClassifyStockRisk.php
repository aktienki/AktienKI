<?php

namespace App\Console\Commands;

use App\Services\StockRiskClassificationService;
use Illuminate\Console\Command;

final class ClassifyStockRisk extends Command
{
    protected $signature = 'stocks:classify-risk {--instrument= : Nur eine Instrument-ID aktualisieren}';
    protected $description = 'Klassifiziert Aktien anhand von Walk-Forward-Profitfaktor, Konfidenz und Drawdown';

    public function handle(StockRiskClassificationService $service): int
    {
        $counts = $service->refresh($this->option('instrument') ? (int) $this->option('instrument') : null);
        $this->table(['Status', 'Aktien'], collect($counts)->map(fn ($count, $status) => [$status, $count])->values()->all());
        return self::SUCCESS;
    }
}
