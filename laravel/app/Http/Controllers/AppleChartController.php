<?php

namespace App\Http\Controllers;

use App\Models\Company;
use Carbon\CarbonImmutable;
use Illuminate\View\View;

class AppleChartController extends Controller
{
    public function __invoke(): View
    {
        $company = Company::query()->where('symbol', 'AAPL')->first();
        $bars = $company?->marketData()->orderByDesc('date')->limit(180)->get()->reverse()->values();
        $isDemo = ! $bars || $bars->isEmpty();

        if ($isDemo) {
            $start = CarbonImmutable::today()->subWeekdays(89);
            $points = collect(range(0, 89))->map(function (int $index) use ($start): array {
                $trend = 184 + ($index * .22);
                $wave = sin($index / 5) * 4.8 + cos($index / 11) * 2.7;

                return [$start->addWeekdays($index)->getTimestampMs(), round($trend + $wave, 2)];
            });
        } else {
            $points = $bars->map(fn ($bar): array => [$bar->date->getTimestampMs(), round($bar->close, 2)]);
        }

        $first = (float) $points->first()[1];
        $last = (float) $points->last()[1];
        $change = $first !== 0.0 ? (($last - $first) / $first) * 100 : 0.0;

        return view('stocks.apple', [
            'company' => $company,
            'points' => $points,
            'isDemo' => $isDemo,
            'lastPrice' => $last,
            'change' => $change,
            'periodHigh' => $points->max(fn (array $point) => $point[1]),
            'periodLow' => $points->min(fn (array $point) => $point[1]),
        ]);
    }
}
