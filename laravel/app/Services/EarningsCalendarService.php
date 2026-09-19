<?php

namespace App\Services;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Upcoming earnings dates across the whole stock universe, grouped into
 * weekly buckets for a calendar-style bar chart plus a flat chronological
 * list underneath. Source is corporate_events rows with a future event_date
 * and no eps_actual yet (scheduled, not yet reported) - the only kind of
 * "upcoming appointment" this app tracks. Coverage is inherently sparse
 * (TwelveData's earnings_calendar endpoint is capped and near-randomly
 * covers the universe, see BackfillShortHistoryStocks/SyncTwelveDataEarningsPerSymbol) -
 * most weeks will show 0-2 events, which is an honest reflection of the
 * data, not a bug.
 */
class EarningsCalendarService
{
    private const WEEKS_SHOWN = 20;

    public function upcoming(): array
    {
        $today = CarbonImmutable::now()->startOfDay();
        $weekStart = $today->startOfWeek();
        $rangeEnd = $weekStart->addWeeks(self::WEEKS_SHOWN);

        $events = DB::table('corporate_events as e')
            ->join('instruments as i', 'i.id', '=', 'e.instrument_id')
            ->where('e.event_type', 'earnings')
            ->whereNull('e.eps_actual')
            ->whereDate('e.event_date', '>=', $today->toDateString())
            ->whereDate('e.event_date', '<', $rangeEnd->toDateString())
            ->where('i.type', 'stock')->whereNull('i.deleted_at')
            ->orderBy('e.event_date')
            ->get(['e.event_date', 'e.event_time', 'i.symbol', 'i.name', 'i.sector']);

        $byWeekStart = $events->groupBy(fn ($e) => CarbonImmutable::parse($e->event_date)->startOfWeek()->toDateString());

        $weeks = [];
        $maxCount = max(1, $byWeekStart->map->count()->max() ?? 1);
        for ($w = 0; $w < self::WEEKS_SHOWN; $w++) {
            $start = $weekStart->addWeeks($w);
            $end = $start->addDays(6);
            $key = $start->toDateString();
            $count = $byWeekStart->get($key, collect())->count();

            $weeks[] = [
                'label' => 'KW '.$start->format('W'),
                'range' => $start->format('d.m.').'–'.$end->format('d.m.'),
                'is_current' => $start->lte($today) && $today->lte($end),
                'count' => $count,
                'intensity' => $count / $maxCount,
            ];
        }

        $list = $events->map(fn ($e) => [
            'symbol' => $e->symbol,
            'name' => $e->name,
            'sector' => $e->sector,
            'date' => CarbonImmutable::parse($e->event_date),
            'time' => $e->event_time,
            'days_until' => $today->diffInDays(CarbonImmutable::parse($e->event_date)->startOfDay(), false),
        ])->values()->all();

        return [
            'weeks' => $weeks,
            'events' => $list,
            'total' => $events->count(),
            'range_end' => $rangeEnd->subDay(),
        ];
    }

    /** @return array{date: CarbonImmutable, time: ?string, days_until: int}|null the single nearest scheduled-but-not-yet-reported earnings date for one instrument, or null if none is on record. */
    public function nextForInstrument(int $instrumentId): ?array
    {
        $today = CarbonImmutable::now()->startOfDay();

        $event = DB::table('corporate_events')
            ->where('instrument_id', $instrumentId)
            ->where('event_type', 'earnings')
            ->whereNull('eps_actual')
            ->whereDate('event_date', '>=', $today->toDateString())
            ->orderBy('event_date')
            ->first(['event_date', 'event_time']);

        if ($event === null) {
            return null;
        }

        $date = CarbonImmutable::parse($event->event_date);

        return [
            'date' => $date,
            'time' => $event->event_time,
            'days_until' => $today->diffInDays($date->startOfDay(), false),
        ];
    }
}
