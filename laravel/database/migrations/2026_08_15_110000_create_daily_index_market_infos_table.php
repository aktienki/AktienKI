<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('daily_index_market_infos', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->foreignId('market_index_id')->constrained('market_indices')->cascadeOnDelete();
            $table->date('analysis_date');
            $table->string('model', 100);
            $table->text('market_info_de');
            $table->text('market_info_en')->nullable();
            $table->jsonb('input_snapshot');
            $table->jsonb('raw_response')->nullable();
            $table->timestampsTz();
            $table->unique(['market_index_id', 'analysis_date']);
            $table->index(['analysis_date', 'market_index_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('daily_index_market_infos');
    }
};
