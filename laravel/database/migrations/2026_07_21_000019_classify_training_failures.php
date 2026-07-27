<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('training_runs', function (Blueprint $table): void {
            $table->string('error_category', 64)->nullable();
            $table->boolean('error_retryable')->default(false);
            $table->string('error_resolution_status', 40)->default('unresolved');
            $table->index(
                ['error_resolution_status', 'error_retryable'],
                'training_runs_failure_policy_idx'
            );
        });
        DB::statement(<<<'SQL'
            UPDATE training_runs SET
                error_category = CASE
                    WHEN error_message ILIKE '%Infinity%' AND error_message ILIKE '%json%' THEN 'json_non_finite'
                    WHEN error_message ILIKE '%tabpfn%' THEN 'removed_model'
                    WHEN error_message ILIKE '%Volatilitätsfilter entfernt zu viele%' THEN 'insufficient_eligible_training_data'
                    WHEN error_message ILIKE '%Zu wenige Marktdaten%' THEN 'insufficient_market_data'
                    WHEN error_message LIKE 'Automatische Recovery:%' THEN 'stale_run_recovery'
                    ELSE 'training_error'
                END,
                error_retryable = false,
                error_resolution_status = CASE
                    WHEN error_message ILIKE '%Infinity%' AND error_message ILIKE '%json%' THEN 'resolved_by_code_change'
                    WHEN error_message ILIKE '%tabpfn%' THEN 'resolved_by_code_change'
                    WHEN error_message LIKE 'Automatische Recovery:%' THEN 'recovered'
                    ELSE 'unresolved'
                END
            WHERE status='failed'
            SQL);
    }

    public function down(): void
    {
        Schema::table('training_runs', function (Blueprint $table): void {
            $table->dropIndex('training_runs_failure_policy_idx');
            $table->dropColumn([
                'error_category', 'error_retryable', 'error_resolution_status',
            ]);
        });
    }
};
