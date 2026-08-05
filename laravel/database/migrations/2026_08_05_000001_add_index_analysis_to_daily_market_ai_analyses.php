<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('daily_market_ai_analyses', function (Blueprint $table): void {
            $table->jsonb('index_analysis')->default(DB::raw("'[]'::jsonb"))->after('sector_analysis');
        });
    }

    public function down(): void
    {
        Schema::table('daily_market_ai_analyses', function (Blueprint $table): void {
            $table->dropColumn('index_analysis');
        });
    }
};
