<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('model_quality_tiers')) {
            return;
        }

        DB::table('model_quality_tiers')
            ->where('code', 'top')
            ->update([
                'name' => 'Quality Gate',
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        if (! Schema::hasTable('model_quality_tiers')) {
            return;
        }

        DB::table('model_quality_tiers')
            ->where('code', 'top')
            ->update([
                'name' => 'Top',
                'updated_at' => now(),
            ]);
    }
};
