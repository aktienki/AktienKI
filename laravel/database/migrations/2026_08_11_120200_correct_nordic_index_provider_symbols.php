<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['OMXC25.CO' => '^OMXC25', 'OMXH25.HE' => '^OMXH25'] as $old => $new) {
            DB::table('market_indices')->where('symbol', $old)->update(['symbol' => $new, 'updated_at' => now()]);
            DB::table('instruments')->where('type', 'index')->where('symbol', $old)->update([
                'symbol' => $new,
                'provider_symbol' => $new,
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        foreach (['^OMXC25' => 'OMXC25.CO', '^OMXH25' => 'OMXH25.HE'] as $old => $new) {
            DB::table('market_indices')->where('symbol', $old)->update(['symbol' => $new, 'updated_at' => now()]);
            DB::table('instruments')->where('type', 'index')->where('symbol', $old)->update(['symbol' => $new, 'provider_symbol' => $new, 'updated_at' => now()]);
        }
    }
};
