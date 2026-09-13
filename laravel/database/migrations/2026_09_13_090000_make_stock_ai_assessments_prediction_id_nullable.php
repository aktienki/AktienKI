<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * stock_ai_assessments.prediction_id pointed at the legacy predictions
     * table, which serving-only instruments never have a row in. The
     * generator now writes assessments for serving-mode stocks too, so the
     * column must accept null instead of requiring a legacy prediction.
     */
    public function up(): void
    {
        DB::statement('ALTER TABLE stock_ai_assessments ALTER COLUMN prediction_id DROP NOT NULL');
    }

    public function down(): void
    {
        DB::statement('DELETE FROM stock_ai_assessments WHERE prediction_id IS NULL');
        DB::statement('ALTER TABLE stock_ai_assessments ALTER COLUMN prediction_id SET NOT NULL');
    }
};
