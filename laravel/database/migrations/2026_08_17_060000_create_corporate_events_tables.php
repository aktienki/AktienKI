<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('corporate_event_imports', function (Blueprint $table): void {
            $table->id();
            $table->string('provider', 32)->default('twelve_data');
            $table->string('event_type', 32);
            $table->date('requested_from');
            $table->date('requested_until');
            $table->string('status', 20)->default('running')->index();
            $table->unsignedInteger('records_received')->default(0);
            $table->unsignedInteger('records_matched')->default(0);
            $table->unsignedInteger('records_ignored')->default(0);
            $table->unsignedSmallInteger('api_credits_used')->nullable();
            $table->jsonb('raw_payload')->nullable();
            $table->text('error_message')->nullable();
            $table->timestampTz('started_at');
            $table->timestampTz('finished_at')->nullable();
            $table->timestampsTz();
            $table->index(['provider', 'event_type', 'started_at']);
        });

        Schema::create('corporate_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('instrument_id')->constrained()->cascadeOnDelete();
            $table->foreignId('import_id')->nullable()->constrained('corporate_event_imports')->nullOnDelete();
            $table->string('event_type', 32);
            $table->date('event_date');
            $table->string('event_time', 40)->nullable();
            $table->string('title');
            $table->decimal('eps_estimate', 18, 6)->nullable();
            $table->decimal('eps_actual', 18, 6)->nullable();
            $table->decimal('surprise_percent', 14, 6)->nullable();
            $table->string('currency', 8)->nullable();
            $table->string('provider', 32)->default('twelve_data');
            $table->string('provider_symbol', 80)->nullable();
            $table->string('provider_event_key', 160);
            $table->string('source_url')->nullable();
            $table->timestampTz('retrieved_at')->index();
            $table->jsonb('data');
            $table->timestampsTz();
            $table->unique(['provider', 'provider_event_key']);
            $table->index(['instrument_id', 'event_date']);
            $table->index(['event_type', 'event_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('corporate_events');
        Schema::dropIfExists('corporate_event_imports');
    }
};
