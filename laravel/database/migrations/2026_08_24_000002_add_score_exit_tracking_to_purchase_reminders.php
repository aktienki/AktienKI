<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('prediction_purchase_reminders', function (Blueprint $table): void {
            $table->string('score_rating', 4)->nullable();
            $table->string('score_rating_source', 24)->nullable();
            $table->unsignedSmallInteger('score_exit_streak')->default(0);
            $table->timestampTz('score_exit_evaluated_at')->nullable();
            $table->timestampTz('score_exit_triggered_at')->nullable();
            $table->jsonb('score_exit_details')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('prediction_purchase_reminders', fn (Blueprint $table) => $table->dropColumn([
            'score_rating', 'score_rating_source', 'score_exit_streak',
            'score_exit_evaluated_at', 'score_exit_triggered_at', 'score_exit_details',
        ]));
    }
};
