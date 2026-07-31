<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('saved_prediction_filters', function (Blueprint $table): void {
            $table->foreignId('portfolio_id')->nullable()->after('filters')->constrained()->nullOnDelete();
            $table->foreignId('watchlist_id')->nullable()->after('portfolio_id')->constrained()->nullOnDelete();
            $table->boolean('email_notification_enabled')->default(false)->after('watchlist_id');
        });
        DB::statement('ALTER TABLE saved_prediction_filters ADD CONSTRAINT saved_prediction_filters_single_link_chk CHECK (portfolio_id IS NULL OR watchlist_id IS NULL)');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE saved_prediction_filters DROP CONSTRAINT IF EXISTS saved_prediction_filters_single_link_chk');
        Schema::table('saved_prediction_filters', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('watchlist_id');
            $table->dropConstrainedForeignId('portfolio_id');
            $table->dropColumn('email_notification_enabled');
        });
    }
};
