<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('predictions', function (Blueprint $table): void {
            $table->string('market_regime_label', 20)->default('NORMAL');
            $table->unsignedSmallInteger('crisis_regime_score')->default(0);
            $table->boolean('crisis_veto_used')->default(false);
            $table->jsonb('regime_performance')->default(DB::raw("'{}'::jsonb"));
            $table->index(
                ['market_regime_label', 'crisis_veto_used'],
                'predictions_crisis_regime_idx'
            );
        });

        DB::statement(<<<'SQL'
            UPDATE predictions SET
                market_regime_label = COALESCE(metadata->>'crisis_regime', 'NORMAL'),
                crisis_regime_score = COALESCE(NULLIF(metadata->>'crisis_regime_score', '')::smallint, 0),
                crisis_veto_used = COALESCE((metadata->'regime_verification'->>'crisis_veto_used')::boolean, false),
                regime_performance = COALESCE(metadata->'regime_performance', '{}'::jsonb)
            SQL);

        DB::statement('DROP VIEW IF EXISTS public_prediction_models');
        DB::statement(<<<'SQL'
            CREATE VIEW public_prediction_models AS
            SELECT p.id, p.instrument_id, p.ai_type, p.prediction_time, p.timeframe,
                   p.prediction_horizon_minutes, p.signal, p.ai_score,
                   p.signal_source, p.fallback_used,
                   p.hurdle_long_status, p.hurdle_short_status,
                   CASE WHEN p.signal_source = 'directional_hurdle'
                        THEN CASE WHEN p.ai_type = 'pulse' THEN 'PN-07' ELSE 'HH-07' END
                        ELSE md.public_code END::varchar(20) AS public_code,
                   CASE WHEN p.signal_source = 'directional_hurdle'
                        THEN CASE WHEN p.ai_type = 'pulse' THEN 'Pulse Aegis' ELSE 'Horizon Aegis' END
                        ELSE md.public_alias END::varchar(100) AS public_alias,
                   CASE WHEN p.signal_source = 'directional_hurdle'
                        THEN 'Directional specialist'
                        ELSE md.public_description END::varchar(255) AS public_description,
                   p.trigger_confirmation_score, p.trigger_bonus, p.trigger_penalty,
                   p.trigger_confirmation_count, p.trigger_release_used,
                   p.trigger_veto_used, p.trigger_details,
                   p.risk_profile, p.base_return_threshold,
                   p.profit_target_return, p.risk_budget_return,
                   p.target_achievement, p.expected_risk_reward,
                   p.opportunity_risk_rating, p.recommendation_class,
                   p.watch_return_threshold, p.buy_return_threshold,
                   p.market_regime_label, p.crisis_regime_score,
                   p.crisis_veto_used, p.regime_performance
            FROM predictions p
            LEFT JOIN trained_models tm ON tm.id = p.trained_model_id
            LEFT JOIN model_definitions md ON md.id = tm.model_definition_id
            SQL);
    }

    public function down(): void
    {
        DB::statement('DROP VIEW IF EXISTS public_prediction_models');
        Schema::table('predictions', function (Blueprint $table): void {
            $table->dropIndex('predictions_crisis_regime_idx');
            $table->dropColumn([
                'market_regime_label', 'crisis_regime_score',
                'crisis_veto_used', 'regime_performance',
            ]);
        });
        DB::statement(<<<'SQL'
            CREATE VIEW public_prediction_models AS
            SELECT p.id, p.instrument_id, p.ai_type, p.prediction_time, p.timeframe,
                   p.prediction_horizon_minutes, p.signal, p.ai_score,
                   p.signal_source, p.fallback_used,
                   p.hurdle_long_status, p.hurdle_short_status,
                   CASE WHEN p.signal_source = 'directional_hurdle'
                        THEN CASE WHEN p.ai_type = 'pulse' THEN 'PN-07' ELSE 'HH-07' END
                        ELSE md.public_code END::varchar(20) AS public_code,
                   CASE WHEN p.signal_source = 'directional_hurdle'
                        THEN CASE WHEN p.ai_type = 'pulse' THEN 'Pulse Aegis' ELSE 'Horizon Aegis' END
                        ELSE md.public_alias END::varchar(100) AS public_alias,
                   CASE WHEN p.signal_source = 'directional_hurdle'
                        THEN 'Directional specialist'
                        ELSE md.public_description END::varchar(255) AS public_description,
                   p.trigger_confirmation_score, p.trigger_bonus, p.trigger_penalty,
                   p.trigger_confirmation_count, p.trigger_release_used,
                   p.trigger_veto_used, p.trigger_details,
                   p.risk_profile, p.base_return_threshold,
                   p.profit_target_return, p.risk_budget_return,
                   p.target_achievement, p.expected_risk_reward,
                   p.opportunity_risk_rating, p.recommendation_class,
                   p.watch_return_threshold, p.buy_return_threshold
            FROM predictions p
            LEFT JOIN trained_models tm ON tm.id = p.trained_model_id
            LEFT JOIN model_definitions md ON md.id = tm.model_definition_id
            SQL);
    }
};
