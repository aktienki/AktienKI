<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('saved_prediction_filters')->orderBy('id')->each(function (object $filter): void {
            $stored = is_string($filter->filters) ? (json_decode($filter->filters, true) ?: []) : (array) $filter->filters;
            $models = $stored['model'] ?? [];
            $stored['model'] = collect(is_array($models) ? $models : [$models])
                ->filter(fn ($id): bool => is_numeric($id) && (int) $id > 0)
                ->map(fn ($id): int => (int) $id)
                ->unique()
                ->values()
                ->all();
            $stored['position_factor'] = max(1, min(5, (int) ($stored['position_factor'] ?? 1)));

            DB::table('saved_prediction_filters')->where('id', $filter->id)->update([
                'filters' => json_encode($stored, JSON_THROW_ON_ERROR),
                'updated_at' => now(),
            ]);
        });
    }

    public function down(): void
    {
        DB::table('saved_prediction_filters')->orderBy('id')->each(function (object $filter): void {
            $stored = is_string($filter->filters) ? (json_decode($filter->filters, true) ?: []) : (array) $filter->filters;
            $models = is_array($stored['model'] ?? null) ? $stored['model'] : [];
            $stored['model'] = $models[0] ?? '';

            DB::table('saved_prediction_filters')->where('id', $filter->id)->update([
                'filters' => json_encode($stored, JSON_THROW_ON_ERROR),
                'updated_at' => now(),
            ]);
        });
    }
};
