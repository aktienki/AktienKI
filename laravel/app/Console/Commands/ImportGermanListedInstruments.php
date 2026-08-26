<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class ImportGermanListedInstruments extends Command
{
    protected $signature = 'instruments:import-german-listed {--dry-run : Nur prüfen, nichts speichern}';

    protected $description = 'Importiert offizielle deutsche Börsenunternehmen, sofern Twelve Data eine Xetra- oder Frankfurt-Notierung führt';

    public function handle(): int
    {
        $source = database_path('data/german_listed_companies.csv');
        if (! is_file($source)) {
            $this->error('Quelldatei fehlt: '.$source);

            return self::FAILURE;
        }

        $catalog = $this->catalog();
        $rows = $this->rows($source);
        $now = now();
        $created = $updated = $skipped = 0;

        $exchangeIds = $this->option('dry-run') ? [] : [
            'XETR' => $this->exchange('XETR', 'Xetra', $now),
            'FSX' => $this->exchange('FSX', 'Frankfurt Stock Exchange', $now),
        ];

        foreach ($rows as $row) {
            $symbol = strtoupper(trim((string) $row['symbol']));
            $listing = $catalog['XETR'][$symbol] ?? $catalog['FSX'][$symbol] ?? null;
            if (! $listing) {
                $skipped++;
                continue;
            }

            $mic = strtoupper((string) $listing['mic_code']);
            $providerSymbol = $symbol.':'.$mic;
            $canonicalSymbol = $symbol.'.DE';
            $existing = DB::table('instruments')
                ->where('type', 'stock')
                ->where(function ($query) use ($row, $providerSymbol, $canonicalSymbol): void {
                    $query->where('isin', $row['isin'])
                        ->orWhere('provider_symbol', $providerSymbol)
                        ->orWhere('symbol', $canonicalSymbol);
                })->first();

            if ($this->option('dry-run')) {
                $existing ? $updated++ : $created++;
                continue;
            }

            $meta = $existing && $existing->meta ? (json_decode((string) $existing->meta, true) ?: []) : [];
            $meta['instrument_source'] = 'Deutsche Börse listed companies';
            $meta['instrument_source_url'] = 'https://www.cashmarket.deutsche-boerse.com/cash-en/Data-Tech/statistics/listed-companies';
            $meta['listing_segment'] = $row['segment'];
            $meta['official_index'] = $row['index'] !== '-' ? $row['index'] : null;
            $meta['twelve_data_mic'] = $mic;
            $values = [
                'exchange_id' => $exchangeIds[$mic],
                'type' => 'stock',
                'symbol' => $canonicalSymbol,
                'provider_symbol' => $providerSymbol,
                'isin' => $row['isin'],
                'name' => $existing?->name ?: $row['name'],
                'country' => 'DE',
                'currency' => 'EUR',
                'sector' => $existing?->sector ?: $this->sector($row['sector']),
                'industry' => $existing?->industry ?: ($row['industry'] ?: null),
                'is_active' => true,
                'is_tradeable' => true,
                'meta' => json_encode($meta, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'deleted_at' => null,
                'updated_at' => $now,
            ];

            if ($existing) {
                DB::table('instruments')->where('id', $existing->id)->update($values);
                $updated++;
            } else {
                DB::table('instruments')->insert($values + ['created_at' => $now]);
                $created++;
            }
        }

        $mode = $this->option('dry-run') ? 'Prüfung' : 'Import';
        $this->info("{$mode}: {$created} neu, {$updated} aktualisiert, {$skipped} ohne passende Twelve-Data-Notierung.");

        return self::SUCCESS;
    }

    private function catalog(): array
    {
        $catalog = ['XETR' => [], 'FSX' => []];
        foreach (array_keys($catalog) as $mic) {
            $response = Http::timeout(90)->get(config('aktienki.twelve_data.base_url').'/stocks', [
                'country' => 'Germany', 'mic_code' => $mic, 'format' => 'JSON',
                'apikey' => config('aktienki.twelve_data.api_key'),
            ]);
            if ($response->failed() || $response->json('status') === 'error') {
                throw new RuntimeException('Twelve Data '.$mic.': '.($response->json('message') ?: 'HTTP '.$response->status()));
            }
            foreach ($response->json('data', []) as $item) {
                if (in_array((string) ($item['type'] ?? ''), ['Common Stock', 'Preferred Stock', 'REIT'], true)) {
                    $catalog[$mic][strtoupper((string) $item['symbol'])] = $item;
                }
            }
        }

        return $catalog;
    }

    private function rows(string $path): array
    {
        $handle = fopen($path, 'rb');
        $header = fgetcsv($handle);
        $rows = [];
        while (($values = fgetcsv($handle)) !== false) {
            if (count($values) === count($header)) $rows[] = array_combine($header, $values);
        }
        fclose($handle);

        return $rows;
    }

    private function exchange(string $mic, string $name, $now): int
    {
        DB::table('exchanges')->updateOrInsert(['code' => $mic], [
            'mic' => $mic, 'name' => $name, 'country' => 'DE', 'currency' => 'EUR',
            'timezone' => 'Europe/Berlin', 'is_active' => true, 'updated_at' => $now, 'created_at' => $now,
        ]);

        return (int) DB::table('exchanges')->where('code', $mic)->value('id');
    }

    private function sector(string $sector): ?string
    {
        return match (strtolower(trim($sector))) {
            'software', 'technology' => 'Technology',
            'telecommunication', 'media' => 'Communication Services',
            'industrial', 'transportation & logistics' => 'Industrials',
            'pharma & healthcare' => 'Healthcare',
            'banks', 'financial services', 'insurance' => 'Financial Services',
            'utilities' => 'Utilities',
            'real estate' => 'Real Estate',
            'energy' => 'Energy',
            'chemicals', 'basic resources' => 'Basic Materials',
            'food & beverages' => 'Consumer Defensive',
            'consumer', 'automobile' => 'Consumer Cyclical',
            default => $sector !== '' ? $sector : null,
        };
    }
}
