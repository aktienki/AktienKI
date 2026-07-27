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
            $table->string('risk_profile', 20)->nullable();
            $table->decimal('base_return_threshold', 8, 6)->nullable();
            $table->decimal('profit_target_return', 8, 6)->nullable();
            $table->decimal('risk_budget_return', 8, 6)->nullable();
            $table->decimal('target_achievement', 10, 6)->nullable();
            $table->decimal('expected_risk_reward', 10, 6)->nullable();
            $table->unsignedSmallInteger('opportunity_risk_rating')->nullable();
            $table->index(
                ['risk_profile', 'opportunity_risk_rating'],
                'predictions_opportunity_risk_idx'
            );
        });

        DB::statement(<<<'SQL'
            UPDATE predictions SET
                risk_profile = metadata->'opportunity_risk'->>'risk_profile',
                base_return_threshold = NULLIF(metadata->'opportunity_risk'->>'base_return_threshold', '')::numeric,
                profit_target_return = NULLIF(metadata->'opportunity_risk'->>'profit_target_return', '')::numeric,
                risk_budget_return = NULLIF(metadata->'opportunity_risk'->>'risk_budget_return', '')::numeric,
                target_achievement = NULLIF(metadata->'opportunity_risk'->>'target_achievement', '')::numeric,
                expected_risk_reward = NULLIF(metadata->'opportunity_risk'->>'expected_risk_reward', '')::numeric,
                opportunity_risk_rating = NULLIF(metadata->'opportunity_risk'->>'rating', '')::smallint
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
                   p.opportunity_risk_rating
            FROM predictions p
            LEFT JOIN trained_models tm ON tm.id = p.trained_model_id
            LEFT JOIN model_definitions md ON md.id = tm.model_definition_id
            SQL);
    }

    public function down(): void
    {
        DB::statement('DROP VIEW IF EXISTS public_prediction_models');
        Schema::table('predictions', function (Blueprint $table): void {
            $table->dropIndex('predictions_opportunity_risk_idx');
            $table->dropColumn([
                'risk_profile', 'base_return_threshold', 'profit_target_return',
                'risk_budget_return', 'target_achievement',
                'expected_risk_reward', 'opportunity_risk_rating',
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
                   p.trigger_veto_used, p.trigger_details
            FROM predictions p
            LEFT JOIN trained_models tm ON tm.id = p.trained_model_id
            LEFT JOIN model_definitions md ON md.id = tm.model_definition_id
            SQL);
    }
};
