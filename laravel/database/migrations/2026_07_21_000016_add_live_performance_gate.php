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
            $table->string('live_performance_status', 32)->default('insufficient_history');
            $table->unsignedInteger('live_performance_sample_size')->default(0);
            $table->decimal('live_direction_accuracy', 8, 6)->nullable();
            $table->decimal('live_median_prediction_error', 10, 6)->nullable();
            $table->boolean('live_performance_veto_used')->default(false);
            $table->jsonb('live_performance_details')->default(DB::raw("'{}'::jsonb"));
            $table->index(
                ['live_performance_status', 'live_performance_veto_used'],
                'predictions_live_performance_idx'
            );
        });
        $this->createPublicView(true);
    }

    public function down(): void
    {
        DB::statement('DROP VIEW IF EXISTS public_prediction_models');
        Schema::table('predictions', function (Blueprint $table): void {
            $table->dropIndex('predictions_live_performance_idx');
            $table->dropColumn([
                'live_performance_status', 'live_performance_sample_size',
                'live_direction_accuracy', 'live_median_prediction_error',
                'live_performance_veto_used', 'live_performance_details',
            ]);
        });
        $this->createPublicView(false);
    }

    private function createPublicView(bool $withLivePerformance): void
    {
        DB::statement('DROP VIEW IF EXISTS public_prediction_models');
        $extra = $withLivePerformance
            ? ', p.live_performance_status, p.live_performance_sample_size, p.live_direction_accuracy, p.live_median_prediction_error, p.live_performance_veto_used, p.live_performance_details'
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
                   p.raw_ai_score, p.score_calibration_version, p.score_calibration,
                   p.signal_consistency_confirmations, p.signal_consistency_required,
                   p.signal_consistency_veto_used, p.signal_consistency_details,
                   p.validated_at, p.validation_bar_time, p.actual_price,
                   p.actual_return, p.realized_strategy_return, p.prediction_error,
                   p.direction_correct, p.target_reached, p.validation_details,
                   p.signal_magnitude_stable, p.signal_magnitude_deviation
                   {$extra}
            FROM predictions p
            LEFT JOIN trained_models tm ON tm.id = p.trained_model_id
            LEFT JOIN model_definitions md ON md.id = tm.model_definition_id
            SQL);
    }
};
