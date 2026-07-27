<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void {
        DB::unprepared(<<<'SQL'
ALTER TABLE trained_models ADD COLUMN position_side VARCHAR(8) NOT NULL DEFAULT 'long',
 ADD CONSTRAINT trained_models_position_side_chk CHECK (position_side IN ('long','short'));
ALTER TABLE predictions ADD COLUMN position_side VARCHAR(8) NOT NULL DEFAULT 'long',
 ADD CONSTRAINT predictions_position_side_chk CHECK (position_side IN ('long','short'));
DROP INDEX trained_models_single_active_scope_idx;
CREATE UNIQUE INDEX trained_models_single_active_scope_idx ON trained_models
 (instrument_id,ai_type,timeframe,prediction_horizon_minutes,feature_set_version,position_side)
 WHERE status='active';
DROP INDEX predictions_source_bar_idempotency_idx;
CREATE UNIQUE INDEX predictions_source_bar_idempotency_idx ON predictions
 (instrument_id,ai_type,timeframe,prediction_horizon_minutes,position_side,source_bar_time)
 WHERE source_bar_time IS NOT NULL;
SQL);
    }
    public function down(): void {
        DB::unprepared(<<<'SQL'
DROP INDEX predictions_source_bar_idempotency_idx;
CREATE UNIQUE INDEX predictions_source_bar_idempotency_idx ON predictions
 (instrument_id,ai_type,timeframe,prediction_horizon_minutes,source_bar_time)
 WHERE source_bar_time IS NOT NULL;
DROP INDEX trained_models_single_active_scope_idx;
CREATE UNIQUE INDEX trained_models_single_active_scope_idx ON trained_models
 (instrument_id,ai_type,timeframe,prediction_horizon_minutes,feature_set_version)
 WHERE status='active';
ALTER TABLE predictions DROP COLUMN position_side;
ALTER TABLE trained_models DROP COLUMN position_side;
SQL);
    }
};
