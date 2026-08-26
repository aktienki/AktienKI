<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('decision_process_runs', function (Blueprint $table): void {
            $table->unsignedBigInteger('duration_ms')->nullable()->after('finished_at');
        });

        Schema::table('decision_process_steps', function (Blueprint $table): void {
            $table->timestampTz('started_at')->nullable()->after('reason');
            $table->timestampTz('finished_at')->nullable()->after('started_at');
            $table->unsignedBigInteger('duration_ms')->nullable()->after('finished_at');
        });
    }

    public function down(): void
    {
        Schema::table('decision_process_steps', function (Blueprint $table): void {
            $table->dropColumn(['started_at', 'finished_at', 'duration_ms']);
        });
        Schema::table('decision_process_runs', function (Blueprint $table): void {
            $table->dropColumn('duration_ms');
        });
    }
};
