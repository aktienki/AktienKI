<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement(<<<'SQL'
            ALTER TABLE predictions
            ADD CONSTRAINT predictions_source_lineage_complete_chk CHECK (
                (source_bar_time IS NULL AND source_data_hash IS NULL)
                OR (
                    source_bar_time IS NOT NULL
                    AND source_data_hash ~ '^[0-9a-f]{64}$'
                )
            ),
            ADD CONSTRAINT predictions_source_not_future_chk CHECK (
                source_bar_time IS NULL OR source_bar_time <= prediction_time
            ),
            ADD CONSTRAINT predictions_validation_after_source_chk CHECK (
                validation_bar_time IS NULL OR source_bar_time IS NULL
                OR validation_bar_time >= source_bar_time
            )
            SQL);
        DB::statement(<<<'SQL'
            ALTER TABLE trained_models
            ADD CONSTRAINT trained_models_period_order_chk CHECK (
                training_period_start IS NULL OR training_period_end IS NULL
                OR training_period_start <= training_period_end
            ),
            ADD CONSTRAINT trained_models_no_future_data_chk CHECK (
                trained_at IS NULL OR training_period_end IS NULL
                OR training_period_end <= trained_at
            )
            SQL);
    }

    public function down(): void
    {
        DB::statement(<<<'SQL'
            ALTER TABLE predictions
            DROP CONSTRAINT IF EXISTS predictions_source_lineage_complete_chk,
            DROP CONSTRAINT IF EXISTS predictions_source_not_future_chk,
            DROP CONSTRAINT IF EXISTS predictions_validation_after_source_chk
            SQL);
        DB::statement(<<<'SQL'
            ALTER TABLE trained_models
            DROP CONSTRAINT IF EXISTS trained_models_period_order_chk,
            DROP CONSTRAINT IF EXISTS trained_models_no_future_data_chk
            SQL);
    }
};
