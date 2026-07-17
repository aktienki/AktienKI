<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('market_snapshots')) {
            Schema::create('market_snapshots', function (Blueprint $table): void {
                $this->addCanonicalColumns($table);
            });

            return;
        }

        Schema::table('market_snapshots', function (Blueprint $table): void {
            if (! Schema::hasColumn('market_snapshots', 'snapshot_time')) {
                $table->timestampTz('snapshot_time')->nullable();
            }
            if (! Schema::hasColumn('market_snapshots', 'market_score')) {
                $table->decimal('market_score', 5, 2)->nullable();
            }
            if (! Schema::hasColumn('market_snapshots', 'risk_mode')) {
                $table->string('risk_mode', 20)->default('NEUTRAL');
            }
            if (! Schema::hasColumn('market_snapshots', 'market_trend')) {
                $table->string('market_trend', 20)->default('NEUTRAL');
            }
            if (! Schema::hasColumn('market_snapshots', 'volatility')) {
                $table->decimal('volatility', 10, 4)->nullable();
            }
            if (! Schema::hasColumn('market_snapshots', 'breadth_score')) {
                $table->decimal('breadth_score', 5, 2)->nullable();
            }
            if (! Schema::hasColumn('market_snapshots', 'buy_signals')) {
                $table->unsignedInteger('buy_signals')->default(0);
            }
            if (! Schema::hasColumn('market_snapshots', 'sell_signals')) {
                $table->unsignedInteger('sell_signals')->default(0);
            }
            if (! Schema::hasColumn('market_snapshots', 'hold_signals')) {
                $table->unsignedInteger('hold_signals')->default(0);
            }
            if (! Schema::hasColumn('market_snapshots', 'winning_sectors')) {
                $table->jsonb('winning_sectors')->nullable();
            }
            if (! Schema::hasColumn('market_snapshots', 'losing_sectors')) {
                $table->jsonb('losing_sectors')->nullable();
            }
            if (! Schema::hasColumn('market_snapshots', 'metadata')) {
                $table->jsonb('metadata')->nullable();
            }
        });

        if (Schema::hasColumn('market_snapshots', 'snapshot_time')) {
            DB::table('market_snapshots')
                ->whereNull('snapshot_time')
                ->update(['snapshot_time' => DB::raw('snapshot_time')]);
        }

        Schema::table('market_snapshots', function (Blueprint $table): void {
            $table->index('snapshot_time', 'market_snapshots_snapshot_time_index');
            $table->index('risk_mode', 'market_snapshots_risk_mode_index');
            $table->index('market_trend', 'market_snapshots_market_trend_index');
        });
    }

    public function down(): void
    {
        // Compatibility migration: intentionally non-destructive.
    }

    private function addCanonicalColumns(Blueprint $table): void
    {
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
    }
};
