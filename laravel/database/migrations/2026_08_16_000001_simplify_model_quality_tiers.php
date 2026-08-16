<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('model_quality_tiers')) {
            return;
        }

        DB::table('model_quality_tiers')->where('code', 'top')->update([
            'rank' => 99,
            'enabled' => false,
            'updated_at' => now(),
        ]);

        $tiers = [
            'strong' => ['name' => 'Stark', 'rank' => 1, 'score' => .70, 'factor' => 1.50, 'sharpe' => .75, 'accuracy' => .58, 'trades' => 30, 'drawdown' => .20],
            'solid' => ['name' => 'Gut', 'rank' => 2, 'score' => .50, 'factor' => 1.25, 'sharpe' => .35, 'accuracy' => .54, 'trades' => 20, 'drawdown' => .30],
            'test' => ['name' => 'Basis', 'rank' => 3, 'score' => .35, 'factor' => 1.10, 'sharpe' => .00, 'accuracy' => .51, 'trades' => 15, 'drawdown' => .40],
        ];

        foreach ($tiers as $code => $tier) {
            DB::table('model_quality_tiers')->where('code', $code)->update([
                'name' => $tier['name'],
                'rank' => $tier['rank'],
                'minimum_quality_score' => $tier['score'],
                'minimum_profit_factor' => $tier['factor'],
                'minimum_sharpe' => $tier['sharpe'],
                'minimum_direction_accuracy' => $tier['accuracy'],
                'minimum_trade_count' => $tier['trades'],
                'maximum_drawdown' => $tier['drawdown'],
                'enabled' => true,
                'updated_at' => now(),
            ]);
        }

        DB::statement(<<<'SQL'
            WITH matches AS (
                SELECT ranking.id AS ranking_id, matched.id AS tier_id
                FROM model_quality_rankings ranking
                LEFT JOIN LATERAL (
                    SELECT tier.id
                    FROM model_quality_tiers tier
                    WHERE tier.enabled
                      AND ranking.quality_score >= tier.minimum_quality_score
                      AND ranking.profit_factor >= tier.minimum_profit_factor
                      AND ranking.trade_count >= tier.minimum_trade_count
                      AND (tier.minimum_sharpe IS NULL OR ranking.sharpe >= tier.minimum_sharpe)
                      AND (tier.minimum_direction_accuracy IS NULL OR ranking.direction_accuracy >= tier.minimum_direction_accuracy)
                      AND (tier.maximum_drawdown IS NULL OR ranking.maximum_drawdown <= tier.maximum_drawdown)
                    ORDER BY tier.rank
                    LIMIT 1
                ) matched ON TRUE
            )
            UPDATE model_quality_rankings ranking
            SET tier_id = matched.id,
                eligible = matched.id IS NOT NULL,
                calculation_version = 'model-quality-tier-v2',
                calculated_at = NOW(),
                updated_at = NOW()
            FROM (SELECT ranking_id, tier_id AS id FROM matches) matched
            WHERE ranking.id = matched.ranking_id
            SQL);

        if (Schema::hasTable('saved_prediction_filters')) {
            DB::statement(<<<'SQL'
                UPDATE saved_prediction_filters
                SET filters = jsonb_set(filters, '{quality_tier}', '"strong"'::jsonb),
                    updated_at = NOW()
                WHERE filters->>'quality_tier' = 'top'
                SQL);
        }
    }

    public function down(): void
    {
        DB::table('model_quality_tiers')->where('code', 'top')->update([
            'name' => 'Quality Gate', 'rank' => 1, 'enabled' => true, 'updated_at' => now(),
        ]);
        DB::table('model_quality_tiers')->where('code', 'strong')->update([
            'name' => 'Stark', 'rank' => 2, 'minimum_quality_score' => .50,
            'minimum_profit_factor' => 1.25, 'minimum_sharpe' => .35,
            'minimum_direction_accuracy' => .54, 'minimum_trade_count' => 20,
            'maximum_drawdown' => .30, 'updated_at' => now(),
        ]);
        DB::table('model_quality_tiers')->where('code', 'solid')->update([
            'name' => 'Solide', 'rank' => 3, 'minimum_quality_score' => .35,
            'minimum_profit_factor' => 1.10, 'minimum_sharpe' => .00,
            'minimum_direction_accuracy' => .51, 'minimum_trade_count' => 15,
            'maximum_drawdown' => .40, 'updated_at' => now(),
        ]);
        DB::table('model_quality_tiers')->where('code', 'test')->update([
            'name' => 'Basis', 'rank' => 4, 'minimum_quality_score' => .00,
            'minimum_profit_factor' => 1.00, 'minimum_sharpe' => null,
            'minimum_direction_accuracy' => null, 'minimum_trade_count' => 10,
            'maximum_drawdown' => null, 'updated_at' => now(),
        ]);
    }
};
