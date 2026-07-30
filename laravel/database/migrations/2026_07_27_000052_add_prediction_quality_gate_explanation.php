<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('predictions', function (Blueprint $table) {
            $table->boolean('quality_gate_passed')->nullable();
            $table->decimal('quality_gate_score', 10, 6)->nullable();
            $table->decimal('quality_gate_threshold', 10, 6)->nullable();
            $table->string('quality_gate_primary_reason', 64)->nullable();
            $table->jsonb('quality_gate_blockers')->default('[]');
            $table->text('quality_gate_explanation')->nullable();
            $table->jsonb('quality_gate_details')->default('{}');
            $table->index(
                ['quality_gate_passed', 'quality_gate_primary_reason'],
                'predictions_quality_gate_reason_index'
            );
        });
    }

    public function down(): void
    {
        Schema::table('predictions', function (Blueprint $table) {
            $table->dropIndex('predictions_quality_gate_reason_index');
            $table->dropColumn([
                'quality_gate_passed', 'quality_gate_score',
                'quality_gate_threshold', 'quality_gate_primary_reason',
                'quality_gate_blockers', 'quality_gate_explanation',
                'quality_gate_details',
            ]);
        });
    }
};
