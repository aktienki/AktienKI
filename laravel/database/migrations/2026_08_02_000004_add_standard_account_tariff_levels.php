<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();
        $plans = [
            ['code' => 'free', 'name' => 'Free', 'monthly_price_cents' => 0, 'yearly_price_cents' => 0, 'sort_order' => 0,
                'entitlements' => ['dashboard' => true], 'limits' => ['saved_filters' => 1]],
            ['code' => 'plus', 'name' => 'Plus', 'monthly_price_cents' => 990, 'yearly_price_cents' => 9900, 'sort_order' => 10,
                'entitlements' => ['dashboard' => true, 'strategy_tester' => true], 'limits' => ['saved_filters' => 3]],
            ['code' => 'pro', 'name' => 'Pro', 'monthly_price_cents' => 1900, 'yearly_price_cents' => 19000, 'sort_order' => 20,
                'entitlements' => ['dashboard' => true, 'strategy_tester' => true, 'custom_quality_gate' => true], 'limits' => ['saved_filters' => 10]],
            ['code' => 'premium', 'name' => 'Premium', 'monthly_price_cents' => 4900, 'yearly_price_cents' => 49000, 'sort_order' => 30,
                'entitlements' => ['dashboard' => true, 'strategy_tester' => true, 'custom_quality_gate' => true, 'all_features' => true], 'limits' => ['saved_filters' => 20]],
        ];
        foreach ($plans as $plan) {
            DB::table('tariff_plans')->updateOrInsert(['code' => $plan['code']], [
                'name' => $plan['name'],
                'monthly_price_cents' => $plan['monthly_price_cents'],
                'yearly_price_cents' => $plan['yearly_price_cents'],
                'currency' => 'EUR',
                'entitlements' => json_encode($plan['entitlements']),
                'limits' => json_encode($plan['limits']),
                'is_active' => true,
                'is_selectable' => true,
                'sort_order' => $plan['sort_order'],
                'deleted_at' => null,
                'updated_at' => $now,
                'created_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        DB::table('tariff_plans')->whereIn('code', ['plus', 'premium'])->delete();
    }
};
