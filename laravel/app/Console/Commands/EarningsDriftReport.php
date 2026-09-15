<?php

namespace App\Console\Commands;

use App\Models\EarningsPriceReaction;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;

/**
 * The actual deliverable of the earnings-drift pipeline: groups
 * earnings_price_reactions by surprise-percent bucket and reports the
 * average forward return per horizon - a direct, data-backed answer to
 * "does beating/missing estimates predict subsequent price movement for
 * our own universe" (the post-earnings-announcement-drift question).
 */
class EarningsDriftReport extends Command
{
    protected $signature = 'earnings:drift-report {--min-sample=5 : Minimum rows a bucket needs before its average is reported}';

    protected $description = 'Report average forward returns by earnings-surprise bucket (post-earnings-drift analysis)';

    /** @var array<string, array{0: ?float, 1: ?float}> */
    private const BUCKETS = [
        'Starker Fehlschlag (< -10%)' => [null, -10.0],
        'Fehlschlag (-10% bis 0%)' => [-10.0, 0.0],
        'Beat (0% bis 10%)' => [0.0, 10.0],
        'Starker Beat (> 10%)' => [10.0, null],
    ];

    private const HORIZONS = ['return_pre_5d', 'return_1d', 'return_5d', 'return_10d', 'return_20d', 'return_40d'];

    public function handle(): int
    {
        $reactions = EarningsPriceReaction::query()->whereNotNull('surprise_percent')->get();

        if ($reactions->isEmpty()) {
            $this->warn('Keine earnings_price_reactions vorhanden. Zuerst events:backfill-earnings-history und earnings:compute-price-reactions laufen lassen.');

            return self::FAILURE;
        }

        $minSample = (int) $this->option('min-sample');

        $this->info("Grundgesamtheit: {$reactions->count()} Ereignisse mit Überraschungswert.");
        $this->newLine();

        $rows = [];
        foreach (self::BUCKETS as $label => [$min, $max]) {
            $bucket = $reactions->filter(fn (EarningsPriceReaction $r): bool => ($min === null || $r->surprise_percent > $min)
                && ($max === null || $r->surprise_percent <= $max));

            $row = [$label, $bucket->count()];
            foreach (self::HORIZONS as $horizon) {
                $row[] = $this->formatAverage($bucket, $horizon, $minSample);
            }
            $rows[] = $row;
        }

        $this->table(
            ['Überraschungs-Bucket', 'n', 'Ø vor 5T', 'Ø +1T', 'Ø +5T', 'Ø +10T', 'Ø +20T', 'Ø +40T'],
            $rows,
        );

        $this->newLine();
        $this->info('Korrelation Überraschung <-> Forward-Return (Pearson, über alle Ereignisse mit Wert je Horizont):');
        foreach (self::HORIZONS as $horizon) {
            $pairs = $reactions->filter(fn (EarningsPriceReaction $r) => $r->{$horizon} !== null);
            $correlation = $this->correlation($pairs->pluck('surprise_percent')->all(), $pairs->pluck($horizon)->all());
            $this->line(sprintf('  %s: r = %s (n = %d)', str_pad($horizon, 14), $correlation === null ? '—' : number_format($correlation, 3), $pairs->count()));
        }

        return self::SUCCESS;
    }

    private function formatAverage(Collection $bucket, string $field, int $minSample): string
    {
        $values = $bucket->pluck($field)->filter(fn ($value) => $value !== null);
        if ($values->count() < $minSample) {
            return '—';
        }

        return sprintf('%+.2f%% (n=%d)', $values->avg(), $values->count());
    }

    /**
     * @param  list<float>  $x
     * @param  list<float>  $y
     */
    private function correlation(array $x, array $y): ?float
    {
        $n = count($x);
        if ($n < 5) {
            return null;
        }

        $meanX = array_sum($x) / $n;
        $meanY = array_sum($y) / $n;

        $covariance = 0.0;
        $varianceX = 0.0;
        $varianceY = 0.0;
        for ($i = 0; $i < $n; $i++) {
            $dx = $x[$i] - $meanX;
            $dy = $y[$i] - $meanY;
            $covariance += $dx * $dy;
            $varianceX += $dx ** 2;
            $varianceY += $dy ** 2;
        }

        if ($varianceX <= 0.0 || $varianceY <= 0.0) {
            return null;
        }

        return $covariance / sqrt($varianceX * $varianceY);
    }
}
