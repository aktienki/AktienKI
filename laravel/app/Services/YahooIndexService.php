<?php

namespace App\Services;

use Illuminate\Http\Client\Pool;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

class YahooIndexService
{
    public function dailyHistory(string $symbol, string $range = '3y'): array
    {
        return Cache::remember(
            'yahoo_index_daily_history_'.sha1(strtoupper($symbol).$range),
            now()->addDay(),
            function () use ($symbol, $range): array {
                $response = Http::withHeaders([
                    'Accept' => 'application/json',
                    'User-Agent' => 'Mozilla/5.0 (compatible; AktienKI/1.0)',
                ])->timeout(20)->get(
                    'https://query1.finance.yahoo.com/v8/finance/chart/'.rawurlencode($symbol),
                    ['interval' => '1d', 'range' => $range, 'events' => 'history'],
                );
                $result = $response->successful() ? $response->json('chart.result.0') : null;
                if (! is_array($result) || strtoupper((string) data_get($result, 'meta.symbol')) !== strtoupper($symbol)) {
                    return [];
                }
                $timestamps = $result['timestamp'] ?? [];
                $quotes = data_get($result, 'indicators.quote.0', []);
                $adjusted = data_get($result, 'indicators.adjclose.0.adjclose', []);

                return collect($timestamps)->map(function (mixed $timestamp, int $index) use ($quotes, $adjusted): ?array {
                    $values = collect(['open', 'high', 'low', 'close'])
                        ->mapWithKeys(fn (string $key): array => [$key => data_get($quotes, "{$key}.{$index}")])
                        ->all();
                    if (! is_numeric($timestamp) || collect($values)->contains(fn ($value): bool => ! is_numeric($value))) {
                        return null;
                    }

                    return [
                        'timestamp' => (int) $timestamp,
                        'open' => (float) $values['open'],
                        'high' => (float) $values['high'],
                        'low' => (float) $values['low'],
                        'close' => (float) $values['close'],
                        'adjusted_close' => is_numeric($adjusted[$index] ?? null) ? (float) $adjusted[$index] : (float) $values['close'],
                        'volume' => is_numeric(data_get($quotes, "volume.{$index}")) ? (float) data_get($quotes, "volume.{$index}") : null,
                    ];
                })->filter()->values()->all();
            },
        );
    }

    public function quotes(array $symbols): array
    {
        $symbols = collect($symbols)->filter()->unique()->values();
        $quotes = [];
        $missing = [];

        foreach ($symbols as $symbol) {
            $cached = Cache::get($this->cacheKey($symbol));
            if (is_array($cached)) {
                $quotes[$symbol] = $cached;
            } elseif (! Cache::has($this->errorKey($symbol))) {
                $missing[] = $symbol;
            }
        }

        if ($missing === []) {
            return $quotes;
        }

        $aliases = collect($missing)->mapWithKeys(
            fn (string $symbol): array => ['index_'.sha1($symbol) => $symbol]
        );
        $responses = Http::pool(fn (Pool $pool): array => $aliases
            ->map(fn (string $symbol, string $alias) => $pool
                ->as($alias)
                ->withHeaders([
                    'Accept' => 'application/json',
                    'User-Agent' => 'Mozilla/5.0 (compatible; AktienKI/1.0)',
                ])
                ->timeout(12)
                ->get('https://query1.finance.yahoo.com/v8/finance/chart/'.rawurlencode($symbol), [
                    'interval' => '1m',
                    'range' => '1d',
                ]))
            ->all());

        foreach ($aliases as $alias => $requestedSymbol) {
            $response = $responses[$alias] ?? null;
            $result = $response?->successful() ? $response->json('chart.result.0') : null;
            $meta = is_array($result) ? ($result['meta'] ?? []) : [];
            $returnedSymbol = strtoupper((string) ($meta['symbol'] ?? ''));
            $instrumentType = strtoupper((string) ($meta['instrumentType'] ?? ''));
            $price = $meta['regularMarketPrice'] ?? null;

            if ($returnedSymbol !== strtoupper($requestedSymbol)
                || $instrumentType !== 'INDEX'
                || ! is_numeric($price)) {
                Cache::put($this->errorKey($requestedSymbol), true, now()->addMinutes(5));
                continue;
            }

            $previous = $meta['chartPreviousClose'] ?? $meta['previousClose'] ?? null;
            $quote = [
                'price' => (float) $price,
                'currency' => (string) ($meta['currency'] ?? ''),
                'change_percent' => is_numeric($previous) && (float) $previous !== 0.0
                    ? (((float) $price - (float) $previous) / (float) $previous) * 100
                    : null,
                'quote_time' => isset($meta['regularMarketTime'])
                    ? \Illuminate\Support\Carbon::createFromTimestampUTC((int) $meta['regularMarketTime'])
                    : null,
                'source' => 'yahoo_index_rest',
            ];
            Cache::put($this->cacheKey($requestedSymbol), $quote, now()->addMinute());
            $this->store($requestedSymbol, $quote);
            $quotes[$requestedSymbol] = $quote;
        }

        return $quotes;
    }

    private function cacheKey(string $symbol): string
    {
        return 'yahoo_index_quote_'.sha1(strtoupper($symbol));
    }

    private function errorKey(string $symbol): string
    {
        return 'yahoo_index_error_'.sha1(strtoupper($symbol));
    }

    private function store(string $symbol, array $quote): void
    {
        $instrumentId = DB::table('instruments')
            ->where('type', 'index')
            ->where('symbol', $symbol)
            ->value('id');
        if (! $instrumentId || ! is_numeric($quote['price'] ?? null)) {
            return;
        }

        $barTime = ($quote['quote_time'] ?? now())->copy()->startOfMinute();
        $price = (float) $quote['price'];
        DB::table('price_bars')->upsert([[
            'instrument_id' => $instrumentId,
            'interval' => '1m',
            'bar_time' => $barTime,
            'open' => $price,
            'high' => $price,
            'low' => $price,
            'close' => $price,
            'adjusted_close' => $price,
            'volume' => null,
            'source' => 'yahoo_index_rest',
            'created_at' => now(),
            'updated_at' => now(),
        ]], ['instrument_id', 'interval', 'bar_time'], [
            'high', 'low', 'close', 'adjusted_close', 'source', 'updated_at',
        ]);
    }
}
