<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('saved_prediction_filters')->orderBy('id')->each(function (object $filter): void {
            $stored = is_string($filter->filters) ? (json_decode($filter->filters, true) ?: []) : (array) $filter->filters;
            DB::table('saved_prediction_filters')->where('id', $filter->id)->update([
                'filters' => json_encode(array_replace([
                    'sector_score_rotation' => 0,
                    'index_score_rotation' => 0,
                ], $stored), JSON_THROW_ON_ERROR),
                'updated_at' => now(),
            ]);
        });
    }

    public function down(): void
    {
        DB::table('saved_prediction_filters')->orderBy('id')->each(function (object $filter): void {
            $stored = is_string($filter->filters) ? (json_decode($filter->filters, true) ?: []) : (array) $filter->filters;
            unset($stored['sector_score_rotation'], $stored['index_score_rotation']);
            DB::table('saved_prediction_filters')->where('id', $filter->id)->update([
                'filters' => json_encode($stored, JSON_THROW_ON_ERROR),
                'updated_at' => now(),
            ]);
        });
    }
};
