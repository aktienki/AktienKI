<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('signal_email_deliveries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('saved_prediction_filter_id')->constrained('saved_prediction_filters')->cascadeOnDelete();
            $table->foreignId('prediction_id')->constrained('predictions')->cascadeOnDelete();
            $table->foreignId('instrument_id')->constrained('instruments')->cascadeOnDelete();
            $table->string('previous_signal', 32);
            $table->string('new_signal', 32);
            $table->string('status', 20)->default('queued')->index();
            $table->timestampTz('queued_at')->nullable();
            $table->timestampTz('sent_at')->nullable();
            $table->timestampTz('failed_at')->nullable();
            $table->text('failure_message')->nullable();
            $table->timestampsTz();

            $table->unique(
                ['saved_prediction_filter_id', 'prediction_id'],
                'signal_email_deliveries_filter_prediction_unique'
            );
            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('signal_email_deliveries');
    }
};
