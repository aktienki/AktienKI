<?php

namespace App\Policies;

use App\Models\Company;
use App\Models\User;
use App\Services\SubscriptionAccessService;

class CompanyPolicy
{
    public function view(User $user, Company $company): bool
    {
        return app(SubscriptionAccessService::class)->canAccessCompany($user, $company);
    }
}
