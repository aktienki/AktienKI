<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_quality_gate_profiles', function (Blueprint $table): void {
            $table->foreignId('tariff_plan_id')->nullable()->after('user_id')->constrained('tariff_plans')->nullOnDelete();
            $table->timestampTz('access_bound_at')->nullable()->after('rules');
        });
        DB::statement('UPDATE user_quality_gate_profiles profile SET tariff_plan_id = users.tariff_plan_id, access_bound_at = NOW() FROM users WHERE users.id = profile.user_id');
    }

    public function down(): void
    {
        Schema::table('user_quality_gate_profiles', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('tariff_plan_id');
            $table->dropColumn('access_bound_at');
        });
    }
};
