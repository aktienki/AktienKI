<?php

namespace App\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Builds 4 decile x decile (10x10) heatmap panels over the whole stock
 * universe's latest fundamentals snapshot, pairing 4 metrics two at a
 * time - KGV, Dividendenrendite, Umsatzwachstum (from instrument_fundamentals)
 * and the current KI-Score (ranking_score, from ServingScreenerService -
 * a *different* physical database, so it's merged in per-instrument in PHP
 * rather than SQL-joined). Each metric is used as an axis in two panels so
 * all four get covered without an arbitrary 5th/6th pairing:
 *   KGV x Dividendenrendite, KGV x Umsatzwachstum,
 *   KI-Score x Dividendenrendite, KI-Score x Umsatzwachstum
 *
 * Buckets are PERCENTILE deciles (evenly populated), not fixed absolute
 * ranges - trailing PE is heavily right-skewed, so a fixed step size would
 * leave most stocks piled in bucket 0.
 *
 * Market cap itself is no longer a heatmap axis, but the Small/Mid/Large
 * Cap quick filter (CAP_GROUPS) still applies to the underlying stock set.
 */
class FundamentalHeatmapService
{
    public function __construct(private readonly ServingScreenerService $servingScreener) {}

    private const BUCKETS = 10;

    /** Same thresholds as AutomatedPortfolioService's market_cap_group filter. */
    public const CAP_GROUPS = [
        'small' => ['label' => 'Small Cap', 'max' => 2_000_000_000],
        'mid' => ['label' => 'Mid Cap', 'min' => 2_000_000_000, 'max' => 10_000_000_000],
        'large' => ['label' => 'Large Cap', 'min' => 10_000_000_000],
    ];

    /** Same country groupings as FreeRegionalStockUniverseService, extended to cover this wider universe's countries. */
    public const REGIONS = [
        'europe' => ['label' => 'Europa', 'countries' => ['AT', 'BE', 'BG', 'CH', 'CY', 'CZ', 'DE', 'DK', 'EE', 'ES', 'FI', 'FR', 'GB', 'GR', 'HR', 'HU', 'IE', 'IT', 'LT', 'LU', 'LV', 'MT', 'NL', 'PL', 'PT', 'RO', 'SE', 'SI', 'SK']],
        'north_america' => ['label' => 'Nordamerika', 'countries' => ['US', 'CA']],
        'asia_pacific' => ['label' => 'Asien-Pazifik', 'countries' => ['AU', 'CN', 'HK', 'ID', 'JP', 'SG', 'TH']],
        'other' => ['label' => 'Sonstige', 'countries' => ['AR', 'BM', 'CL', 'IL', 'KY', 'ZA']],
    ];

    private const COUNTRY_NAMES = [
        'AR' => 'Argentinien', 'AT' => 'Österreich', 'AU' => 'Australien', 'BE' => 'Belgien',
        'BM' => 'Bermuda', 'CA' => 'Kanada', 'CH' => 'Schweiz', 'CL' => 'Chile', 'CN' => 'China',
        'DE' => 'Deutschland', 'DK' => 'Dänemark', 'ES' => 'Spanien', 'FI' => 'Finnland',
        'FR' => 'Frankreich', 'GB' => 'Vereinigtes Königreich', 'HK' => 'Hongkong', 'ID' => 'Indonesien',
        'IE' => 'Irland', 'IL' => 'Israel', 'IT' => 'Italien', 'JP' => 'Japan', 'KY' => 'Kaimaninseln',
        'LU' => 'Luxemburg', 'NL' => 'Niederlande', 'SE' => 'Schweden', 'SG' => 'Singapur',
        'TH' => 'Thailand', 'US' => 'USA', 'ZA' => 'Südafrika',
    ];

    public static function countryName(string $code): string
    {
        return self::COUNTRY_NAMES[strtoupper($code)] ?? strtoupper($code);
    }

    /** ISO 3166-1 alpha-2 -> flag emoji, via Unicode regional indicator symbols (works for any valid code, no lookup table needed). */
    public static function countryFlag(string $code): string
    {
        $code = strtoupper($code);
        if (strlen($code) !== 2) {
            return '';
        }

        return mb_chr(0x1F1E6 + (ord($code[0]) - 65)).mb_chr(0x1F1E6 + (ord($code[1]) - 65));
    }

    /** @return array{sectors: string[], countries: string[]} distinct values present in the stock universe, for populating filter dropdowns. */
    public function filterOptions(): array
    {
        return [
            'sectors' => DB::table('instruments')->where('type', 'stock')->whereNull('deleted_at')
                ->whereNotNull('sector')->where('sector', '!=', '')->distinct()->orderBy('sector')->pluck('sector')->all(),
            'countries' => DB::table('instruments')->where('type', 'stock')->whereNull('deleted_at')
                ->whereNotNull('country')->where('country', '!=', '')->distinct()->pluck('country')
                ->sort(fn ($a, $b) => self::countryName($a) <=> self::countryName($b))->values()->all(),
        ];
    }

    /**
     * @param  array<string, array{min?: float, max?: float}>  $metricRanges  Real-value min/max per metric key (trailing_pe, dividend_yield, market_cap in raw currency, revenue_growth), from dragging the heatmap axis lines.
     */
    public function build(?string $capGroup = null, ?string $sector = null, ?string $country = null, ?string $region = null, array $metricRanges = []): array
    {
        $labels = [
            'trailing_pe' => ['label' => __('KGV'), 'unit' => 'x'],
            'dividend_yield' => ['label' => __('Dividendenrendite'), 'unit' => '%'],
            'ki_score' => ['label' => __('Score'), 'unit' => __('Pkt.')],
            'panel_score' => ['label' => __('Panel'), 'unit' => __('Pkt.')],
        ];

        // The axis SCALE (decile boundaries) is fixed from the whole,
        // unfiltered universe - only which cells are populated should react
        // to sector/country/region/cap/range filters, not the axis itself,
        // otherwise every filter change reshuffles the grid and comparisons
        // across filter states become meaningless.
        $boundaries = collect($labels)->map(
            fn ($_, $key) => $this->decileBoundaries($this->sanitizedMetricValues($this->latestSnapshotPerInstrument(), $key))
        );

        $rows = $this->latestSnapshotPerInstrument($sector, $country, $region);

        if ($capGroup !== null && isset(self::CAP_GROUPS[$capGroup])) {
            $range = self::CAP_GROUPS[$capGroup];
            $rows = $rows->filter(function ($row) use ($range) {
                if ($row->market_cap === null) {
                    return false;
                }

                return (! isset($range['min']) || $row->market_cap >= $range['min'])
                    && (! isset($range['max']) || $row->market_cap < $range['max']);
            })->values();
        }

        $rows = $this->applyMetricRanges($rows, $metricRanges);

        $metrics = $labels;

        $panels = [
            ['x' => 'trailing_pe', 'y' => 'ki_score'],
            ['x' => 'trailing_pe', 'y' => 'panel_score'],
            ['x' => 'dividend_yield', 'y' => 'ki_score'],
            ['x' => 'dividend_yield', 'y' => 'panel_score'],
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
                'x_unit' => $metrics[$xKey]['unit'], 'y_unit' => $metrics[$yKey]['unit'],
                'title' => $metrics[$xKey]['label'].' × '.$metrics[$yKey]['label'],
                'x_ticks' => $this->axisTicks($xKey, $boundaries[$xKey]),
                'y_ticks' => $this->axisTicks($yKey, $boundaries[$yKey]),
                'x_boundaries_raw' => $this->rawBoundaries($xKey, $boundaries[$xKey]),
                'y_boundaries_raw' => $this->rawBoundaries($yKey, $boundaries[$yKey]),
                'grid' => $grid,
                'max' => $max,
                'instruments_used' => $used,
            ];
        })->all();
    }

    /** @param  array<string, array{min?: float, max?: float}>  $metricRanges */
    private function applyMetricRanges(Collection $rows, array $metricRanges): Collection
    {
        foreach ($metricRanges as $key => $range) {
            if (! in_array($key, ['trailing_pe', 'dividend_yield', 'market_cap', 'revenue_growth', 'ki_score', 'panel_score'], true)) {
                continue;
            }
            $rows = $rows->filter(function ($row) use ($key, $range) {
                $v = $row->{$key};
                if ($v === null) {
                    return false;
                }

                return (! isset($range['min']) || $v >= $range['min']) && (! isset($range['max']) || $v <= $range['max']);
            })->values();
        }

        return $rows;
    }

    /** @param  array<string, array{min?: float, max?: float}>  $metricRanges */
    private function applyMetricRangesToQuery($query, array $metricRanges)
    {
        $columns = ['trailing_pe' => 'f.trailing_pe', 'dividend_yield' => 'f.dividend_yield', 'market_cap' => 'f.market_cap', 'revenue_growth' => 'f.revenue_growth'];

        foreach ($metricRanges as $key => $range) {
            if (! isset($columns[$key])) {
                continue;
            }
            $column = $columns[$key];
            // dividend_yield/revenue_growth are stored as fractions (0.05 = 5%) in instrument_fundamentals; the range comes in already as percent from the heatmap, so convert back.
            $scale = in_array($key, ['dividend_yield', 'revenue_growth'], true) ? 100 : 1;
            if (isset($range['min'])) {
                $query->where($column, '>=', $range['min'] / $scale);
            }
            if (isset($range['max'])) {
                $query->where($column, '<=', $range['max'] / $scale);
            }
        }

        return $query;
    }

    private const SORTABLE_COLUMNS = ['symbol', 'name', 'trailing_pe', 'dividend_yield', 'ki_score', 'panel_score', 'revenue_growth'];

    /**
     * Sortable/filterable/paginated stock list backing the table below the
     * heatmaps - same latest-snapshot-per-instrument data and cap-group
     * filter, joined to instruments for symbol/name/sector. KI-Score lives
     * in a different physical database (see class docblock), so it's
     * merged in per-instrument here rather than SQL-joined - the whole
     * matching set (a few thousand rows at most) is fetched, merged,
     * sorted and paginated in PHP instead of at the SQL level.
     */
    /** @param  array<string, array{min?: float, max?: float}>  $metricRanges */
    public function table(?string $capGroup, string $sort, string $dir, ?string $search, int $page, int $perPage = 50, ?string $sector = null, ?string $country = null, ?string $region = null, array $metricRanges = []): array
    {
        $sort = in_array($sort, self::SORTABLE_COLUMNS, true) ? $sort : 'ki_score';
        $dir = $dir === 'asc' ? 'asc' : 'desc';

        $query = DB::table('instruments as i')
            ->joinSub(
                "select distinct on (instrument_id) instrument_id, trailing_pe, dividend_yield, market_cap, revenue_growth
                 from instrument_fundamentals order by instrument_id, snapshot_date desc, id desc",
                'f',
                'f.instrument_id', '=', 'i.id',
            )
            ->where('i.type', 'stock')->whereNull('i.deleted_at')
            ->when($sector !== null && $sector !== '', fn ($q) => $q->where('i.sector', $sector))
            ->when($country !== null && $country !== '', fn ($q) => $q->where('i.country', $country))
            ->when($region !== null && isset(self::REGIONS[$region]), fn ($q) => $q->whereIn('i.country', self::REGIONS[$region]['countries']))
            // A handful of .JO/.T listings have a market_cap several orders
            // of magnitude too large (likely a currency-unit conversion bug
            // in the source feed, e.g. JSE prices quoted in ZA cents) - hide
            // rather than let them dominate a market-cap sort.
            ->where(fn ($q) => $q->whereNull('f.market_cap')->orWhere('f.market_cap', '<', 5_000_000_000_000))
            ->select([
                'i.id as instrument_id', 'i.symbol', 'i.name', 'i.sector', 'i.country',
                'f.trailing_pe', DB::raw('f.dividend_yield * 100 as dividend_yield'),
                'f.market_cap', DB::raw('f.revenue_growth * 100 as revenue_growth'),
            ]);

        if ($capGroup !== null && isset(self::CAP_GROUPS[$capGroup])) {
            $range = self::CAP_GROUPS[$capGroup];
            $query->whereNotNull('f.market_cap');
            if (isset($range['min'])) {
                $query->where('f.market_cap', '>=', $range['min']);
            }
            if (isset($range['max'])) {
                $query->where('f.market_cap', '<', $range['max']);
            }
        }

        if ($search !== null && trim($search) !== '') {
            $term = '%'.trim($search).'%';
            $query->where(fn ($q) => $q->where('i.symbol', 'ilike', $term)->orWhere('i.name', 'ilike', $term));
        }

        // ki_score isn't a real SQL column here, applyMetricRangesToQuery
        // ignores it and it's filtered in PHP below instead.
        $this->applyMetricRangesToQuery($query, $metricRanges);

        $servingMetrics = $this->servingMetricsByInstrument();
        $rows = collect($query->get())->map(function ($row) use ($servingMetrics) {
            $metrics = $servingMetrics->get((int) $row->instrument_id);
            $row->ki_score = $metrics?->ki_score;
            $row->panel_score = $metrics?->panel_score;

            return $row;
        });

        foreach (['ki_score', 'panel_score'] as $key) {
            if (! isset($metricRanges[$key])) {
                continue;
            }
            $range = $metricRanges[$key];
            $rows = $rows->filter(fn ($row) => $row->{$key} !== null
                && (! isset($range['min']) || $row->{$key} >= $range['min'])
                && (! isset($range['max']) || $row->{$key} <= $range['max']));
        }

        $total = $rows->count();

        // Nulls sort last regardless of direction (a missing value isn't "the smallest").
        $rows = $dir === 'asc'
            ? $rows->sortBy(fn ($row) => $row->{$sort} ?? INF)
            : $rows->sortByDesc(fn ($row) => $row->{$sort} ?? -INF);
        $rows = $rows->values()->forPage($page, $perPage)->values();

        return ['rows' => $rows, 'total' => $total, 'page' => $page, 'per_page' => $perPage, 'sort' => $sort, 'dir' => $dir];
    }

    private function latestSnapshotPerInstrument(?string $sector = null, ?string $country = null, ?string $region = null): Collection
    {
        $query = DB::table('instruments as i')
            ->joinSub(
                "select distinct on (instrument_id) instrument_id, trailing_pe, dividend_yield, market_cap, revenue_growth
                 from instrument_fundamentals order by instrument_id, snapshot_date desc, id desc",
                'f',
                'f.instrument_id', '=', 'i.id',
            )
            ->where('i.type', 'stock')->whereNull('i.deleted_at')
            ->select(['f.instrument_id', 'f.trailing_pe', 'f.dividend_yield', 'f.market_cap', 'f.revenue_growth']);

        if ($sector !== null && $sector !== '') {
            $query->where('i.sector', $sector);
        }
        if ($country !== null && $country !== '') {
            $query->where('i.country', $country);
        }
        if ($region !== null && isset(self::REGIONS[$region])) {
            $query->whereIn('i.country', self::REGIONS[$region]['countries']);
        }

        $servingMetrics = $this->servingMetricsByInstrument();

        return collect($query->get())->map(function ($row) use ($servingMetrics) {
            $metrics = $servingMetrics->get((int) $row->instrument_id);

            return (object) [
                'instrument_id' => (int) $row->instrument_id,
                'trailing_pe' => $row->trailing_pe !== null ? (float) $row->trailing_pe : null,
                'dividend_yield' => $row->dividend_yield !== null ? (float) $row->dividend_yield * 100 : null,
                'market_cap' => $row->market_cap !== null ? (float) $row->market_cap : null,
                'revenue_growth' => $row->revenue_growth !== null ? (float) $row->revenue_growth * 100 : null,
                'ki_score' => $metrics?->ki_score,
                'panel_score' => $metrics?->panel_score,
            ];
        });
    }

    /**
     * ranking_score (the raw, unfiltered KI-Score - see
     * ServingScreenerService::stock(), labelled "KI-Score" in the
     * screener) and panel_percentile (the separate cross-sectional panel
     * model's percentile rank) from the serving DB, keyed by instrument_id.
     * Cached 5 min inside currentStocks() itself.
     *
     * @return Collection<int, object{ki_score: ?float, panel_score: ?float}>
     */
    private function servingMetricsByInstrument(): Collection
    {
        return $this->servingScreener->currentStocks()
            ->filter(fn ($s) => isset($s->instrument_id))
            ->keyBy('instrument_id')
            ->map(fn ($s) => (object) [
                'ki_score' => is_numeric($s->ranking_score ?? null) ? (float) $s->ranking_score : null,
                'panel_score' => is_numeric($s->panel_percentile ?? null) ? (float) $s->panel_percentile : null,
            ]);
    }

    /** Same per-metric plausibility bounds used everywhere else in this class, extracted so the axis-scale computation (unfiltered universe) and the actual bucketing (filtered universe) apply identical sanitization. */
    private function sanitizedMetricValues(Collection $rows, string $key): Collection
    {
        $values = match ($key) {
            'trailing_pe' => $rows->pluck('trailing_pe')->filter(fn ($v) => $v !== null && $v > 0 && $v < 200),
            'dividend_yield' => $rows->pluck('dividend_yield')->filter(fn ($v) => $v !== null && $v >= 0 && $v <= 20),
            default => $rows->pluck($key)->filter(fn ($v) => $v !== null),
        };

        return $values->values();
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

    /**
     * One label per bucket (0-9), showing that bucket's LOWER real-value
     * edge (market cap converted back out of log scale, into Mrd.), so
     * the grid reads in actual KGV/%/Mrd. numbers instead of abstract
     * decile indices. Bucket 0 has no lower edge to show (it's
     * "everything below boundaries[0]"), so it's labelled with a "<".
     */
    private function axisTicks(string $key, array $boundaries): array
    {
        if ($boundaries === []) {
            return array_fill(0, self::BUCKETS, '–');
        }

        $format = function (float $v) use ($key): string {
            if ($key === 'market_cap') {
                $v = (10 ** $v) / 1_000_000_000;
            }

            return $v >= 100 ? number_format($v, 0, ',', '.') : number_format($v, 1, ',', '.');
        };

        $ticks = [];
        for ($bucket = 0; $bucket < self::BUCKETS; $bucket++) {
            if ($bucket === 0) {
                $ticks[] = '<'.$format($boundaries[0]);
            } else {
                $ticks[] = $format($boundaries[$bucket - 1]);
            }
        }

        return $ticks;
    }

    /** Same 9 boundaries as axisTicks(), but as raw numbers (market cap converted back out of log scale into full currency units) for building real min/max filter values from a dragged decile range. */
    private function rawBoundaries(string $key, array $boundaries): array
    {
        if ($key !== 'market_cap') {
            return $boundaries;
        }

        return array_map(fn ($v) => 10 ** $v, $boundaries);
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
