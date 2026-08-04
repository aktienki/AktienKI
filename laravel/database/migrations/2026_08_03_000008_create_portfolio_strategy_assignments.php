<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('portfolio_strategy_assignments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('portfolio_id')->constrained()->cascadeOnDelete();
            $table->foreignId('saved_prediction_filter_id')->constrained('saved_prediction_filters')->cascadeOnDelete();
            $table->boolean('enabled')->default(true);
            $table->unsignedSmallInteger('priority')->default(100);
            $table->decimal('capital_weight', 6, 3)->default(1);
            $table->jsonb('settings')->nullable();
            $table->timestampsTz();
            $table->unique(['portfolio_id', 'saved_prediction_filter_id'], 'portfolio_strategy_assignment_uq');
            $table->index(['portfolio_id', 'enabled', 'priority'], 'portfolio_strategy_active_idx');
        });

        DB::statement(<<<'SQL'
            INSERT INTO portfolio_strategy_assignments
                (portfolio_id, saved_prediction_filter_id, enabled, priority, capital_weight, created_at, updated_at)
            SELECT portfolio_id, id, automatic_portfolio_enabled,
                   ROW_NUMBER() OVER (PARTITION BY portfolio_id ORDER BY id) * 10,
                   1, NOW(), NOW()
            FROM saved_prediction_filters
            WHERE portfolio_id IS NOT NULL
            ON CONFLICT (portfolio_id, saved_prediction_filter_id) DO NOTHING
        SQL);

        // Portfolio assignments now live exclusively in the pivot table. Keeping
        // the legacy column populated would violate the existing one-target
        // constraint when a strategy is also linked to a watchlist.
        DB::table('saved_prediction_filters')->whereNotNull('portfolio_id')->update(['portfolio_id' => null]);
    }

    public function down(): void
    {
        Schema::dropIfExists('portfolio_strategy_assignments');
    }
};
