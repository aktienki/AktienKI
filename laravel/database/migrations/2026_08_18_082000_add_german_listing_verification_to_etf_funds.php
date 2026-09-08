<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('etf_funds', function (Blueprint $table): void {
            if (! Schema::hasColumn('etf_funds', 'mic_code')) $table->string('mic_code', 12)->nullable()->index();
            if (! Schema::hasColumn('etf_funds', 'german_listing_symbol')) $table->string('german_listing_symbol', 60)->nullable();
            if (! Schema::hasColumn('etf_funds', 'german_tradeability_verified_at')) $table->timestampTz('german_tradeability_verified_at')->nullable();
            if (! Schema::hasColumn('etf_funds', 'german_tradeability_source')) $table->string('german_tradeability_source')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('etf_funds', function (Blueprint $table): void {
            $columns = ['mic_code', 'german_listing_symbol', 'german_tradeability_verified_at', 'german_tradeability_source'];
            foreach ($columns as $column) if (Schema::hasColumn('etf_funds', $column)) $table->dropColumn($column);
        });
    }
};
