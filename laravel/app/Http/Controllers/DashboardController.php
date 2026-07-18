<?php

namespace App\Http\Controllers;

use App\Models\Instrument;
use App\Models\MarketSnapshot;
use App\Models\ModelChampion;
use App\Models\Prediction;
use App\Models\TrainingRun;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class DashboardController extends Controller
{
    public function __invoke(): View
    {
        $snapshot = MarketSnapshot::query()
            ->with([
                'assets' => fn ($query) => $query
                    ->orderBy('category')
                    ->orderBy('name'),
                'sectors' => fn ($query) => $query
                    ->orderBy('rank')
                    ->limit(8),
                'statistics',
            ])
            ->latest('snapshot_time')
            ->latest('id')
            ->first();

        $topSignals = Prediction::query()
            ->with('instrument')
            ->whereNotNull('ai_score')
            ->whereHas('instrument')
            ->latest('prediction_time')
            ->orderByDesc('ai_score')
            ->limit(40)
            ->get()
            ->unique('instrument_id')
            ->take(5)
            ->values();

        $marketCards = $this->buildMarketCards(
            $snapshot?->assets ?? collect()
        );

        $latestRun = TrainingRun::query()
            ->with(['trainedModel.definition', 'instrument'])
            ->latest('started_at')
            ->latest('id')
            ->first();

        $champions = ModelChampion::query()
            ->with(['activeModel.definition', 'instrument'])
            ->where('status', 'active')
            ->latest('activated_at')
            ->limit(5)
            ->get();

        $signalCounts = $this->signalCounts($snapshot);
        $signalTotal = max(1, array_sum($signalCounts));

        return view('dashboard', [
            'snapshot' => $snapshot,
            'marketAssets' => $snapshot?->assets ?? collect(),
            'marketCards' => $marketCards,
            'sectorSnapshots' => $snapshot?->sectors ?? collect(),
            'topSignals' => $topSignals,
            'latestRun' => $latestRun,
            'champions' => $champions,
            'signalCounts' => $signalCounts,
            'signalTotal' => $signalTotal,
            'instrumentCount' => Instrument::query()
                ->where('is_active', true)
                ->count(),
        ]);
    }

    /**
     * @param Collection<int, mixed> $assets
     * @return Collection<int, array<string, mixed>>
     */
    private function buildMarketCards(Collection $assets): Collection
    {
        $usTop = $this->topPredictionForCountries([
            'United States',
            'USA',
            'US',
            'United States of America',
        ]);

        $usSecond = $this->topPredictionForCountries(
            ['United States', 'USA', 'US', 'United States of America'],
            $usTop?->instrument_id
        );

        return collect([
            [
                'key' => 'dax',
                'name' => 'DAX',
                'icon' => '🇩🇪',
                'asset' => $this->findAsset(
                    $assets,
                    ['^GDAXI'],
                    ['DAX']
                ),
                'top_stock' => $this->topPredictionForCountries([
                    'Germany',
                    'Deutschland',
                    'DE',
                ]),
            ],
            [
                'key' => 'sp500',
                'name' => 'S&P 500',
                'icon' => '🇺🇸',
                'asset' => $this->findAsset(
                    $assets,
                    ['^GSPC'],
                    ['S&P 500', 'SP500']
                ),
                'top_stock' => $usTop,
            ],
            [
                'key' => 'nasdaq',
                'name' => 'NASDAQ',
                'icon' => '🇺🇸',
                'asset' => $this->findAsset(
                    $assets,
                    ['^IXIC'],
                    ['NASDAQ']
                ),
                'top_stock' => $usSecond ?? $usTop,
            ],
            [
                'key' => 'china',
                'name' => 'China',
                'icon' => '🇨🇳',
                'asset' => $this->findAsset(
                    $assets,
                    ['000001.SS', '^SSEC', '000300.SS', '^HSI'],
                    ['SHANGHAI', 'CSI 300', 'HANG SENG', 'CHINA']
                ),
                'top_stock' => $this->topPredictionForCountries([
                    'China',
                    'CN',
                    'Hong Kong',
                    'Hongkong',
                    'HK',
                ]),
            ],
            [
                'key' => 'japan',
                'name' => 'Nikkei 225',
                'icon' => '🇯🇵',
                'asset' => $this->findAsset(
                    $assets,
                    ['^N225'],
                    ['NIKKEI']
                ),
                'top_stock' => $this->topPredictionForCountries([
                    'Japan',
                    'JP',
                ]),
            ],
        ]);
    }

    /**
     * @param Collection<int, mixed> $assets
     * @param array<int, string> $symbols
     * @param array<int, string> $nameFragments
     */
    private function findAsset(
        Collection $assets,
        array $symbols,
        array $nameFragments
    ): mixed {
        $asset = $assets->first(function ($item) use ($symbols): bool {
            return in_array(
                strtoupper((string) ($item->symbol ?? '')),
                array_map('strtoupper', $symbols),
                true
            );
        });

        if ($asset) {
            return $asset;
        }

        return $assets->first(function ($item) use ($nameFragments): bool {
            $name = strtoupper((string) ($item->name ?? ''));

            foreach ($nameFragments as $fragment) {
                if (str_contains($name, strtoupper($fragment))) {
                    return true;
                }
            }

            return false;
        });
    }

    /**
     * @param array<int, string> $countries
     */
    private function topPredictionForCountries(
        array $countries,
        ?int $excludeInstrumentId = null
    ): ?Prediction {
        return Prediction::query()
            ->with('instrument')
            ->whereNotNull('ai_score')
            ->whereHas('instrument', function (Builder $query) use ($countries): void {
                $query->where(function (Builder $countryQuery) use ($countries): void {
                    foreach ($countries as $index => $country) {
                        $method = $index === 0 ? 'whereRaw' : 'orWhereRaw';
                        $countryQuery->{$method}(
                            'LOWER(TRIM(country)) = ?',
                            [mb_strtolower(trim($country))]
                        );
                    }
                });
            })
            ->when(
                $excludeInstrumentId,
                fn (Builder $query) => $query
                    ->where('instrument_id', '!=', $excludeInstrumentId)
            )
            ->latest('prediction_time')
            ->orderByDesc('ai_score')
            ->first();
    }

    /**
     * @return array{buy:int,hold:int,sell:int}
     */
    private function signalCounts(?MarketSnapshot $snapshot): array
    {
        if ($snapshot) {
            return [
                'buy' => (int) $snapshot->buy_signals,
                'hold' => (int) $snapshot->hold_signals,
                'sell' => (int) $snapshot->sell_signals,
            ];
        }

        $counts = Prediction::query()
            ->selectRaw(
                'UPPER(signal) AS normalized_signal, COUNT(*) AS aggregate'
            )
            ->whereNotNull('signal')
            ->groupByRaw('UPPER(signal)')
            ->pluck('aggregate', 'normalized_signal');

        return [
            'buy' => (int) ($counts['BUY'] ?? 0),
            'hold' => (int) ($counts['HOLD'] ?? 0),
            'sell' => (int) ($counts['SELL'] ?? 0),
        ];
    }
}
