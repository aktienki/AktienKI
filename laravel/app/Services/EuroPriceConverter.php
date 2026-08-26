<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;

final class EuroPriceConverter
{
    public function __construct(private readonly TwelveDataService $marketData) {}

    public function factor(string $currency): ?float
    {
        [$code, $minorFactor] = $this->normalizedCurrency($currency);
        if ($code === 'EUR') {
            return $minorFactor;
        }
        if (! preg_match('/^[A-Z]{3}$/', $code)) {
            return null;
        }

        $eurPerUnit = Cache::remember(
            "eur_conversion_factor_{$code}",
            now()->addHours(6),
            function () use ($code): ?float {
                $quote = $this->marketData->quote("EUR/{$code}");
                $unitsPerEuro = $quote['price'] ?? null;

                return is_numeric($unitsPerEuro) && (float) $unitsPerEuro > 0
                    ? 1 / (float) $unitsPerEuro
                    : null;
            },
        );

        return $eurPerUnit === null ? null : $eurPerUnit * $minorFactor;
    }

    /** @return array{string, float} */
    private function normalizedCurrency(string $currency): array
    {
        $rawCurrency = trim($currency);
        if (in_array($rawCurrency, ['GBp', 'GBX', 'GBx'], true)) {
            return ['GBP', 0.01];
        }
        $currency = strtoupper($rawCurrency);

        return match ($currency) {
            'GBP', 'USD', 'CHF', 'JPY', 'CNY', 'HKD', 'AUD', 'CAD', 'SEK', 'NOK', 'DKK', 'PLN', 'CZK', 'HUF', 'ZAR', 'EUR' => [$currency, 1.0],
            'GBPENCE' => ['GBP', 0.01],
            'ZAC' => ['ZAR', 0.01],
            default => [$currency, 1.0],
        };
    }
}
