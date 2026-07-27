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
            $table->decimal('live_profit_factor', 12, 6)->nullable();
            $table->decimal('live_maximum_drawdown', 10, 6)->nullable();
            $table->decimal('live_drawdown_limit', 10, 6)->nullable();
            $table->boolean('live_drawdown_warning_used')->default(false);
            $table->index(
                ['live_drawdown_warning_used', 'prediction_time'],
                'predictions_live_drawdown_warning_idx'
            );
        });
        $this->rewriteView(true);
    }

    public function down(): void
    {
        $this->rewriteView(false);
        Schema::table('predictions', function (Blueprint $table): void {
            $table->dropIndex('predictions_live_drawdown_warning_idx');
            $table->dropColumn([
                'live_profit_factor', 'live_maximum_drawdown',
                'live_drawdown_limit', 'live_drawdown_warning_used',
            ]);
        });
    }

    private function rewriteView(bool $append): void
    {
        $row = DB::selectOne(
            "SELECT pg_get_viewdef('public_prediction_models'::regclass, true) AS definition"
        );
        $marker = "\n   FROM predictions p";
        $extra = $append
            ? ",\n    p.live_profit_factor,\n    p.live_maximum_drawdown,"
                . "\n    p.live_drawdown_limit,\n    p.live_drawdown_warning_used"
            : '';
        if ($append) {
            if (!str_contains($row->definition, $marker)) {
                throw new RuntimeException('Unexpected public prediction view');
            }
            $view = str_replace($marker, $extra . $marker, $row->definition);
        } else {
            foreach ([
                'live_profit_factor', 'live_maximum_drawdown',
                'live_drawdown_limit', 'live_drawdown_warning_used',
            ] as $column) {
                $view = $view ?? $row->definition;
                $view = preg_replace(
                    '/,?\s*p\.' . $column . '\b/',
                    '',
                    $view,
                    1,
                    $count
                );
                if ($count !== 1) {
                    throw new RuntimeException("{$column} not found in public view");
                }
            }
        }
        DB::statement('DROP VIEW public_prediction_models');
        DB::statement('CREATE VIEW public_prediction_models AS ' . $view);
    }
};
