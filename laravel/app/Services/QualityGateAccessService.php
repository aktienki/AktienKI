<?php

namespace App\Services;

use App\Models\User;
use App\Enums\PlanLevel;

final class QualityGateAccessService
{
    public function __construct(private readonly PlanAccessService $plans) {}

    public function allowed(User $user): bool
    {
        return $this->plans->allows($user, PlanLevel::Premium);
    }
}
