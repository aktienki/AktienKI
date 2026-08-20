<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('instruments', function (Blueprint $table): void {
            $table->string('risk_status', 24)->nullable()->index();
            $table->decimal('risk_profit_factor', 10, 4)->nullable();
            $table->decimal('risk_confidence', 7, 3)->nullable();
            $table->decimal('risk_max_drawdown', 7, 3)->nullable();
            $table->timestampTz('risk_status_updated_at')->nullable();
        });

        DB::statement("ALTER TABLE instruments ADD CONSTRAINT instruments_risk_status_chk CHECK (risk_status IS NULL OR risk_status IN ('defensive','balanced','opportunity','risk','sleep'))");
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE instruments DROP CONSTRAINT IF EXISTS instruments_risk_status_chk');
        Schema::table('instruments', function (Blueprint $table): void {
            $table->dropIndex(['risk_status']);
            $table->dropColumn(['risk_status', 'risk_profit_factor', 'risk_confidence', 'risk_max_drawdown', 'risk_status_updated_at']);
        });
    }
};
