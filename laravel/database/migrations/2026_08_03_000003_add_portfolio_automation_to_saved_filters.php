<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('saved_prediction_filters', function (Blueprint $table): void {
            $table->boolean('automatic_portfolio_enabled')->default(false)->after('email_notification_enabled');
        });

        Schema::create('portfolio_automation_executions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('saved_prediction_filter_id')->constrained('saved_prediction_filters')->cascadeOnDelete();
            $table->foreignId('portfolio_id')->constrained()->cascadeOnDelete();
            $table->foreignId('prediction_id')->constrained()->cascadeOnDelete();
            $table->foreignId('instrument_id')->constrained('instruments')->cascadeOnDelete();
            $table->foreignId('portfolio_transaction_id')->nullable()->constrained()->nullOnDelete();
            $table->string('action', 24)->default('buy');
            $table->decimal('sector_average_score', 8, 4)->nullable();
            $table->unsignedSmallInteger('position_factor')->default(1);
            $table->decimal('allocated_capital', 20, 6);
            $table->jsonb('details')->nullable();
            $table->timestampsTz();

            $table->unique(['saved_prediction_filter_id', 'prediction_id'], 'portfolio_automation_strategy_prediction_uq');
            $table->index(['portfolio_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('portfolio_automation_executions');
        Schema::table('saved_prediction_filters', function (Blueprint $table): void {
            $table->dropColumn('automatic_portfolio_enabled');
        });
    }
};
