<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

class ImportLinkedSecurities extends Command
{
    protected $signature = 'securities:import-linked {source : CSV-Datei oder HTTPS-URL} {--provider= : Anbieter/Emittent der Daten}';
    protected $description = 'Importiert in Deutschland handelbare Zertifikate und Unternehmensanleihen anhand der Basiswert-ISIN';

    public function handle(): int
    {
        $source = (string) $this->argument('source');
        $payload = str_starts_with($source, 'https://')
            ? Http::retry(3, 750)->timeout(60)->get($source)->throw()->body()
            : @file_get_contents($source);
        if (! is_string($payload) || trim($payload) === '') {
            $this->error('Die Quelldatei konnte nicht gelesen werden.'); return self::FAILURE;
        }
        $lines = preg_split('/\R/u', preg_replace('/^\xEF\xBB\xBF/', '', $payload)) ?: [];
        $headerIndex = collect($lines)->search(fn ($line) => str_contains(strtolower($line), 'isin'));
        if ($headerIndex === false) { $this->error('Keine CSV-Kopfzeile mit ISIN gefunden.'); return self::FAILURE; }
        $delimiter = substr_count($lines[$headerIndex], ';') > substr_count($lines[$headerIndex], ',') ? ';' : ',';
        $key = fn ($value) => trim(strtolower(preg_replace('/[^a-zA-Z0-9]+/', '_', iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', trim($value)) ?: $value)), '_');
        $headers = array_map($key, str_getcsv($lines[$headerIndex], $delimiter));
        $germanMics = ['XETR', 'XFRA', 'XGAT', 'XMUN', 'XBER', 'XDUS', 'XHAM', 'XHAN', 'XSTU'];
        $imported = $skipped = 0;
        foreach (array_slice($lines, $headerIndex + 1) as $line) {
            if (trim($line) === '') continue;
            $values = array_pad(str_getcsv($line, $delimiter), count($headers), null);
            $row = array_combine($headers, array_slice($values, 0, count($headers))) ?: [];
            $get = function (...$keys) use ($row) { foreach ($keys as $key) if (filled($row[$key] ?? null)) return trim((string) $row[$key]); return null; };
            $isin = strtoupper((string) $get('isin', 'produkt_isin'));
            $underlyingIsin = strtoupper((string) $get('underlying_isin', 'basiswert_isin', 'issuer_isin', 'emittent_isin'));
            $mic = strtoupper((string) $get('mic', 'mic_code'));
            $type = strtolower(str_replace([' ', '-'], '_', (string) $get('type', 'produkttyp', 'product_type')));
            $type = match (true) {
                str_contains($type, 'discount') => 'discount_certificate',
                str_contains($type, 'bonus') => 'bonus_certificate',
                str_contains($type, 'bond') || str_contains($type, 'anleihe') => 'bond',
                default => null,
            };
            $instrumentId = DB::table('instruments')->where('isin', $underlyingIsin)->value('id');
            if (! $type || ! $instrumentId || ! preg_match('/^[A-Z]{2}[A-Z0-9]{9}[0-9]$/', $isin) || ! in_array($mic, $germanMics, true)) { $skipped++; continue; }
            $number = function ($value) { $value = str_replace(['%', ' '], '', (string) $value); if (str_contains($value, ',') && ! str_contains($value, '.')) $value = str_replace(',', '.', $value); return is_numeric($value) ? (float) $value : null; };
            DB::table('linked_securities')->updateOrInsert(['isin' => $isin], [
                'underlying_instrument_id' => $instrumentId, 'type' => $type,
                'wkn' => $get('wkn'), 'name' => $get('name', 'bezeichnung') ?: $isin,
                'issuer' => $get('issuer', 'emittent'), 'currency' => strtoupper($get('currency', 'waehrung') ?: 'EUR'),
                'exchange' => $get('exchange', 'boerse', 'handelsplatz') ?: $mic, 'mic_code' => $mic,
                'german_listing_symbol' => $get('symbol', 'ticker'), 'maturity_date' => $get('maturity_date', 'faelligkeit'),
                'price' => $number($get('price', 'kurs')), 'cap' => $number($get('cap')),
                'barrier' => $number($get('barrier', 'barriere')), 'bonus_level' => $number($get('bonus_level', 'bonuslevel')),
                'coupon_percent' => $number($get('coupon_percent', 'kupon')), 'discount_percent' => $number($get('discount_percent', 'discount')),
                'quote_at' => now(), 'german_tradeability_verified_at' => now(),
                'source_provider' => $this->option('provider') ?: ($get('provider', 'datenquelle') ?: 'official issuer/exchange'),
                'source_url' => str_starts_with($source, 'https://') ? $source : null,
                'is_active' => true, 'updated_at' => now(), 'created_at' => now(),
            ]);
            $imported++;
        }
        $this->info("{$imported} Produkte importiert; {$skipped} ohne gültige deutsche Handelbarkeit/Basiswert-Zuordnung übersprungen.");
        return self::SUCCESS;
    }
}
