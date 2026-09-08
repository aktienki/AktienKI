<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('prediction_purchase_reminders', function (Blueprint $table): void {
            $table->dropUnique(['user_id', 'prediction_id', 'horizon_days']);
            $table->foreignId('prediction_id')->nullable()->change();
            $table->unsignedBigInteger('serving_prediction_id')->nullable()->after('prediction_id');
            $table->string('serving_batch_id')->nullable()->after('serving_prediction_id');
        });
    }

    public function down(): void
    {
        Schema::table('prediction_purchase_reminders', function (Blueprint $table): void {
            $table->dropColumn(['serving_prediction_id', 'serving_batch_id']);
            $table->foreignId('prediction_id')->nullable(false)->change();
            $table->unique(['user_id', 'prediction_id', 'horizon_days']);
        });
    }
};
