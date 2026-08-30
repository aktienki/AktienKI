<?php

namespace App\Http\Controllers;

use App\Services\ServingReadService;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\View\View;

final class ServingPerformanceTransparencyController extends Controller
{
    public function __invoke(Request $request, ServingReadService $serving): View
    {
        $rows = $serving->activeStocks()->flatMap(function (object $stock) {
            return $stock->horizons->map(function (object $scope) use ($stock): object {
                return (object) [
                    'instrument_id' => $stock->instrument_id,
                    'symbol' => $stock->symbol,
                    'name' => $stock->name,
                    'country' => $stock->country_code,
                    'exchange' => $stock->exchange,
                    'release_id' => $stock->release_id,
                    'released_at' => $stock->released_at,
                    'stock_quality' => $stock->quality_class,
                    'horizon' => $scope->horizon,
                    'horizon_quality' => $scope->quality_class,
                    'variant' => $scope->variant,
                    'variant_label' => $scope->variant_label,
                    'champion' => $scope->champion,
                    'prediction_status' => $scope->prediction_status,
                    'prediction_enabled' => $scope->prediction_enabled,
                    'quality_gate_passed' => $scope->quality_gate_passed,
                    'quality_gate_status' => $scope->quality_gate_status,
                    'skip_reason' => $scope->skip_reason,
                    'entry_threshold' => $scope->entry_threshold,
                    'pure_tcn_entry_threshold' => $scope->pure_tcn_entry_threshold,
                    'metrics' => $scope->metrics,
                    'standard_metrics' => $scope->standard_metrics,
                    'tcn_metrics' => $scope->tcn_metrics,
                    'prediction' => $scope->prediction,
                ];
            });
        })->values();

        $allRows = $rows;
        $search = mb_strtolower(trim((string) $request->query('q', '')));
        $horizon = (int) $request->query('horizon', 0);
        $variant = strtolower(trim((string) $request->query('variant', '')));
        $quality = strtolower(trim((string) $request->query('quality', '')));
        $status = strtolower(trim((string) $request->query('status', '')));
        $minimumHitRate = $request->filled('hit_rate') ? (float) $request->query('hit_rate') : null;
        $minimumProfitFactor = $request->filled('profit_factor') ? (float) $request->query('profit_factor') : null;

        $rows = $rows->filter(function (object $row) use ($search, $horizon, $variant, $quality, $status, $minimumHitRate, $minimumProfitFactor): bool {
            if ($search !== '' && ! str_contains(mb_strtolower($row->name.' '.$row->symbol), $search)) return false;
            if ($horizon > 0 && $row->horizon !== $horizon) return false;
            if ($variant !== '' && $row->variant !== $variant) return false;
            if ($quality !== '' && $row->horizon_quality !== $quality && $row->stock_quality !== $quality) return false;
            if ($status === 'eligible' && ! $row->prediction_enabled) return false;
            if ($status === 'blocked' && $row->prediction_enabled) return false;
            if ($status === 'quality_gate' && ! $row->quality_gate_passed) return false;
            if ($minimumHitRate !== null && (float) ($row->metrics->hit_rate ?? -INF) < $minimumHitRate) return false;
            if ($minimumProfitFactor !== null && (float) ($row->metrics->profit_factor ?? -INF) < $minimumProfitFactor) return false;

            return true;
        })->sortBy([
            ['prediction_enabled', 'desc'],
            ['name', 'asc'],
            ['horizon', 'asc'],
        ])->values();

        $page = max(1, (int) $request->query('page', 1));
        $perPage = 50;
        $rows = new LengthAwarePaginator(
            $rows->forPage($page, $perPage)->values(),
            $rows->count(),
            $perPage,
            $page,
            ['path' => $request->url(), 'query' => $request->query()]
        );
        $summary = (object) [
            'configurations' => $allRows->count(),
            'eligible' => $allRows->where('prediction_enabled', true)->count(),
            'quality_gate' => $allRows->where('quality_gate_passed', true)->count(),
            'standard_champions' => $allRows->where('variant', 'standard')->count(),
            'tcn_champions' => $allRows->where('variant', 'pure_tcn')->count(),
            'with_prediction' => $allRows->whereNotNull('prediction')->count(),
        ];

        return view('predictions.serving-performance-transparency', compact('rows', 'summary'));
    }
}
