<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('market_snapshots')) {
            return;
        }

        Schema::create('market_snapshots', function (Blueprint $table): void {
            $table->id();
            $table->timestampTz('snapshot_time')->index();
            $table->decimal('market_score', 5, 2)->nullable();
            $table->string('risk_mode', 20)->default('NEUTRAL')->index();
            $table->string('market_trend', 20)->default('NEUTRAL')->index();
            $table->decimal('volatility', 10, 4)->nullable();
            $table->decimal('breadth_score', 5, 2)->nullable();
            $table->unsignedInteger('buy_signals')->default(0);
            $table->unsignedInteger('sell_signals')->default(0);
            $table->unsignedInteger('hold_signals')->default(0);
            $table->jsonb('winning_sectors')->nullable();
            $table->jsonb('losing_sectors')->nullable();
            $table->jsonb('metadata')->nullable();
            $table->timestampsTz();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('market_snapshots');
    }
};
