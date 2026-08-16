<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('prediction_purchase_reminders', fn (Blueprint $table) =>
            $table->string('intent', 20)->default('purchased')->after('horizon_days'));
    }

    public function down(): void
    {
        Schema::table('prediction_purchase_reminders', fn (Blueprint $table) => $table->dropColumn('intent'));
    }
};
