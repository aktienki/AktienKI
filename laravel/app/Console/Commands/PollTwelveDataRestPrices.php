<?php

namespace App\Console\Commands;

use App\Events\MarketPriceUpdated;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * REST fallback for live prices.
 *
 * The realtime websocket feed (App\Console\Commands\StreamTwelveDataPrices) only
 * carries the exchanges the TwelveData plan streams over websocket. XETR and most
 * European venues are not included there and every subscribe is rejected with a
 * "not authorized to access XETR data" add-on notice, so the screener never sees
 * a moving price in Pro mode.
 *
 * The REST /price batch endpoint *is* entitled for Europe and the US on the
 * current plan. This command polls it on a short interval and writes the exact
 * same cache keys and broadcast event as the websocket command, so the existing
 * screener frontend, the /recommendations/live-quotes poller and the Reverb
 * "market-prices" channel light up without any further change.
 */
class PollTwelveDataRestPrices extends Command
{
    protected $signature = 'market:poll-rest
        {--interval=20 : Seconds to wait between polls}
        {--batch=120 : Symbols per REST request (TwelveData caps /price at 120)}';

    protected $description = 'Poll TwelveData REST prices for requested instruments and broadcast every refresh';

    public function handle(): int
    {
        $apiKey = (string) config('aktienki.twelve_data.api_key');
        if ($apiKey === '') {
            $this->error('TWELVE_DATA_API_KEY is not configured.');

            return self::FAILURE;
        }

        $interval = max(5, (int) $this->option('interval'));
        $batchSize = max(1, min(120, (int) $this->option('batch')));

        while (true) {
            try {
                $written = $this->poll($apiKey, $batchSize);
                if ($written > 0) {
                    $this->line(now()->toTimeString().'  refreshed '.$written.' quotes');
                }
            } catch (Throwable $error) {
                $this->warn('REST price poll error: '.$error->getMessage());
                Log::warning('market:poll-rest failed', ['message' => $error->getMessage()]);
            }

            sleep($interval);
        }
    }

    private function poll(string $apiKey, int $batchSize): int
    {
        $instruments = $this->requestedInstruments();
        if ($instruments->isEmpty()) {
            return 0;
        }

        // The screener displays every quote in EUR (serving current_price and
        // predicted_price_Nd are both EUR-normalised), and the client recomputes
        // the horizon forecasts as (target - livePrice) / livePrice. So the live
        // price must be EUR too. A bare TwelveData ticker resolves to the US
        // listing - wrong currency, sometimes the wrong company (e.g. "AIR" is
        // AAR Corp, not Airbus) - so we only query fully exchange-qualified,
        // EUR-denominated symbols and skip anything we cannot pin down.
        //
        // $query: TwelveData symbol => [source, advertised, ccy]. "source" is our
        // canonical instrument symbol (drives the cache key the /recommendations/
        // live-quotes poller reads). "advertised" is the unqualified provider
        // symbol LivePriceSubscriptionController told the browser, so the Reverb
        // broadcast still maps back to the right rows.
        $query = [];
        foreach ($instruments as $instrument) {
            $tdSymbol = $this->eurSymbol($instrument);
            if ($tdSymbol === null) {
                continue;
            }
            $advertised = strtoupper((string) (
                strtoupper((string) $instrument->german_listing_currency) === 'EUR'
                    && filled($instrument->german_listing_symbol)
                        ? $instrument->german_listing_symbol
                        : ($instrument->provider_symbol ?: $instrument->symbol)
            ));
            $query[$tdSymbol] = [
                'source' => (string) $instrument->symbol,
                'advertised' => $advertised,
            ];
        }

        if ($query === []) {
            return 0;
        }

        $written = 0;
        foreach (array_chunk(array_keys($query), $batchSize) as $chunk) {
            $response = Http::baseUrl((string) config('aktienki.twelve_data.base_url', 'https://api.twelvedata.com'))
                ->withHeaders(['Authorization' => "apikey {$apiKey}"])
                ->acceptJson()
                ->timeout(15)
                ->retry(2, 300, throw: false)
                ->get('/price', ['symbol' => implode(',', $chunk)]);

            if (! $response->successful()) {
                $this->warn('REST /price HTTP '.$response->status());

                continue;
            }

            $payload = $response->json();
            if (! is_array($payload)) {
                continue;
            }

            // A single-symbol request replies {"price": "..."} without the key.
            $rows = count($chunk) === 1 ? [$chunk[0] => $payload] : $payload;
            $timestamp = now()->timestamp;

            foreach ($rows as $tdSymbol => $row) {
                $tdSymbol = strtoupper((string) $tdSymbol);
                $price = is_array($row) ? ($row['price'] ?? null) : null;
                if (! is_numeric($price) || ! isset($query[$tdSymbol])) {
                    continue;
                }

                $sourceSymbol = $query[$tdSymbol]['source'];
                $advertised = $query[$tdSymbol]['advertised'];
                Cache::put(
                    'twelve_data_stream_quote_'.sha1(strtoupper($sourceSymbol)),
                    [
                        'price' => (float) $price,
                        'timestamp' => $timestamp,
                        'currency' => 'EUR',
                        'provider_symbol' => $advertised,
                    ],
                    now()->addHours(12),
                );

                try {
                    MarketPriceUpdated::dispatch($advertised, (float) $price, $timestamp);
                } catch (Throwable $error) {
                    // A broadcast hiccup must not stop the cache refresh, which
                    // is what the /recommendations/live-quotes poller reads.
                    Log::warning('market:poll-rest broadcast failed', ['message' => $error->getMessage()]);
                }

                $written++;
            }
        }

        return $written;
    }

    /**
     * Fully exchange-qualified, EUR-denominated TwelveData symbol for this
     * instrument, or null when we cannot get a trustworthy EUR quote (in which
     * case the static serving forecast - already correct - is left untouched).
     */
    private function eurSymbol(object $instrument): ?string
    {
        $deSymbol = strtoupper(trim((string) ($instrument->german_listing_symbol ?? '')));
        $deCurrency = strtoupper(trim((string) ($instrument->german_listing_currency ?? '')));
        if ($deSymbol !== '' && $deCurrency === 'EUR') {
            if (str_contains($deSymbol, ':')) {
                return $deSymbol;
            }
            $mic = strtoupper(trim((string) ($instrument->german_listing_mic ?? '')));
            if (! in_array($mic, ['XETR', 'XFRA'], true)) {
                $mic = 'XETR';
            }

            return preg_replace('/\.[A-Z]+$/', '', $deSymbol).':'.$mic;
        }

        $providerSymbol = strtoupper(trim((string) ($instrument->provider_symbol ?? '')));
        if (str_contains($providerSymbol, ':XETR') || str_contains($providerSymbol, ':XFRA')) {
            return $providerSymbol;
        }

        // A native-EUR listing that still carries an explicit exchange qualifier
        // is safe; a bare ticker is not (TwelveData maps it to the US listing).
        if (strtoupper(trim((string) ($instrument->currency ?? ''))) === 'EUR'
            && str_contains($providerSymbol, ':')) {
            return $providerSymbol;
        }

        return null;
    }

    private function requestedInstruments()
    {
        $now = time();
        $ids = collect(Cache::get('current_stock_quote_requests', []))
            ->filter(fn (array $request): bool => ($request['expires_at'] ?? 0) >= $now)
            ->flatMap(fn (array $request): array => $request['instrument_ids'] ?? [])
            ->map(fn ($id): int => (int) $id)
            ->filter(fn (int $id): bool => $id > 0)
            ->unique()
            ->take(120)
            ->values();

        return $ids->isEmpty()
            ? collect()
            : DB::table('instruments')
                ->whereIn('id', $ids)
                ->get(['id', 'symbol', 'currency', 'provider_symbol', 'german_listing_symbol', 'german_listing_currency', 'german_listing_mic']);
    }
}
