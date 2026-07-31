<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;

final class SavedFilterLimitService
{
    public function limitFor(User $user): int
    {
        if (($user->role ?? null) === 'admin') {
            return 100;
        }

        $plan = DB::table('tariff_plans')->where('id', $user->tariff_plan_id)->first(['code', 'limits']);
        $limits = is_string($plan?->limits) ? (json_decode($plan->limits, true) ?: []) : (array) ($plan?->limits ?? []);
        if (isset($limits['saved_filters']) && is_numeric($limits['saved_filters'])) {
            return max(0, (int) $limits['saved_filters']);
        }

        return (int) config('aktienki.saved_filter_limits.'.strtolower((string) ($plan?->code ?: 'free')),
            config('aktienki.saved_filter_limits.default', 1));
    }
}
