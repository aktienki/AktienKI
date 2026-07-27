<?php

namespace App\Repositories;

use App\Models\Company;
use App\Models\User;
use App\Models\Watchlist;

class WatchlistRepository
{
    public function defaultFor(User $user): Watchlist
    {
        return Watchlist::query()->firstOrCreate(
            ['user_id' => $user->id, 'is_default' => true],
            ['name' => 'Meine Watchlist']
        );
    }

    public function addCompany(User $user, Company $company, ?Watchlist $watchlist = null): void
    {
        $watchlist ??= $this->defaultFor($user);

        $watchlist->items()->firstOrCreate(['company_id' => $company->id]);
    }

    public function removeCompany(User $user, Company $company, ?Watchlist $watchlist = null): void
    {
        $watchlist ??= $this->defaultFor($user);

        $watchlist->items()->where('company_id', $company->id)->delete();
    }
}
