<?php

namespace App\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Builds 4 decile x decile (10x10) heatmap panels over the whole stock
 * universe's latest fundamentals snapshot, pairing KGV and Dividendenrendite
 * (valuation/income) against Return on Equity and Gewinnwachstum (quality/
 * growth) - KGV x ROE, KGV x Gewinnwachstum,
 *   Dividendenrendite x ROE, Dividendenrendite x Gewinnwachstum
 *
 * (Market cap, revenue growth, Operating Margin, and two serving-DB model
 * scores - KI-Score/Panel - were tried as axes here first; market cap and
 * revenue growth were dropped by request, KI-Score/Panel were dropped
 * because the serving DB's active-prediction-universe + frozen-panel-model
 * coverage shrank the "all 4 metrics present" intersection down to ~330
 * instruments, and Operating Margin was later swapped for Gewinnwachstum
 * (quarterly YoY earnings growth) by request. Gewinnwachstum isn't a
 * dedicated instrument_fundamentals column - it's extracted from the
 * statistics.statistics.financials.income_statement.quarterly_earnings_growth_yoy
 * path inside the jsonb raw_data blob, same source data as everything else
 * on this page.)
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

    /**
     * A handful of instrument_fundamentals.dividend_yield rows store the
     * value already as a percent (15.65) instead of the usual fraction
     * (0.0165) - a source-data inconsistency, not a currency issue. No
     * real dividend yield exceeds 100% as a fraction, so >1 reliably means
     * "already a percent" and skips the *100. Mirrors the CASE expression
     * in table()'s SQL for the PHP-side (build()/ratios) call sites.
     */
    public static function normalizeYieldPercent(mixed $rawFraction): ?float
    {
        if ($rawFraction === null) {
            return null;
        }

        $value = (float) $rawFraction;

        return $value > 1 ? $value : $value * 100;
    }

    public static function countryName(string $code): string
    {
        return self::COUNTRY_NAMES[strtoupper($code)] ?? strtoupper($code);
    }

    /** ISO 3166-1 alpha-2 -> flag emoji, via Unicode regional indicator symbols (works for any valid code, no lookup table needed). */
    public static function countryFlag(?string $code): string
    {
        $code = strtoupper((string) $code);
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
     * @param  array<string, array{min?: float, max?: float}>  $metricRanges  Real-value min/max per metric key (trailing_pe, dividend_yield, return_on_equity, earnings_growth, market_cap in raw currency), from dragging the heatmap axis lines.
     * @param  string|null  $search  Symbol/name filter from the table's search box - applied to the heatmaps' baseline too, so typing a name also narrows what the panels count.
     */
    public function build(?string $capGroup = null, ?string $sector = null, ?string $country = null, ?string $region = null, array $metricRanges = [], ?string $search = null): array
    {
        $labels = [
            'trailing_pe' => ['label' => __('KGV'), 'unit' => 'x'],
            'dividend_yield' => ['label' => __('Dividendenrendite'), 'unit' => '%'],
            'return_on_equity' => ['label' => __('ROE'), 'unit' => '%'],
            'earnings_growth' => ['label' => __('Gewinnwachstum'), 'unit' => '%'],
        ];

        // Same stock base for all 4 panels: only instruments that have all
        // 4 metrics at once, so "n Aktien" means the same thing on every
        // panel instead of each pairing silently using whichever subset
        // happens to have that particular pair of metrics.
        $universe = $this->latestSnapshotPerInstrument()->filter(fn ($row) => $this->hasAllFourMetrics($row))->values();

        // The axis SCALE (decile boundaries) is fixed from the whole,
        // unfiltered universe - only which cells are populated should react
        // to sector/country/region/cap/range filters, not the axis itself,
        // otherwise every filter change reshuffles the grid and comparisons
        // across filter states become meaningless.
        $boundaries = collect($labels)->map(
            fn ($_, $key) => $this->decileBoundaries($this->sanitizedMetricValues($universe, $key))
        );

        $baselineRows = $this->latestSnapshotPerInstrument($sector, $country, $region, $search)
            ->filter(fn ($row) => $this->hasAllFourMetrics($row))->values();

        if ($capGroup !== null && isset(self::CAP_GROUPS[$capGroup])) {
            $range = self::CAP_GROUPS[$capGroup];
            $baselineRows = $baselineRows->filter(function ($row) use ($range) {
                if ($row->market_cap === null) {
                    return false;
                }

                return (! isset($range['min']) || $row->market_cap >= $range['min'])
                    && (! isset($range['max']) || $row->market_cap < $range['max']);
            })->values();
        }

        // "Baseline" = sector/country/region/cap applied (those affect every
        // panel identically already), but WITHOUT the per-axis slider
        // filters (metricRanges) - the reference used to grey out cells in
        // panels that don't share the dragged axis, so dragging e.g. the
        // KGV line visibly affects the ROE/Op.-Margin-only panels too,
        // instead of only the panels that happen to plot KGV themselves.
        $rows = $this->applyMetricRanges($baselineRows, $metricRanges);

        $metrics = $labels;

        $panels = [
            ['x' => 'trailing_pe', 'y' => 'return_on_equity'],
            ['x' => 'trailing_pe', 'y' => 'earnings_growth'],
            ['x' => 'dividend_yield', 'y' => 'return_on_equity'],
            ['x' => 'dividend_yield', 'y' => 'earnings_growth'],
        ];

        $buildGrid = function (array $pair, Collection $rowSet) use ($boundaries, $metrics): array {
            [$xKey, $yKey] = [$pair['x'], $pair['y']];

            $grid = array_fill(0, self::BUCKETS, array_fill(0, self::BUCKETS, 0));
            $used = 0;

            foreach ($rowSet as $row) {
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
        };

        return collect($panels)->map(function (array $pair) use ($rows, $baselineRows, $buildGrid): array {
            $current = $buildGrid($pair, $rows);
            $baseline = $buildGrid($pair, $baselineRows);

            // A cell is "reduced" if some active filter (including ones
            // from OTHER axes, e.g. dragging the KGV line on a panel that
            // doesn't even plot KGV) removed stocks from it, so every panel
            // visibly reacts to every active filter - not just the 1-2
            // panels that happen to share the dragged metric.
            $reduced = array_map(
                fn (array $currentRow, array $baselineRow) => array_map(
                    fn (int $c, int $b) => $c < $b,
                    $currentRow, $baselineRow,
                ),
                $current['grid'], $baseline['grid'],
            );

            return [...$current, 'reduced' => $reduced];
        })->all();
    }

    /**
     * Same universe/filters as build(), but as 4 independent 1D decile
     * histograms (one bar chart per metric) instead of paired 2D grids -
     * for the /fundamental-verteilung page, where each metric gets its own
     * slider instead of sharing an axis with another metric.
     *
     * @param  array<string, array{min?: float, max?: float}>  $metricRanges
     */
    public function buildHistograms(?string $capGroup = null, ?string $sector = null, ?string $country = null, ?string $region = null, array $metricRanges = [], ?string $search = null): array
    {
        $labels = [
            'trailing_pe' => ['label' => __('KGV'), 'unit' => 'x'],
            'dividend_yield' => ['label' => __('Dividendenrendite'), 'unit' => '%'],
            'return_on_equity' => ['label' => __('ROE'), 'unit' => '%'],
            'earnings_growth' => ['label' => __('Gewinnwachstum'), 'unit' => '%'],
        ];

        $universe = $this->latestSnapshotPerInstrument()->filter(fn ($row) => $this->hasAllFourMetrics($row))->values();
        $boundaries = collect($labels)->map(
            fn ($_, $key) => $this->decileBoundaries($this->sanitizedMetricValues($universe, $key))
        );

        $baselineRows = $this->latestSnapshotPerInstrument($sector, $country, $region, $search)
            ->filter(fn ($row) => $this->hasAllFourMetrics($row))->values();

        if ($capGroup !== null && isset(self::CAP_GROUPS[$capGroup])) {
            $range = self::CAP_GROUPS[$capGroup];
            $baselineRows = $baselineRows->filter(function ($row) use ($range) {
                if ($row->market_cap === null) {
                    return false;
                }

                return (! isset($range['min']) || $row->market_cap >= $range['min'])
                    && (! isset($range['max']) || $row->market_cap < $range['max']);
            })->values();
        }

        $rows = $this->applyMetricRanges($baselineRows, $metricRanges);

        $buildHistogram = function (string $key, Collection $rowSet) use ($boundaries, $labels): array {
            $counts = array_fill(0, self::BUCKETS, 0);
            $used = 0;

            foreach ($rowSet as $row) {
                $v = $row->{$key};
                if ($v === null) {
                    continue;
                }
                $bucket = $this->bucketFor($v, $boundaries[$key]);
                if ($bucket === null) {
                    continue;
                }
                $counts[$bucket]++;
                $used++;
            }

            return [
                'key' => $key,
                'label' => $labels[$key]['label'],
                'unit' => $labels[$key]['unit'],
                'ticks' => $this->axisTicks($key, $boundaries[$key]),
                'boundaries_raw' => $this->rawBoundaries($key, $boundaries[$key]),
                'counts' => $counts,
                'max' => max(1, max($counts)),
                'instruments_used' => $used,
            ];
        };

        return collect(array_keys($labels))->map(function (string $key) use ($rows, $baselineRows, $buildHistogram): array {
            $current = $buildHistogram($key, $rows);
            $baseline = $buildHistogram($key, $baselineRows);

            // Same cross-metric "reduced" marking as the heatmaps: a bucket
            // is greyed if ANY active filter (including from a different
            // metric's own slider) removed stocks from it, so dragging one
            // chart's slider visibly affects the other 3 charts too.
            $reduced = array_map(fn (int $c, int $b) => $c < $b, $current['counts'], $baseline['counts']);

            return [...$current, 'reduced' => $reduced];
        })->all();
    }

    /** @param  array<string, array{min?: float, max?: float}>  $metricRanges */
    private function applyMetricRanges(Collection $rows, array $metricRanges): Collection
    {
        foreach ($metricRanges as $key => $range) {
            if (! in_array($key, ['trailing_pe', 'dividend_yield', 'market_cap', 'return_on_equity', 'earnings_growth'], true)) {
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
        $columns = [
            'trailing_pe' => 'f.trailing_pe', 'dividend_yield' => 'f.dividend_yield', 'market_cap' => 'f.market_cap',
            'return_on_equity' => 'f.return_on_equity', 'earnings_growth' => 'f.earnings_growth',
        ];

        foreach ($metricRanges as $key => $range) {
            if (! isset($columns[$key])) {
                continue;
            }
            $column = $columns[$key];
            // dividend_yield/return_on_equity/earnings_growth are stored as
            // fractions (0.05 = 5%) in instrument_fundamentals; the range
            // comes in already as percent from the heatmap, so convert back.
            $scale = in_array($key, ['dividend_yield', 'return_on_equity', 'earnings_growth'], true) ? 100 : 1;
            if (isset($range['min'])) {
                $query->where($column, '>=', $range['min'] / $scale);
            }
            if (isset($range['max'])) {
                $query->where($column, '<=', $range['max'] / $scale);
            }
        }

        return $query;
    }

    private const SORTABLE_COLUMNS = ['symbol', 'name', 'trailing_pe', 'dividend_yield', 'return_on_equity', 'earnings_growth', 'market_cap'];

    /**
     * Sortable/filterable/paginated stock list backing the table below the
     * heatmaps - same latest-snapshot-per-instrument data and cap-group
     * filter, joined to instruments for symbol/name/sector.
     */
    /** @param  array<string, array{min?: float, max?: float}>  $metricRanges */
    public function table(?string $capGroup, string $sort, string $dir, ?string $search, int $page, int $perPage = 50, ?string $sector = null, ?string $country = null, ?string $region = null, array $metricRanges = []): array
    {
        $sort = in_array($sort, self::SORTABLE_COLUMNS, true) ? $sort : 'market_cap';
        $dir = $dir === 'asc' ? 'asc' : 'desc';

        $query = DB::table('instruments as i')
            ->joinSub(
                "select distinct on (instrument_id) instrument_id, trailing_pe, dividend_yield, market_cap, return_on_equity, shares_outstanding,
                        (raw_data #>> '{statistics,statistics,financials,income_statement,quarterly_earnings_growth_yoy}')::float as earnings_growth
                 from instrument_fundamentals order by instrument_id, snapshot_date desc, id desc",
                'f',
                'f.instrument_id', '=', 'i.id',
            )
            ->leftJoinSub(
                "select distinct on (instrument_id) instrument_id, close as current_close
                 from price_bars where interval = '1d' order by instrument_id, bar_time desc",
                'p',
                'p.instrument_id', '=', 'i.id',
            )
            ->where('i.type', 'stock')->whereNull('i.deleted_at')
            // Only list stocks that actually have quarterly earnings history
            // (corporate_events) - otherwise clicking through lands on an
            // empty Kennzahlen-im-Verlauf/Quartalszahlen page. This drops
            // the list from ~1400+ to ~600 stocks; the heatmaps themselves
            // are unaffected (they use instrument_fundamentals, not this).
            ->whereExists(fn ($q) => $q->select(DB::raw(1))->from('corporate_events as e')
                ->whereColumn('e.instrument_id', 'i.id')->where('e.event_type', 'earnings'))
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
                'f.trailing_pe',
                // A handful of instrument_fundamentals rows store dividend_yield
                // already as a percent (e.g. 15.65) instead of the usual
                // fraction (0.0165) - a source-data inconsistency (TwelveData
                // apparently mixes both), not a currency issue. No real yield
                // exceeds 100% as a fraction, so >1 reliably means "already a
                // percent" and skips the *100.
                DB::raw('CASE WHEN f.dividend_yield > 1 THEN f.dividend_yield ELSE f.dividend_yield * 100 END as dividend_yield'),
                'f.market_cap',
                DB::raw('f.return_on_equity * 100 as return_on_equity'),
                DB::raw('f.earnings_growth * 100 as earnings_growth'),
                // Live KGV = stored trailing_pe scaled by how much the price has
                // moved since the fundamentals snapshot (snapshot price =
                // market_cap / shares_outstanding) - same trailing EPS, current
                // price. NULLIF guards div-by-zero on missing shares_outstanding.
                DB::raw("
                    CASE WHEN f.trailing_pe IS NOT NULL AND p.current_close IS NOT NULL
                              AND f.market_cap IS NOT NULL AND NULLIF(f.shares_outstanding, 0) IS NOT NULL
                         THEN f.trailing_pe * (p.current_close / (f.market_cap / f.shares_outstanding))
                    END as trailing_pe_live
                "),
                // Live dividend yield = stored yield scaled INVERSELY to the
                // price move (yield = dividend/price, so it moves opposite to
                // price, unlike KGV) - same trailing dividend, current price.
                DB::raw("
                    CASE WHEN f.dividend_yield IS NOT NULL AND p.current_close IS NOT NULL
                              AND f.market_cap IS NOT NULL AND NULLIF(f.shares_outstanding, 0) IS NOT NULL
                         THEN (CASE WHEN f.dividend_yield > 1 THEN f.dividend_yield ELSE f.dividend_yield * 100 END)
                              * ((f.market_cap / f.shares_outstanding) / p.current_close)
                    END as dividend_yield_live
                "),
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

        $this->applyMetricRangesToQuery($query, $metricRanges);

        $total = (clone $query)->count();

        $rows = $query->orderByRaw("{$sort} {$dir} NULLS LAST")->orderBy('i.symbol')
            ->forPage($page, $perPage)->get();

        return ['rows' => $rows, 'total' => $total, 'page' => $page, 'per_page' => $perPage, 'sort' => $sort, 'dir' => $dir];
    }

    private function latestSnapshotPerInstrument(?string $sector = null, ?string $country = null, ?string $region = null, ?string $search = null): Collection
    {
        $query = DB::table('instruments as i')
            ->joinSub(
                "select distinct on (instrument_id) instrument_id, trailing_pe, dividend_yield, market_cap, return_on_equity,
                        (raw_data #>> '{statistics,statistics,financials,income_statement,quarterly_earnings_growth_yoy}')::float as earnings_growth
                 from instrument_fundamentals order by instrument_id, snapshot_date desc, id desc",
                'f',
                'f.instrument_id', '=', 'i.id',
            )
            ->where('i.type', 'stock')->whereNull('i.deleted_at')
            ->select(['f.instrument_id', 'f.trailing_pe', 'f.dividend_yield', 'f.market_cap', 'f.return_on_equity', 'f.earnings_growth']);

        if ($sector !== null && $sector !== '') {
            $query->where('i.sector', $sector);
        }
        if ($country !== null && $country !== '') {
            $query->where('i.country', $country);
        }
        if ($region !== null && isset(self::REGIONS[$region])) {
            $query->whereIn('i.country', self::REGIONS[$region]['countries']);
        }
        if ($search !== null && trim($search) !== '') {
            $term = '%'.trim($search).'%';
            $query->where(fn ($q) => $q->where('i.symbol', 'ilike', $term)->orWhere('i.name', 'ilike', $term));
        }

        return collect($query->get())->map(fn ($row) => (object) [
            'instrument_id' => (int) $row->instrument_id,
            'trailing_pe' => $row->trailing_pe !== null ? (float) $row->trailing_pe : null,
            'dividend_yield' => self::normalizeYieldPercent($row->dividend_yield),
            'market_cap' => $row->market_cap !== null ? (float) $row->market_cap : null,
            'return_on_equity' => $row->return_on_equity !== null ? (float) $row->return_on_equity * 100 : null,
            'earnings_growth' => $row->earnings_growth !== null ? (float) $row->earnings_growth * 100 : null,
        ]);
    }

    /** True if a row has a plausible (sanitized) value for all 4 heatmap metrics - see sanitizedMetricValues() for the same bounds applied per-metric across a collection. */
    private function hasAllFourMetrics(object $row): bool
    {
        foreach (['trailing_pe', 'dividend_yield', 'return_on_equity', 'earnings_growth'] as $key) {
            if ($this->sanitizedMetricValues(collect([$row]), $key)->isEmpty()) {
                return false;
            }
        }

        return true;
    }

    /** Same per-metric plausibility bounds used everywhere else in this class, extracted so the axis-scale computation (unfiltered universe) and the actual bucketing (filtered universe) apply identical sanitization. */
    private function sanitizedMetricValues(Collection $rows, string $key): Collection
    {
        $values = match ($key) {
            'trailing_pe' => $rows->pluck('trailing_pe')->filter(fn ($v) => $v !== null && $v > 0 && $v < 200),
            'dividend_yield' => $rows->pluck('dividend_yield')->filter(fn ($v) => $v !== null && $v >= 0 && $v <= 20),
            'return_on_equity' => $rows->pluck('return_on_equity')->filter(fn ($v) => $v !== null && $v > -100 && $v < 200),
            // quarterly YoY earnings growth is far more right-skewed than a
            // margin (a near-zero prior-year base can blow the ratio up to
            // several thousand percent) - 300% still keeps ~96% of the
            // instruments that have a value at all.
            'earnings_growth' => $rows->pluck('earnings_growth')->filter(fn ($v) => $v !== null && $v > -100 && $v < 300),
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
