<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('price_bars', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->foreignId('instrument_id')->constrained()->cascadeOnDelete();
            $table->string('interval',10)->index();
            $table->timestampTz('bar_time')->index();

            $table->decimal('open',24,10);
            $table->decimal('high',24,10);
            $table->decimal('low',24,10);
            $table->decimal('close',24,10);
            $table->decimal('adjusted_close',24,10)->nullable();
            $table->decimal('volume',28,4)->nullable();

            $table->string('source',30)->nullable();
            $table->timestampsTz();

            $table->unique(['instrument_id','interval','bar_time']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('price_bars');
    }
};
