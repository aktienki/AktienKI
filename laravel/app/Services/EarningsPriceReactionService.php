<?php

namespace App\Services;

use App\Models\CorporateEvent;
use App\Models\EarningsPriceReaction;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Fills earnings_price_reactions from corporate_events + price_bars:
 * every earnings event that either has no reaction row yet, or whose row
 * is still marked incomplete (not enough forward trading days existed the
 * last time it was computed), gets (re)computed. Batches by instrument so
 * each instrument's price history is fetched once, not once per event.
 */
class EarningsPriceReactionService
{
    public function __construct(private readonly EarningsPriceReactionCalculator $calculator) {}

    /**
     * @return array{processed: int, computed: int, skipped: int}
     */
    public function sync(int $limit = 2000): array
    {
        $pendingEventIds = CorporateEvent::query()
            ->where('event_type', 'earnings')
            ->where(function ($query): void {
                $query->whereDoesntHave('reaction')
                    ->orWhereHas('reaction', fn ($reaction) => $reaction->where('is_complete', false));
            })
            ->orderBy('event_date')
            ->limit($limit)
            ->pluck('id');

        if ($pendingEventIds->isEmpty()) {
            return ['processed' => 0, 'computed' => 0, 'skipped' => 0];
        }

        $events = CorporateEvent::query()->whereIn('id', $pendingEventIds)->get()->groupBy('instrument_id');

        $computed = 0;
        $skipped = 0;

        foreach ($events as $instrumentId => $instrumentEvents) {
            $bars = DB::table('price_bars')
                ->where('instrument_id', $instrumentId)
                ->where('interval', '1d')
                ->orderBy('bar_time')
                ->get(['bar_time', 'close']);

            foreach ($instrumentEvents as $event) {
                $result = $this->calculator->compute($bars, CarbonImmutable::parse($event->event_date));

                if ($result === null) {
                    $skipped++;

                    continue;
                }

                EarningsPriceReaction::updateOrCreate(
                    ['corporate_event_id' => $event->id],
                    [
                        'instrument_id' => $event->instrument_id,
                        'event_date' => $event->event_date,
                        'surprise_percent' => $event->surprise_percent,
                        'eps_estimate' => $event->eps_estimate,
                        'eps_actual' => $event->eps_actual,
                        ...$result,
                        'computed_at' => now(),
                    ],
                );
                $computed++;
            }
        }

        return ['processed' => $pendingEventIds->count(), 'computed' => $computed, 'skipped' => $skipped];
    }
}
