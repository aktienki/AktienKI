<?php

namespace App\Console\Commands;

use App\Events\MarketPriceUpdated;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Throwable;
use WebSocket\Client;
use WebSocket\TimeoutException;

class StreamTwelveDataPrices extends Command
{
    protected $signature = 'market:stream';

    protected $description = 'Stream requested TwelveData prices and broadcast every tick';

    public function handle(): int
    {
        $apiKey = (string) config('aktienki.twelve_data.api_key');
        if ($apiKey === '') {
            $this->error('TWELVE_DATA_API_KEY is not configured.');

            return self::FAILURE;
        }

        while (true) {
            $instruments = $this->requestedInstruments();
            if ($instruments->isEmpty()) {
                sleep(1);
                continue;
            }

            try {
                $this->stream($apiKey);
            } catch (Throwable $error) {
                $this->warn('TwelveData stream reconnect: '.$error->getMessage());
                sleep(2);
            }
        }
    }

    private function stream(string $apiKey): void
    {
        $client = new Client(
            'wss://ws.twelvedata.com/v1/quotes/price?apikey='.rawurlencode($apiKey),
            ['timeout' => 1],
        );
        $subscribed = [];
        $lastHeartbeatAt = 0;

        try {
            while (true) {
                $instruments = $this->requestedInstruments();
                $wanted = $instruments
                    ->mapWithKeys(fn (object $instrument): array => [
                        strtoupper((string) (
                            strtoupper((string) $instrument->german_listing_currency) === 'EUR'
                                && filled($instrument->german_listing_symbol)
                                    ? $instrument->german_listing_symbol
                                    : ($instrument->provider_symbol ?: $instrument->symbol)
                        )) => (string) $instrument->symbol,
                    ])
                    ->all();

                $subscribe = array_diff_key($wanted, $subscribed);
                $unsubscribe = array_diff_key($subscribed, $wanted);
                if ($subscribe !== []) {
                    $this->send($client, 'subscribe', array_keys($subscribe));
                }
                if ($unsubscribe !== []) {
                    $this->send($client, 'unsubscribe', array_keys($unsubscribe));
                }
                $subscribed = $wanted;

                if ($subscribed === []) {
                    return;
                }

                if (time() - $lastHeartbeatAt >= 10) {
                    $client->send(json_encode(['action' => 'heartbeat'], JSON_THROW_ON_ERROR));
                    $lastHeartbeatAt = time();
                }

                try {
                    $message = $client->receive();
                } catch (TimeoutException) {
                    continue;
                }
                if (! is_string($message) || $message === '') {
                    continue;
                }

                $event = json_decode($message, true);
                if (($event['event'] ?? null) !== 'price' || ! is_numeric($event['price'] ?? null)) {
                    continue;
                }

                $providerSymbol = strtoupper((string) ($event['symbol'] ?? ''));
                if (! isset($subscribed[$providerSymbol])) {
                    continue;
                }

                $timestamp = is_numeric($event['timestamp'] ?? null)
                    ? (int) $event['timestamp']
                    : now()->timestamp;
                $sourceSymbol = $subscribed[$providerSymbol];
                Cache::put(
                    'twelve_data_stream_quote_'.sha1(strtoupper($sourceSymbol)),
                    [
                        'price' => (float) $event['price'],
                        'timestamp' => $timestamp,
                        'provider_symbol' => $providerSymbol,
                    ],
                    now()->addHours(12),
                );
                MarketPriceUpdated::dispatch(
                    $providerSymbol,
                    (float) $event['price'],
                    $timestamp,
                );
            }
        } finally {
            try {
                $client->close();
            } catch (Throwable) {
            }
        }
    }

    private function send(Client $client, string $action, array $symbols): void
    {
        $client->send(json_encode([
            'action' => $action,
            'params' => ['symbols' => implode(',', $symbols)],
        ], JSON_THROW_ON_ERROR));
    }

    private function requestedInstruments()
    {
        $now = time();
        $requests = collect(Cache::get('current_stock_quote_requests', []))
            ->filter(fn (array $request): bool => ($request['expires_at'] ?? 0) >= $now);

        Cache::put('current_stock_quote_requests', $requests->all(), now()->addMinutes(3));
        $ids = $requests
            ->flatMap(fn (array $request): array => $request['instrument_ids'] ?? [])
            ->map(fn ($id): int => (int) $id)
            ->filter(fn (int $id): bool => $id > 0)
            ->unique()
            ->take(100)
            ->values();

        return $ids->isEmpty()
            ? collect()
            : DB::table('instruments')
                ->whereIn('id', $ids)
                ->get(['id', 'symbol', 'provider_symbol', 'german_listing_symbol', 'german_listing_currency']);
    }
}
