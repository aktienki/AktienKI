<?php

namespace App\Services;

use App\Models\ExternalBuyReview;
use Illuminate\Http\Client\Pool;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Throwable;

final class TwelveDataBuyContextService
{
    public function __construct(private readonly TwelveDataService $marketData) {}

    /** @return array<string, mixed> */
    public function forReview(ExternalBuyReview $review): array
    {
        $identity = (array) $review->request_identity;
        $rawSymbol = trim((string) ($identity['ticker'] ?? ''));
        if ($rawSymbol === '') {
            return ['status' => 'unavailable', 'limitations' => ['Kein Twelve-Data-Symbol vorhanden.']];
        }

        $symbol = $this->marketData->providerSymbol($rawSymbol);
        $live = Cache::remember(
            'external-buy-review:twelve-data:'.sha1($symbol),
            now()->addMinutes(30),
            fn (): array => $this->fetchLiveContext($symbol),
        );

        return [
            'provider' => 'twelve_data',
            'symbol' => $symbol,
            'retrieved_at' => now()->toIso8601String(),
            'market' => $live['market'] ?? null,
            'press_releases' => $live['press_releases'] ?? [],
            'fundamentals' => $this->storedFundamentals((int) $review->instrument_id),
            'earnings' => $this->storedEarnings((int) $review->instrument_id),
            'limitations' => $live['limitations'] ?? [],
        ];
    }

    /** @return array<string, mixed> */
    private function fetchLiveContext(string $symbol): array
    {
        $apiKey = trim((string) config('aktienki.twelve_data.api_key'));
        if ($apiKey === '') return ['limitations' => ['TWELVE_DATA_API_KEY ist nicht konfiguriert.']];

        try {
            $baseUrl = (string) config('aktienki.twelve_data.base_url', 'https://api.twelvedata.com');
            $responses = Http::pool(fn (Pool $pool): array => [
                $pool->as('quote')->baseUrl($baseUrl)->withHeaders(['Authorization' => 'apikey '.$apiKey])
                    ->acceptJson()->timeout(30)->get('quote', ['symbol' => $symbol]),
                $pool->as('press')->baseUrl($baseUrl)->withHeaders(['Authorization' => 'apikey '.$apiKey])
                    ->acceptJson()->timeout(45)->get('press_releases', [
                        'symbol' => $symbol,
                        'start_date' => now()->subDays(14)->utc()->format('Y-m-d\TH:i:s'),
                        'end_date' => now()->utc()->format('Y-m-d\TH:i:s'),
                        'outputsize' => 5,
                    ]),
            ]);
            $limitations = [];
            $quote = $this->validPayload($responses['quote']) ?? [];
            if ($quote === []) $limitations[] = 'Aktuelles Twelve-Data-Quote nicht verfügbar.';
            $press = $this->validPayload($responses['press']) ?? [];
            if ($press === []) $limitations[] = 'Twelve-Data-Pressemitteilungen nicht verfügbar.';

            return [
                'market' => array_filter([
                    'datetime' => $quote['datetime'] ?? null, 'currency' => $quote['currency'] ?? null,
                    'open' => $quote['open'] ?? null, 'high' => $quote['high'] ?? null,
                    'low' => $quote['low'] ?? null, 'close' => $quote['close'] ?? null,
                    'previous_close' => $quote['previous_close'] ?? null, 'change' => $quote['change'] ?? null,
                    'percent_change' => $quote['percent_change'] ?? null, 'volume' => $quote['volume'] ?? null,
                    'average_volume' => $quote['average_volume'] ?? null,
                ], static fn ($value): bool => $value !== null && $value !== ''),
                'press_releases' => collect((array) ($press['press_releases'] ?? []))
                    ->filter(fn ($item): bool => is_array($item) && filled($item['title'] ?? null))->take(5)
                    ->map(fn (array $item): array => [
                        'id' => $item['id'] ?? null, 'datetime' => $item['datetime'] ?? null,
                        'title' => trim((string) $item['title']),
                        'body' => mb_substr(trim(preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags((string) ($item['body'] ?? '')), ENT_QUOTES | ENT_HTML5, 'UTF-8')) ?? ''), 0, 3500),
                    ])->values()->all(),
                'limitations' => $limitations,
            ];
        } catch (Throwable $exception) {
            return ['limitations' => ['Twelve Data konnte nicht geladen werden: '.mb_substr($exception->getMessage(), 0, 300)]];
        }
    }

    private function validPayload(mixed $response): ?array
    {
        if (! $response || ! $response->successful()) return null;
        $payload = $response->json();
        if (! is_array($payload) || ($payload['status'] ?? null) === 'error' || isset($payload['code'])) return null;
        return $payload;
    }

    /** @return array<string, mixed>|null */
    private function storedFundamentals(int $instrumentId): ?array
    {
        $row = DB::table('instrument_fundamentals')->where('instrument_id', $instrumentId)->orderByDesc('snapshot_date')->first();
        if (! $row) return null;
        $fields = ['snapshot_date', 'fiscal_date', 'retrieved_at', 'market_cap', 'trailing_pe', 'forward_pe',
            'price_to_book', 'price_to_sales', 'dividend_yield', 'profit_margin', 'operating_margin',
            'return_on_equity', 'revenue_growth', 'total_cash', 'total_debt', 'debt_to_equity',
            'current_ratio', 'free_cash_flow', 'source'];
        return collect($fields)->mapWithKeys(fn (string $field): array => [$field => $row->{$field} ?? null])
            ->filter(fn ($value): bool => $value !== null && $value !== '')->all();
    }

    /** @return array<int, array<string, mixed>> */
    private function storedEarnings(int $instrumentId): array
    {
        return DB::table('instrument_earnings')->where('instrument_id', $instrumentId)->orderByDesc('earnings_date')->limit(4)
            ->get(['earnings_date', 'period', 'eps_estimate', 'eps_actual', 'surprise_percent', 'source'])
            ->map(fn ($row): array => (array) $row)->all();
    }
}
