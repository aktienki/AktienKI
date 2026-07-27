<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('predictions', function (Blueprint $table): void {
            $table->unsignedInteger('validation_attempt_count')->default(0);
            $table->timestampTz('validation_attempted_at')->nullable();
            $table->string('validation_pending_reason', 50)->nullable();
            $table->index(
                ['status', 'validation_attempted_at'],
                'predictions_validation_retry_idx'
            );
        });
    }

    public function down(): void
    {
        Schema::table('predictions', function (Blueprint $table): void {
            $table->dropIndex('predictions_validation_retry_idx');
            $table->dropColumn([
                'validation_attempt_count',
                'validation_attempted_at',
                'validation_pending_reason',
            ]);
        });
    }
};
