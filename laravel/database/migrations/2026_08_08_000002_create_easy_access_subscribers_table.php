<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('easy_access_subscribers', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('saved_prediction_filter_id')->constrained('saved_prediction_filters')->cascadeOnDelete();
            $table->string('email')->index();
            $table->string('investment_profile', 40);
            $table->boolean('accepted_terms')->default(false);
            $table->timestampTz('accepted_at')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->timestampTz('unsubscribed_at')->nullable();
            $table->timestampsTz();
            $table->unique(['saved_prediction_filter_id', 'email']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('easy_access_subscribers');
    }
};
