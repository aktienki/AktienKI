<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('calendar_event_reminders', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            // 'earnings' -> corporate_events.id, 'sell' -> portfolio_positions.id.
            // No FK constraint since reference_id points to two different tables
            // depending on event_type.
            $table->string('event_type');
            $table->unsignedBigInteger('reference_id');
            $table->foreignId('instrument_id')->constrained()->cascadeOnDelete();
            $table->date('event_date');
            // Earnings reminders fire the day before event_date; sell
            // reminders fire on event_date itself - computed once at
            // creation so the sender job is a plain date lookup.
            $table->date('send_at');
            $table->boolean('enabled')->default(true);
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'event_type', 'reference_id']);
            $table->index(['enabled', 'send_at', 'sent_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('calendar_event_reminders');
    }
};
