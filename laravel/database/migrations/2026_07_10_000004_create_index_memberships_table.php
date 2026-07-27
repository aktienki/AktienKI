<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('index_memberships', function (Blueprint $table) {
            $table->id();
            $table->foreignId('market_index_id')->constrained('market_indices')->cascadeOnDelete();
            $table->foreignId('instrument_id')->constrained('instruments')->cascadeOnDelete();
            $table->decimal('weight',10,6)->nullable();
            $table->date('added_at')->nullable();
            $table->date('removed_at')->nullable();
            $table->timestampsTz();
            $table->unique(['market_index_id','instrument_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('index_memberships');
    }
};
