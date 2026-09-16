<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Copy table of serving_predictions into main DB for join
        DB::connection('pgsql')->statement(<<<'SQL'
            CREATE TABLE IF NOT EXISTS serving_predictions_copy (
                id BIGSERIAL PRIMARY KEY,
                instrument_id INT NOT NULL,
                signal VARCHAR(10),
                expected_return NUMERIC(10, 6),
                created_at TIMESTAMP,
                UNIQUE(instrument_id, created_at)
            )
        SQL);

        // Materialized view combining predictions + serving predictions
        DB::connection('pgsql')->statement(<<<'SQL'
            CREATE MATERIALIZED VIEW today_highlights_mv AS
            SELECT
                COALESCE(p.instrument_id, sp.instrument_id) as instrument_id,
                p.signal as predictions_signal,
                p.created_at::date as prediction_date,
                sp.signal as serving_signal,
                sp.expected_return,
                i.symbol,
                i.name
            FROM predictions p
            FULL OUTER JOIN serving_predictions_copy sp
                ON sp.instrument_id = p.instrument_id
                AND sp.created_at::date = p.created_at::date
            LEFT JOIN instruments i ON i.id = COALESCE(p.instrument_id, sp.instrument_id)
            WHERE (p.created_at::date = CURRENT_DATE OR sp.created_at::date = CURRENT_DATE)
        SQL);

        DB::connection('pgsql')->statement('CREATE INDEX idx_today_highlights_mv_instrument ON today_highlights_mv(instrument_id)');
        DB::connection('pgsql')->statement('CREATE INDEX idx_today_highlights_mv_prediction_signal ON today_highlights_mv(predictions_signal)');
        DB::connection('pgsql')->statement('CREATE INDEX idx_today_highlights_mv_serving_signal ON today_highlights_mv(serving_signal)');
    }

    public function down(): void
    {
        DB::connection('pgsql')->statement('DROP MATERIALIZED VIEW IF EXISTS today_highlights_mv CASCADE');
        DB::connection('pgsql')->statement('DROP TABLE IF EXISTS serving_predictions_copy');
    }
};
