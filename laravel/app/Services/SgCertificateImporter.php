<?php

namespace App\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

final class SgCertificateImporter
{
    private const API = 'https://www.sg-zertifikate.de/EmcWebApi/api/ProductSearch/Search';

    /** @var array<int, string> */
    private const PRODUCT_TYPES = [20 => 'discount_certificate', 16 => 'bonus_certificate'];

    public function sync(int $pageSize = 200, ?int $maxPages = null): array
    {
        $pageSize = min(1000, max(10, $pageSize));
        $instruments = DB::table('instruments')
            ->where('type', 'stock')->where('is_active', true)
            ->get(['id', 'isin', 'symbol', 'provider_symbol', 'german_listing_symbol', 'name', 'short_name']);

        $byIsin = $instruments->filter(fn ($row) => filled($row->isin))
            ->keyBy(fn ($row) => strtoupper((string) $row->isin));
        $byName = $instruments->groupBy(fn ($row) => $this->normaliseName((string) ($row->short_name ?: $row->name)))
            ->filter(fn ($rows, $key) => $key !== '' && $rows->count() === 1)
            ->map(fn ($rows) => $rows->first());

        $seen = [];
        $result = ['fetched' => 0, 'imported' => 0, 'unmatched' => 0, 'types' => []];

        foreach (self::PRODUCT_TYPES as $classificationId => $type) {
            $page = 1;
            $total = null;
            do {
                $response = Http::retry(3, 750)->timeout(60)->get(self::API, [
                    'ProductClassificationId' => $classificationId,
                    'PageSize' => $pageSize,
                    'PageNum' => $page,
                ])->throw()->json();

                $products = collect($response['Products'] ?? []);
                $total ??= (int) ($response['TotalCount'] ?? $products->count());
                $result['fetched'] += $products->count();
                $upserts = [];

                foreach ($products as $product) {
                    $isin = strtoupper(trim((string) ($product['Isin'] ?? '')));
                    $underlyingIsin = strtoupper(trim((string) ($product['AssetIsin'] ?? '')));
                    if (! preg_match('/^[A-Z]{2}[A-Z0-9]{9}[0-9]$/', $isin)) {
                        continue;
                    }

                    $instrument = $byIsin->get($underlyingIsin);
                    if (! $instrument) {
                        $instrument = $byName->get($this->normaliseName((string) ($product['AssetName'] ?? '')));
                    }
                    if (! $instrument) {
                        $result['unmatched']++;
                        continue;
                    }

                    if (! filled($instrument->isin) && preg_match('/^[A-Z]{2}[A-Z0-9]{9}[0-9]$/', $underlyingIsin)) {
                        DB::table('instruments')->where('id', $instrument->id)->update([
                            'isin' => $underlyingIsin,
                            'updated_at' => now(),
                        ]);
                        $instrument->isin = $underlyingIsin;
                        $byIsin->put($underlyingIsin, $instrument);
                    }

                    $maturity = $this->date($product['MaturityDate'] ?? $product['CalcDate'] ?? null);
                    $offer = $this->number($product['Offer'] ?? null);
                    $active = (int) ($product['Status'] ?? 0) === 1
                        && $offer !== null
                        && ($maturity === null || $maturity->isToday() || $maturity->isFuture());

                    $upserts[$isin] = [
                        'isin' => $isin,
                        'underlying_instrument_id' => $instrument->id,
                        'type' => $type,
                        'wkn' => trim((string) ($product['Code'] ?? '')) ?: null,
                        'name' => trim(($product['AssetName'] ?? 'Basiswert').' '.($type === 'discount_certificate' ? 'Discount-Zertifikat' : 'Bonus-Zertifikat')),
                        'issuer' => 'Société Générale Effekten GmbH',
                        'currency' => strtoupper((string) ($product['Currency'] ?? 'EUR')),
                        'exchange' => 'Société Générale Deutschland',
                        'mic_code' => (string) ($product['ExchangeCode'] ?? 'CBDE'),
                        'german_listing_symbol' => null,
                        'maturity_date' => $maturity?->toDateString(),
                        'price' => $offer,
                        'cap' => $this->number($product['StrikeDiscountCertificate'] ?? $product['Cap'] ?? null),
                        'barrier' => $this->number($product['BarrierAbsoluteLevel'] ?? $product['Barrier'] ?? null),
                        'bonus_level' => $this->number($product['BonusLevel'] ?? $product['BonusAmount'] ?? null),
                        'coupon_percent' => null,
                        'discount_percent' => $this->number($product['DiscountStrikeCurrencyPercent'] ?? null),
                        'quote_at' => now(),
                        'german_tradeability_verified_at' => now(),
                        'source_provider' => 'sg_zertifikate',
                        'source_url' => filled($product['Code'] ?? null)
                            ? 'https://www.sg-zertifikate.de/product-details/'.mb_strtolower(trim((string) $product['Code']))
                            : 'https://www.sg-zertifikate.de/produkte/produktsuche/'.($type === 'discount_certificate' ? 'discount-zertifikate' : 'bonus-zertifikate'),
                        'is_active' => $active,
                        'meta' => json_encode([
                            'provider_product_id' => $product['Id'] ?? null,
                            'classification_id' => $product['ProductClassificationId'] ?? null,
                            'bid' => $this->number($product['Bid'] ?? null),
                            'ask' => $offer,
                            'underlying_isin' => $underlyingIsin ?: null,
                            'underlying_ric' => $product['AssetRic'] ?? null,
                            'status' => $product['Status'] ?? null,
                        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                        'updated_at' => now(),
                        'created_at' => now(),
                    ];
                    $seen[] = $isin;
                    $result['imported']++;
                    $result['types'][$type] = ($result['types'][$type] ?? 0) + 1;
                }

                if ($upserts !== []) {
                    DB::table('linked_securities')->upsert(array_values($upserts), ['isin'], [
                        'underlying_instrument_id', 'type', 'wkn', 'name', 'issuer', 'currency',
                        'exchange', 'mic_code', 'german_listing_symbol', 'maturity_date', 'price',
                        'cap', 'barrier', 'bonus_level', 'coupon_percent', 'discount_percent',
                        'quote_at', 'german_tradeability_verified_at', 'source_provider',
                        'source_url', 'is_active', 'meta', 'updated_at',
                    ]);
                }

                $page++;
            } while (($page - 1) * $pageSize < $total && ($maxPages === null || $page <= $maxPages));
        }

        // Only a complete run may deactivate products missing from today's feed.
        if ($maxPages === null) {
            DB::table('linked_securities')->where('source_provider', 'sg_zertifikate')
                ->when($seen !== [], fn ($query) => $query->whereNotIn('isin', $seen))
                ->update(['is_active' => false, 'updated_at' => now()]);
        }

        return $result;
    }

    private function normaliseName(string $value): string
    {
        $value = Str::ascii(mb_strtolower($value));
        $value = preg_replace('/\b(ag|se|sa|nv|plc|inc|corp|corporation|company|co|ltd|limited|holding|holdings)\b/', '', $value) ?? $value;
        return trim(preg_replace('/[^a-z0-9]+/', ' ', $value) ?? '');
    }

    private function number(mixed $value): ?float
    {
        return is_numeric($value) ? (float) $value : null;
    }

    private function date(mixed $value): ?Carbon
    {
        if (! filled($value)) return null;
        try { return Carbon::parse((string) $value); } catch (\Throwable) { return null; }
    }
}
