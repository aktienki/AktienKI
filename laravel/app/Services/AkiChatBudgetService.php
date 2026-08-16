<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

final class AkiChatBudgetService
{
    public function planCode(User $user): string
    {
        if ($user->is_admin) return 'pro';

        return strtolower((string) (DB::table('tariff_plans')->where('id', $user->tariff_plan_id)->value('code') ?: 'free'));
    }

    public function modeFor(User $user, string $requested): string
    {
        return $requested === 'deep' && $this->planCode($user) === 'pro' ? 'deep' : 'standard';
    }

    public function reserve(User $user, string $mode): string
    {
        $plan = $this->planCode($user);
        $hardLimit = (int) config("aki_chat.monthly_hard_limit_cents.$plan", 0) * 10_000;
        $reservation = (int) config("aki_chat.reservation_cents.$mode", 5) * 10_000;
        $requestId = (string) Str::uuid();

        DB::transaction(function () use ($user, $mode, $hardLimit, $reservation, $requestId): void {
            DB::table('users')->where('id', $user->id)->lockForUpdate()->first(['id']);
            $monthStart = now()->startOfMonth();
            $spent = (int) DB::table('aki_chat_usages')->where('user_id', $user->id)->where('occurred_at', '>=', $monthStart)->where('status', 'completed')->sum('cost_micros_eur');
            $pending = (int) DB::table('aki_chat_usages')->where('user_id', $user->id)->where('occurred_at', '>=', $monthStart)->where('status', 'reserved')->sum('reserved_micros_eur');
            if ($hardLimit <= 0 || ($spent + $pending + $reservation) > $hardLimit) {
                throw new RuntimeException('AKI_MONTHLY_BUDGET_EXHAUSTED');
            }
            DB::table('aki_chat_usages')->insert([
                'request_id' => $requestId, 'user_id' => $user->id, 'mode' => $mode,
                'model' => (string) config("aki_chat.models.$mode"), 'status' => 'reserved',
                'reserved_micros_eur' => $reservation, 'occurred_at' => now(), 'created_at' => now(), 'updated_at' => now(),
            ]);
        });

        return $requestId;
    }

    public function complete(string $requestId, array $usage, string $model): void
    {
        $input = (int) ($usage['input_tokens'] ?? 0);
        $cached = min($input, (int) data_get($usage, 'input_tokens_details.cached_tokens', 0));
        $output = (int) ($usage['output_tokens'] ?? 0);
        $priceTable = config('aki_chat.prices_usd_per_million', []);
        $prices = is_array($priceTable) ? ($priceTable[$model] ?? $priceTable['gpt-5.4-mini'] ?? null) : null;
        $prices = array_merge([
            'input' => 0.75,
            'cached_input' => 0.075,
            'output' => 4.50,
        ], is_array($prices) ? $prices : []);
        $usd = (($input - $cached) * (float) $prices['input'] + $cached * (float) $prices['cached_input'] + $output * (float) $prices['output']) / 1_000_000;
        $micros = (int) ceil($usd * (float) config('aki_chat.usd_to_eur', .92) * 1_000_000);

        DB::table('aki_chat_usages')->where('request_id', $requestId)->update([
            'model' => $model, 'status' => 'completed', 'input_tokens' => $input,
            'cached_input_tokens' => $cached, 'output_tokens' => $output,
            'cost_micros_eur' => $micros, 'reserved_micros_eur' => 0, 'updated_at' => now(),
        ]);
    }

    public function release(string $requestId): void
    {
        DB::table('aki_chat_usages')->where('request_id', $requestId)->where('status', 'reserved')->update(['status' => 'failed', 'reserved_micros_eur' => 0, 'updated_at' => now()]);
    }

    public function summary(User $user): array
    {
        $plan = $this->planCode($user);
        $budget = (int) config("aki_chat.monthly_budget_cents.$plan", 0) * 10_000;
        $spent = (int) DB::table('aki_chat_usages')->where('user_id', $user->id)->where('occurred_at', '>=', now()->startOfMonth())->where('status', 'completed')->sum('cost_micros_eur');
        $percent = $budget > 0 ? min(100, (int) round($spent / $budget * 100)) : 100;

        return ['spent_eur' => round($spent / 1_000_000, 2), 'budget_eur' => round($budget / 1_000_000, 2), 'remaining_eur' => round(max(0, $budget - $spent) / 1_000_000, 2), 'percent' => $percent, 'warning' => $percent >= (int) config('aki_chat.warning_percent', 80), 'resets_at' => now()->addMonthNoOverflow()->startOfMonth()->toDateString()];
    }

    public function mergeUsage(array ...$usages): array
    {
        $result = ['input_tokens' => 0, 'output_tokens' => 0, 'input_tokens_details' => ['cached_tokens' => 0]];
        foreach ($usages as $usage) {
            $result['input_tokens'] += (int) ($usage['input_tokens'] ?? 0);
            $result['output_tokens'] += (int) ($usage['output_tokens'] ?? 0);
            $result['input_tokens_details']['cached_tokens'] += (int) data_get($usage, 'input_tokens_details.cached_tokens', 0);
        }
        return $result;
    }
}
