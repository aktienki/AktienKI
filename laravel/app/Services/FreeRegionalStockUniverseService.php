<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final class FreeRegionalStockUniverseService
{
    private const EUROPE = ['AT', 'BE', 'BG', 'CH', 'CY', 'CZ', 'DE', 'DK', 'EE', 'ES', 'FI', 'FR', 'GB', 'GR', 'HR', 'HU', 'IE', 'IT', 'LT', 'LU', 'LV', 'MT', 'NL', 'PL', 'PT', 'RO', 'SE', 'SI', 'SK'];
    private const NORTH_AMERICA = ['US', 'CA'];
    private const ASIA_PACIFIC = ['AU', 'CN', 'HK', 'JP'];

    /** @return Collection<int, int> */
    public function instrumentIds(User $user, int $limit = 100): Collection
    {
        $country = strtoupper((string) data_get($user->preferences, 'country_code', 'DE'));
        $countries = $this->regionCountries($country);

        return DB::table('instruments as instrument')
            ->where('instrument.type', 'stock')
            ->where('instrument.is_active', true)
            ->whereNull('instrument.deleted_at')
            ->whereIn('instrument.country', $countries)
            // The selected country is filled first. Smaller markets are then
            // completed with the largest companies from the same region.
            ->orderByRaw('CASE WHEN instrument.country = ? THEN 0 ELSE 1 END', [$country])
            ->orderByDesc('instrument.market_cap')
            ->orderBy('instrument.symbol')
            ->limit(max(1, min(100, $limit)))
            ->pluck('instrument.id')
            ->map(fn ($id): int => (int) $id)
            ->values();
    }

    public function country(User $user): string
    {
        return strtoupper((string) data_get($user->preferences, 'country_code', 'DE'));
    }

    private function regionCountries(string $country): array
    {
        foreach ([self::EUROPE, self::NORTH_AMERICA, self::ASIA_PACIFIC] as $region) {
            if (in_array($country, $region, true)) return array_values(array_unique([$country, ...$region]));
        }

        return [$country];
    }
}
