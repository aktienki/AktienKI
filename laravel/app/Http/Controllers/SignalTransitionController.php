<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use App\Support\AiScore;

final class SignalTransitionController extends Controller
{
    public function index(Request $request): View
    {
        $days = max(7, min(365, (int) $request->query('days', 90)));
        // Keep the history aligned with the currently deployed Triple
        // Timeline universe.  Older prediction rows can still exist in the
        // database, but their transitions must not be mixed with the new
        // walk-forward results.
        $latestTripleRunId = DB::table('walk_forward_backtest_runs')
            ->where('status', 'completed')
            ->whereRaw("parameters->>'procedure_version' = ?", ['triple-timeline-v1'])
            ->orderByDesc('id')
            ->value('id');
        $tripleInstrumentIds = $latestTripleRunId
            ? DB::table('walk_forward_backtest_scores')->where('run_id', $latestTripleRunId)->pluck('instrument_id')
            : collect();
        $history = DB::table('predictions as p')
            ->join('instruments as instrument', 'instrument.id', '=', 'p.instrument_id')
            ->where('instrument.type', 'stock')
            ->when($tripleInstrumentIds->isNotEmpty(), fn ($query) => $query->whereIn('p.instrument_id', $tripleInstrumentIds->all()))
            ->where('p.prediction_time', '>=', now()->subDays($days))
            ->select([
                'p.id', 'p.instrument_id', 'p.prediction_time', 'p.signal', 'p.current_price',
                'p.predicted_price_5d', 'p.predicted_price_10d', 'p.predicted_price_20d',
                'p.ai_score', 'p.confidence', 'p.risk_score', 'p.prediction_score',
                'p.actual_return', 'p.realized_strategy_return', 'p.validated_at', 'p.target_reached',
                'p.prediction_horizon_minutes', 'instrument.symbol', 'instrument.name', 'instrument.currency',
            ])
            ->selectRaw('LAG(p.id) OVER (PARTITION BY p.instrument_id, COALESCE(p.trained_model_id,0), p.prediction_horizon_minutes ORDER BY p.prediction_time,p.id) AS previous_id')
            ->selectRaw('LAG(p.signal) OVER (PARTITION BY p.instrument_id, COALESCE(p.trained_model_id,0), p.prediction_horizon_minutes ORDER BY p.prediction_time,p.id) AS previous_signal')
            ->selectRaw('LAG(p.prediction_time) OVER (PARTITION BY p.instrument_id, COALESCE(p.trained_model_id,0), p.prediction_horizon_minutes ORDER BY p.prediction_time,p.id) AS previous_prediction_time');

        $rows = DB::query()->fromSub($history, 'h')
            ->whereNotNull('h.previous_signal')
            ->whereColumn('h.previous_signal', '<>', 'h.signal')
            ->whereIn(DB::raw('UPPER(h.signal)'), ['BUY', 'SELL'])
            ->when($request->filled('signal'), fn ($q) => $q->whereRaw('UPPER(h.signal) = ?', [strtoupper((string) $request->query('signal'))]))
            ->orderByDesc('h.prediction_time')
            ->limit(300)
            ->get()
            ->map(function (object $row): object {
                $return = $row->realized_strategy_return ?? $row->actual_return;
                $row->performance_percent = is_numeric($return) ? (float) $return * 100 : null;
                $row->score_at_signal = AiScore::toTen(is_numeric($row->ai_score) ? $row->ai_score : $row->prediction_score);
                $row->confidence_at_signal = is_numeric($row->confidence) ? (float) $row->confidence * 100 : null;
                $row->risk_at_signal = is_numeric($row->risk_score) ? (float) $row->risk_score * 100 : null;
                $row->closed = $row->validated_at !== null || $row->actual_return !== null || $row->realized_strategy_return !== null;
                $row->horizon_days = max(1, (int) round(((int) $row->prediction_horizon_minutes) / 1440));
                return $row;
            });

        $closed = $rows->filter(fn (object $row): bool => $row->closed && $row->performance_percent !== null);
        $equity = 0.0; $peak = 0.0; $grossWins = 0.0; $grossLosses = 0.0;
        $chartData = ['performance' => [], 'profit_factor' => [], 'drawdown' => []];
        foreach ($closed->sortBy('prediction_time')->values() as $trade) {
            $value = (float) $trade->performance_percent;
            $equity += $value;
            $peak = max($peak, $equity);
            if ($value > 0) $grossWins += $value;
            if ($value < 0) $grossLosses += abs($value);
            $chartData['performance'][] = round($equity, 4);
            $chartData['profit_factor'][] = $grossLosses > 0 ? round($grossWins / $grossLosses, 4) : ($grossWins > 0 ? 1.0 : 0.0);
            $chartData['drawdown'][] = round($equity - $peak, 4);
        }
        $demo = $request->boolean('demo');
        if ($demo && count($chartData['performance']) < 2) {
            // Explicit preview only; these values are never stored or included in statistics.
            $chartData = [
                'performance' => [0.0, 1.8, 0.9, 3.4, 2.6, 5.1, 4.2, 7.0, 6.4, 8.1],
                'profit_factor' => [0.0, 1.35, 1.12, 1.58, 1.42, 1.73, 1.61, 1.88, 1.77, 1.95],
                'drawdown' => [0.0, 0.0, -0.9, 0.0, -0.8, 0.0, -0.9, 0.0, -0.6, 0.0],
            ];
        }
        $stats = (object) [
            'transitions' => $rows->count(),
            'closed' => $closed->count(),
            'wins' => $closed->filter(fn (object $row): bool => $row->performance_percent > 0)->count(),
            'average' => $closed->isNotEmpty() ? $closed->avg('performance_percent') : 0,
            'total' => $closed->sum('performance_percent'),
        ];

        return view('predictions.signal-history', compact('rows', 'stats', 'days', 'chartData', 'demo'));
    }
}
