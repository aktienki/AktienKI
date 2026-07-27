<?php

namespace App\Services;

use App\Helpers\Aktienki;
use App\Models\Company;
use App\Models\MarketIndex;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class CompanyImportService
{
    public function upsertCompany(array $data): Company
    {
        $symbol = Aktienki::normalizeSymbol((string) Arr::get($data, 'symbol'));

        return Company::query()->updateOrCreate(
            ['symbol' => $symbol],
            [
                'isin' => Arr::get($data, 'isin'),
                'wkn' => Arr::get($data, 'wkn'),
                'name' => Arr::get($data, 'name', $symbol),
                'short_name' => Arr::get($data, 'short_name'),
                'exchange' => Arr::get($data, 'exchange'),
                'country' => Str::upper((string) Arr::get($data, 'country')) ?: null,
                'currency' => Str::upper((string) Arr::get($data, 'currency')) ?: null,
                'sector' => Arr::get($data, 'sector'),
                'industry' => Arr::get($data, 'industry'),
                'active' => Arr::get($data, 'active', true),
            ]
        );
    }

    public function syncIndexes(Company $company, array $indexCodes): void
    {
        $ids = collect($indexCodes)
            ->map(fn ($code) => strtoupper(trim((string) $code)))
            ->filter()
            ->map(fn ($code) => MarketIndex::query()->where('code', $code)->value('id'))
            ->filter()
            ->values()
            ->all();

        $company->marketIndexes()->sync($ids);
    }
}
