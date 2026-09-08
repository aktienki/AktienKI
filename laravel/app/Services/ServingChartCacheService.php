<?php

namespace App\Services;

use Illuminate\Contracts\Cache\Repository;
use Illuminate\Support\Facades\Cache;
use Throwable;

final class ServingChartCacheService
{
    private const MAX_POINTS = 320;

    private const TRADING_DAYS = 280;

    public function __construct(private readonly TwelveDataService $marketData) {}

    public function providerSymbol(object $instrument): string
    {
        $metadata = $this->json($instrument->display_metadata ?? null);
        $trainingSymbol = trim((string) data_get($metadata, 'training_provider_symbol'));
        if ($trainingSymbol !== '') {
            return strtoupper($trainingSymbol);
        }

        $germanSymbol = trim((string) ($instrument->german_listing_symbol ?? ''));
        if ($germanSymbol !== '') {
            $exchange = trim((string) ($instrument->german_listing_exchange ?? ''));

            return strtoupper($germanSymbol.($exchange !== '' ? ':'.$exchange : ''));
        }

        return strtoupper(trim((string) (($instrument->provider_symbol ?? null) ?: $instrument->symbol)));
    }

    public function peek(int $instrumentId, string $providerSymbol): ?array
    {
        $payload = $this->cached($instrumentId, $providerSymbol);

        return is_array($payload) && ($payload['points'] ?? []) !== [] ? $payload : null;
    }

    public function load(int $instrumentId, string $providerSymbol, string $currency): array
    {
        $key = $this->key($instrumentId, $providerSymbol);
        if ($cached = $this->cached($instrumentId, $providerSymbol)) {
            return [...$cached, 'cache_hit' => true];
        }

        try {
            return $this->cache()->lock($key.':lock', 60)->block(15, function () use ($key, $instrumentId, $providerSymbol, $currency): array {
                if ($cached = $this->cached($instrumentId, $providerSymbol)) {
                    return [...$cached, 'cache_hit' => true];
                }

                $history = method_exists($this->marketData, 'chartHistory')
                    ? $this->marketData->chartHistory($providerSymbol, self::TRADING_DAYS)
                    : $this->marketData->dailyHistory($providerSymbol, self::TRADING_DAYS);
                $bars = collect($history)
                    ->filter(fn (array $bar): bool => is_numeric($bar['close'] ?? null) && is_numeric($bar['timestamp'] ?? null))
                    ->sortBy('timestamp')
                    ->values();

                if ($bars->isEmpty()) {
                    $empty = [
                        'instrument_id' => $instrumentId,
                        'provider_symbol' => $providerSymbol,
                        'currency' => $currency,
                        'points' => [],
                        'cached_at' => now()->toIso8601String(),
                        'cache_hit' => false,
                    ];
                    $this->cache()->put($key, $empty, now()->addMinutes(5));

                    return $empty;
                }

                if ($bars->count() > self::MAX_POINTS) {
                    $last = $bars->count() - 1;
                    $bars = collect(range(0, self::MAX_POINTS - 1))
                        ->map(fn (int $index): array => $bars->get((int) round($index * $last / (self::MAX_POINTS - 1))))
                        ->values();
                }

                $payload = [
                    'instrument_id' => $instrumentId,
                    'provider_symbol' => $providerSymbol,
                    'currency' => $currency,
                    'points' => $bars->map(fn (array $bar): array => [
                        'timestamp' => (int) $bar['timestamp'],
                        'open' => (float) ($bar['open'] ?? $bar['close']),
                        'high' => (float) ($bar['high'] ?? $bar['close']),
                        'low' => (float) ($bar['low'] ?? $bar['close']),
                        'close' => (float) $bar['close'],
                        'volume' => is_numeric($bar['volume'] ?? null) ? (float) $bar['volume'] : null,
                    ])->all(),
                    'cached_at' => now()->toIso8601String(),
                    'cache_hit' => false,
                ];

                $this->cache()->put(
                    $key,
                    $payload,
                    now()->addHours(max(1, (int) config('aktienki.serving.chart_cache_hours', 12))),
                );

                return $payload;
            });
        } catch (Throwable) {
            if ($cached = $this->cached($instrumentId, $providerSymbol)) {
                return [...$cached, 'cache_hit' => true];
            }

            $fallback = [
                'instrument_id' => $instrumentId,
                'provider_symbol' => $providerSymbol,
                'currency' => $currency,
                'points' => [],
                'cached_at' => now()->toIso8601String(),
                'cache_hit' => false,
            ];
            $this->cache()->put($key, $fallback, now()->addMinutes(5));

            return $fallback;
        }
    }

    private function key(int $instrumentId, string $providerSymbol): string
    {
        return 'serving.screener.chart.v2.'.$instrumentId.'.'.sha1(strtoupper($providerSymbol));
    }

    private function cached(int $instrumentId, string $providerSymbol): ?array
    {
        $payload = $this->cache()->get($this->key($instrumentId, $providerSymbol));

        return is_array($payload) ? $payload : null;
    }

    private function json(mixed $value): array
    {
        if (is_array($value)) {
            return $value;
        }
        if (is_object($value)) {
            return (array) $value;
        }

        $decoded = is_string($value) ? json_decode($value, true) : null;

        return is_array($decoded) ? $decoded : [];
    }

    private function cache(): Repository
    {
        return Cache::store((string) config('aktienki.serving.chart_cache_store', 'file'));
    }
}
