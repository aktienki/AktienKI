<?php

namespace App\Services;

use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class EtfHoldingImportService
{
    /** @return array{fund_id:int,imported:int,matched:int,effective_date:string} */
    public function sync(object $fund): array
    {
        if (! filled($fund->source_url)) {
            throw new RuntimeException("ETF {$fund->id} hat keine Anbieter-URL.");
        }

        $response = Http::retry(3, 750)->timeout(45)->withHeaders([
            'Accept' => 'text/csv,application/csv,application/json;q=0.9,*/*;q=0.8',
            'User-Agent' => 'AktienKI ETF Holdings Import/1.0',
        ])->get($fund->source_url);
        $response->throw();

        return $this->import($fund, $response->body(), (string) ($fund->source_format ?: 'csv'));
    }

    /** @return array{fund_id:int,imported:int,matched:int,effective_date:string} */
    public function import(object $fund, string $payload, string $format = 'csv'): array
    {
        $rows = strtolower($format) === 'json' ? $this->jsonRows($payload) : $this->csvRows($payload);
        if ($rows->isEmpty()) {
            throw new RuntimeException("Die Anbieterdatei für {$fund->name} enthält keine Bestände.");
        }

        $effectiveDate = $rows->pluck('date')->filter()->map(fn ($date) => $this->date($date))->filter()->max()
            ?: $this->dateFromMetadata($payload)
            ?: now()->toDateString();
        $instrumentsByIsin = DB::table('instruments')->whereNotNull('isin')
            ->whereIn('isin', $rows->pluck('isin')->filter()->unique()->values())
            ->pluck('id', 'isin');
        $instrumentsByGermanSymbol = DB::table('instruments')
            ->whereNotNull('german_listing_symbol')
            ->whereIn(DB::raw('UPPER(german_listing_symbol)'), $rows->pluck('symbol')->filter()->map(fn ($symbol) => strtoupper(trim($symbol)))->unique()->values())
            ->get(['id', 'isin', 'german_listing_symbol'])
            ->keyBy(fn ($instrument) => strtoupper((string) $instrument->german_listing_symbol));
        $matched = 0;

        DB::transaction(function () use ($fund, $rows, $effectiveDate, $instrumentsByIsin, $instrumentsByGermanSymbol, &$matched): void {
            foreach ($rows as $row) {
                $isin = $this->isin($row['isin'] ?? null);
                $symbol = strtoupper(trim((string) ($row['symbol'] ?? '')));
                $instrument = $isin
                    ? (object) ['id' => $instrumentsByIsin->get($isin), 'isin' => $isin]
                    : $instrumentsByGermanSymbol->get($symbol);
                $instrumentId = $instrument?->id;
                $isin = $isin ?: $this->isin($instrument?->isin);
                if (! $isin || ! $instrumentId) {
                    continue;
                }
                $matched++;
                DB::table('etf_holdings')->updateOrInsert(
                    ['etf_fund_id' => $fund->id, 'holding_isin' => $isin, 'effective_date' => $effectiveDate],
                    [
                        'instrument_id' => $instrumentId,
                        'holding_symbol' => $this->text($row['symbol'] ?? null, 60),
                        'holding_name' => $this->text($row['name'] ?? null, 255),
                        'weight_percent' => $this->number($row['weight'] ?? null),
                        'source_updated_at' => now(), 'updated_at' => now(), 'created_at' => now(),
                    ]
                );
            }
            DB::table('etf_funds')->where('id', $fund->id)->update([
                'last_synced_at' => now(),
                'current_holding_count' => DB::table('etf_holdings')->where('etf_fund_id', $fund->id)->where('effective_date', $effectiveDate)->count(),
                'updated_at' => now(),
            ]);
        });

        return ['fund_id' => (int) $fund->id, 'imported' => $rows->count(), 'matched' => $matched, 'effective_date' => $effectiveDate];
    }

    private function csvRows(string $payload): Collection
    {
        $lines = preg_split('/\R/u', preg_replace('/^\xEF\xBB\xBF/', '', $payload)) ?: [];
        $headerIndex = collect($lines)->search(function ($line) {
            $line = strtolower($line);
            return str_contains($line, 'isin') || str_contains($line, 'emittententicker') || str_contains($line, 'issuer ticker');
        });
        if ($headerIndex === false) return collect();
        $delimiter = substr_count($lines[$headerIndex], ';') > substr_count($lines[$headerIndex], ',') ? ';' : ',';
        $headers = array_map([$this, 'key'], str_getcsv($lines[$headerIndex], $delimiter));

        return collect(array_slice($lines, $headerIndex + 1))->filter(fn ($line) => trim($line) !== '')
            ->map(function ($line) use ($delimiter, $headers) {
                $values = str_getcsv($line, $delimiter);
                if (count($values) < count($headers)) $values = array_pad($values, count($headers), null);
                return $this->normalize(array_combine($headers, array_slice($values, 0, count($headers))) ?: []);
            })->filter(fn ($row) => filled($row['isin'] ?? null) || filled($row['symbol'] ?? null))->values();
    }

    private function jsonRows(string $payload): Collection
    {
        $decoded = json_decode($payload, true, 512, JSON_THROW_ON_ERROR);
        $rows = $decoded['holdings'] ?? $decoded['data'] ?? $decoded;
        return collect(is_array($rows) ? $rows : [])->map(fn ($row) => $this->normalize((array) $row))
            ->filter(fn ($row) => filled($row['isin'] ?? null))->values();
    }

    private function normalize(array $row): array
    {
        $find = function (array $aliases) use ($row) {
            foreach ($aliases as $alias) if (array_key_exists($alias, $row)) return $row[$alias];
            return null;
        };
        return [
            'isin' => $find(['isin']),
            'symbol' => $find(['ticker', 'symbol', 'borsenticker', 'lokaler_ticker', 'emittententicker', 'issuer_ticker']),
            'name' => $find(['name', 'bezeichnung', 'security_name', 'holding_name', 'wertpapiername']),
            'weight' => $find(['weight', 'gewicht', 'gewichtung', 'weight_percent', 'portfolio_weight', 'fondsgewichtung']),
            'date' => $find(['date', 'datum', 'as_of_date', 'stand']),
        ];
    }

    private function key(string $value): string
    {
        $value = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', trim($value)) ?: $value;
        return trim(strtolower(preg_replace('/[^a-zA-Z0-9]+/', '_', $value)), '_');
    }

    private function isin(mixed $value): ?string
    {
        $isin = strtoupper(preg_replace('/\s+/', '', (string) $value));
        return preg_match('/^[A-Z]{2}[A-Z0-9]{9}[0-9]$/', $isin) ? $isin : null;
    }

    private function number(mixed $value): ?float
    {
        $value = trim(str_replace(['%', ' '], '', (string) $value));
        if (str_contains($value, ',') && ! str_contains($value, '.')) $value = str_replace(',', '.', $value);
        elseif (str_contains($value, ',') && str_contains($value, '.')) $value = str_replace(',', '', $value);
        return is_numeric($value) ? (float) $value : null;
    }

    private function date(mixed $value): ?string
    {
        try { return filled($value) ? CarbonImmutable::parse((string) $value)->toDateString() : null; }
        catch (\Throwable) { return null; }
    }

    private function dateFromMetadata(string $payload): ?string
    {
        if (preg_match('/[" ](\d{1,2}\.[A-Za-zÄÖÜäöü]+\.\d{4})["\r\n]/u', mb_substr($payload, 0, 500), $match)) {
            $months = ['Jan'=>'Jan', 'Feb'=>'Feb', 'Mär'=>'Mar', 'Apr'=>'Apr', 'Mai'=>'May', 'Jun'=>'Jun', 'Jul'=>'Jul', 'Aug'=>'Aug', 'Sep'=>'Sep', 'Okt'=>'Oct', 'Nov'=>'Nov', 'Dez'=>'Dec'];
            $date = str_replace(array_keys($months), array_values($months), $match[1]);
            return $this->date($date);
        }
        return null;
    }

    private function text(mixed $value, int $length): ?string
    {
        $value = trim((string) $value);
        return $value === '' ? null : mb_substr($value, 0, $length);
    }
}
