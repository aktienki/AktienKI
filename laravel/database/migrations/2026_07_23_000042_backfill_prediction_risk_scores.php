<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement(<<<'SQL'
            UPDATE predictions
            SET risk_score = COALESCE(
                drawdown_risk_factor,
                NULLIF(metadata->'factor_ratings'->'drawdown_risk'->>'value', '')::numeric
            )
            WHERE risk_score IS NULL
              AND (
                  drawdown_risk_factor IS NOT NULL
                  OR metadata->'factor_ratings'->'drawdown_risk'->>'value' IS NOT NULL
              )
            SQL);

        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION sync_prediction_risk_score()
            RETURNS trigger AS $$
            BEGIN
                NEW.risk_score := COALESCE(
                    NEW.risk_score,
                    NEW.drawdown_risk_factor,
                    NULLIF(
                        NEW.metadata->'factor_ratings'->'drawdown_risk'->>'value',
                        ''
                    )::numeric
                );

                RETURN NEW;
            END;
            $$ LANGUAGE plpgsql
            SQL);

        DB::unprepared(<<<'SQL'
            CREATE TRIGGER predictions_sync_risk_score
            BEFORE INSERT OR UPDATE OF metadata, drawdown_risk_factor, risk_score
            ON predictions
            FOR EACH ROW
            EXECUTE FUNCTION sync_prediction_risk_score()
            SQL);
    }

    public function down(): void
    {
        DB::statement(
            'DROP TRIGGER IF EXISTS predictions_sync_risk_score ON predictions'
        );
        DB::statement('DROP FUNCTION IF EXISTS sync_prediction_risk_score()');
    }
};
