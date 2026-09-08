<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class ImportUsTopGermanTradeable extends Command
{
    protected $signature = 'instruments:import-us-top-german-tradeable {--dry-run : Nur prüfen, nichts speichern}';

    protected $description = 'Importiert die größten US-Aktien mit bestätigter deutscher EUR-Notierung';

    public function handle(): int
    {
        $source = database_path('data/us_top1000.csv');
        if (! is_file($source)) {
            $this->error('Quelldatei fehlt: '.$source);
            return self::FAILURE;
        }

        $rows = $this->rows($source);
        $usCatalog = $this->catalog('United States');
        $germanCatalog = $this->catalog('Germany');
        $primaryBySymbol = $this->primaryBySymbol($usCatalog);
        $germanByName = $this->germanByName($germanCatalog);
        $usedGermanListings = [];
        $matched = [];
        $created = $updated = $skippedPrimary = $skippedGerman = 0;
        $now = now();

        foreach ($rows as $row) {
            $symbol = strtoupper(trim((string) $row['symbol']));
            $existing = DB::table('instruments')->where('type', 'stock')
                ->where(fn ($query) => $query->whereRaw('UPPER(symbol) = ?', [$symbol])->orWhereRaw('UPPER(provider_symbol) = ?', [$symbol]))
                ->first();
            $primary = $primaryBySymbol[$this->tickerKey($symbol)] ?? null;
            if (! $primary && ! $existing) {
                $skippedPrimary++;
                continue;
            }

            $listing = null;
            if ($existing && $existing->is_german_tradeable && $existing->german_listing_symbol && strtoupper((string) $existing->german_listing_currency) === 'EUR') {
                $listing = [
                    'symbol' => $existing->german_listing_symbol,
                    'exchange' => $existing->german_listing_exchange,
                    'mic_code' => $existing->german_listing_mic,
                    'currency' => 'EUR',
                ];
            } else {
                $candidates = $germanByName[$this->nameKey((string) $row['name'])] ?? [];
                $listing = collect($candidates)->first(function (array $candidate) use (&$usedGermanListings): bool {
                    $key = strtoupper((string) $candidate['mic_code']).':'.strtoupper((string) $candidate['symbol']);
                    return ! isset($usedGermanListings[$key]);
                });
            }
            if (! $listing) {
                $skippedGerman++;
                continue;
            }

            if (count($matched) >= 1000) break;

            $listingKey = strtoupper((string) $listing['mic_code']).':'.strtoupper((string) $listing['symbol']);
            $usedGermanListings[$listingKey] = true;
            $matched[] = ['row' => $row, 'existing' => $existing, 'primary' => $primary, 'listing' => $listing];
            $existing ? $updated++ : $created++;
        }

        if ($this->option('dry-run')) {
            $this->info("Prüfung: {$created} neu, {$updated} vorhanden, {$skippedPrimary} ohne US-Katalogtreffer, {$skippedGerman} ohne eindeutige deutsche EUR-Notierung.");
            return self::SUCCESS;
        }

        DB::transaction(function () use ($matched, $now): void {
            $indexId = $this->marketIndex($now);
            $selectedIds = [];
            $totalMarketCap = max(1.0, (float) collect($matched)->sum(fn (array $item) => (float) $item['row']['market_cap']));

            foreach ($matched as $item) {
                $row = $item['row'];
                $existing = $item['existing'];
                $primary = $item['primary'];
                $listing = $item['listing'];
                $primaryMic = strtoupper((string) ($primary['mic_code'] ?? ''));
                $exchangeId = $this->exchange($primaryMic, (string) ($primary['exchange'] ?? $row['exchange']), $now);
                $meta = $existing?->meta ? (json_decode((string) $existing->meta, true) ?: []) : [];
                $meta['universe_source'] = 'Yahoo Equity Screener';
                $meta['universe_rank'] = (int) $row['rank'];
                $meta['german_listing_source'] = 'Twelve Data stocks catalog';
                $values = [
                    'exchange_id' => $exchangeId ?: $existing?->exchange_id,
                    'type' => 'stock', 'symbol' => strtoupper((string) $row['symbol']),
                    'provider_symbol' => strtoupper((string) ($primary['symbol'] ?? $row['symbol'])),
                    'name' => $existing?->name ?: $row['name'], 'country' => 'US', 'currency' => 'USD',
                    'sector' => $existing?->sector ?: ($row['sector'] ?: null),
                    'industry' => $existing?->industry ?: ($row['industry'] ?: null),
                    'market_cap' => (float) $row['market_cap'],
                    'german_listing_symbol' => strtoupper((string) $listing['symbol']),
                    'german_listing_exchange' => $listing['exchange'] ?: null,
                    'german_listing_mic' => $listing['mic_code'] ?: null,
                    'german_listing_currency' => 'EUR',
                    'german_listing_verified_at' => $now, 'german_listing_checked_at' => $now,
                    'is_german_tradeable' => true, 'is_active' => true, 'is_tradeable' => true,
                    'meta' => json_encode($meta, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                    'deleted_at' => null, 'updated_at' => $now,
                ];
                if ($existing) {
                    DB::table('instruments')->where('id', $existing->id)->update($values);
                    $instrumentId = (int) $existing->id;
                } else {
                    $instrumentId = (int) DB::table('instruments')->insertGetId($values + ['created_at' => $now]);
                }
                $selectedIds[] = $instrumentId;
                DB::table('index_memberships')->updateOrInsert(
                    ['market_index_id' => $indexId, 'instrument_id' => $instrumentId],
                    ['weight' => round(((float) $row['market_cap'] / $totalMarketCap) * 100, 6),
                        'added_at' => $now->toDateString(), 'removed_at' => null, 'updated_at' => $now, 'created_at' => $now]
                );
            }
            DB::table('index_memberships')->where('market_index_id', $indexId)->whereNull('removed_at')
                ->when($selectedIds !== [], fn ($query) => $query->whereNotIn('instrument_id', $selectedIds))
                ->update(['removed_at' => $now->toDateString(), 'updated_at' => $now]);
        });

        $this->info("Import: {$created} neu, {$updated} aktualisiert, {$skippedPrimary} ohne US-Katalogtreffer, {$skippedGerman} ohne eindeutige deutsche EUR-Notierung.");
        return self::SUCCESS;
    }

    private function catalog(string $country): array
    {
        $response = Http::timeout(120)->get(config('aktienki.twelve_data.base_url').'/stocks', [
            'country' => $country, 'format' => 'JSON', 'apikey' => config('aktienki.twelve_data.api_key'),
        ]);
        if ($response->failed() || $response->json('status') === 'error') {
            throw new RuntimeException('Twelve Data '.$country.': '.($response->json('message') ?: 'HTTP '.$response->status()));
        }
        return $response->json('data', []);
    }

    private function primaryBySymbol(array $catalog): array
    {
        $result = [];
        foreach ($catalog as $item) {
            if (! in_array(strtoupper((string) ($item['mic_code'] ?? '')), [
                'XNAS', 'XNYS', 'XNCM', 'XNGS', 'XNMS', 'ARCX', 'BATS', 'XASE',
                'PINX', 'PSGM', 'OTCB', 'OTCQ', 'EXPM',
            ], true)) continue;
            if (! in_array((string) ($item['type'] ?? ''), ['Common Stock', 'Preferred Stock', 'REIT', 'American Depositary Receipt'], true)) continue;
            $result[$this->tickerKey((string) $item['symbol'])] ??= $item;
        }
        return $result;
    }

    private function germanByName(array $catalog): array
    {
        $result = [];
        $priority = ['XETR' => 0, 'FSX' => 1, 'XFRA' => 1, 'XMUN' => 2, 'XDUS' => 3, 'XSTU' => 4, 'XHAN' => 5, 'XHAM' => 6, 'XBER' => 7];
        foreach ($catalog as $item) {
            $mic = strtoupper((string) ($item['mic_code'] ?? ''));
            if (! isset($priority[$mic]) || strtoupper((string) ($item['currency'] ?? '')) !== 'EUR') continue;
            if (! in_array((string) ($item['type'] ?? ''), ['Common Stock', 'Preferred Stock', 'REIT', 'Depositary Receipt'], true)) continue;
            $result[$this->nameKey((string) $item['name'])][] = $item;
        }
        foreach ($result as &$items) usort($items, fn ($a, $b) => ($priority[strtoupper((string) $a['mic_code'])] ?? 99) <=> ($priority[strtoupper((string) $b['mic_code'])] ?? 99));
        return $result;
    }

    private function rows(string $path): array
    {
        $handle = fopen($path, 'rb'); $header = fgetcsv($handle); $rows = [];
        while (($values = fgetcsv($handle)) !== false) if (count($values) === count($header)) $rows[] = array_combine($header, $values);
        fclose($handle); return $rows;
    }

    private function tickerKey(string $symbol): string { return preg_replace('/[^A-Z0-9]/', '', strtoupper($symbol)) ?: ''; }

    private function nameKey(string $name): string
    {
        $key = strtoupper(iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $name) ?: $name);
        $key = preg_replace('/\b(CORPORATION|CORP|INCORPORATED|INC|COMPANY|CO|LIMITED|LTD|PLC|N V|NV|S A|SA)\b/', ' ', $key);
        return trim(preg_replace('/[^A-Z0-9]+/', ' ', $key) ?: '');
    }

    private function exchange(string $mic, string $name, $now): ?int
    {
        if ($mic === '') return null;
        DB::table('exchanges')->updateOrInsert(['code' => $mic], ['mic' => $mic, 'name' => $name ?: $mic,
            'country' => 'US', 'currency' => 'USD', 'timezone' => 'America/New_York', 'is_active' => true,
            'updated_at' => $now, 'created_at' => $now]);
        return (int) DB::table('exchanges')->where('code', $mic)->value('id');
    }

    private function marketIndex($now): int
    {
        DB::table('market_indices')->updateOrInsert(['symbol' => 'US-DE-TOP1000'], [
            'name' => 'US Top 1000 · Deutschland handelbar', 'country' => 'US', 'currency' => 'USD',
            'region' => 'Nordamerika', 'global_rank' => 2,
            'description' => 'Die größten verfügbaren US-Aktien mit bestätigter deutscher EUR-Notierung.',
            'is_active' => true, 'updated_at' => $now, 'created_at' => $now,
        ]);
        return (int) DB::table('market_indices')->where('symbol', 'US-DE-TOP1000')->value('id');
    }
}
