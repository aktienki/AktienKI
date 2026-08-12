<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('trained_models', function (Blueprint $table): void {
            $table->string('deployment_version', 64)->nullable()->index();
            $table->string('deployment_status', 24)->default('development')->index();
            $table->string('deployment_path', 512)->nullable();
        });
        Schema::table('predictions', function (Blueprint $table): void {
            $table->string('horizon_fusion_version', 64)->nullable()->index();
            $table->decimal('horizon_fusion_consensus_return', 12, 8)->nullable();
            $table->decimal('horizon_fusion_dispersion', 12, 8)->nullable();
            $table->decimal('horizon_fusion_direction_consistency', 8, 6)->nullable();
            $table->decimal('horizon_fusion_stability_score', 8, 6)->nullable();
            $table->boolean('horizon_fusion_noise_passed')->nullable();
            $table->boolean('horizon_fusion_stability_passed')->nullable();
            $table->boolean('horizon_fusion_veto_used')->default(false);
            $table->string('horizon_fusion_raw_signal', 16)->nullable();
            $table->index(['horizon_fusion_version', 'horizon_fusion_veto_used'], 'predictions_horizon_fusion_idx');
        });
    }

    public function down(): void
    {
        Schema::table('predictions', function (Blueprint $table): void {
            $table->dropIndex('predictions_horizon_fusion_idx');
            $table->dropColumn(['horizon_fusion_version', 'horizon_fusion_consensus_return', 'horizon_fusion_dispersion', 'horizon_fusion_direction_consistency', 'horizon_fusion_stability_score', 'horizon_fusion_noise_passed', 'horizon_fusion_stability_passed', 'horizon_fusion_veto_used', 'horizon_fusion_raw_signal']);
        });
        Schema::table('trained_models', function (Blueprint $table): void {
            $table->dropColumn(['deployment_version', 'deployment_status', 'deployment_path']);
        });
    }
};
