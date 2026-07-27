<?php

namespace App\Repositories;

use App\Models\Company;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

class CompanyRepository
{
    public function findBySymbol(string $symbol): ?Company
    {
        return Company::query()->where('symbol', strtoupper(trim($symbol)))->first();
    }

    public function activeWithLatestPrediction(int $perPage = 25): LengthAwarePaginator
    {
        return Company::query()
            ->active()
            ->with(['profile', 'latestPrediction', 'marketIndexes'])
            ->orderBy('name')
            ->paginate($perPage);
    }

    public function byIndex(string $indexCode): Collection
    {
        return Company::query()
            ->active()
            ->whereHas('marketIndexes', fn ($query) => $query->where('code', strtoupper(trim($indexCode))))
            ->with(['profile', 'latestPrediction', 'marketIndexes'])
            ->orderBy('name')
            ->get();
    }

    public function search(string $term, int $limit = 20): Collection
    {
        $term = trim($term);

        return Company::query()
            ->active()
            ->where(function ($query) use ($term) {
                $query->where('symbol', 'like', "%{$term}%")
                    ->orWhere('name', 'like', "%{$term}%")
                    ->orWhere('short_name', 'like', "%{$term}%")
                    ->orWhere('isin', 'like', "%{$term}%")
                    ->orWhere('wkn', 'like', "%{$term}%");
            })
            ->orderBy('symbol')
            ->limit($limit)
            ->get();
    }
}
