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
            $table->unsignedSmallInteger('signal_consistency_confirmations')->default(0);
            $table->unsignedSmallInteger('signal_consistency_required')->default(0);
            $table->boolean('signal_consistency_veto_used')->default(false);
            $table->jsonb('signal_consistency_details')->default(DB::raw("'{}'::jsonb"));
            $table->index(
                ['signal_consistency_veto_used', 'signal_consistency_confirmations'],
                'predictions_signal_consistency_idx'
            );
        });

        DB::statement(<<<'SQL'
            UPDATE predictions SET
                signal_consistency_confirmations = COALESCE(NULLIF(metadata->'signal_consistency'->>'confirmations', '')::smallint, 0),
                signal_consistency_required = COALESCE(NULLIF(metadata->'signal_consistency'->>'required_confirmations', '')::smallint, 0),
                signal_consistency_veto_used = COALESCE((metadata->'signal_consistency'->>'veto_used')::boolean, false),
                signal_consistency_details = COALESCE(metadata->'signal_consistency', '{}'::jsonb)
            SQL);

        $this->createPublicView(true);
    }

    public function down(): void
    {
        DB::statement('DROP VIEW IF EXISTS public_prediction_models');
        Schema::table('predictions', function (Blueprint $table): void {
            $table->dropIndex('predictions_signal_consistency_idx');
            $table->dropColumn([
                'signal_consistency_confirmations', 'signal_consistency_required',
                'signal_consistency_veto_used', 'signal_consistency_details',
            ]);
        });
        $this->createPublicView(false);
    }

    private function createPublicView(bool $withConsistency): void
    {
        DB::statement('DROP VIEW IF EXISTS public_prediction_models');
        $extra = $withConsistency
            ? ', p.signal_consistency_confirmations, p.signal_consistency_required, p.signal_consistency_veto_used, p.signal_consistency_details'
            : '';
        DB::statement(<<<SQL
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
                   p.crisis_veto_used, p.regime_performance,
                   p.prediction_plausibility_limit, p.prediction_outlier_detected,
                   p.prediction_outlier_veto_used, p.prediction_plausibility_details,
                   p.raw_ai_score, p.score_calibration_version, p.score_calibration
                   {$extra}
            FROM predictions p
            LEFT JOIN trained_models tm ON tm.id = p.trained_model_id
            LEFT JOIN model_definitions md ON md.id = tm.model_definition_id
            SQL);
    }
};
