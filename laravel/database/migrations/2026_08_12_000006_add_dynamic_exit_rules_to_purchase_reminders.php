<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('prediction_purchase_reminders', function (Blueprint $table): void {
            $table->jsonb('exit_rules')->default('{}');
            $table->decimal('active_stop_price', 24, 10)->nullable();
            $table->string('exit_state', 24)->default('scheduled');
        });
    }

    public function down(): void
    {
        Schema::table('prediction_purchase_reminders', fn (Blueprint $table) =>
            $table->dropColumn(['exit_rules', 'active_stop_price', 'exit_state']));
    }
};
