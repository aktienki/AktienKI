<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('market_snapshots', function (Blueprint $table): void {
            $table->id();
            $table->timestampTz('snapshot_time');
            $table->string('interval', 10)->default('1d');
            $table->jsonb('market_data')->default('{}');
            $table->jsonb('feature_data')->default('{}');
            $table->string('schema_version', 20)->default('1.0.0');
            $table->timestampsTz();

            $table->unique(
                ['snapshot_time', 'interval'],
                'market_snapshots_snapshot_time_interval_unique'
            );

            $table->index(['interval', 'snapshot_time']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('market_snapshots');
    }
};
