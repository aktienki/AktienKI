<?php

namespace App\Services;

use App\Enums\PlanLevel;
use App\Models\User;

final class PersonalCollectionLimitService
{
    /** Null means unlimited. */
    public function watchlists(User $user): ?int
    {
        return $this->limitFor($user);
    }

    /** Null means unlimited. */
    public function paperDepots(User $user): ?int
    {
        return $this->limitFor($user);
    }

    private function limitFor(User $user): ?int
    {
        return match (app(PlanAccessService::class)->level($user)) {
            PlanLevel::Free => 1,
            PlanLevel::Plus => 5,
            PlanLevel::Pro, PlanLevel::Premium => null,
        };
    }
}
