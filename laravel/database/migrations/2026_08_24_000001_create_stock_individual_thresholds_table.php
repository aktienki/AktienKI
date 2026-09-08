<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_individual_thresholds', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('instrument_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('horizon_days');
            $table->string('algorithm_version', 100);
            $table->string('status', 32);
            $table->decimal('minimum_phase_probability', 8, 6)->nullable();
            $table->decimal('minimum_ai_score', 8, 4)->nullable();
            $table->unsignedInteger('event_count')->default(0);
            $table->unsignedInteger('calibration_event_count')->default(0);
            $table->unsignedInteger('validation_event_count')->default(0);
            $table->boolean('validation_passed')->default(false);
            $table->unsignedSmallInteger('validation_year')->nullable();
            $table->jsonb('phase_result')->nullable();
            $table->jsonb('score_result')->nullable();
            $table->jsonb('phase_matrix')->nullable();
            $table->jsonb('score_matrix')->nullable();
            $table->string('source_report_checksum', 64);
            $table->timestampTz('calculated_at')->nullable();
            $table->timestampTz('activated_at')->nullable();
            $table->timestampsTz();

            $table->unique(
                ['instrument_id', 'horizon_days', 'algorithm_version'],
                'stock_individual_thresholds_version_unique'
            );
            $table->index(['status', 'validation_passed']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_individual_thresholds');
    }
};
