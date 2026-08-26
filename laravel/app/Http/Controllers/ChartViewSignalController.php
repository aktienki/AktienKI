<?php

namespace App\Http\Controllers;

use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class ChartViewSignalController extends Controller
{
    public function __invoke(Request $request): View
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:80'],
            'event' => ['nullable', 'string', 'max:64'],
            'scope' => ['nullable', 'in:global,blended,instrument'],
            'tone' => ['nullable', 'in:positive,negative'],
            'days' => ['nullable', 'integer', 'between:1,3'],
            'min_probability' => ['nullable', 'numeric', 'between:0,100'],
        ]);
        $tradingDays = DB::table('chartview_signal_events')->selectRaw('bar_time::date AS day')->distinct()->orderByDesc('day')->pluck('day');
        $days = (int) ($filters['days'] ?? 3);
        $selectedTradingDays = $tradingDays->take($days);
        $minimumProbability = (float) ($filters['min_probability'] ?? 0);
        $latestPredictions = DB::table('predictions')->selectRaw('instrument_id, MAX(id) AS prediction_id')->groupBy('instrument_id');
        $query = DB::table('chartview_signal_events as event')
            ->join('chartview_signal_statistics as statistic', 'statistic.event_key', '=', 'event.event_key')
            ->join('instruments as instrument', 'instrument.id', '=', 'event.instrument_id')
            ->leftJoinSub($latestPredictions, 'latest_prediction', fn ($join) => $join->on('latest_prediction.instrument_id', '=', 'event.instrument_id'))
            // Die Detailansicht benötigt eine konkrete Prognose für KI-Donuts,
            // Horizonte und die historische Prognoseauswertung.
            ->whereNotNull('latest_prediction.prediction_id')
            ->when($selectedTradingDays->isNotEmpty(), fn ($query) => $query->whereDate('event.bar_time', '>=', $selectedTradingDays->last()))
            ->where('event.rise_probability', '>=', $minimumProbability)
            ->when($filters['q'] ?? null, fn ($query, $term) => $query->where(fn ($nested) => $nested
                ->where('instrument.symbol', 'ilike', "%{$term}%")->orWhere('instrument.name', 'ilike', "%{$term}%")))
            ->when($filters['event'] ?? null, fn ($query, $event) => $query->where('event.event_key', $event))
            ->when($filters['scope'] ?? null, fn ($query, $scope) => $query->where('event.probability_scope', $scope))
            ->when($filters['tone'] ?? null, fn ($query, $tone) => $query->where('event.tone', $tone))
            ->orderByDesc('event.bar_time')->orderByDesc('event.rise_probability');
        $signals = $query->paginate(50, [
            'event.id', 'event.bar_time', 'event.event_key', 'event.tone', 'event.rise_probability',
            'event.global_probability', 'event.instrument_probability', 'event.probability_scope', 'event.sample_size',
            'statistic.label_de', 'statistic.label_en', 'statistic.sample_size as global_sample_size',
            'instrument.symbol', 'instrument.name', 'latest_prediction.prediction_id',
        ])->withQueryString();
        $eventTypes = DB::table('chartview_signal_statistics')->orderBy('label_de')->get(['event_key', 'label_de', 'label_en']);

        return view('predictions.chartview-signals', compact('signals', 'eventTypes', 'tradingDays', 'days', 'minimumProbability'));
    }
}
