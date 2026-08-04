<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE predictions ALTER COLUMN general_score_source TYPE VARCHAR(40)');
    }

    public function down(): void
    {
        DB::statement(<<<'SQL'
            UPDATE predictions
            SET general_score_source = LEFT(general_score_source, 24)
            WHERE LENGTH(general_score_source) > 24
        SQL);
        DB::statement('ALTER TABLE predictions ALTER COLUMN general_score_source TYPE VARCHAR(24)');
    }
};
