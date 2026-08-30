<?php

namespace App\Services;

use Carbon\CarbonImmutable;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Throwable;

class TwelveDataService
{
    public function usListing(?string $isin, string $name, string $symbol): ?array
    {
        foreach (array_filter([trim((string) $isin), trim($name), trim($symbol)]) as $term) {
            $response = $this->request('symbol_search', ['symbol' => $term, 'outputsize' => 120]);
            if (! $this->valid($response)) continue;

            $match = collect($response->json('data', []))
                ->filter(fn (array $item): bool => strtoupper((string) ($item['currency'] ?? '')) === 'USD')
                ->filter(fn (array $item): bool => strtolower((string) ($item['country'] ?? '')) === 'united states'
                    || in_array(strtoupper((string) ($item['mic_code'] ?? '')), ['XNAS', 'XNYS', 'ARCX', 'BATS'], true))
                ->sortBy(fn (array $item): int => match (strtoupper((string) ($item['mic_code'] ?? ''))) {
                    'XNAS' => 0, 'XNYS' => 1, 'ARCX' => 2, default => 3,
                })->first();

            if ($match) return [
                'symbol' => (string) $match['symbol'],
                'exchange' => (string) ($match['exchange'] ?? ''),
                'mic_code' => (string) ($match['mic_code'] ?? ''),
                'currency' => 'USD',
            ];
        }

        return null;
    }

    public function germanListing(?string $isin, string $name, string $symbol): ?array
    {
        foreach (array_filter([trim((string) $isin), trim($name), trim($symbol)]) as $term) {
            $response = $this->request('symbol_search', ['symbol' => $term, 'outputsize' => 120]);
            if (! $this->valid($response)) {
                continue;
            }

            $match = collect($response->json('data', []))
                ->filter(fn (array $item): bool => strtoupper((string) ($item['currency'] ?? '')) === 'EUR')
                ->filter(fn (array $item): bool => strtolower((string) ($item['country'] ?? '')) === 'germany'
                    || in_array(strtoupper((string) ($item['mic_code'] ?? '')), ['XETR', 'XFRA', 'XMUN', 'XBER', 'XDUS', 'XSTU', 'XHAM', 'XHAN'], true))
                ->sortBy(fn (array $item): int => match (strtoupper((string) ($item['mic_code'] ?? ''))) {
                    'XETR' => 0, 'XFRA' => 1, default => 2,
                })
                ->first();

            if ($match) {
                return [
                    'symbol' => (string) $match['symbol'],
                    'exchange' => (string) ($match['exchange'] ?? ''),
                    'mic_code' => (string) ($match['mic_code'] ?? ''),
                    'currency' => 'EUR',
                ];
            }
        }

        return null;
    }

    public function listingQuote(string $symbol, ?string $exchange = null): ?array
    {
        $providerSymbol = trim($symbol).($exchange ? ':'.trim($exchange) : '');
        $quote = $this->quote($providerSymbol);

        return $quote ?: $this->quote($symbol);
    }

    public function quotes(array $symbols): array
    {
        return collect($symbols)
            ->filter()
            ->unique()
            ->mapWithKeys(fn (string $symbol): array => [$symbol => $this->quote($symbol)])
            ->filter()
            ->all();
    }

    public function indexQuotes(array $symbols): array
    {
        if (! config('aktienki.twelve_data.indexes_enabled', false)) {
            return [];
        }

        return collect($symbols)
            ->filter()
            ->unique()
            ->mapWithKeys(function (string $symbol): array {
                $quote = Cache::store('file')->remember(
                    'twelve_data_index_quote_'.sha1(strtoupper($symbol)),
                    now()->addMinutes(5),
                    function () use ($symbol): ?array {
                        $response = $this->request('quote', [
                            'symbol' => $this->symbol($symbol),
                        ]);

                        if (! $this->valid($response)) {
                            return null;
                        }

                        $instrumentType = strtolower((string) $response->json('type'));
                        if (! str_contains($instrumentType, 'index')) {
                            return null;
                        }

                        $price = $this->number($response->json('close'));
                        $previous = $this->number($response->json('previous_close'));
                        $changePercent = $this->number($response->json('percent_change'));

                        if ($changePercent === null && $price !== null && $previous) {
                            $changePercent = (($price - $previous) / $previous) * 100;
                        }

                        return [
                            'price' => $price,
                            'currency' => (string) ($response->json('currency') ?? ''),
                            'change_percent' => $changePercent,
                            'timestamp' => $response->json('timestamp'),
                        ];
                    },
                );

                return $quote ? [$symbol => $quote] : [];
            })
            ->all();
    }

    public function quote(string $symbol): ?array
    {
        return Cache::store('file')->remember(
            'twelve_data_quote_'.sha1(strtoupper($symbol)),
            now()->addMinutes(5),
            fn (): ?array => $this->fetchQuote($symbol),
        );
    }

    public function liveQuote(string $symbol): ?array
    {
        return Cache::store('file')->remember(
            'twelve_data_live_quote_'.sha1(strtoupper($symbol)),
            now()->addSeconds(15),
            fn (): ?array => $this->fetchQuote($symbol),
        );
    }

    public function sparkline(string $symbol): array
    {
        return Cache::store('file')->remember(
            'twelve_data_sparkline_'.sha1(strtoupper($symbol)),
            now()->addMinute(),
            fn (): array => collect($this->timeSeries($symbol, '5min', 48)['values'] ?? [])
                ->reverse()
                ->pluck('close')
                ->filter(fn ($value): bool => is_numeric($value))
                ->map(fn ($value): float => round((float) $value, 2))
                ->values()
                ->all(),
        );
    }

    public function candles(string $symbol): array
    {
        return Cache::store('file')->remember(
            'twelve_data_candles_'.sha1(strtoupper($symbol)),
            now()->addMinute(),
            function () use ($symbol): array {
                $series = $this->timeSeries($symbol, '1h', 48);

                return $this->ohlc($series)
                    ->map(fn (array $bar): array => [
                        'x' => $bar['timestamp'] * 1000,
                        'y' => [
                            round($bar['open'], 2),
                            round($bar['high'], 2),
                            round($bar['low'], 2),
                            round($bar['close'], 2),
                        ],
                    ])
                    ->all();
            },
        );
    }

    public function dailyCandles(string $symbol, int $days = 20): array
    {
        $days = max(5, min(120, $days));

        return Cache::store('file')->remember(
            'twelve_data_daily_'.sha1(strtoupper($symbol))."_{$days}",
            now()->addMinutes(15),
            function () use ($symbol, $days): array {
                $series = $this->timeSeries($symbol, '1day', $days, ['adjust' => 'all']);

                return $this->ohlc($series)
                    ->map(fn (array $bar): array => [
                        ...$bar,
                        'adjusted_close' => $bar['close'],
                    ])
                    ->all();
            },
        );
    }

    public function dailyHistory(string $symbol, int $tradingDays = 800): array
    {
        $tradingDays = max(20, min(5000, $tradingDays));

        return Cache::store('file')->remember(
            'twelve_data_daily_history_'.sha1(strtoupper($symbol))."_{$tradingDays}",
            now()->addDay(),
            fn (): array => $this->ohlc($this->timeSeries($symbol, '1day', $tradingDays, ['adjust' => 'all']))
                ->map(fn (array $bar): array => [...$bar, 'adjusted_close' => $bar['close']])
                ->all(),
        );
    }

    /**
     * Load chart candles without persisting market history in PostgreSQL.
     *
     * The short-lived file cache keeps the web request fast on this single
     * application server. A longer stale copy preserves chart availability
     * during a temporary provider outage without growing the database cache.
     */
    public function chartHistory(
        string $symbol,
        int $tradingDays = 300,
        ?CarbonImmutable $focusAt = null,
    ): array {
        $tradingDays = max(20, min(5000, $tradingDays));
        $normalizedSymbol = strtoupper(trim($symbol));
        $focusKey = $focusAt?->format('Y-m-d') ?? 'latest';
        $cacheKey = 'twelve_data_chart_history_v1_'.sha1($normalizedSymbol."|{$tradingDays}|{$focusKey}");
        $staleKey = $cacheKey.'_stale';
        $cache = Cache::store('file');
        $cached = $cache->get($cacheKey);

        if (is_array($cached)) {
            return $cached;
        }

        try {
            $parameters = ['adjust' => 'all'];
            $outputSize = $tradingDays;
            if ($focusAt) {
                // TwelveData recommends omitting outputsize when both date
                // boundaries are present, otherwise the range is truncated.
                $parameters['start_date'] = $focusAt->subYear()->subDays(35)->format('Y-m-d');
                $parameters['end_date'] = $focusAt->addDays(50)->format('Y-m-d');
                $outputSize = null;
            }

            $candles = $this->ohlc($this->timeSeries(
                $normalizedSymbol,
                '1day',
                $outputSize,
                $parameters,
            ))->map(fn (array $bar): array => [
                ...$bar,
                'adjusted_close' => $bar['close'],
            ])->all();

            if ($candles !== []) {
                $cache->put(
                    $cacheKey,
                    $candles,
                    $focusAt ? now()->addDays(30) : now()->addMinutes(15),
                );
                $cache->put(
                    $staleKey,
                    $candles,
                    $focusAt ? now()->addDays(90) : now()->addDays(14),
                );

                return $candles;
            }
        } catch (Throwable $exception) {
            report($exception);
        }

        $stale = $cache->get($staleKey);
        $fallback = is_array($stale) ? $stale : [];
        // Negative-cache a provider failure briefly to avoid a request storm.
        $cache->put($cacheKey, $fallback, now()->addMinutes(5));

        return $fallback;
    }

    private function quoteFromDailySeries(string $symbol): ?array
    {
        $series = $this->timeSeries($symbol, '1day', 2);
        $bars = $this->ohlc($series)->values();
        $latest = $bars->last();
        $previous = $bars->count() > 1 ? $bars->get($bars->count() - 2) : null;

        if (! $latest) {
            return null;
        }

        $changePercent = $previous && $previous['close']
            ? (($latest['close'] - $previous['close']) / $previous['close']) * 100
            : null;

        return [
            'price' => $latest['close'],
            'currency' => (string) data_get($series, 'meta.currency', ''),
            'change_percent' => $changePercent,
            'timestamp' => $latest['timestamp'],
        ];
    }

    private function fetchQuote(string $symbol): ?array
    {
        $response = $this->request('quote', ['symbol' => $this->symbol($symbol)]);
        if (! $this->valid($response)) {
            return $this->quoteFromDailySeries($symbol);
        }

        $price = $this->number($response->json('close'));
        $previous = $this->number($response->json('previous_close'));
        $changePercent = $this->number($response->json('percent_change'));

        if ($changePercent === null && $price !== null && $previous) {
            $changePercent = (($price - $previous) / $previous) * 100;
        }

        return [
            'price' => $price,
            'currency' => (string) ($response->json('currency') ?? ''),
            'change_percent' => $changePercent,
            'timestamp' => $response->json('timestamp'),
        ];
    }

    private function timeSeries(string $symbol, string $interval, ?int $outputSize, array $parameters = []): array
    {
        $request = [
            'symbol' => $this->symbol($symbol),
            'interval' => $interval,
            ...$parameters,
        ];
        if ($outputSize !== null) {
            $request['outputsize'] = $outputSize;
        }
        $response = $this->request('time_series', $request);

        return $this->valid($response) ? $response->json() : [];
    }

    private function ohlc(array $series)
    {
        $timezone = (string) data_get($series, 'meta.exchange_timezone', 'UTC');

        return collect($series['values'] ?? [])
            ->map(function (array $value) use ($timezone): ?array {
                $ohlc = collect(['open', 'high', 'low', 'close'])
                    ->mapWithKeys(fn (string $key): array => [$key => $this->number($value[$key] ?? null)])
                    ->all();

                if (in_array(null, $ohlc, true) || empty($value['datetime'])) {
                    return null;
                }

                return [
                    'timestamp' => CarbonImmutable::parse($value['datetime'], $timezone)->utc()->timestamp,
                    ...$ohlc,
                    'volume' => $this->number($value['volume'] ?? null),
                ];
            })
            ->filter()
            ->sortBy('timestamp')
            ->values();
    }

    private function request(string $endpoint, array $parameters): Response
    {
        $apiKey = (string) config('aktienki.twelve_data.api_key');

        return Http::baseUrl((string) config('aktienki.twelve_data.base_url', 'https://api.twelvedata.com'))
            ->withHeaders(['Authorization' => "apikey {$apiKey}"])
            ->acceptJson()
            ->connectTimeout(3)
            ->retry(2, 300, throw: false)
            ->timeout(8)
            ->get($endpoint, $parameters);
    }

    private function valid(Response $response): bool
    {
        return $response->successful()
            && $response->json('status') !== 'error'
            && ! $response->json('code');
    }

    private function number(mixed $value): ?float
    {
        return is_numeric($value) ? (float) $value : null;
    }

    private function symbol(string $symbol): string
    {
        $symbol = strtoupper(trim($symbol));

        $aliases = [
            // Twelve Data lists the German performance index as GDAXI on XETR.
            // "DAX" is ambiguous there and can resolve to an unrelated ETF.
            '^GDAXI' => 'GDAXI',
            '^IXIC' => 'IXIC',
            '^GSPC' => 'SPX',
            '^DJI' => 'DJI',
            '^N225' => 'N225',
            '^AEX' => 'AEX',
            '^SSMI' => 'SSMI',
            '^FTSE' => 'FTSE',
            '^FCHI' => 'FCHI',
            '^AXJO' => 'XJO',
            '^HSI' => 'HSI',
            '^NYA' => 'NYA',
            '^J203.JO' => 'J203',
            '000001.SS' => '000001:SSE',
            'EURUSD=X' => 'EUR/USD',
            'GC=F' => 'XAU/USD',
        ];

        if (isset($aliases[$symbol])) {
            return $aliases[$symbol];
        }

        $exchangeSuffixes = [
            '.DE' => 'XETR',
            '.L' => 'LSE',
            '.PA' => 'PAR',
            '.AS' => 'AMS',
            '.SW' => 'SIX',
            '.T' => 'JPX',
            '.HK' => 'HKEX',
            '.JO' => 'JSE',
            '.AX' => 'ASX',
            '.SS' => 'SSE',
            '.SZ' => 'SZSE',
        ];

        foreach ($exchangeSuffixes as $suffix => $exchange) {
            if (str_ends_with($symbol, $suffix)) {
                return substr($symbol, 0, -strlen($suffix)).":{$exchange}";
            }
        }

        return $symbol;
    }

    public function providerSymbol(string $symbol): string
    {
        return $this->symbol($symbol);
    }
}
