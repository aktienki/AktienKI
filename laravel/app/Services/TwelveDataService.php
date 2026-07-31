<?php

namespace App\Services;

use Carbon\CarbonImmutable;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class TwelveDataService
{
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
                $quote = Cache::remember(
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
        return Cache::remember(
            'twelve_data_quote_'.sha1(strtoupper($symbol)),
            now()->addMinute(),
            function () use ($symbol): ?array {
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
            },
        );
    }

    public function sparkline(string $symbol): array
    {
        return Cache::remember(
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
        return Cache::remember(
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

        return Cache::remember(
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
        $tradingDays = max(20, min(1200, $tradingDays));

        return Cache::remember(
            'twelve_data_daily_history_'.sha1(strtoupper($symbol))."_{$tradingDays}",
            now()->addDay(),
            fn (): array => $this->ohlc($this->timeSeries($symbol, '1day', $tradingDays, ['adjust' => 'all']))
                ->map(fn (array $bar): array => [...$bar, 'adjusted_close' => $bar['close']])
                ->all(),
        );
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

    private function timeSeries(string $symbol, string $interval, int $outputSize, array $parameters = []): array
    {
        $response = $this->request('time_series', [
            'symbol' => $this->symbol($symbol),
            'interval' => $interval,
            'outputsize' => $outputSize,
            ...$parameters,
        ]);

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
            ->retry(2, 300, throw: false)
            ->timeout(12)
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
            '^GDAXI' => 'DAX',
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
