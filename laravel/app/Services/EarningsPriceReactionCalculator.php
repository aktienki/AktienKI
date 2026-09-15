<?php

namespace App\Services;

use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Pure computation: given an instrument's daily closing prices (already
 * fetched and sorted by bar_time) and an earnings event date, computes the
 * price move 3 trading days before publication and 3 trading days after -
 * trading-day indexed, not calendar-day, since price_bars only has
 * trading sessions and weekends/holidays would otherwise skew a fixed
 * 3-day calendar window.
 */
class EarningsPriceReactionCalculator
{
    public const WINDOW_DAYS = 3;

    /**
     * @param  Collection<int, object{bar_time: string, close: string|float}>  $bars  Sorted ascending by bar_time.
     * @return array{close_at_event: float, return_pre_3d: ?float, return_post_3d: ?float, is_complete: bool}|null
     */
    public function compute(Collection $bars, CarbonImmutable $eventDate): ?array
    {
        if ($bars->isEmpty()) {
            return null;
        }

        $anchorIndex = $bars->search(
            fn (object $bar): bool => CarbonImmutable::parse($bar->bar_time)->startOfDay()->gte($eventDate->startOfDay()),
        );

        if ($anchorIndex === false) {
            // No trading day on or after the event yet - too fresh to react to.
            return null;
        }

        $closeAtEvent = (float) $bars[$anchorIndex]->close;

        $preIndex = $anchorIndex - self::WINDOW_DAYS;
        $postIndex = $anchorIndex + self::WINDOW_DAYS;

        $returnPre3d = $preIndex >= 0 ? $this->percentChange((float) $bars[$preIndex]->close, $closeAtEvent) : null;
        $returnPost3d = $postIndex < $bars->count() ? $this->percentChange($closeAtEvent, (float) $bars[$postIndex]->close) : null;

        return [
            'close_at_event' => $closeAtEvent,
            'return_pre_3d' => $returnPre3d,
            'return_post_3d' => $returnPost3d,
            'is_complete' => $returnPre3d !== null && $returnPost3d !== null,
        ];
    }

    private function percentChange(float $from, float $to): ?float
    {
        if ($from == 0.0) {
            return null;
        }

        return round((($to - $from) / $from) * 100, 4);
    }
}
