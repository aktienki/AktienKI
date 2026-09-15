<?php

namespace App\Services;

use App\Models\EarningsPriceReaction;
use Illuminate\Support\Collection;

/**
 * Per-stock historical earnings-reaction stats from earnings_price_reactions
 * - the basis for a stock-specific forecast ("this stock has historically
 * risen ~X% in the 3 days after a beat, and fallen ~Y% after a miss").
 * Shared by earnings:drift-report-by-stock and the concept dashboard's
 * "Quartalszahlen-Historie" tab so both read the exact same numbers.
 */
class EarningsDriftStatsService
{
    /**
     * @param  list<int>  $instrumentIds
     * @return Collection<int, array{instrumentId: int, symbol: string, name: string, n: int, pre3d: ?float, post3d: ?float, post3dBeat: ?float, post3dMiss: ?float, beatCount: int, missCount: int, tendency: string, events: list<array{date: string, epsEstimate: ?float, epsActual: ?float, surprisePercent: ?float, pre3d: ?float, post3d: ?float}>}> Keyed by instrument_id.
     */
    public function forInstruments(array $instrumentIds): Collection
    {
        if ($instrumentIds === []) {
            return collect();
        }

        return $this->baseQuery()
            ->whereIn('instrument_id', $instrumentIds)
            ->get()
            ->groupBy('instrument_id')
            ->map(fn (Collection $group): array => $this->statsFor($group));
    }

    /**
     * @param  int  $minSample  Minimum number of past events a stock needs to be included at all.
     * @return Collection<int, array{instrumentId: int, symbol: string, name: string, n: int, pre3d: ?float, post3d: ?float, post3dBeat: ?float, post3dMiss: ?float, beatCount: int, missCount: int, tendency: string, events: list<array{date: string, epsEstimate: ?float, epsActual: ?float, surprisePercent: ?float, pre3d: ?float, post3d: ?float}>}> Keyed by instrument_id.
     */
    public function forAllStocks(int $minSample = 1, ?string $symbol = null): Collection
    {
        $query = $this->baseQuery();

        if ($symbol !== null) {
            $query->whereHas('instrument', fn ($q) => $q->where('symbol', $symbol));
        }

        return $query->get()
            ->groupBy('instrument_id')
            ->filter(fn (Collection $group): bool => $group->count() >= max(1, $minSample))
            ->map(fn (Collection $group): array => $this->statsFor($group));
    }

    private function baseQuery()
    {
        return EarningsPriceReaction::query()
            ->with('instrument:id,symbol,name')
            ->whereNotNull('surprise_percent')
            ->whereHas('instrument');
    }

    /**
     * @return array{instrumentId: int, symbol: string, name: string, n: int, pre3d: ?float, post3d: ?float, post3dBeat: ?float, post3dMiss: ?float, beatCount: int, missCount: int, tendency: string, events: list<array{date: string, epsEstimate: ?float, epsActual: ?float, surprisePercent: ?float, pre3d: ?float, post3d: ?float}>}
     */
    private function statsFor(Collection $group): array
    {
        $instrument = $group->first()->instrument;
        $beats = $group->filter(fn (EarningsPriceReaction $r): bool => $r->surprise_percent > 0);
        $misses = $group->filter(fn (EarningsPriceReaction $r): bool => $r->surprise_percent <= 0);

        $avgPost3dBeat = $this->average($beats->pluck('return_post_3d'));
        $avgPost3dMiss = $this->average($misses->pluck('return_post_3d'));

        return [
            'instrumentId' => $instrument->id,
            'symbol' => $instrument->symbol,
            'name' => $instrument->name ?: $instrument->symbol,
            'n' => $group->count(),
            'pre3d' => $this->average($group->pluck('return_pre_3d')),
            'post3d' => $this->average($group->pluck('return_post_3d')),
            'post3dBeat' => $avgPost3dBeat,
            'post3dMiss' => $avgPost3dMiss,
            'beatCount' => $beats->count(),
            'missCount' => $misses->count(),
            'tendency' => $this->tendency($avgPost3dBeat, $avgPost3dMiss, $beats->count(), $misses->count()),
            // The individual events behind the averages above - every past
            // report this stock's stats were computed from, newest first.
            'events' => $group->sortByDesc('event_date')->map(fn (EarningsPriceReaction $r): array => [
                'date' => $r->event_date->toDateString(),
                'epsEstimate' => $r->eps_estimate,
                'epsActual' => $r->eps_actual,
                'surprisePercent' => $r->surprise_percent,
                'pre3d' => $r->return_pre_3d,
                'post3d' => $r->return_post_3d,
            ])->values()->all(),
        ];
    }

    private function tendency(?float $avgPost3dBeat, ?float $avgPost3dMiss, int $beatCount, int $missCount): string
    {
        if ($beatCount === 0 || $missCount === 0 || $avgPost3dBeat === null || $avgPost3dMiss === null) {
            return 'Zu wenig Historie';
        }

        if ($avgPost3dBeat > 0 && $avgPost3dMiss < 0) {
            return 'Reagiert erwartungsgemäß';
        }

        if ($avgPost3dBeat <= 0 && $avgPost3dMiss >= 0) {
            return 'Reagiert gegenläufig';
        }

        return 'Uneinheitlich';
    }

    private function average(Collection $values): ?float
    {
        $filtered = $values->filter(fn ($value) => $value !== null);

        return $filtered->isEmpty() ? null : $filtered->avg();
    }
}
