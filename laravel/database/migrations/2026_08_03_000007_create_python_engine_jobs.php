<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('python_engine_jobs', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('backtest_run_id')->nullable()->constrained('backtest_runs')->cascadeOnDelete();
            $table->string('type', 80)->index();
            $table->string('calculation_version', 40)->default('portfolio-v1');
            $table->string('status', 24)->default('queued')->index();
            $table->unsignedSmallInteger('progress')->default(0);
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->string('locked_by')->nullable();
            $table->timestampTz('locked_at')->nullable();
            $table->timestampTz('heartbeat_at')->nullable();
            $table->timestampTz('started_at')->nullable();
            $table->timestampTz('finished_at')->nullable();
            $table->jsonb('payload');
            $table->jsonb('result')->nullable();
            $table->text('error_message')->nullable();
            $table->timestampsTz();
            $table->index(['status', 'type', 'created_at'], 'python_engine_jobs_claim_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('python_engine_jobs');
    }
};
