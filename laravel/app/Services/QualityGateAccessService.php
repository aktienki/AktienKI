<?php

namespace App\Services;

use App\Enums\PlanLevel;
use App\Models\User;

final class QualityGateAccessService
{
    public function __construct(private readonly PlanAccessService $plans) {}

    public function allowed(User $user): bool
    {
        return $this->plans->allows($user, PlanLevel::Premium);
    }
}
