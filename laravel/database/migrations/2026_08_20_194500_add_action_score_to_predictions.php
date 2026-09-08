<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('predictions', function (Blueprint $table): void {
            $table->decimal('model_prediction_score', 8, 4)->nullable()->after('prediction_score');
            $table->string('action_score_version', 32)->nullable()->index();
            $table->jsonb('action_score_components')->nullable();
            $table->timestampTz('action_score_calculated_at')->nullable()->index();
        });
    }

    public function down(): void
    {
        Schema::table('predictions', function (Blueprint $table): void {
            $table->dropIndex(['action_score_version']);
            $table->dropIndex(['action_score_calculated_at']);
            $table->dropColumn(['model_prediction_score', 'action_score_version', 'action_score_components', 'action_score_calculated_at']);
        });
    }
};
