<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('scheduler_jobs', function (Blueprint $table): void {
            $table->string('job_key', 100)->primary();
            $table->jsonb('command');
            $table->unsignedInteger('interval_seconds');
            $table->unsignedInteger('timeout_seconds');
            $table->string('status', 20)->default('pending');
            $table->unsignedBigInteger('run_count')->default(0);
            $table->timestampTz('last_started_at')->nullable();
            $table->timestampTz('last_finished_at')->nullable();
            $table->timestampTz('next_due_at')->nullable()->index();
            $table->integer('exit_code')->nullable();
            $table->text('output_tail')->nullable();
            $table->text('error_message')->nullable();
            $table->timestampsTz();
        });
        DB::statement(
            "ALTER TABLE scheduler_jobs ADD CONSTRAINT scheduler_jobs_status_chk "
            . "CHECK (status IN ('pending','running','completed','failed','timed_out'))"
        );
        DB::statement(
            'ALTER TABLE scheduler_jobs ADD CONSTRAINT scheduler_jobs_interval_chk '
            . 'CHECK (interval_seconds >= 60 AND timeout_seconds >= 1)'
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('scheduler_jobs');
    }
};
