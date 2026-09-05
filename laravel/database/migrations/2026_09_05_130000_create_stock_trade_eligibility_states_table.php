<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_trade_eligibility_states', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('instrument_id')->unique();
            $table->string('model_signal', 16)->index();
            $table->string('status', 32)->index();
            $table->unsignedSmallInteger('horizon_days')->nullable();
            $table->decimal('net_return_percent', 12, 6)->nullable();
            $table->string('reason', 64)->nullable();
            $table->timestampTz('transitioned_at');
            $table->timestampsTz();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_trade_eligibility_states');
    }
};
