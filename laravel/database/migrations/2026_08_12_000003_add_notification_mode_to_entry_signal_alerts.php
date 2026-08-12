<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('entry_signal_alerts', function (Blueprint $table): void {
            $table->string('notification_mode', 24)->default('buy_only');
        });
    }

    public function down(): void
    {
        Schema::table('entry_signal_alerts', fn (Blueprint $table) => $table->dropColumn('notification_mode'));
    }
};
