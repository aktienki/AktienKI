<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('smart_selection_label_instruments', function (Blueprint $table): void {
            $table->foreignId('smart_selection_label_id')->constrained('smart_selection_labels')->cascadeOnDelete();
            $table->foreignId('instrument_id')->constrained()->cascadeOnDelete();
            $table->timestampsTz();

            $table->primary(['smart_selection_label_id', 'instrument_id'], 'smart_selection_label_instruments_pk');
            $table->index('instrument_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('smart_selection_label_instruments');
    }
};
