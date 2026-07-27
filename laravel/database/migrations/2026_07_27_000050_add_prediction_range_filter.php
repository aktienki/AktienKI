<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void {
        DB::unprepared(<<<'SQL'
ALTER TABLE predictions
 ADD COLUMN range_probability NUMERIC(8,7),
 ADD COLUMN below_range_probability NUMERIC(8,7),
 ADD COLUMN above_range_probability NUMERIC(8,7),
 ADD COLUMN range_lower_return NUMERIC(12,10),
 ADD COLUMN range_upper_return NUMERIC(12,10),
 ADD COLUMN range_veto_used BOOLEAN NOT NULL DEFAULT FALSE,
 ADD CONSTRAINT predictions_range_probability_chk CHECK (
   range_probability IS NULL OR range_probability BETWEEN 0 AND 1
 ),
 ADD CONSTRAINT predictions_range_bounds_chk CHECK (
   range_lower_return IS NULL OR range_upper_return IS NULL
   OR range_lower_return < range_upper_return
 );
SQL);
    }
    public function down(): void {
        DB::unprepared(<<<'SQL'
ALTER TABLE predictions
 DROP CONSTRAINT predictions_range_probability_chk,
 DROP CONSTRAINT predictions_range_bounds_chk,
 DROP COLUMN range_probability,
 DROP COLUMN below_range_probability,
 DROP COLUMN above_range_probability,
 DROP COLUMN range_lower_return,
 DROP COLUMN range_upper_return,
 DROP COLUMN range_veto_used;
SQL);
    }
};
