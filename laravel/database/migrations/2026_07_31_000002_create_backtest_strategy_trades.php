<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('backtest_strategy_trades', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('backtest_run_id')->constrained('backtest_runs')->cascadeOnDelete();
            $table->foreignId('backtest_trade_id')->constrained('backtest_trades')->cascadeOnDelete();
            $table->foreignId('instrument_id')->constrained('instruments')->cascadeOnDelete();
            $table->string('strategy', 32)->index();
            $table->date('entry_date')->index();
            $table->date('exit_date')->index();
            $table->decimal('entry_price', 20, 8);
            $table->decimal('exit_price', 20, 8);
            $table->decimal('gross_return', 14, 8);
            $table->decimal('max_drawdown', 14, 8)->nullable();
            $table->jsonb('metadata')->nullable();
            $table->timestampsTz();

            $table->unique(['backtest_run_id', 'backtest_trade_id', 'strategy'], 'backtest_strategy_trade_unique');
            $table->index(['backtest_run_id', 'strategy', 'entry_date'], 'backtest_strategy_run_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('backtest_strategy_trades');
    }
};
