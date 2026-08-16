<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

final class TechnicalPriceLevelService
{
    public function levels(int $instrumentId, int $limit = 180): array
    {
        $bars = DB::table('price_bars')
            ->where('instrument_id', $instrumentId)
            ->where('interval', '1d')
            ->orderByDesc('bar_time')->orderByDesc('id')->limit($limit)
            ->get(['high', 'low', 'close'])->reverse()->values();
        if ($bars->count() < 7) return ['support' => null, 'resistance' => null, 'broken_resistance' => null];

        $current = (float) $bars->last()->close;
        $range = max(0.01, (float) $bars->max('high') - (float) $bars->min('low'));
        $tolerance = max($range * .012, $current * .006);
        $supports = [];
        $resistances = [];

        for ($index = 2; $index < $bars->count() - 2; $index++) {
            $low = (float) $bars[$index]->low;
            $high = (float) $bars[$index]->high;
            if ($low <= (float) $bars[$index - 1]->low && $low <= (float) $bars[$index - 2]->low
                && $low <= (float) $bars[$index + 1]->low && $low <= (float) $bars[$index + 2]->low) {
                $supports[] = ['price' => $low, 'index' => $index];
            }
            if ($high >= (float) $bars[$index - 1]->high && $high >= (float) $bars[$index - 2]->high
                && $high >= (float) $bars[$index + 1]->high && $high >= (float) $bars[$index + 2]->high) {
                $resistances[] = ['price' => $high, 'index' => $index];
            }
        }

        return [
            'support' => $this->nearestZone($supports, $tolerance, $current, false),
            'resistance' => $this->nearestZone($resistances, $tolerance, $current, true),
            'broken_resistance' => $this->nearestZone($resistances, $tolerance, $current, false),
        ];
    }

    private function nearestZone(array $points, float $tolerance, float $current, bool $above): ?float
    {
        $zones = [];
        foreach ($points as $point) {
            $zoneIndex = collect($zones)->search(fn (array $zone): bool => abs($zone['price'] - $point['price']) <= $tolerance);
            if ($zoneIndex === false) {
                $zones[] = ['price' => $point['price'], 'points' => [$point]];
            } else {
                $zones[$zoneIndex]['points'][] = $point;
                $zones[$zoneIndex]['price'] = collect($zones[$zoneIndex]['points'])->avg('price');
            }
        }

        return collect($zones)
            ->filter(fn (array $zone): bool => count($zone['points']) >= 2 && ($above ? $zone['price'] >= $current : $zone['price'] <= $current))
            ->sortBy(fn (array $zone): float => abs($zone['price'] - $current))
            ->value('price');
    }
}
