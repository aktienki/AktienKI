<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('entry_signal_alerts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('instrument_id')->constrained('instruments')->cascadeOnDelete();
            $table->foreignId('source_prediction_id')->nullable()->constrained('predictions')->nullOnDelete();
            $table->string('status', 20)->default('active')->index();
            $table->string('initial_signal', 16)->default('WAIT');
            $table->foreignId('triggered_prediction_id')->nullable()->constrained('predictions')->nullOnDelete();
            $table->timestampTz('triggered_at')->nullable();
            $table->timestampTz('notified_at')->nullable();
            $table->timestampsTz();
            $table->unique(['user_id', 'instrument_id', 'status'], 'entry_signal_alert_active_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('entry_signal_alerts');
    }
};
