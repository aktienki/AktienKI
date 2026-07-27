<?php

namespace App\Http\Controllers;

use App\Services\PersonalizedSignalService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

final class StockComparisonController extends Controller
{
    public function __invoke(Request $request, PersonalizedSignalService $personalizedSignals): View
    {
        $instrumentIds = collect(explode(',', (string) $request->query('ids')))
            ->filter(fn ($id) => ctype_digit(trim((string) $id)))
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0)
            ->unique()
            ->take(5)
            ->values();

        abort_unless($instrumentIds->count() >= 2, 422, __('Bitte wähle mindestens zwei Aktien für den Vergleich aus.'));

        $latestPredictions = DB::table('predictions')
            ->whereIn('instrument_id', $instrumentIds)
            ->selectRaw('instrument_id, MAX(id) AS prediction_id')
            ->groupBy('instrument_id');
        $signalSql = $personalizedSignals->sql('prediction', $request->user());

        $rows = DB::table('instruments as instrument')
            ->leftJoinSub($latestPredictions, 'latest', fn ($join) =>
                $join->on('latest.instrument_id', '=', 'instrument.id'))
            ->leftJoin('predictions as prediction', 'prediction.id', '=', 'latest.prediction_id')
            ->whereIn('instrument.id', $instrumentIds)
            ->where('instrument.type', 'stock')
            ->whereNull('instrument.deleted_at')
            ->select([
                'instrument.id',
                'instrument.symbol',
                'instrument.name',
                'instrument.country',
                'instrument.sector',
                'instrument.industry',
                'instrument.currency',
                'prediction.current_price',
                'prediction.predicted_price_5d',
                'prediction.predicted_price_20d',
                'prediction.prediction_score',
                'prediction.confidence',
                'prediction.risk_score',
                'prediction.drawdown_risk_factor',
                'prediction.prediction_time',
            ])
            ->selectRaw("{$signalSql} AS signal")
            ->get()
            ->sortBy(fn ($row) => $instrumentIds->search((int) $row->id))
            ->values()
            ->map(function ($row) {
                $fundamental = DB::table('instrument_fundamentals')
                    ->where('instrument_id', $row->id)
                    ->orderByDesc('snapshot_date')
                    ->orderByDesc('id')
                    ->first(['data', 'snapshot_date']);
                $row->fundamentals = $fundamental?->data
                    ? (json_decode((string) $fundamental->data, true) ?: [])
                    : [];
                $row->fundamental_date = $fundamental?->snapshot_date;

                return $row;
            });

        abort_unless($rows->count() >= 2, 422, __('Für den Vergleich wurden nicht genügend Aktien gefunden.'));

        return view('stocks.compare', compact('rows'));
    }
}
