<?php

namespace App\Console\Commands;

use App\Services\MarketDashboardService;
use Illuminate\Console\Command;

class MarketDashboardStatus extends Command
{
    protected $signature = 'aktienki:market-status';
    protected $description = 'Zeigt den aktuellen Market-Snapshot-Status für Dashboard und API.';

    public function handle(MarketDashboardService $service): int
    {
        $snapshot = $service->latest();

        if (! $snapshot) {
            $this->warn('Noch kein Market Snapshot vorhanden.');
            return self::FAILURE;
        }

        $this->table(['Kennzahl', 'Wert'], [
            ['Snapshot', $snapshot['snapshot_time'] ?? '-'],
            ['Market Score', $snapshot['market_score'] ?? '-'],
            ['Risk Mode', $snapshot['risk_mode'] ?? '-'],
            ['Trend', $snapshot['market_trend'] ?? '-'],
            ['Breadth Score', $snapshot['breadth_score'] ?? '-'],
            ['Assets', count($snapshot['assets'])],
            ['Sektoren', count($snapshot['sectors'])],
        ]);

        return self::SUCCESS;
    }
}
