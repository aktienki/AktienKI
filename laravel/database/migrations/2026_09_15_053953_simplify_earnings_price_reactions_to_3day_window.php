<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Replaces the original 5-horizon design (pre-5d, +1/5/10/20/40 trading
 * days) with exactly what was actually asked for: the price move 3
 * trading days before publication and 3 trading days after, plus the
 * forecast (eps_estimate) and the real reported figure (eps_actual)
 * denormalized onto the row so the drift report needs no extra join.
 *
 * Truncates first: rows computed under the old window are meaningless
 * once the fields they came from are gone, and leaving them with
 * is_complete=true (a stale value from the old schema) would make
 * earnings:compute-price-reactions skip recomputing them under the new
 * one, since it only revisits rows currently marked incomplete.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('earnings_price_reactions')->truncate();

        Schema::table('earnings_price_reactions', function (Blueprint $table): void {
            $table->dropColumn(['return_pre_5d', 'return_1d', 'return_5d', 'return_10d', 'return_20d', 'return_40d']);
            $table->decimal('eps_estimate', 18, 6)->nullable()->after('surprise_percent');
            $table->decimal('eps_actual', 18, 6)->nullable()->after('eps_estimate');
            $table->decimal('return_pre_3d', 14, 6)->nullable()->after('close_at_event');
            $table->decimal('return_post_3d', 14, 6)->nullable()->after('return_pre_3d');
        });
    }

    public function down(): void
    {
        Schema::table('earnings_price_reactions', function (Blueprint $table): void {
            $table->dropColumn(['eps_estimate', 'eps_actual', 'return_pre_3d', 'return_post_3d']);
            $table->decimal('return_pre_5d', 14, 6)->nullable();
            $table->decimal('return_1d', 14, 6)->nullable();
            $table->decimal('return_5d', 14, 6)->nullable();
            $table->decimal('return_10d', 14, 6)->nullable();
            $table->decimal('return_20d', 14, 6)->nullable();
            $table->decimal('return_40d', 14, 6)->nullable();
        });
    }
};
