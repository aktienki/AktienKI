<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const TESTER_LIMIT = 50;

    public function up(): void
    {
        $testerIds = DB::table('users')
            ->orderBy('created_at')
            ->orderBy('id')
            ->limit(self::TESTER_LIMIT)
            ->pluck('id');

        if ($testerIds->isNotEmpty()) {
            DB::table('users')
                ->whereIn('id', $testerIds)
                ->update([
                    'account_status' => 'tester',
                    'updated_at' => now(),
                ]);
        }
    }

    public function down(): void
    {
        DB::table('users')
            ->where('account_status', 'tester')
            ->update([
                'account_status' => 'active',
                'updated_at' => now(),
            ]);
    }
};
