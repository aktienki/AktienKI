<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('backtest_runs', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->string('status', 24)->default('running')->index();
            $table->string('strategy', 64)->default('horizon_20d');
            $table->string('timeframe', 16)->default('1d');
            $table->unsignedSmallInteger('horizon_days')->default(20);
            $table->timestampTz('started_at');
            $table->timestampTz('finished_at')->nullable();
            $table->unsignedInteger('instruments_total')->default(0);
            $table->unsignedInteger('instruments_completed')->default(0);
            $table->unsignedInteger('trades_count')->default(0);
            $table->jsonb('settings')->nullable();
            $table->jsonb('summary')->nullable();
            $table->text('error_message')->nullable();
            $table->timestampsTz();
        });

        Schema::create('backtest_trades', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('backtest_run_id')->constrained('backtest_runs')->cascadeOnDelete();
            $table->foreignId('instrument_id')->constrained('instruments')->cascadeOnDelete();
            $table->foreignId('trained_model_id')->nullable()->constrained('trained_models')->nullOnDelete();
            $table->foreignId('model_definition_id')->nullable()->constrained('model_definitions')->nullOnDelete();
            $table->string('ai_type', 24)->default('horizon')->index();
            $table->string('timeframe', 16)->default('1d');
            $table->unsignedSmallInteger('horizon_days')->default(20);
            $table->date('entry_date')->index();
            $table->date('exit_date')->index();
            $table->string('signal', 12)->index();
            $table->decimal('entry_price', 20, 8);
            $table->decimal('exit_price', 20, 8);
            $table->decimal('predicted_return', 14, 8)->nullable();
            $table->decimal('gross_return', 14, 8);
            $table->decimal('net_return', 14, 8);
            $table->decimal('max_drawdown', 14, 8)->nullable();
            $table->decimal('ki_score', 6, 3)->index();
            $table->decimal('confidence', 7, 4)->index();
            $table->decimal('quality_gate_score', 10, 8)->nullable();
            $table->decimal('transaction_cost', 10, 8)->default(0);
            $table->jsonb('metadata')->nullable();
            $table->timestampsTz();

            $table->unique(
                ['backtest_run_id', 'instrument_id', 'trained_model_id', 'entry_date', 'signal'],
                'backtest_trades_run_instrument_model_entry_signal_unique'
            );
            $table->index(['ki_score', 'confidence'], 'backtest_trades_heatmap_idx');
            $table->index(['model_definition_id', 'signal'], 'backtest_trades_model_signal_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('backtest_trades');
        Schema::dropIfExists('backtest_runs');
    }
};
