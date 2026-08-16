<?php

namespace App\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

final class MarketContextPredictionService
{
    public function generate(?Carbon $date = null): array
    {
        $day = ($date ?? now())->toDateString();
        $latest = DB::table('predictions')
            ->whereDate('prediction_time', $day)
            ->selectRaw('instrument_id, MAX(id) AS prediction_id')
            ->groupBy('instrument_id');
        $scoreSql = '(CASE WHEN prediction.prediction_score <= 1 THEN prediction.prediction_score * 10 WHEN prediction.prediction_score <= 10 THEN prediction.prediction_score ELSE prediction.prediction_score / 10 END)';
        $confidenceSql = '(CASE WHEN prediction.confidence <= 1 THEN prediction.confidence * 100 ELSE prediction.confidence END)';

        $sectors = DB::table('predictions as prediction')->joinSub($latest, 'latest', fn ($join) => $join->on('latest.prediction_id', '=', 'prediction.id'))
            ->join('instruments as instrument', 'instrument.id', '=', 'prediction.instrument_id')
            ->whereNotNull('instrument.sector')->where('instrument.sector', '<>', '')
            ->groupBy('instrument.sector')->get([
                'instrument.sector as scope_key', DB::raw("AVG({$scoreSql}) AS score"),
                DB::raw("AVG({$confidenceSql}) AS confidence"), DB::raw('COUNT(*) AS member_count'),
            ]);
        $indices = DB::table('predictions as prediction')->joinSub($latest, 'latest', fn ($join) => $join->on('latest.prediction_id', '=', 'prediction.id'))
            ->join('index_memberships as membership', fn ($join) => $join->on('membership.instrument_id', '=', 'prediction.instrument_id')->whereNull('membership.removed_at'))
            ->join('market_indices as market_index', 'market_index.id', '=', 'membership.market_index_id')
            ->groupBy('market_index.id')->get([
                DB::raw('market_index.id::text AS scope_key'), DB::raw("AVG({$scoreSql}) AS score"),
                DB::raw("AVG({$confidenceSql}) AS confidence"), DB::raw('COUNT(*) AS member_count'),
            ]);

        foreach (['sector' => $sectors, 'index' => $indices] as $type => $rows) {
            foreach ($rows as $row) {
                $score = (float) $row->score;
                DB::table('market_context_predictions')->upsert([[
                    'prediction_date' => $day, 'scope_type' => $type, 'scope_key' => (string) $row->scope_key,
                    'score' => $score, 'confidence' => (float) $row->confidence,
                    'signal' => $score >= 6 ? 'BUY' : ($score <= 4 ? 'SELL' : 'HOLD'),
                    'member_count' => (int) $row->member_count,
                    'meta' => json_encode(['source' => 'daily_constituent_predictions'], JSON_THROW_ON_ERROR),
                    'created_at' => now(), 'updated_at' => now(),
                ]], ['prediction_date', 'scope_type', 'scope_key'], ['score', 'confidence', 'signal', 'member_count', 'meta', 'updated_at']);
            }
        }

        return ['date' => $day, 'sectors' => $sectors->count(), 'indices' => $indices->count()];
    }
}
