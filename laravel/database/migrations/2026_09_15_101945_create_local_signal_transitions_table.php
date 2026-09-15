<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Own signal-change log for the local, server-scheduled prediction
 * pipeline (predictions:run-server -> predictions:apply-horizon-fusion),
 * independent of the external "serving" system's serving_signal_transitions
 * (which has no scheduled job on this server and has never written a row).
 * Populated by SignalTransitionRecorder, called from ApplyHorizonFusion
 * every time the daily horizon-fusion signal is (re)computed.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('local_signal_transitions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('instrument_id')->constrained()->cascadeOnDelete();
            // Null on the very first row we ever see for an instrument - that
            // is a baseline, not a real transition (there is no genuine "from"
            // state to report yet).
            $table->string('from_signal', 20)->nullable();
            $table->string('to_signal', 20);
            $table->timestampTz('changed_at')->index();
            $table->decimal('score_at_change', 10, 4)->nullable();
            $table->decimal('risk_at_change', 10, 4)->nullable();
            $table->timestampsTz();
            $table->index(['instrument_id', 'changed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('local_signal_transitions');
    }
};
