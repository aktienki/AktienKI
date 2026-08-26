<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (! Schema::hasTable('walk_forward_horizon_forecasts')) {
            Schema::create('walk_forward_horizon_forecasts', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('run_id')->constrained('walk_forward_backtest_runs')->cascadeOnDelete();
                $table->foreignId('instrument_id')->constrained()->cascadeOnDelete();
                $table->date('model_cutoff_date');
                $table->date('signal_date');
                $table->unsignedSmallInteger('horizon_days');
                $table->decimal('predicted_return', 16, 10);
                $table->string('algorithm', 100);
                $table->timestampsTz();
                $table->unique(['run_id', 'instrument_id', 'signal_date', 'horizon_days'], 'wf_horizon_forecast_unique');
                $table->index(['instrument_id', 'signal_date', 'horizon_days', 'run_id'], 'wf_horizon_forecast_lookup');
            });
        }

        if (! Schema::hasTable('historical_noise_scores')) {
            Schema::create('historical_noise_scores', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('instrument_id')->constrained()->cascadeOnDelete();
                $table->date('signal_date');
                foreach ([5, 10, 15, 20] as $days) $table->decimal("return_{$days}d", 16, 10);
                $table->decimal('positive_area', 18, 8);
                $table->decimal('negative_area', 18, 8);
                $table->decimal('net_area', 18, 8);
                $table->decimal('score', 7, 4);
                $table->boolean('passed');
                $table->string('calculation_version', 64)->default('noise-score-tanh-v1');
                $table->timestampsTz();
                $table->unique(['instrument_id', 'signal_date', 'calculation_version'], 'historical_noise_score_unique');
                $table->index(['signal_date', 'score', 'instrument_id'], 'historical_noise_score_filter');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('historical_noise_scores');
        Schema::dropIfExists('walk_forward_horizon_forecasts');
    }
};
