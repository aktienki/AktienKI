<?php

namespace App\Console\Commands;

use App\Events\MarketPriceUpdated;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class StreamTwelveDataPrices extends Command
{
    protected $signature = 'market:stream';

    protected $description = 'Broadcast current_stock_quotes updates to authenticated users';

    public function handle(): int
    {
        $lastBroadcast = [];

        while (true) {
            foreach ($this->requestedInstrumentIds() as $instrumentId) {
                $quote = DB::table('current_stock_quotes as quote')
                    ->join('instruments as instrument', 'instrument.id', '=', 'quote.instrument_id')
                    ->where('quote.instrument_id', $instrumentId)
                    ->where('quote.status', 'current')
                    ->orderByDesc('quote.quote_time')
                    ->orderByDesc('quote.id')
                    ->first(['quote.id', 'quote.price', 'quote.quote_time', 'instrument.symbol']);

                if (! $quote || ($lastBroadcast[$instrumentId] ?? null) === (int) $quote->id) {
                    continue;
                }

                try {
                    MarketPriceUpdated::dispatch(
                        (string) $quote->symbol,
                        (float) $quote->price,
                        \Illuminate\Support\Carbon::parse($quote->quote_time)->timestamp,
                    );
                } catch (\Throwable) {
                    // Persisting the quote must continue if Reverb is briefly unavailable.
                }
                $lastBroadcast[$instrumentId] = (int) $quote->id;
            }

            usleep(1_000_000);
        }
    }

    private function requestedInstrumentIds(): array
    {
        $now = time();
        $requests = collect(Cache::get('current_stock_quote_requests', []))
            ->filter(fn (array $request): bool => ($request['expires_at'] ?? 0) >= $now);

        Cache::put('current_stock_quote_requests', $requests->all(), now()->addMinutes(3));

        return $requests
            ->flatMap(fn (array $request): array => $request['instrument_ids'] ?? [])
            ->map(fn ($id): int => (int) $id)
            ->filter(fn (int $id): bool => $id > 0)
            ->unique()
            ->take(8)
            ->sort()
            ->values()
            ->all();
    }
}
