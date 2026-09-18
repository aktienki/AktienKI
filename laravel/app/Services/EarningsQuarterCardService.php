<?php

namespace App\Services;

use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Builds the "4 years x 4 quarters" earnings-reaction card data for a
 * single instrument: EPS actual/estimate, surprise, the 3-trading-day price
 * reaction, and a +/-10 trading day candlestick series per reported
 * quarter. Quarters are grouped by CALENDAR quarter of the report date
 * (Q1=Jan-Mar ... Q4=Oct-Dec) rather than the company's own fiscal
 * calendar - we don't have a reliable fiscal-quarter label per company, so
 * this stays an honest, universally-computable simplification instead of
 * guessing at company-specific fiscal periods.
 */
class EarningsQuarterCardService
{
    private const YEARS_SHOWN = 4;

    private const CANDLE_WINDOW_DAYS = 10;

    private const TREND_QUARTERS_SHOWN = 8;

    public function forInstrument(int $instrumentId): array
    {
        $currentYear = (int) CarbonImmutable::now()->format('Y');
        $years = range($currentYear - self::YEARS_SHOWN + 1, $currentYear);

        $events = DB::table('corporate_events')
            ->where('instrument_id', $instrumentId)
            ->where('event_type', 'earnings')
            ->whereYear('event_date', '>=', $years[0])
            ->orderBy('event_date')
            ->get(['id', 'event_date', 'eps_estimate', 'eps_actual', 'surprise_percent']);

        // Same-quarter duplicate dates (a handful of days apart) happen in
        // the source feed - keep the first per (year, quarter) rather than
        // showing two cards for one real report.
        $byYearQuarter = [];
        foreach ($events as $event) {
            $date = CarbonImmutable::parse($event->event_date);
            $year = (int) $date->format('Y');
            $quarter = (int) ceil(((int) $date->format('n')) / 3);
            $key = $year.'-'.$quarter;
            if (! isset($byYearQuarter[$key])) {
                $byYearQuarter[$key] = $event;
            }
        }

        $bars = DB::table('price_bars')->where('instrument_id', $instrumentId)->where('interval', '1d')
            ->orderBy('bar_time')
            ->get(['bar_time', 'open', 'high', 'low', 'close'])
            ->map(fn ($b) => [
                'date' => CarbonImmutable::parse($b->bar_time)->toDateString(),
                'open' => (float) $b->open, 'high' => (float) $b->high,
                'low' => (float) $b->low, 'close' => (float) $b->close,
            ])->values();

        $reactionsByEventId = DB::table('earnings_price_reactions')
            ->whereIn('corporate_event_id', collect($byYearQuarter)->pluck('id'))
            ->get()->keyBy('corporate_event_id');

        $medianAbsEps = $this->medianAbsEps(collect($byYearQuarter)->pluck('eps_actual'));

        $result = [];
        foreach ($years as $year) {
            $quarters = [];
            for ($q = 1; $q <= 4; $q++) {
                $event = $byYearQuarter[$year.'-'.$q] ?? null;
                $quarters[] = $event === null
                    ? ['quarter' => $q, 'has_data' => false]
                    : $this->buildQuarter($q, $event, $reactionsByEventId->get($event->id), $bars, $medianAbsEps);
            }
            $result[] = ['year' => $year, 'quarters' => $quarters, 'count' => collect($quarters)->where('has_data', true)->count()];
        }

        return $result;
    }

    /**
     * The same 4 metrics as the Fundamental heatmaps (KGV, Dividendenrendite,
     * ROE, Gewinnwachstum) as small per-stock bar charts. KGV and
     * Gewinnwachstum genuinely vary per quarter (derived from reported EPS +
     * price, and YoY EPS growth) and get one bar per reported quarter we
     * have. ROE and Dividendenrendite have no historical time series
     * anywhere in the data (instrument_fundamentals only ever keeps the
     * latest snapshot) - rather than fake a flat multi-quarter line, they
     * get a single "aktuell" bar for the current value.
     */
    public function kennzahlenTrend(int $instrumentId): array
    {
        $currentYear = (int) CarbonImmutable::now()->format('Y');
        $sinceYear = $currentYear - self::YEARS_SHOWN + 1;

        $events = DB::table('corporate_events')
            ->where('instrument_id', $instrumentId)
            ->where('event_type', 'earnings')
            ->whereYear('event_date', '>=', $sinceYear)
            ->orderBy('event_date')
            ->get(['event_date', 'eps_actual']);

        $byYearQuarter = [];
        foreach ($events as $event) {
            if ($event->eps_actual === null) {
                continue;
            }
            $date = CarbonImmutable::parse($event->event_date);
            $year = (int) $date->format('Y');
            $quarter = (int) ceil(((int) $date->format('n')) / 3);
            $key = $year.'-'.$quarter;
            if (! isset($byYearQuarter[$key])) {
                $byYearQuarter[$key] = ['year' => $year, 'quarter' => $quarter, 'date' => $date, 'eps' => (float) $event->eps_actual];
            }
        }

        $medianAbsEps = $this->medianAbsEps(collect($byYearQuarter)->pluck('eps'));
        $byYearQuarter = collect($byYearQuarter)
            ->filter(fn ($q) => ! $this->looksImplausible($q['eps'], $medianAbsEps))
            ->sortBy(fn ($q) => $q['year'].str_pad((string) $q['quarter'], 2, '0', STR_PAD_LEFT))
            ->values();

        $bars = DB::table('price_bars')->where('instrument_id', $instrumentId)->where('interval', '1d')
            ->orderBy('bar_time')
            ->get(['bar_time', 'close'])
            ->map(fn ($b) => ['date' => CarbonImmutable::parse($b->bar_time)->toDateString(), 'close' => (float) $b->close])
            ->values();

        $kgvBars = [];
        $growthBars = [];
        foreach ($byYearQuarter as $i => $q) {
            $label = 'Q'.$q['quarter'].' \''.substr((string) $q['year'], -2);

            $price = $this->closeOnOrAfter($bars, $q['date']->toDateString());
            $kgvBars[] = [
                'label' => $label,
                'value' => ($price !== null && $q['eps'] > 0) ? round($price / ($q['eps'] * 4), 1) : null,
            ];

            $priorYear = $byYearQuarter->first(fn ($p) => $p['year'] === $q['year'] - 1 && $p['quarter'] === $q['quarter']);
            $growthBars[] = [
                'label' => $label,
                'value' => ($priorYear !== null && $priorYear['eps'] != 0.0)
                    ? round((($q['eps'] - $priorYear['eps']) / abs($priorYear['eps'])) * 100, 1)
                    : null,
            ];
        }

        $kgvBars = array_slice($kgvBars, -self::TREND_QUARTERS_SHOWN);
        $growthBars = array_slice($growthBars, -self::TREND_QUARTERS_SHOWN);

        // Dividendenrendite per REAL payment (instrument_dividends), not the
        // instrument_fundamentals snapshot - that table has no history at
        // all, whereas dividend payments are genuine dated events, even
        // though most instruments only have 1-2 of them on record. Yield
        // here is this single payment over the price near its ex-date, not
        // annualized - we don't reliably know the payment frequency.
        $dividendBars = DB::table('instrument_dividends')->where('instrument_id', $instrumentId)
            ->whereNotNull('amount')->whereNotNull('ex_date')
            ->orderBy('ex_date')
            ->get(['ex_date', 'amount'])
            ->map(function ($d) use ($bars) {
                $date = CarbonImmutable::parse($d->ex_date);
                $price = $this->closeOnOrAfter($bars, $date->toDateString());

                return [
                    'label' => $date->format('d.m.').'\''.$date->format('y'),
                    'value' => ($price !== null && $price > 0) ? round(((float) $d->amount / $price) * 100, 2) : null,
                ];
            })
            ->values()->all();
        $dividendBars = array_slice($dividendBars, -self::TREND_QUARTERS_SHOWN);

        $fundamental = DB::table('instrument_fundamentals')->where('instrument_id', $instrumentId)
            ->orderByDesc('snapshot_date')->orderByDesc('id')
            ->first(['return_on_equity']);

        return [
            'trailing_pe' => ['label' => __('KGV'), 'unit' => 'x', 'bars' => $kgvBars],
            'earnings_growth' => ['label' => __('Gewinnwachstum'), 'unit' => '%', 'bars' => $growthBars],
            'return_on_equity' => [
                'label' => __('ROE'), 'unit' => '%',
                'bars' => [['label' => __('aktuell'), 'value' => $fundamental?->return_on_equity !== null ? round((float) $fundamental->return_on_equity * 100, 1) : null]],
            ],
            'dividend_yield' => ['label' => __('Dividendenrendite'), 'unit' => '%', 'bars' => $dividendBars],
        ];
    }

    private function closeOnOrAfter(Collection $bars, string $dateIso): ?float
    {
        $idx = $bars->search(fn ($b) => $b['date'] >= $dateIso);

        return $idx === false ? null : $bars[$idx]['close'];
    }

    private function buildQuarter(int $quarter, object $event, ?object $reaction, Collection $bars, ?float $medianAbsEps): array
    {
        $eventDate = CarbonImmutable::parse($event->event_date);
        $eps = ['estimate' => $event->eps_estimate, 'actual' => $event->eps_actual, 'surprise' => $event->surprise_percent];

        // A known scale-mismatch pattern (EPS a full order of magnitude off
        // this instrument's own other reported quarters) - surface as
        // "unreliable" rather than a plausible-looking wrong number.
        $unreliable = $this->looksImplausible($event->eps_actual, $medianAbsEps);

        $candles = null;
        $postReturn = $reaction?->return_post_3d !== null ? (float) $reaction->return_post_3d : null;
        $preReturn = $reaction?->return_pre_3d !== null ? (float) $reaction->return_pre_3d : null;

        if (! $unreliable) {
            $candles = $this->candleWindow($bars, $eventDate->toDateString());
        }

        return [
            'quarter' => $quarter,
            'has_data' => true,
            'unreliable' => $unreliable,
            'date' => $eventDate,
            'eps_estimate' => $eps['estimate'],
            'eps_actual' => $eps['actual'],
            'surprise_percent' => $eps['surprise'],
            'is_beat' => $eps['surprise'] !== null && (float) $eps['surprise'] >= 0,
            'return_pre_3d' => $preReturn,
            'return_post_3d' => $postReturn,
            'candles' => $candles,
        ];
    }

    private function looksImplausible(?float $epsActual, ?float $medianAbsEps): bool
    {
        if ($epsActual === null || $medianAbsEps === null || $epsActual === 0.0) {
            return false;
        }

        $ratio = abs($epsActual) / $medianAbsEps;

        return $ratio < 0.15 || $ratio > 6.0;
    }

    private function medianAbsEps(Collection $epsValues): ?float
    {
        $values = $epsValues->filter(fn ($v) => $v !== null)->map(fn ($v) => abs((float) $v))->filter(fn ($v) => $v > 0)->sort()->values();
        if ($values->count() < 3) {
            return null;
        }

        $mid = intdiv($values->count(), 2);

        return $values->count() % 2 === 0 ? ($values[$mid - 1] + $values[$mid]) / 2 : $values[$mid];
    }

    private function candleWindow(Collection $bars, string $eventDateIso): ?array
    {
        $idx = $bars->search(fn ($b) => $b['date'] >= $eventDateIso);
        if ($idx === false || $idx < self::CANDLE_WINDOW_DAYS) {
            return null;
        }

        $window = $bars->slice($idx - self::CANDLE_WINDOW_DAYS, self::CANDLE_WINDOW_DAYS * 2 + 1)->values();
        if ($window->count() < self::CANDLE_WINDOW_DAYS * 2 + 1) {
            return null;
        }

        $eventBarDate = $window[self::CANDLE_WINDOW_DAYS]['date'];

        return $window->map(fn ($bar) => [
            'd' => CarbonImmutable::parse($bar['date'])->format('m-d').($bar['date'] === $eventBarDate ? '*' : ''),
            'o' => round($bar['open'], 2), 'h' => round($bar['high'], 2),
            'l' => round($bar['low'], 2), 'c' => round($bar['close'], 2),
        ])->values()->all();
    }
}
