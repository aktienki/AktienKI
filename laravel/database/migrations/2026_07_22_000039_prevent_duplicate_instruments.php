<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
            LOCK TABLE instruments IN SHARE ROW EXCLUSIVE MODE;

            UPDATE instruments
            SET symbol = UPPER(BTRIM(symbol)), updated_at = NOW()
            WHERE symbol <> UPPER(BTRIM(symbol));

            CREATE TEMP TABLE instrument_duplicate_map ON COMMIT DROP AS
            SELECT id AS duplicate_id,
                   MIN(id) OVER (PARTITION BY symbol) AS canonical_id
            FROM instruments;

            DELETE FROM instrument_duplicate_map
            WHERE duplicate_id = canonical_id;

            -- Time-series overlaps are equivalent observations. Keep the row
            -- already attached to the canonical instrument, then move the rest.
            DELETE FROM price_bars duplicate_bar
            USING instrument_duplicate_map map, price_bars canonical_bar
            WHERE duplicate_bar.instrument_id = map.duplicate_id
              AND canonical_bar.instrument_id = map.canonical_id
              AND canonical_bar.interval = duplicate_bar.interval
              AND canonical_bar.bar_time = duplicate_bar.bar_time;

            UPDATE price_bars row SET instrument_id = map.canonical_id
            FROM instrument_duplicate_map map
            WHERE row.instrument_id = map.duplicate_id;

            -- Update every existing optional relation that has an instrument_id.
            -- Some deployments intentionally omit parts of the platform schema.
            DO $migration$
            DECLARE
                relation_name text;
            BEGIN
                FOR relation_name IN
                    SELECT columns.table_name
                    FROM information_schema.columns
                    JOIN information_schema.tables
                      ON tables.table_schema = columns.table_schema
                     AND tables.table_name = columns.table_name
                    WHERE columns.table_schema = current_schema()
                      AND columns.column_name = 'instrument_id'
                      AND columns.table_name NOT IN ('instruments', 'price_bars')
                      AND tables.table_type = 'BASE TABLE'
                LOOP
                    EXECUTE format(
                        'UPDATE %I row SET instrument_id = map.canonical_id '
                        'FROM instrument_duplicate_map map WHERE row.instrument_id = map.duplicate_id',
                        relation_name
                    );
                END LOOP;
            END
            $migration$;

            DELETE FROM instruments instrument
            USING instrument_duplicate_map map
            WHERE instrument.id = map.duplicate_id;

            ALTER TABLE instruments
            ADD CONSTRAINT instruments_symbol_normalized_chk
            CHECK (symbol = UPPER(BTRIM(symbol)));

            CREATE UNIQUE INDEX instruments_symbol_unique ON instruments (symbol);
            SQL);
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS instruments_symbol_unique');
        DB::statement(
            'ALTER TABLE instruments DROP CONSTRAINT IF EXISTS instruments_symbol_normalized_chk'
        );
    }
};
