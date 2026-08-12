<?php

namespace App\Services;

use App\Enums\PlanLevel;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final class PlanAccessService
{
    public function allows(User $user, PlanLevel|string $required): bool
    {
        if ((bool) $user->is_admin || strtolower((string) ($user->role ?? '')) === 'admin') return true;
        if ((bool) $user->beta_access_exempt) return true;
        return $this->allowsTariff($user, $required);
    }

    /** Check the purchased tariff itself without admin or beta overrides. */
    public function allowsTariff(User $user, PlanLevel|string $required): bool
    {
        $requiredLevel = $required instanceof PlanLevel ? $required : PlanLevel::tryFrom(strtolower($required));
        if (! $requiredLevel) return false;

        return $this->level($user)->includes($requiredLevel);
    }

    public function level(User $user): PlanLevel
    {

        $directPlan = DB::table('tariff_plans')->where('id', $user->tariff_plan_id)->first(['code']);
        $code = $directPlan?->code;
        if ($code && strtolower((string) $code) !== 'free') {
            $statusValid = in_array((string) ($user->tariff_status ?? ''), ['active', 'trialing'], true);
            $periodValid = $user->tariff_ends_at === null || $user->tariff_ends_at->isFuture();
            if (! $statusValid || ! $periodValid) $code = null;
        }
        if (! $code) {
            $code = DB::table('billing_subscriptions as subscription')
                ->join('tariff_plans as plan', 'plan.id', '=', 'subscription.tariff_plan_id')
                ->where('subscription.user_id', $user->id)
                ->where('subscription.product_type', 'tariff')
                ->whereIn('subscription.status', ['active', 'trialing'])
                ->where(fn ($query) => $query->whereNull('subscription.current_period_end')->orWhere('subscription.current_period_end', '>', now()))
                ->orderByDesc('plan.sort_order')->value('plan.code');
        }
        $normalized = match (strtolower((string) $code)) {
            'basic', 'basis' => 'plus',
            'expert', 'ultimate' => 'premium',
            default => strtolower((string) ($code ?: 'free')),
        };
        return PlanLevel::tryFrom($normalized) ?? PlanLevel::Free;
    }
}
