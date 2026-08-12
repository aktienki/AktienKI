<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('market_sectors', function (Blueprint $table) {
            $table->string('reference_etf_symbol', 30)->nullable()->index();
            $table->foreignId('reference_instrument_id')->nullable()->constrained('instruments')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('market_sectors', function (Blueprint $table) {
            $table->dropConstrainedForeignId('reference_instrument_id');
            $table->dropColumn('reference_etf_symbol');
        });
    }
};
