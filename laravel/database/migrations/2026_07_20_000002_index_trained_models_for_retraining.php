<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('trained_models', function (Blueprint $table): void {
            // `trained_at` is the authoritative date of the last training.
            // Together with the horizon it keeps Pulse and Horizon schedules
            // independent for the same instrument.
            $table->index(
                [
                    'instrument_id',
                    'timeframe',
                    'prediction_horizon_minutes',
                    'trained_at',
                ],
                'trained_models_retraining_schedule_idx'
            );
        });
    }

    public function down(): void
    {
        Schema::table('trained_models', function (Blueprint $table): void {
            $table->dropIndex('trained_models_retraining_schedule_idx');
        });
    }
};
