<?php

// app/Services/YahooFinanceService.php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class YahooFinanceService
{
    public function quote(string $symbol): ?array
    {
        return Cache::remember(
            "quote_{$symbol}",
            now()->addSeconds(10),
            function () use ($symbol) {

                $response = Http::withHeaders([
                    'User-Agent' => 'Mozilla/5.0',
                ])->timeout(10)->get(
                    "https://query1.finance.yahoo.com/v8/finance/chart/{$symbol}",
                    [
                        'interval' => '1m',
                        'range'    => '1d',
                    ]
                );

                if (!$response->successful()) {
                    return null;
                }

                $result = $response->json('chart.result.0');

                if (!$result) {
                    return null;
                }

                $meta = $result['meta'];

                $price = $meta['regularMarketPrice'] ?? null;
                $previous = $meta['chartPreviousClose'] ?? null;

                $change = null;
                $changePercent = null;

                if ($price && $previous) {
                    $change = $price - $previous;
                    $changePercent = ($change / $previous) * 100;
                }

                return [

                    'price' => $price,

                    'currency' => $meta['currency'] ?? '',

                    'change_percent' => $changePercent,

                    'timestamp' => $meta['regularMarketTime'] ?? null,

                ];
            }
        );
    }

    public function sparkline(string $symbol): array
    {
        return Cache::remember(
            "sparkline_{$symbol}",
            now()->addSeconds(10),
            function () use ($symbol) {

                $response = Http::withHeaders([
                    'User-Agent' => 'Mozilla/5.0',
                ])->timeout(10)->get(
                    "https://query1.finance.yahoo.com/v8/finance/chart/{$symbol}",
                    [
                        'interval' => '5m',
                        'range'    => '1d',
                    ]
                );

                if (!$response->successful()) {
                    return [];
                }

                $result = $response->json('chart.result.0');

                if (!$result) {
                    return [];
                }

                $close = $result['indicators']['quote'][0]['close'] ?? [];

                return collect($close)
                    ->filter()
                    ->map(fn ($value) => round((float) $value, 2))
                    ->values()
                    ->all();
            }
        );
    }

    public function candles(string $symbol): array
    {
        return Cache::remember(
            "candles_{$symbol}",
            now()->addSeconds(10),
            function () use ($symbol) {
                $response = Http::withHeaders([
                    'User-Agent' => 'Mozilla/5.0',
                ])->timeout(10)->get(
                    "https://query1.finance.yahoo.com/v8/finance/chart/{$symbol}",
                    [
                        'interval' => '1h',
                        'range' => '5d',
                    ]
                );

                if (! $response->successful()) {
                    return [];
                }

                $result = $response->json('chart.result.0');

                if (! $result) {
                    return [];
                }

                $quotes = $result['indicators']['quote'][0] ?? [];
                $timestamps = $result['timestamp'] ?? [];

                if (! $timestamps) {
                    return [];
                }

                return collect($timestamps)
                    ->map(function ($timestamp, $index) use ($quotes) {
                        $values = [
                            $quotes['open'][$index] ?? null,
                            $quotes['high'][$index] ?? null,
                            $quotes['low'][$index] ?? null,
                            $quotes['close'][$index] ?? null,
                        ];

                        if (in_array(null, $values, true)) {
                            return null;
                        }

                        return [
                            'x' => (int) $timestamp * 1000,
                            'y' => array_map(fn ($value) => round((float) $value, 2), $values),
                        ];
                    })
                    ->filter()
                    ->take(-48)
                    ->values()
                    ->all();
            }
        );
    }

    public function dailyCandles(string $symbol, int $days = 20): array
    {
        $days = max(5, min(120, $days));
        $cacheKey = 'daily_candles_'.sha1(strtoupper($symbol))."_{$days}";

        return Cache::remember(
            $cacheKey,
            now()->addMinutes(5),
            function () use ($symbol, $days) {
                $response = Http::withHeaders([
                    'User-Agent' => 'Mozilla/5.0',
                ])->timeout(10)->get(
                    'https://query1.finance.yahoo.com/v8/finance/chart/'.rawurlencode($symbol),
                    [
                        'interval' => '1d',
                        'range' => $days > 70 ? '6mo' : '3mo',
                    ]
                );

                if (! $response->successful()) {
                    return [];
                }

                $result = $response->json('chart.result.0');
                $quotes = $result['indicators']['quote'][0] ?? [];
                $adjustedCloses = $result['indicators']['adjclose'][0]['adjclose'] ?? [];
                $timestamps = $result['timestamp'] ?? [];

                if (! $timestamps) {
                    return [];
                }

                return collect($timestamps)
                    ->map(function ($timestamp, $index) use ($quotes, $adjustedCloses) {
                        $ohlc = [
                            $quotes['open'][$index] ?? null,
                            $quotes['high'][$index] ?? null,
                            $quotes['low'][$index] ?? null,
                            $quotes['close'][$index] ?? null,
                        ];

                        if (in_array(null, $ohlc, true)) {
                            return null;
                        }

                        return [
                            'timestamp' => (int) $timestamp,
                            'open' => (float) $ohlc[0],
                            'high' => (float) $ohlc[1],
                            'low' => (float) $ohlc[2],
                            'close' => (float) $ohlc[3],
                            'adjusted_close' => isset($adjustedCloses[$index])
                                ? (float) $adjustedCloses[$index]
                                : null,
                            'volume' => isset($quotes['volume'][$index])
                                ? (float) $quotes['volume'][$index]
                                : null,
                        ];
                    })
                    ->filter()
                    ->take(-$days)
                    ->values()
                    ->all();
            }
        );
    }
}
