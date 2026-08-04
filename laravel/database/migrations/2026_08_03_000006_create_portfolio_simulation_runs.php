<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('portfolio_simulation_runs', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('saved_prediction_filter_id')->constrained('saved_prediction_filters')->cascadeOnDelete();
            $table->foreignId('portfolio_id')->constrained()->cascadeOnDelete();
            $table->foreignId('backtest_run_id')->constrained('backtest_runs')->cascadeOnDelete();
            $table->string('status', 24)->default('running')->index();
            $table->timestampTz('started_at');
            $table->timestampTz('finished_at')->nullable();
            $table->date('simulation_start_date')->nullable();
            $table->date('simulation_end_date')->nullable();
            $table->decimal('initial_capital', 20, 6);
            $table->decimal('final_capital', 20, 6)->nullable();
            $table->unsignedInteger('trades_count')->default(0);
            $table->jsonb('settings')->nullable();
            $table->jsonb('summary')->nullable();
            $table->text('error_message')->nullable();
            $table->timestampsTz();
            $table->index(['portfolio_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('portfolio_simulation_runs');
    }
};
