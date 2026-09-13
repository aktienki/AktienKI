<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('backtest_trades', function (Blueprint $table): void {
            $table->decimal('composite_score', 5, 2)
                ->nullable()
                ->after('signal_quality_score');
            $table->index(
                ['backtest_run_id', 'composite_score'],
                'backtest_trades_run_composite_score_idx',
            );
        });
    }

    public function down(): void
    {
        Schema::table('backtest_trades', function (Blueprint $table): void {
            $table->dropIndex('backtest_trades_run_composite_score_idx');
            $table->dropColumn('composite_score');
        });
    }
};
