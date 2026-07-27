<?php

namespace App\Repositories;

use App\Models\MarketIndex;
use Illuminate\Database\Eloquent\Collection;

class MarketIndexRepository
{
    public function active(): Collection
    {
        return MarketIndex::query()
            ->active()
            ->withCount('companies')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();
    }

    public function findByCode(string $code): ?MarketIndex
    {
        return MarketIndex::query()->where('code', strtoupper(trim($code)))->first();
    }
}
