<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leveraged_product_risk_matrices', function (Blueprint $table): void {
            $table->unsignedInteger('gain_count')->default(0)->after('barrier_breach_count');
            $table->unsignedInteger('target_hit_count')->default(0)->after('gain_count');
            $table->decimal('gain_probability', 9, 6)->default(0)->after('barrier_breach_probability');
            $table->decimal('target_hit_probability', 9, 6)->default(0)->after('gain_probability');
            $table->decimal('expected_upside_10', 14, 8)->default(0)->after('expected_shortfall_10');
            $table->decimal('average_favorable_excursion', 14, 8)->default(0)->after('average_adverse_excursion');
        });
    }

    public function down(): void
    {
        Schema::table('leveraged_product_risk_matrices', function (Blueprint $table): void {
            $table->dropColumn([
                'gain_count', 'target_hit_count', 'gain_probability',
                'target_hit_probability', 'expected_upside_10', 'average_favorable_excursion',
            ]);
        });
    }
};
