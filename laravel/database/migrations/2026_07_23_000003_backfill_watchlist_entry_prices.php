<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement(<<<'SQL'
            UPDATE watchlist_items AS item
            SET entry_price = latest.current_price,
                entry_price_at = NOW(),
                entry_currency = instrument.currency,
                updated_at = NOW()
            FROM instruments AS instrument
            JOIN LATERAL (
                SELECT prediction.current_price
                FROM predictions AS prediction
                WHERE prediction.instrument_id = instrument.id
                  AND prediction.current_price IS NOT NULL
                ORDER BY prediction.prediction_time DESC, prediction.id DESC
                LIMIT 1
            ) AS latest ON TRUE
            WHERE item.instrument_id = instrument.id
              AND item.entry_price IS NULL
        SQL);
    }

    public function down(): void
    {
        // Historical entry prices cannot be reconstructed after the fact.
    }
};
