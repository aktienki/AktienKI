<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('predictions', function (Blueprint $table): void {
            $table->string('higher_timeframe_trend', 16)->nullable();
            $table->boolean('higher_timeframe_confirmed')->nullable();
            $table->boolean('higher_timeframe_veto_used')->default(false);
            $table->jsonb('higher_timeframe_details')
                ->default(DB::raw("'{}'::jsonb"));
            $table->index(
                ['ai_type', 'higher_timeframe_trend', 'higher_timeframe_confirmed'],
                'predictions_higher_timeframe_idx'
            );
        });
    }

    public function down(): void
    {
        Schema::table('predictions', function (Blueprint $table): void {
            $table->dropIndex('predictions_higher_timeframe_idx');
            $table->dropColumn([
                'higher_timeframe_trend',
                'higher_timeframe_confirmed',
                'higher_timeframe_veto_used',
                'higher_timeframe_details',
            ]);
        });
    }
};
