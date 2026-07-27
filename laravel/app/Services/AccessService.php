<?php

namespace App\Services;

use App\Enums\PlanLevel;
use App\Models\Company;
use App\Models\MarketIndex;
use App\Models\SubscriptionPlan;
use App\Models\User;

class AccessService
{
    public function userCanAccessIndex(?User $user, MarketIndex|string $index): bool
    {
        if ($user?->isAdmin()) {
            return true;
        }

        $code = $index instanceof MarketIndex ? $index->code : strtoupper(trim($index));
        $plan = $user?->currentPlan() ?? SubscriptionPlan::query()->where('code', config('aktienki.default_plan', 'free'))->first();

        return (bool) $plan?->allowsIndex($code);
    }

    public function userCanAccessCompany(?User $user, Company $company): bool
    {
        if ($user?->isAdmin()) {
            return true;
        }

        $company->loadMissing('marketIndexes');

        if ($company->marketIndexes->isEmpty()) {
            return true;
        }

        foreach ($company->marketIndexes as $index) {
            if ($this->userCanAccessIndex($user, $index)) {
                return true;
            }
        }

        return false;
    }

    public function planIncludesLevel(SubscriptionPlan|string|null $plan, string|null $requiredLevel): bool
    {
        $level = $plan instanceof SubscriptionPlan ? $plan->level : ($plan ?: config('aktienki.default_plan', 'free'));
        $planLevel = PlanLevel::tryFrom(strtolower((string) $level)) ?? PlanLevel::Free;

        return $planLevel->includes($requiredLevel);
    }
}
