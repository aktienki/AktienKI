<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const DEFAULTS = [
        'q' => '', 'country' => '', 'exchange' => '', 'sector' => '', 'ai_type' => '',
        'model' => '', 'quality_tier' => '', 'signal' => '',
        'score_min' => 0, 'confidence_min' => 0, 'drawdown_max' => 50,
        'profit_factor_min' => 0, 'volatility_max' => 100,
        'pe_max' => 100, 'dividend_yield_min' => 0, 'market_cap_min' => 0,
        'revenue_growth_min' => -50, 'hit_rate_min' => 0,
    ];

    public function up(): void
    {
        DB::table('saved_prediction_filters')->orderBy('id')->each(function (object $filter): void {
            $stored = is_string($filter->filters) ? (json_decode($filter->filters, true) ?: []) : (array) $filter->filters;
            DB::table('saved_prediction_filters')->where('id', $filter->id)->update([
                'filters' => json_encode(array_replace(self::DEFAULTS, $stored), JSON_THROW_ON_ERROR),
                'updated_at' => now(),
            ]);
        });
    }

    public function down(): void
    {
        // The original sparse representation cannot be reconstructed reliably.
    }
};
