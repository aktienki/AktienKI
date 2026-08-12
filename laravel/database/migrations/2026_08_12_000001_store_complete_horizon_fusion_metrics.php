<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('predictions', function (Blueprint $table): void {
            $table->decimal('horizon_fusion_positive_area', 16, 8)->nullable();
            $table->decimal('horizon_fusion_negative_area', 16, 8)->nullable();
            $table->decimal('horizon_fusion_net_area', 16, 8)->nullable();
            $table->decimal('horizon_fusion_median_return', 12, 8)->nullable();
            $table->decimal('horizon_fusion_mad_return', 12, 8)->nullable();
            $table->decimal('horizon_fusion_slope', 12, 8)->nullable();
            $table->decimal('horizon_fusion_slope_alignment', 8, 6)->nullable();
            $table->decimal('horizon_fusion_magnitude_support', 8, 6)->nullable();
            $table->decimal('horizon_fusion_curvature_5_10_15', 12, 8)->nullable();
            $table->decimal('horizon_fusion_curvature_10_15_20', 12, 8)->nullable();
            $table->decimal('horizon_fusion_max_abs_curvature', 12, 8)->nullable();
            $table->unsignedSmallInteger('horizon_fusion_direction_reversals')->nullable();
            $table->unsignedSmallInteger('horizon_fusion_zero_crossings')->nullable();
            $table->jsonb('horizon_fusion_details')->default('{}');
        });
    }

    public function down(): void
    {
        Schema::table('predictions', function (Blueprint $table): void {
            $table->dropColumn([
                'horizon_fusion_positive_area', 'horizon_fusion_negative_area', 'horizon_fusion_net_area',
                'horizon_fusion_median_return', 'horizon_fusion_mad_return', 'horizon_fusion_slope',
                'horizon_fusion_slope_alignment', 'horizon_fusion_magnitude_support',
                'horizon_fusion_curvature_5_10_15', 'horizon_fusion_curvature_10_15_20',
                'horizon_fusion_max_abs_curvature', 'horizon_fusion_direction_reversals',
                'horizon_fusion_zero_crossings', 'horizon_fusion_details',
            ]);
        });
    }
};
