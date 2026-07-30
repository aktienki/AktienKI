<?php

namespace App\Services;

use App\Support\AiScore;
use Illuminate\Support\Facades\DB;

class IndexAiScoreService
{
    public function countryScores(): array
    {
        $latestPredictions = DB::table('predictions')
            ->selectRaw('instrument_id, MAX(id) AS prediction_id')
            ->groupBy('instrument_id');

        return DB::table('instruments as instrument')
            ->leftJoinSub($latestPredictions, 'latest', fn ($join) =>
                $join->on('latest.instrument_id', '=', 'instrument.id'))
            ->leftJoin('predictions as prediction', 'prediction.id', '=', 'latest.prediction_id')
            ->where('instrument.type', 'stock')
            ->where('instrument.is_active', true)
            ->whereNull('instrument.deleted_at')
            ->whereNotNull('instrument.country')
            ->where('instrument.country', '<>', '')
            ->groupBy('instrument.country')
            ->selectRaw('instrument.country, AVG(prediction.prediction_score) AS score, COUNT(instrument.id) AS stocks')
            ->get()
            ->mapWithKeys(function ($row) {
                $score = AiScore::toPercent($row->score) ?? 0;

                return [strtoupper($row->country) => [
                    'score' => round($score, 1),
                    'stocks' => (int) $row->stocks,
                ]];
            })
            ->all();
    }

    public function dailyAverages(int $days = 14): array
    {
        return DB::table('predictions')
            ->whereNotNull('prediction_score')
            ->selectRaw('DATE(prediction_time) AS day, AVG(prediction_score) AS score')
            ->groupByRaw('DATE(prediction_time)')
            ->orderByDesc('day')
            ->limit($days)
            ->get()
            ->reverse()
            ->map(function ($row) {
                return [
                    'x' => (string) $row->day,
                    'y' => round(AiScore::toTen($row->score) ?? 0, 2),
                ];
            })
            ->values()
            ->all();
    }

    public function scores(): array
    {
        $latestPredictions = DB::table('predictions')
            ->selectRaw('instrument_id, MAX(id) AS prediction_id')
            ->groupBy('instrument_id');

        $indexScores = DB::table('index_memberships as membership')
            ->join('market_indices as market_index', 'market_index.id', '=', 'membership.market_index_id')
            ->joinSub($latestPredictions, 'latest', fn ($join) =>
                $join->on('latest.instrument_id', '=', 'membership.instrument_id'))
            ->join('predictions as prediction', 'prediction.id', '=', 'latest.prediction_id')
            ->whereNull('membership.removed_at')
            ->groupBy('market_index.id', 'market_index.symbol', 'market_index.name')
            ->selectRaw('market_index.symbol, market_index.name, AVG(prediction.prediction_score) AS score, COUNT(*) AS companies')
            ->get();

        $scores = [];

        foreach ($indexScores as $index) {
            $identity = strtoupper($index->symbol.' '.$index->name);
            $dashboardName = match (true) {
                str_contains($identity, 'DAX') || str_contains($identity, 'GDAXI') => 'DAX',
                str_contains($identity, 'NASDAQ') || str_contains($identity, 'IXIC') => 'NASDAQ',
                str_contains($identity, 'S&P') || str_contains($identity, 'SP500') || str_contains($identity, 'GSPC') => 'S&P 500',
                str_contains($identity, 'NIKKEI') || str_contains($identity, 'N225') => 'Japan',
                str_contains($identity, 'SHANGHAI') || str_contains($identity, 'SSE') || str_contains($identity, '000001') => 'China',
                default => null,
            };

            if ($dashboardName) {
                $scores[$dashboardName] = [
                    'score' => AiScore::toPercent($index->score),
                    'companies' => (int) $index->companies,
                ];
            }
        }

        return $scores;
    }
}
