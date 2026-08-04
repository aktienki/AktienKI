<?php

namespace App\Http\Controllers;

use App\Services\PersonalizedSignalService;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

final class SignalChangeController extends Controller
{
    public function __invoke(Request $request, PersonalizedSignalService $signals): View
    {
        $sortColumns = [
            'time' => 'signal_change.prediction_time',
            'stock' => 'signal_change.symbol',
            'model' => 'signal_change.model_alias',
            'change' => 'signal_change.current_signal',
            'score' => 'signal_change.score_10',
            'confidence' => 'signal_change.confidence_percent',
            'risk' => 'signal_change.risk_percent',
            'price' => 'signal_change.current_price',
        ];
        $sort = array_key_exists((string) $request->query('sort'), $sortColumns)
            ? (string) $request->query('sort')
            : 'time';
        $direction = strtolower((string) $request->query('direction')) === 'asc' ? 'asc' : 'desc';
        $days = in_array($request->integer('days'), [1, 2, 3, 7, 14, 30, 90, 180, 365], true)
            ? $request->integer('days')
            : 30;
        $signalSql = $signals->sql('prediction', $request->user());
        $scoreSql = '(CASE WHEN prediction.prediction_score <= 1 THEN prediction.prediction_score * 10 WHEN prediction.prediction_score <= 10 THEN prediction.prediction_score ELSE prediction.prediction_score / 10 END)';
        $confidenceSql = '(CASE WHEN prediction.confidence <= 1 THEN prediction.confidence * 100 ELSE prediction.confidence END)';
        $riskSql = '(CASE WHEN COALESCE(prediction.risk_score, prediction.drawdown_risk_factor) <= 1 THEN COALESCE(prediction.risk_score, prediction.drawdown_risk_factor) * 100 ELSE COALESCE(prediction.risk_score, prediction.drawdown_risk_factor) END)';
        $latestQualityRankings = DB::table('model_quality_rankings')
            ->selectRaw('trained_model_id, MAX(id) AS ranking_id')
            ->groupBy('trained_model_id');

        $history = DB::table('predictions as prediction')
            ->join('instruments as instrument', 'instrument.id', '=', 'prediction.instrument_id')
            ->leftJoin('exchanges as exchange', 'exchange.id', '=', 'instrument.exchange_id')
            ->leftJoin('trained_models as trained_model', 'trained_model.id', '=', 'prediction.trained_model_id')
            ->leftJoin('model_definitions as model_definition', 'model_definition.id', '=', 'trained_model.model_definition_id')
            ->leftJoinSub($latestQualityRankings, 'latest_quality', fn ($join) =>
                $join->on('latest_quality.trained_model_id', '=', 'trained_model.id'))
            ->leftJoin('model_quality_rankings as model_quality', 'model_quality.id', '=', 'latest_quality.ranking_id')
            ->leftJoin('model_quality_tiers as quality_tier', 'quality_tier.id', '=', 'model_quality.tier_id')
            ->where('instrument.type', 'stock')
            ->where('instrument.is_active', true)
            ->whereNull('instrument.deleted_at')
            ->when($request->filled('q'), function (Builder $query) use ($request): void {
                $term = '%'.strtolower(trim((string) $request->query('q'))).'%';
                $query->where(fn (Builder $query) => $query
                    ->whereRaw('LOWER(instrument.symbol) LIKE ?', [$term])
                    ->orWhereRaw('LOWER(instrument.name) LIKE ?', [$term]));
            })
            ->when(in_array($request->query('ai_type'), ['horizon', 'pulse'], true), fn (Builder $query) =>
                $query->where('prediction.ai_type', $request->query('ai_type')))
            ->when($request->integer('model') > 0, fn (Builder $query) =>
                $query->where('trained_model.model_definition_id', $request->integer('model')))
            ->when(in_array($request->query('quality_tier'), ['top', 'strong', 'solid', 'test'], true), fn (Builder $query) =>
                $query->where('quality_tier.code', $request->query('quality_tier')))
            ->when($request->query('quality_tier') === 'unqualified', fn (Builder $query) =>
                $query->whereNull('quality_tier.code'))
            ->select([
                'prediction.id',
                'prediction.instrument_id',
                'prediction.prediction_time',
                'prediction.ai_type',
                'prediction.interval',
                'prediction.current_price',
                'instrument.symbol',
                'instrument.name',
                'instrument.country',
                'instrument.currency',
                'exchange.code as exchange_code',
                'model_definition.id as model_definition_id',
                'model_definition.public_alias as model_alias',
                'model_quality.quality_score as model_quality_score',
                'model_quality.eligible as model_quality_eligible',
                'quality_tier.code as model_quality_tier_code',
                'quality_tier.name as model_quality_tier_name',
            ])
            ->selectRaw("{$signalSql} AS current_signal")
            ->selectRaw("{$scoreSql} AS score_10")
            ->selectRaw("{$confidenceSql} AS confidence_percent")
            ->selectRaw("{$riskSql} AS risk_percent");

        $sequenced = DB::query()
            ->fromSub($history, 'history')
            ->select('history.*')
            ->selectRaw('LAG(history.current_signal) OVER (
                PARTITION BY history.instrument_id, COALESCE(history.model_definition_id, 0)
                ORDER BY history.prediction_time, history.id
            ) AS previous_signal');

        $changes = DB::query()
            ->fromSub($sequenced, 'signal_change')
            ->whereNotNull('signal_change.previous_signal')
            ->whereColumn('signal_change.previous_signal', '<>', 'signal_change.current_signal')
            ->where('signal_change.prediction_time', '>=', now()->subDays($days))
            ->when(in_array(strtoupper((string) $request->query('from_signal')), ['SELL', 'HOLD', 'WATCH', 'BUY'], true), fn (Builder $query) =>
                $query->where('signal_change.previous_signal', strtoupper((string) $request->query('from_signal'))))
            ->when(in_array(strtoupper((string) $request->query('to_signal')), ['SELL', 'HOLD', 'WATCH', 'BUY'], true), fn (Builder $query) =>
                $query->where('signal_change.current_signal', strtoupper((string) $request->query('to_signal'))))
            ->when($request->filled('score_min') && is_numeric($request->query('score_min')), fn (Builder $query) =>
                $query->where('signal_change.score_10', '>=', max(0, min(10, (float) $request->query('score_min')))))
            ->when($request->filled('confidence_min') && is_numeric($request->query('confidence_min')), fn (Builder $query) =>
                $query->where('signal_change.confidence_percent', '>=', max(0, min(100, (float) $request->query('confidence_min')))))
            ->orderBy($sortColumns[$sort], $direction)
            ->orderByDesc('signal_change.id')
            ->get();

        $aiTypes = DB::table('predictions')
            ->whereNotNull('ai_type')
            ->distinct()
            ->orderBy('ai_type')
            ->pluck('ai_type');
        $models = DB::table('model_definitions')
            ->whereNotNull('public_alias')
            ->where('public_alias', '<>', '')
            ->orderBy('public_alias')
            ->get(['id', 'public_alias']);
        $qualityTiers = DB::table('model_quality_tiers')
            ->whereIn('code', ['top', 'strong', 'solid', 'test'])
            ->get(['code', 'name'])
            ->sortBy(fn (object $tier): int => array_search($tier->code, ['top', 'strong', 'solid', 'test'], true))
            ->values();

        return view('signal-changes.index', compact('changes', 'days', 'aiTypes', 'models', 'qualityTiers', 'sort', 'direction'));
    }
}
