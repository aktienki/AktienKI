<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('instrument_exit_profiles', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('instrument_id')->constrained('instruments')->cascadeOnDelete();
            $table->foreignId('pulse_trained_model_id')->nullable()->constrained('trained_models')->nullOnDelete();
            $table->foreignId('intermediate_trained_model_id')->nullable()->constrained('trained_models')->nullOnDelete();
            $table->foreignId('horizon_trained_model_id')->nullable()->constrained('trained_models')->nullOnDelete();
            $table->string('strategy_key', 64)->default('combined_5_10_20');
            $table->string('model_signature', 128);
            $table->unsignedSmallInteger('holding_days')->default(20);
            $table->unsignedSmallInteger('window_start_days');
            $table->unsignedSmallInteger('window_end_days');
            $table->string('selection_metric', 32)->default('total_return');
            $table->string('validation_status', 24)->default('candidate')->index();
            $table->boolean('is_active')->default(false)->index();
            $table->date('backtest_from');
            $table->date('backtest_to');
            $table->unsignedInteger('trade_count')->default(0);
            $table->decimal('total_return', 16, 8)->nullable();
            $table->decimal('smoothed_total_return', 16, 8)->nullable();
            $table->decimal('baseline_20d_total_return', 16, 8)->nullable();
            $table->decimal('improvement_over_20d', 16, 8)->nullable();
            $table->decimal('window_stability_score', 12, 8)->nullable();
            $table->decimal('calendar_cagr', 12, 8)->nullable();
            $table->decimal('profit_factor', 12, 8)->nullable();
            $table->decimal('win_rate', 12, 8)->nullable();
            $table->decimal('max_drawdown', 12, 8)->nullable();
            $table->decimal('market_exposure', 12, 8)->nullable();
            $table->jsonb('exit_sweep')->nullable();
            $table->jsonb('metadata')->nullable();
            $table->timestampTz('selected_at')->nullable();
            $table->timestampTz('validated_at')->nullable();
            $table->timestampsTz();

            $table->unique(
                ['instrument_id', 'strategy_key', 'model_signature'],
                'instrument_exit_profiles_signature_unique'
            );
            $table->index(
                ['instrument_id', 'strategy_key', 'is_active'],
                'instrument_exit_profiles_active_lookup_idx'
            );
        });

        DB::statement(<<<'SQL'
            ALTER TABLE instrument_exit_profiles
            ADD CONSTRAINT instrument_exit_profiles_holding_days_chk
            CHECK (holding_days BETWEEN 5 AND 20),
            ADD CONSTRAINT instrument_exit_profiles_window_chk
            CHECK (
                window_start_days = holding_days - 1
                AND window_end_days = holding_days + 1
                AND window_start_days >= 5
                AND window_end_days <= 20
            ),
            ADD CONSTRAINT instrument_exit_profiles_validation_status_chk
            CHECK (validation_status IN ('candidate', 'validated', 'rejected', 'stale'))
        SQL);
        DB::statement(<<<'SQL'
            CREATE UNIQUE INDEX instrument_exit_profiles_single_active_idx
            ON instrument_exit_profiles (instrument_id, strategy_key)
            WHERE is_active = TRUE
        SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('instrument_exit_profiles');
    }
};
