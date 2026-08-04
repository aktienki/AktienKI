<?php

namespace App\Services;

use App\Models\User;
use App\Models\UserQualityGateProfile;

final class UserQualityGateService
{
    public function __construct(private readonly QualityGateAccessService $access) {}

    public function activeProfile(User $user): ?UserQualityGateProfile
    {
        if (! $this->access->allowed($user)) return null;
        $profile = UserQualityGateProfile::query()->where('user_id', $user->id)->where('is_active', true)->first();
        if (! $profile || (int) $profile->tariff_plan_id !== (int) $user->tariff_plan_id) return null;
        return $profile;
    }

    public function rules(User $user): ?array
    {
        return $this->activeProfile($user)?->rules;
    }
}
