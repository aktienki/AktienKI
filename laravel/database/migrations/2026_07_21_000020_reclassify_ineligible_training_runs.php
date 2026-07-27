<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement(<<<'SQL'
            UPDATE training_runs SET
                status='rejected',
                error_resolution_status='rejected_by_policy',
                error_retryable=false,
                updated_at=NOW()
            WHERE status='failed'
              AND error_category IN (
                  'insufficient_eligible_training_data',
                  'insufficient_crisis_adjusted_training_data',
                  'insufficient_market_data'
              )
            SQL);
    }

    public function down(): void
    {
        DB::statement(<<<'SQL'
            UPDATE training_runs SET
                status='failed',
                error_resolution_status='unresolved',
                updated_at=NOW()
            WHERE status='rejected'
              AND error_resolution_status='rejected_by_policy'
              AND error_category IN (
                  'insufficient_eligible_training_data',
                  'insufficient_crisis_adjusted_training_data',
                  'insufficient_market_data'
              )
            SQL);
    }
};
