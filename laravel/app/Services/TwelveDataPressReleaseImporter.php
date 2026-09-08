<?php

namespace App\Services;

use Carbon\CarbonImmutable;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Throwable;

final class TwelveDataPressReleaseImporter
{
    private const PROVIDER = 'twelve_data';

    public function __construct(private readonly TwelveDataService $marketData) {}

    public function sync(int $limit = 2500): array
    {
        $instruments = DB::table('instruments as instrument')
            ->leftJoin('news_source_sync_states as sync', function ($join): void {
                $join->on('sync.instrument_id', '=', 'instrument.id')
                    ->where('sync.provider', self::PROVIDER);
            })
            ->where('instrument.type', 'stock')
            ->where('instrument.is_active', true)
            ->where('instrument.is_german_tradeable', true)
            ->whereNull('instrument.deleted_at')
            ->orderByRaw('sync.last_checked_at IS NULL DESC')
            ->orderBy('sync.last_checked_at')
            ->orderBy('instrument.id')
            ->limit(max(1, $limit))
            ->get(['instrument.id', 'instrument.symbol', 'instrument.provider_symbol', 'sync.last_success_at']);

        $result = ['checked' => 0, 'successful' => 0, 'failed' => 0, 'received' => 0, 'created' => 0];
        foreach ($instruments as $instrument) {
            $result['checked']++;
            try {
                $symbol = $this->marketData->providerSymbol((string) ($instrument->provider_symbol ?: $instrument->symbol));
                $from = $instrument->last_success_at
                    ? CarbonImmutable::parse($instrument->last_success_at)->subHours(6)
                    : CarbonImmutable::now()->subDays((int) config('aktienki.news.initial_lookback_days', 7));
                $response = $this->request($symbol, $from);
                $payload = $response->json() ?: [];
                if (! $response->successful() || data_get($payload, 'status') === 'error' || data_get($payload, 'code')) {
                    throw new \RuntimeException((string) (data_get($payload, 'message') ?: 'Twelve Data HTTP '.$response->status()));
                }

                $releases = collect((array) ($payload['press_releases'] ?? []));
                $result['received'] += $releases->count();
                $latest = null;
                foreach ($releases as $release) {
                    if (! is_array($release) || blank($release['id'] ?? null) || blank($release['title'] ?? null)) continue;
                    $publishedAt = filled($release['datetime'] ?? null) ? CarbonImmutable::parse($release['datetime']) : null;
                    if ($publishedAt && ($latest === null || $publishedAt->greaterThan($latest))) $latest = $publishedAt;
                    $body = (string) ($release['body'] ?? '');
                    $bodyText = trim(preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags($body), ENT_QUOTES | ENT_HTML5, 'UTF-8')) ?? '');
                    $languages = collect((array) ($release['language'] ?? []))->filter()->implode(',');
                    $inserted = DB::table('news')->insertOrIgnore([
                        'instrument_id' => $instrument->id,
                        'headline' => trim((string) $release['title']),
                        'summary' => null,
                        'body' => $bodyText ?: null,
                        'language' => $languages ?: null,
                        'url' => null,
                        'source' => 'Twelve Data Press Releases',
                        'provider' => self::PROVIDER,
                        'provider_id' => (string) $release['id'],
                        'published_at' => $publishedAt,
                        'raw_data' => json_encode($release, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE),
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                    $result['created'] += $inserted;
                }

                DB::table('news_source_sync_states')->updateOrInsert(
                    ['instrument_id' => $instrument->id, 'provider' => self::PROVIDER],
                    [
                        'last_checked_at' => now(), 'last_success_at' => now(),
                        'last_release_at' => $latest, 'consecutive_failures' => 0,
                        'last_error' => null, 'created_at' => now(), 'updated_at' => now(),
                    ],
                );
                $result['successful']++;
            } catch (Throwable $exception) {
                $state = DB::table('news_source_sync_states')->where('instrument_id', $instrument->id)->where('provider', self::PROVIDER)->first();
                DB::table('news_source_sync_states')->updateOrInsert(
                    ['instrument_id' => $instrument->id, 'provider' => self::PROVIDER],
                    [
                        'last_checked_at' => now(),
                        'consecutive_failures' => ((int) ($state->consecutive_failures ?? 0)) + 1,
                        'last_error' => mb_substr($exception->getMessage(), 0, 2000),
                        'created_at' => $state->created_at ?? now(), 'updated_at' => now(),
                    ],
                );
                $result['failed']++;
            }

            $delayMs = max(0, (int) config('aktienki.news.twelve_data_request_delay_ms', 250));
            if ($delayMs > 0) usleep($delayMs * 1000);
        }

        return $result;
    }

    private function request(string $symbol, CarbonImmutable $from): Response
    {
        return Http::baseUrl((string) config('aktienki.twelve_data.base_url'))
            ->withHeaders(['Authorization' => 'apikey '.config('aktienki.twelve_data.api_key')])
            ->acceptJson()->retry(2, 500, throw: false)->timeout(45)
            ->get('press_releases', [
                'symbol' => $symbol,
                'start_date' => $from->utc()->format('Y-m-d\TH:i:s'),
                'end_date' => now()->utc()->format('Y-m-d\TH:i:s'),
                'outputsize' => 10,
            ]);
    }
}
