<?php

namespace App\Http\Controllers;

use App\Services\ServingReadService;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\View\View;

final class ServingChartViewSignalController extends Controller
{
    public function __invoke(Request $request, ServingReadService $serving): View
    {
        $rows = $serving->latestPredictions()->flatMap(function (object $prediction): array {
            $events = (array) data_get($prediction->context, 'chart_signals', []);

            return collect($events)->map(fn ($event): object => (object) [
                'instrument_id' => $prediction->instrument_id,
                'symbol' => $prediction->symbol,
                'name' => $prediction->name,
                'horizon' => $prediction->horizon,
                'as_of' => $prediction->as_of,
                'signal' => $prediction->signal,
                'event' => is_array($event) ? ($event['label'] ?? $event['event'] ?? 'Chart-Signal') : (string) $event,
                'probability' => is_array($event) && is_numeric($event['probability'] ?? null)
                    ? (float) $event['probability']
                    : null,
            ])->all();
        })->sortByDesc('as_of')->values();
        $page = max(1, (int) $request->query('page', 1));
        $perPage = 50;
        $signals = new LengthAwarePaginator(
            $rows->forPage($page, $perPage)->values(),
            $rows->count(),
            $perPage,
            $page,
            ['path' => $request->url(), 'query' => $request->query()]
        );

        return view('predictions.serving-chartview-signals', compact('signals'));
    }
}
