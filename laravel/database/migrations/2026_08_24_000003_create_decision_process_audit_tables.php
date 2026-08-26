<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('decision_process_runs', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('instrument_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('prediction_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('training_run_id')->nullable()->constrained()->nullOnDelete();
            $table->string('process_type', 32)->index(); // training, prediction, ranking, entry, exit
            $table->string('pipeline_version', 64)->index();
            $table->string('status', 24)->index();
            $table->string('final_decision', 32)->nullable()->index();
            $table->string('environment', 24)->default('production');
            $table->jsonb('context')->default(DB::raw("'{}'::jsonb"));
            $table->jsonb('final_values')->nullable();
            $table->string('input_checksum', 64)->nullable()->index();
            $table->timestampTz('started_at');
            $table->timestampTz('finished_at')->nullable();
            $table->timestampsTz();
            $table->index(['instrument_id', 'process_type', 'started_at'], 'decision_runs_instrument_process_idx');
            $table->index(['prediction_id', 'process_type'], 'decision_runs_prediction_process_idx');
        });

        Schema::create('decision_process_steps', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('decision_process_run_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('sequence');
            $table->string('stage_key', 64)->index();
            $table->string('stage_name', 120);
            $table->string('status', 24)->index(); // PASSED, BLOCKED, NOT_AVAILABLE, NOT_APPLICABLE
            $table->string('decision', 48)->nullable()->index();
            $table->string('rule_version', 64)->nullable();
            $table->jsonb('raw_values')->default(DB::raw("'{}'::jsonb"));
            $table->jsonb('normalized_values')->default(DB::raw("'{}'::jsonb"));
            $table->jsonb('thresholds')->default(DB::raw("'{}'::jsonb"));
            $table->jsonb('sources')->default(DB::raw("'{}'::jsonb"));
            $table->jsonb('evidence')->default(DB::raw("'{}'::jsonb"));
            $table->text('reason')->nullable();
            $table->timestampTz('evaluated_at');
            $table->timestampsTz();
            $table->unique(['decision_process_run_id', 'sequence'], 'decision_steps_run_sequence_unique');
            $table->index(['decision_process_run_id', 'stage_key'], 'decision_steps_run_stage_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('decision_process_steps');
        Schema::dropIfExists('decision_process_runs');
    }
};
