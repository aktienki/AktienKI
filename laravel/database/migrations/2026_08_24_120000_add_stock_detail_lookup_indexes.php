<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public $withinTransaction = false;

    public function up(): void
    {
        DB::unprepared('CREATE INDEX CONCURRENTLY IF NOT EXISTS backtest_trades_instrument_run_idx ON backtest_trades (instrument_id, backtest_run_id)');
        DB::unprepared('CREATE INDEX CONCURRENTLY IF NOT EXISTS walk_forward_trades_instrument_run_idx ON walk_forward_backtest_trades (instrument_id, run_id)');
        DB::unprepared('CREATE INDEX CONCURRENTLY IF NOT EXISTS news_instrument_published_idx ON news (instrument_id, published_at DESC)');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS news_instrument_published_idx');
        DB::statement('DROP INDEX IF EXISTS walk_forward_trades_instrument_run_idx');
        DB::statement('DROP INDEX IF EXISTS backtest_trades_instrument_run_idx');
    }
};
