<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('watchlist_items', function (Blueprint $table): void {
            $table->foreignId('prediction_id')
                ->nullable()
                ->after('instrument_id')
                ->constrained('predictions')
                ->nullOnDelete();
        });

        DB::statement(<<<'SQL'
            UPDATE watchlist_items AS item
            SET prediction_id = (
                SELECT prediction.id
                FROM predictions AS prediction
                WHERE prediction.instrument_id = item.instrument_id
                  AND prediction.prediction_time <= COALESCE(item.entry_price_at, item.added_at)
                ORDER BY prediction.prediction_time DESC, prediction.id DESC
                LIMIT 1
            )
            WHERE item.prediction_id IS NULL
        SQL);
    }

    public function down(): void
    {
        Schema::table('watchlist_items', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('prediction_id');
        });
    }
};
