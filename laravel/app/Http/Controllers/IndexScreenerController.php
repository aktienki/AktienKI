<?php

namespace App\Http\Controllers;

use App\Support\AiScore;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class IndexScreenerController extends Controller
{
    public function __invoke(Request $request): View
    {
        $latestPredictions = DB::table('predictions')
            ->selectRaw('instrument_id, MAX(id) AS prediction_id')
            ->groupBy('instrument_id');

        $query = DB::table('market_indices as market_index')
            ->leftJoin('index_memberships as membership', function ($join) {
                $join->on('membership.market_index_id', '=', 'market_index.id')->whereNull('membership.removed_at');
            })
            ->leftJoinSub($latestPredictions, 'latest', fn ($join) => $join->on('latest.instrument_id', '=', 'membership.instrument_id'))
            ->leftJoin('predictions as prediction', 'prediction.id', '=', 'latest.prediction_id')
            ->where('market_index.is_active', true)
            ->when($request->filled('q'), function ($query) use ($request) {
                $term = '%'.mb_strtolower(trim((string) $request->query('q'))).'%';
                $query->where(fn ($nested) => $nested->whereRaw('LOWER(market_index.name) LIKE ?', [$term])->orWhereRaw('LOWER(market_index.symbol) LIKE ?', [$term]));
            })
            ->when($request->filled('region'), fn ($query) => $query->where('market_index.region', $request->query('region')))
            ->groupBy('market_index.id')
            ->select('market_index.*')
            ->selectRaw('COUNT(DISTINCT membership.instrument_id) AS members_count')
            ->selectRaw('COUNT(prediction.id) AS analyzed_count')
            ->selectRaw('AVG(prediction.prediction_score) AS calculated_rating')
            ->selectRaw('AVG(prediction.confidence) AS average_confidence')
            ->selectRaw('AVG(((prediction.predicted_price_20d - prediction.current_price) / NULLIF(prediction.current_price, 0)) * 100) AS expected_return')
            ->havingRaw('COUNT(DISTINCT membership.instrument_id) > 0');

        $indices = $query->orderBy('market_index.global_rank')->get()->each(function ($index) {
            $index->rating_value = is_numeric($index->calculated_rating)
                ? AiScore::toTen($index->calculated_rating)
                : (is_numeric($index->rating) ? (float) $index->rating : null);
        });
        $regions = DB::table('market_indices')->where('is_active', true)->whereNotNull('region')->distinct()->orderBy('region')->pluck('region');

        return view('indices.index', compact('indices', 'regions'));
    }
}
