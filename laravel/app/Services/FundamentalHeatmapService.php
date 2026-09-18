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

    public function build(?string $capGroup = null, ?string $sector = null, ?string $country = null, ?string $region = null): array
    {
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

        $metrics = [
            'trailing_pe' => [
                'label' => __('KGV'), 'unit' => 'x',
                'values' => $rows->pluck('trailing_pe')->filter(fn ($v) => $v !== null && $v > 0 && $v < 200)->values(),
            ],
            'dividend_yield' => [
                'label' => __('Dividendenrendite'), 'unit' => '%',
                'values' => $rows->pluck('dividend_yield')->filter(fn ($v) => $v !== null && $v >= 0 && $v <= 20)->values(),
            ],
            'market_cap' => [
                'label' => __('Marktkapitalisierung'), 'unit' => __('Mrd.'),
                // log scale - raw values span many orders of magnitude.
                'values' => $rows->pluck('market_cap')->filter(fn ($v) => $v !== null && $v > 0 && $v < 5_000_000_000_000)->map(fn ($v) => log($v, 10))->values(),
            ],
            'revenue_growth' => [
                'label' => __('Umsatzwachstum'), 'unit' => '%',
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
                'x_unit' => $metrics[$xKey]['unit'], 'y_unit' => $metrics[$yKey]['unit'],
                'title' => $metrics[$xKey]['label'].' × '.$metrics[$yKey]['label'],
                'x_ticks' => $this->axisTicks($xKey, $boundaries[$xKey]),
                'y_ticks' => $this->axisTicks($yKey, $boundaries[$yKey]),
                'grid' => $grid,
                'max' => $max,
                'instruments_used' => $used,
            ];
        })->all();
    }

    private const SORTABLE_COLUMNS = ['symbol', 'name', 'trailing_pe', 'dividend_yield', 'market_cap', 'revenue_growth'];

    /**
     * Sortable/filterable/paginated stock list backing the table below the
     * heatmaps - same latest-snapshot-per-instrument data and cap-group
     * filter, joined to instruments for symbol/name/sector.
     */
    public function table(?string $capGroup, string $sort, string $dir, ?string $search, int $page, int $perPage = 50, ?string $sector = null, ?string $country = null, ?string $region = null): array
    {
        $sort = in_array($sort, self::SORTABLE_COLUMNS, true) ? $sort : 'market_cap';
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
                'i.symbol', 'i.name', 'i.sector', 'i.country',
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

        $total = (clone $query)->count();

        $rows = $query->orderByRaw("{$sort} {$dir} NULLS LAST")->orderBy('i.symbol')
            ->forPage($page, $perPage)->get();

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

        return collect($query->get())->map(fn ($row) => (object) [
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
