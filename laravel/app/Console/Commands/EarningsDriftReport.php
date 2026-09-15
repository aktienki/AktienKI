<?php

namespace App\Console\Commands;

use App\Models\EarningsPriceReaction;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;

/**
 * The actual deliverable of the earnings-drift pipeline: for each
 * surprise-percent bucket, shows the forecast (Ø EPS-Vorhersage) next to
 * the real reported figure (Ø EPS-Ist), and the price move 3 trading days
 * before publication vs. 3 trading days after - a direct, data-backed
 * answer to "does beating/missing estimates predict the price move around
 * the report for our own universe".
 */
class EarningsDriftReport extends Command
{
    protected $signature = 'earnings:drift-report {--min-sample=5 : Minimum rows a bucket needs before its averages are reported}';

    protected $description = 'Report EPS forecast vs. actual and the 3-day pre/post price move by earnings-surprise bucket';

    /** @var array<string, array{0: ?float, 1: ?float}> */
    private const BUCKETS = [
        'Starker Fehlschlag (< -10%)' => [null, -10.0],
        'Fehlschlag (-10% bis 0%)' => [-10.0, 0.0],
        'Beat (0% bis 10%)' => [0.0, 10.0],
        'Starker Beat (> 10%)' => [10.0, null],
    ];

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

            $rows[] = [
                $label,
                $bucket->count(),
                $this->formatAverage($bucket, 'eps_estimate', $minSample, decimals: 2),
                $this->formatAverage($bucket, 'eps_actual', $minSample, decimals: 2),
                $this->formatAverage($bucket, 'return_pre_3d', $minSample),
                $this->formatAverage($bucket, 'return_post_3d', $minSample),
            ];
        }

        $this->table(
            ['Überraschungs-Bucket', 'n', 'Ø EPS-Vorhersage', 'Ø EPS-Ist', 'Ø Kurs -3T', 'Ø Kurs +3T'],
            $rows,
        );

        $this->newLine();
        $this->info('Korrelation Überraschung <-> Kursbewegung (Pearson):');
        foreach (['return_pre_3d' => 'vor Veröffentlichung (-3T)', 'return_post_3d' => 'nach Veröffentlichung (+3T)'] as $field => $label) {
            $pairs = $reactions->filter(fn (EarningsPriceReaction $r) => $r->{$field} !== null);
            $correlation = $this->correlation($pairs->pluck('surprise_percent')->all(), $pairs->pluck($field)->all());
            $this->line(sprintf('  %s: r = %s (n = %d)', $label, $correlation === null ? '—' : number_format($correlation, 3), $pairs->count()));
        }

        return self::SUCCESS;
    }

    private function formatAverage(Collection $bucket, string $field, int $minSample, int $decimals = 4): string
    {
        $values = $bucket->pluck($field)->filter(fn ($value) => $value !== null);
        if ($values->count() < $minSample) {
            return '—';
        }

        $average = $values->avg();
        $sign = str_contains($field, 'return') && $average >= 0 ? '+' : '';

        return sprintf('%s%s%s (n=%d)', $sign, number_format($average, $decimals, ',', '.'), str_contains($field, 'return') ? '%' : '', $values->count());
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
