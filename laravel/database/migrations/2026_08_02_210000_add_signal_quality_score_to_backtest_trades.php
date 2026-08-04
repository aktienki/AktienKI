<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('backtest_trades', function (Blueprint $table): void {
            $table->decimal('signal_quality_score', 5, 2)
                ->nullable()
                ->after('ki_score');
            $table->index(
                ['backtest_run_id', 'signal_quality_score'],
                'backtest_trades_run_signal_quality_idx'
            );
        });
    }

    public function down(): void
    {
        Schema::table('backtest_trades', function (Blueprint $table): void {
            $table->dropIndex('backtest_trades_run_signal_quality_idx');
            $table->dropColumn('signal_quality_score');
        });
    }
};
