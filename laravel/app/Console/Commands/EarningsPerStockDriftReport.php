<?php

namespace App\Console\Commands;

use App\Services\EarningsDriftStatsService;
use Illuminate\Console\Command;

/**
 * The per-stock counterpart to earnings:drift-report: instead of pooling
 * every stock into one surprise bucket, this groups by instrument and
 * shows each stock's own historical reaction pattern - how it has moved
 * 3 trading days before/after its own past earnings, split by whether
 * that report beat or missed the estimate. That split is the basis for a
 * per-stock forecast ("this stock has historically risen ~X% in the 3
 * days after a beat, and fallen ~Y% after a miss"). Stats come from
 * EarningsDriftStatsService, shared with the concept dashboard's
 * "Quartalszahlen-Historie" tab.
 */
class EarningsPerStockDriftReport extends Command
{
    protected $signature = 'earnings:drift-report-by-stock
        {--symbol= : Restrict to one instrument symbol}
        {--min-sample=1 : Minimum number of past events a stock needs to be listed at all}';

    protected $description = 'Report each stock\'s own historical 3-day pre/post earnings price reaction, split by beat vs. miss';

    public function handle(EarningsDriftStatsService $stats): int
    {
        $minSample = max(1, (int) $this->option('min-sample'));
        $rows = $stats->forAllStocks($minSample, $this->option('symbol'))
            ->sortByDesc(fn (array $row): int => $row['n'])
            ->values();

        if ($rows->isEmpty()) {
            $this->warn("Keine Aktie erreicht die Mindest-Stichprobe von {$minSample}, oder es liegen noch keine earnings_price_reactions vor.");

            return self::FAILURE;
        }

        $this->table(
            ['Symbol', 'Name', 'n', 'Ø Kurs -3T', 'Ø Kurs +3T (alle)', 'Ø Kurs +3T (Beat)', 'Ø Kurs +3T (Miss)', 'Tendenz'],
            $rows->map(fn (array $row) => [
                $row['symbol'], $row['name'], $row['n'],
                $this->formatPercent($row['pre3d']), $this->formatPercent($row['post3d']),
                $this->formatPercent($row['post3dBeat']), $this->formatPercent($row['post3dMiss']),
                $row['tendency'],
            ])->all(),
        );

        return self::SUCCESS;
    }

    private function formatPercent(?float $value): string
    {
        if ($value === null) {
            return '—';
        }

        return sprintf('%s%s%%', $value >= 0 ? '+' : '', number_format($value, 2, ',', '.'));
    }
}
