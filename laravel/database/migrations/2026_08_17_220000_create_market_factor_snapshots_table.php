<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('market_factor_snapshots', function (Blueprint $table): void {
            $table->id();
            $table->date('trading_date')->index();
            $table->string('scope_type', 16); // market, sector or index
            $table->string('scope_key', 120);
            $table->decimal('trend_score', 6, 2)->nullable();
            $table->decimal('timing_score', 6, 2)->nullable();
            $table->decimal('relative_rank', 6, 2)->nullable();
            $table->unsignedInteger('member_count')->default(0);
            $table->json('meta')->nullable();
            $table->timestampsTz();

            $table->unique(['trading_date', 'scope_type', 'scope_key'], 'market_factor_snapshot_unique');
            $table->index(['scope_type', 'scope_key', 'trading_date'], 'market_factor_snapshot_lookup');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('market_factor_snapshots');
    }
};
