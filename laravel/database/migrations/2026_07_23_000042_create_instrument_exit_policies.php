<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('instrument_exit_policies', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->foreignId('instrument_id')
                ->constrained('instruments')->cascadeOnDelete();
            $table->string('ai_type', 32);
            $table->string('timeframe', 16);
            $table->string('policy_name', 64);
            $table->string('source', 32);
            $table->string('status', 32);
            $table->unsignedInteger('sample_trades')->default(0);
            $table->decimal('score', 12, 10)->default(0);
            $table->jsonb('metrics')->default(DB::raw("'{}'::jsonb"));
            $table->jsonb('parameters')->default(DB::raw("'{}'::jsonb"));
            $table->boolean('is_active')->default(true);
            $table->timestampTz('evaluated_at');
            $table->timestampsTz();
            $table->unique(
                ['instrument_id', 'ai_type', 'timeframe'],
                'instrument_exit_policies_scope_unique'
            );
            $table->index(
                ['policy_name', 'status', 'is_active'],
                'instrument_exit_policies_selection_idx'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('instrument_exit_policies');
    }
};
