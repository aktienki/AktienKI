<?php

namespace App\Http\Controllers;

use App\Services\PersonalizedSignalService;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

final class PredictionController extends Controller
{
    public function index(Request $request): View
    {
        $signalSql = app(PersonalizedSignalService::class)->sql('prediction', $request->user());
        $sortColumns = [
            'time' => 'prediction.prediction_time',
            'stock' => 'instrument.symbol',
            'model' => 'model_definition.public_alias',
            'signal' => 'personalized_signal',
            'price' => 'prediction.current_price',
            'return_5d' => 'expected_return_5d',
            'return_20d' => 'expected_return_20d',
            'score' => 'score_10',
            'confidence' => 'confidence_percent',
            'risk' => 'risk_percent',
            'quality' => 'prediction.quality_band',
            'validation' => 'prediction.validated_at',
        ];
        $sort = array_key_exists((string) $request->query('sort'), $sortColumns)
            ? (string) $request->query('sort')
            : 'time';
        $direction = strtolower((string) $request->query('direction')) === 'asc' ? 'asc' : 'desc';

        $latestQualityRankings = DB::table('model_quality_rankings')
            ->selectRaw('trained_model_id, MAX(id) AS ranking_id')
            ->groupBy('trained_model_id');

        $historicalBaseQuery = fn (): Builder => DB::table('predictions as prediction')
            ->join('instruments as instrument', 'instrument.id', '=', 'prediction.instrument_id')
            ->leftJoin('trained_models as trained_model', 'trained_model.id', '=', 'prediction.trained_model_id')
            ->leftJoin('model_definitions as model_definition', 'model_definition.id', '=', 'trained_model.model_definition_id')
            ->leftJoinSub($latestQualityRankings, 'latest_quality', fn ($join) =>
                $join->on('latest_quality.trained_model_id', '=', 'trained_model.id'))
            ->leftJoin('model_quality_rankings as model_quality', 'model_quality.id', '=', 'latest_quality.ranking_id')
            ->leftJoin('model_quality_tiers as quality_tier', 'quality_tier.id', '=', 'model_quality.tier_id')
            ->where('instrument.type', 'stock')
            ->where('instrument.is_active', true)
            ->whereNull('instrument.deleted_at');

        $baseQuery = fn (): Builder => $historicalBaseQuery()
            ->whereRaw('prediction.id = (
                SELECT latest_prediction.id
                FROM predictions AS latest_prediction
                WHERE latest_prediction.instrument_id = prediction.instrument_id
                ORDER BY latest_prediction.prediction_time DESC NULLS LAST, latest_prediction.id DESC
                LIMIT 1
            )');

        $scoreSql = '(CASE WHEN prediction.prediction_score <= 1 THEN prediction.prediction_score * 10 WHEN prediction.prediction_score <= 10 THEN prediction.prediction_score ELSE prediction.prediction_score / 10 END)';
        $confidenceSql = '(CASE WHEN prediction.confidence <= 1 THEN prediction.confidence * 100 ELSE prediction.confidence END)';

        $applyFilters = function (Builder $query, ?string $excluded = null) use ($request, $signalSql, $scoreSql, $confidenceSql): Builder {
            $qualityTier = (string) $request->query('quality_tier');

            return $query
            ->when($request->filled('q'), function (Builder $query) use ($request): void {
                $term = '%'.strtolower(trim((string) $request->query('q'))).'%';
                $query->where(fn (Builder $query) => $query
                    ->whereRaw('LOWER(instrument.symbol) LIKE ?', [$term])
                    ->orWhereRaw('LOWER(instrument.name) LIKE ?', [$term]));
            })
            ->when($excluded !== 'ai_type' && in_array($request->query('ai_type'), ['horizon', 'pulse'], true), fn (Builder $query) =>
                $query->where('prediction.ai_type', $request->query('ai_type')))
            ->when($excluded !== 'model' && $request->integer('model') > 0, fn (Builder $query) =>
                $query->where('trained_model.model_definition_id', $request->integer('model')))
            ->when($excluded !== 'quality_tier' && in_array($qualityTier, ['top', 'strong', 'solid', 'test'], true), fn (Builder $query) =>
                $query->where('quality_tier.code', $qualityTier))
            ->when($excluded !== 'quality_tier' && $qualityTier === 'unqualified', fn (Builder $query) =>
                $query->whereNull('quality_tier.code'))
            ->when($excluded !== 'signal' && in_array(strtoupper((string) $request->query('signal')), ['SELL', 'HOLD', 'WATCH', 'BUY'], true), fn (Builder $query) =>
                $query->whereRaw("({$signalSql}) = ?", [strtoupper((string) $request->query('signal'))]))
            ->when($excluded !== 'validation' && $request->query('validation') === 'validated', fn (Builder $query) =>
                $query->whereNotNull('prediction.validated_at'))
            ->when($excluded !== 'validation' && $request->query('validation') === 'pending', fn (Builder $query) =>
                $query->whereNull('prediction.validated_at'))
            ->when($excluded !== 'score' && $request->filled('score_min') && is_numeric($request->query('score_min')), fn (Builder $query) =>
                $query->whereRaw("{$scoreSql} >= ?", [max(0, min(10, (float) $request->query('score_min')))]))
            ->when($excluded !== 'confidence' && $request->filled('confidence_min') && is_numeric($request->query('confidence_min')), fn (Builder $query) =>
                $query->whereRaw("{$confidenceSql} >= ?", [max(0, min(100, (float) $request->query('confidence_min')))]));
        };

        $query = $applyFilters($baseQuery())
            ->select([
                'prediction.id',
                'prediction.instrument_id',
                'prediction.prediction_time',
                'prediction.interval',
                'prediction.ai_type',
                'prediction.current_price',
                'prediction.predicted_price_5d',
                'prediction.predicted_price_20d',
                'prediction.confidence',
                'prediction.risk_score',
                'prediction.drawdown_risk_factor',
                'prediction.prediction_score',
                'prediction.quality_band',
                'prediction.validated_at',
                'prediction.direction_correct',
                'prediction.actual_return',
                'instrument.symbol',
                'instrument.name',
                'instrument.country',
                'instrument.currency',
                'model_definition.public_alias as model_alias',
                'model_quality.quality_score as model_quality_score',
                'model_quality.eligible as model_quality_eligible',
                'quality_tier.code as model_quality_tier_code',
                'quality_tier.name as model_quality_tier_name',
            ])
            ->selectRaw("{$signalSql} AS personalized_signal")
            ->selectRaw('((prediction.predicted_price_5d - prediction.current_price) / NULLIF(prediction.current_price, 0)) * 100 AS expected_return_5d')
            ->selectRaw('((prediction.predicted_price_20d - prediction.current_price) / NULLIF(prediction.current_price, 0)) * 100 AS expected_return_20d')
            ->selectRaw("{$scoreSql} AS score_10")
            ->selectRaw("{$confidenceSql} AS confidence_percent")
            ->selectRaw('(CASE WHEN COALESCE(prediction.risk_score, prediction.drawdown_risk_factor) <= 1 THEN COALESCE(prediction.risk_score, prediction.drawdown_risk_factor) * 100 ELSE COALESCE(prediction.risk_score, prediction.drawdown_risk_factor) END) AS risk_percent');

        $query
            ->orderBy($sortColumns[$sort], $direction)
            ->orderByDesc('prediction.id');

        $predictions = $query->get();

        $summary = $baseQuery()
            ->selectRaw('COUNT(*) AS total')
            ->selectRaw('COUNT(DISTINCT prediction.instrument_id) AS instruments')
            ->selectRaw('COUNT(prediction.validated_at) AS validated')
            ->selectRaw('MAX(prediction.prediction_time) AS latest')
            ->first();

        $aiTypes = $applyFilters($baseQuery(), 'ai_type')
            ->whereNotNull('prediction.ai_type')
            ->distinct()
            ->orderBy('prediction.ai_type')
            ->pluck('prediction.ai_type');

        $models = $applyFilters($baseQuery(), 'model')
            ->whereNotNull('model_definition.public_alias')
            ->where('model_definition.public_alias', '<>', '')
            ->select('model_definition.id', 'model_definition.public_alias')
            ->distinct()
            ->orderBy('model_definition.public_alias')
            ->get();

        $qualityTiers = $applyFilters($baseQuery(), 'quality_tier')
            ->selectRaw("COALESCE(quality_tier.code, 'unqualified') AS code")
            ->selectRaw("COALESCE(quality_tier.name, 'Nicht qualifiziert') AS name")
            ->distinct()
            ->get()
            ->sortBy(fn (object $tier): int => array_search($tier->code, ['top', 'strong', 'solid', 'test', 'unqualified'], true))
            ->values();

        $signals = $applyFilters($baseQuery(), 'signal')
            ->selectRaw("({$signalSql}) AS available_signal")
            ->distinct()
            ->orderBy('available_signal')
            ->pluck('available_signal')
            ->map(fn ($signal) => strtoupper((string) $signal))
            ->filter(fn (string $signal) => in_array($signal, ['SELL', 'HOLD', 'WATCH', 'BUY'], true));

        $validationStates = $applyFilters($baseQuery(), 'validation')
            ->selectRaw("CASE WHEN prediction.validated_at IS NULL THEN 'pending' ELSE 'validated' END AS validation_state")
            ->distinct()
            ->orderBy('validation_state')
            ->pluck('validation_state');

        $scoreBucketSql = 'LEAST(9, GREATEST(0, FLOOR(CASE WHEN prediction.prediction_score <= 1 THEN prediction.prediction_score * 10 WHEN prediction.prediction_score <= 10 THEN prediction.prediction_score ELSE prediction.prediction_score / 10 END)))::integer';
        $confidenceBucketSql = 'LEAST(9, GREATEST(0, FLOOR((CASE WHEN prediction.confidence <= 1 THEN prediction.confidence * 100 ELSE prediction.confidence END) / 10)))::integer';
        $heatmap = $applyFilters($historicalBaseQuery(), 'validation')
            ->whereNotNull('prediction.validated_at')
            ->whereNotNull('prediction.prediction_score')
            ->whereNotNull('prediction.confidence')
            ->selectRaw("{$scoreBucketSql} AS score_bucket")
            ->selectRaw("{$confidenceBucketSql} AS confidence_bucket")
            ->selectRaw('COUNT(*) AS samples')
            ->selectRaw('AVG(CASE WHEN prediction.direction_correct THEN 1.0 WHEN prediction.direction_correct = FALSE THEN 0.0 END) * 100 AS hit_rate')
            ->selectRaw('AVG(prediction.actual_return) * 100 AS average_return')
            ->groupByRaw("{$scoreBucketSql}, {$confidenceBucketSql}")
            ->get()
            ->keyBy(fn ($row) => $row->score_bucket.'-'.$row->confidence_bucket);

        $userWatchlists = DB::table('watchlists')
            ->where('user_id', $request->user()->id)
            ->where('active', true)
            ->orderByDesc('is_default')
            ->orderBy('name')
            ->get(['id', 'name', 'is_default']);
        $watchlistMemberships = $userWatchlists->isEmpty()
            ? collect()
            : DB::table('watchlist_items')
                ->whereIn('watchlist_id', $userWatchlists->pluck('id'))
                ->get(['instrument_id', 'watchlist_id'])
                ->groupBy('instrument_id')
                ->map(fn ($items) => $items->pluck('watchlist_id')->map(fn ($id) => (int) $id));

        return view('predictions.index', compact(
            'predictions',
            'summary',
            'aiTypes',
            'models',
            'qualityTiers',
            'signals',
            'validationStates',
            'heatmap',
            'userWatchlists',
            'watchlistMemberships',
            'sort',
            'direction',
        ));
    }
}
