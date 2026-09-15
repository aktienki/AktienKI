<?php

namespace App\Services;

use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Pure computation: given an instrument's daily closing prices (already
 * fetched and sorted by bar_time) and an earnings event date, finds the
 * trading-day-indexed close at/after the event and the pre/post returns
 * around it. Trading-day indexed, not calendar-day - price_bars only has
 * trading days, so "+20 days" here means 20 trading sessions, matching
 * how the panel model's own return horizons are defined elsewhere.
 */
class EarningsPriceReactionCalculator
{
    /** @var list<int> */
    public const FORWARD_HORIZONS = [1, 5, 10, 20, 40];

    public const PRE_WINDOW = 5;

    /**
     * @param  Collection<int, object{bar_time: string, close: string|float}>  $bars  Sorted ascending by bar_time.
     * @return array{close_at_event: float, return_pre_5d: ?float, return_1d: ?float, return_5d: ?float, return_10d: ?float, return_20d: ?float, return_40d: ?float, is_complete: bool}|null
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
        $preIndex = $anchorIndex - self::PRE_WINDOW;

        $returns = [];
        $isComplete = true;
        foreach (self::FORWARD_HORIZONS as $horizon) {
            $index = $anchorIndex + $horizon;
            if ($index < $bars->count()) {
                $returns[$horizon] = $this->percentChange($closeAtEvent, (float) $bars[$index]->close);
            } else {
                $returns[$horizon] = null;
                $isComplete = false;
            }
        }

        return [
            'close_at_event' => $closeAtEvent,
            'return_pre_5d' => $preIndex >= 0 ? $this->percentChange((float) $bars[$preIndex]->close, $closeAtEvent) : null,
            'return_1d' => $returns[1],
            'return_5d' => $returns[5],
            'return_10d' => $returns[10],
            'return_20d' => $returns[20],
            'return_40d' => $returns[40],
            'is_complete' => $isComplete,
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
