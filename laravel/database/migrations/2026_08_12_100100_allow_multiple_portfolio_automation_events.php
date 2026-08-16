<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('portfolio_automation_executions', function (Blueprint $table): void {
            $table->dropUnique('portfolio_automation_strategy_prediction_uq');
            $table->index(['saved_prediction_filter_id', 'prediction_id', 'action'], 'portfolio_automation_strategy_prediction_action_idx');
        });
    }

    public function down(): void
    {
        Schema::table('portfolio_automation_executions', function (Blueprint $table): void {
            $table->dropIndex('portfolio_automation_strategy_prediction_action_idx');
            $table->unique(['saved_prediction_filter_id', 'prediction_id'], 'portfolio_automation_strategy_prediction_uq');
        });
    }
};
