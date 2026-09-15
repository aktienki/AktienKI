<?php

namespace App\Console\Commands;

use App\Models\EarningsPriceReaction;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;

/**
 * The per-stock counterpart to earnings:drift-report: instead of pooling
 * every stock into one surprise bucket, this groups by instrument and
 * shows each stock's own historical reaction pattern - how it has moved
 * 3 trading days before/after its own past earnings, split by whether
 * that report beat or missed the estimate. That split is the basis for a
 * per-stock forecast ("this stock has historically risen ~X% in the 3
 * days after a beat, and fallen ~Y% after a miss").
 *
 * With only a few months of real corporate_events history, most stocks
 * currently have just 1-2 rows - genuinely useful per-stock numbers need
 * events:backfill-earnings-history run first.
 */
class EarningsPerStockDriftReport extends Command
{
    protected $signature = 'earnings:drift-report-by-stock
        {--symbol= : Restrict to one instrument symbol}
        {--min-sample=1 : Minimum number of past events a stock needs to be listed at all}';

    protected $description = 'Report each stock\'s own historical 3-day pre/post earnings price reaction, split by beat vs. miss';

    public function handle(): int
    {
        $query = EarningsPriceReaction::query()
            ->with('instrument:id,symbol,name')
            ->whereNotNull('surprise_percent')
            ->whereHas('instrument');

        if ($symbol = $this->option('symbol')) {
            $query->whereHas('instrument', fn ($q) => $q->where('symbol', $symbol));
        }

        $reactions = $query->get();

        if ($reactions->isEmpty()) {
            $this->warn('Keine passenden earnings_price_reactions vorhanden. Zuerst events:backfill-earnings-history und earnings:compute-price-reactions laufen lassen.');

            return self::FAILURE;
        }

        $minSample = max(1, (int) $this->option('min-sample'));

        $rows = $reactions
            ->groupBy(fn (EarningsPriceReaction $r): int => $r->instrument_id)
            ->filter(fn (Collection $group): bool => $group->count() >= $minSample)
            ->map(fn (Collection $group): array => $this->stockRow($group))
            ->sortByDesc(fn (array $row): int => $row['n'])
            ->values();

        if ($rows->isEmpty()) {
            $this->warn("Keine Aktie erreicht die Mindest-Stichprobe von {$minSample}.");

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

    /**
     * @return array{symbol: string, name: string, n: int, pre3d: ?float, post3d: ?float, post3dBeat: ?float, post3dMiss: ?float, tendency: string}
     */
    private function stockRow(Collection $group): array
    {
        $instrument = $group->first()->instrument;
        $beats = $group->filter(fn (EarningsPriceReaction $r): bool => $r->surprise_percent > 0);
        $misses = $group->filter(fn (EarningsPriceReaction $r): bool => $r->surprise_percent <= 0);

        $avgPost3dBeat = $this->average($beats->pluck('return_post_3d'));
        $avgPost3dMiss = $this->average($misses->pluck('return_post_3d'));

        return [
            'symbol' => $instrument->symbol,
            'name' => $instrument->name ?: $instrument->symbol,
            'n' => $group->count(),
            'pre3d' => $this->average($group->pluck('return_pre_3d')),
            'post3d' => $this->average($group->pluck('return_post_3d')),
            'post3dBeat' => $avgPost3dBeat,
            'post3dMiss' => $avgPost3dMiss,
            'tendency' => $this->tendency($avgPost3dBeat, $avgPost3dMiss, $beats->count(), $misses->count()),
        ];
    }

    private function tendency(?float $avgPost3dBeat, ?float $avgPost3dMiss, int $beatCount, int $missCount): string
    {
        if ($beatCount === 0 || $missCount === 0 || $avgPost3dBeat === null || $avgPost3dMiss === null) {
            return 'Zu wenig Historie';
        }

        if ($avgPost3dBeat > 0 && $avgPost3dMiss < 0) {
            return 'Reagiert erwartungsgemäß';
        }

        if ($avgPost3dBeat <= 0 && $avgPost3dMiss >= 0) {
            return 'Reagiert gegenläufig';
        }

        return 'Uneinheitlich';
    }

    private function average(Collection $values): ?float
    {
        $filtered = $values->filter(fn ($value) => $value !== null);

        return $filtered->isEmpty() ? null : $filtered->avg();
    }

    private function formatPercent(?float $value): string
    {
        if ($value === null) {
            return '—';
        }

        return sprintf('%s%s%%', $value >= 0 ? '+' : '', number_format($value, 2, ',', '.'));
    }
}
