<?php

namespace App\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Builds 4 decile x decile (10x10) heatmap panels over the whole stock
 * universe's latest fundamentals snapshot, pairing the app's 4 "core"
 * fundamental filter parameters (trailing PE, dividend yield, market cap,
 * revenue growth - the same 4 AutomatedPortfolioService actually filters
 * on) two at a time. Each metric is used as an axis in two panels so all
 * four get covered without an arbitrary 5th/6th pairing:
 *   KGV x Dividendenrendite, KGV x Umsatzwachstum,
 *   Marktkap x Dividendenrendite, Marktkap x Umsatzwachstum
 *
 * Buckets are PERCENTILE deciles (evenly populated), not fixed absolute
 * ranges - trailing PE and market cap are heavily right-skewed, so a fixed
 * step size would leave most stocks piled in bucket 0.
 */
class FundamentalHeatmapService
{
    private const BUCKETS = 10;

    public function build(): array
    {
        $rows = $this->latestSnapshotPerInstrument();

        $metrics = [
            'trailing_pe' => [
                'label' => __('KGV'),
                'values' => $rows->pluck('trailing_pe')->filter(fn ($v) => $v !== null && $v > 0 && $v < 200)->values(),
            ],
            'dividend_yield' => [
                'label' => __('Dividendenrendite'),
                'values' => $rows->pluck('dividend_yield')->filter(fn ($v) => $v !== null && $v >= 0 && $v <= 20)->values(),
            ],
            'market_cap' => [
                'label' => __('Marktkapitalisierung'),
                // log scale - raw values span many orders of magnitude.
                'values' => $rows->pluck('market_cap')->filter(fn ($v) => $v !== null && $v > 0)->map(fn ($v) => log($v, 10))->values(),
            ],
            'revenue_growth' => [
                'label' => __('Umsatzwachstum'),
                'values' => $rows->pluck('revenue_growth')->filter(fn ($v) => $v !== null && $v > -100 && $v < 300)->values(),
            ],
        ];

        $boundaries = collect($metrics)->map(fn ($m) => $this->decileBoundaries($m['values']));

        $panels = [
            ['x' => 'trailing_pe', 'y' => 'dividend_yield'],
            ['x' => 'trailing_pe', 'y' => 'revenue_growth'],
            ['x' => 'market_cap', 'y' => 'dividend_yield'],
            ['x' => 'market_cap', 'y' => 'revenue_growth'],
        ];

        return collect($panels)->map(function (array $pair) use ($rows, $metrics, $boundaries): array {
            [$xKey, $yKey] = [$pair['x'], $pair['y']];

            $grid = array_fill(0, self::BUCKETS, array_fill(0, self::BUCKETS, 0));
            $used = 0;

            foreach ($rows as $row) {
                $x = $row->{$xKey};
                $y = $row->{$yKey};
                if ($x === null || $y === null) {
                    continue;
                }
                if ($xKey === 'market_cap') {
                    $x = $x > 0 ? log($x, 10) : null;
                }
                if ($yKey === 'market_cap') {
                    $y = $y > 0 ? log($y, 10) : null;
                }
                if ($x === null || $y === null) {
                    continue;
                }

                $xBucket = $this->bucketFor($x, $boundaries[$xKey]);
                $yBucket = $this->bucketFor($y, $boundaries[$yKey]);
                if ($xBucket === null || $yBucket === null) {
                    continue;
                }

                $grid[$yBucket][$xBucket]++;
                $used++;
            }

            $max = collect($grid)->flatten()->max() ?: 1;

            return [
                'x_key' => $xKey, 'y_key' => $yKey,
                'x_label' => $metrics[$xKey]['label'], 'y_label' => $metrics[$yKey]['label'],
                'title' => $metrics[$xKey]['label'].' × '.$metrics[$yKey]['label'],
                'x_boundaries' => $boundaries[$xKey],
                'y_boundaries' => $boundaries[$yKey],
                'grid' => $grid,
                'max' => $max,
                'instruments_used' => $used,
            ];
        })->all();
    }

    private function latestSnapshotPerInstrument(): Collection
    {
        return collect(DB::select("
            select distinct on (instrument_id) instrument_id, trailing_pe, dividend_yield, market_cap, revenue_growth
            from instrument_fundamentals
            order by instrument_id, snapshot_date desc, id desc
        "))->map(fn ($row) => (object) [
            'instrument_id' => (int) $row->instrument_id,
            'trailing_pe' => $row->trailing_pe !== null ? (float) $row->trailing_pe : null,
            'dividend_yield' => $row->dividend_yield !== null ? (float) $row->dividend_yield * 100 : null,
            'market_cap' => $row->market_cap !== null ? (float) $row->market_cap : null,
            'revenue_growth' => $row->revenue_growth !== null ? (float) $row->revenue_growth * 100 : null,
        ]);
    }

    /** @return float[] the 9 boundary values splitting the sorted values into 10 equally-populated buckets. */
    private function decileBoundaries(Collection $sortedableValues): array
    {
        $values = $sortedableValues->sort()->values();
        $n = $values->count();
        if ($n < self::BUCKETS) {
            return [];
        }

        $boundaries = [];
        for ($i = 1; $i < self::BUCKETS; $i++) {
            $idx = (int) floor($n * $i / self::BUCKETS);
            $boundaries[] = $values[min($idx, $n - 1)];
        }

        return $boundaries;
    }

    private function bucketFor(float $value, array $boundaries): ?int
    {
        if ($boundaries === []) {
            return null;
        }

        foreach ($boundaries as $i => $boundary) {
            if ($value < $boundary) {
                return $i;
            }
        }

        return self::BUCKETS - 1;
    }
}
