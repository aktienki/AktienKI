<?php

namespace App\Services;

use App\Support\AiScore;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class IndexAiScoreService
{
    public function countryScores(): array
    {
        return Cache::remember('dashboard_country_index_scores_v3', now()->addMinutes(2), function (): array {
            $rankedBars = DB::table('price_bars as bar')
                ->whereIn('bar.interval', ['1m', '1d'])
                ->selectRaw('bar.instrument_id, bar.close, bar.bar_time, bar.updated_at AS observed_at, bar.interval, ROW_NUMBER() OVER (PARTITION BY bar.instrument_id ORDER BY bar.bar_time DESC, bar.id DESC) AS row_number');

            $latestMoves = DB::query()
                ->fromSub($rankedBars, 'ranked_bar')
                ->where('row_number', 1)
                ->select(['instrument_id', 'close as latest_close', 'bar_time as market_date', 'observed_at', 'interval as latest_interval']);

            $rankedDailyBars = DB::table('price_bars as bar')
                ->where('bar.interval', '1d')
                ->selectRaw('bar.instrument_id, bar.close, bar.bar_time, ROW_NUMBER() OVER (PARTITION BY bar.instrument_id ORDER BY bar.bar_time DESC, bar.id DESC) AS row_number');

            $dailyCloses = DB::query()
                ->fromSub($rankedDailyBars, 'ranked_daily_bar')
                ->where('row_number', '<=', 2)
                ->groupBy('instrument_id')
                ->selectRaw('instrument_id')
                ->selectRaw('MAX(close) FILTER (WHERE row_number = 1) AS latest_daily_close')
                ->selectRaw('MAX(bar_time) FILTER (WHERE row_number = 1) AS latest_daily_date')
                ->selectRaw('MAX(close) FILTER (WHERE row_number = 2) AS previous_daily_close');

            $indices = DB::table('market_indices as market_index')
                ->join('instruments as instrument', function ($join): void {
                    $join->on('instrument.symbol', '=', 'market_index.symbol')
                        ->where('instrument.type', '=', 'index');
                })
                ->joinSub($latestMoves, 'latest_move', fn ($join) =>
                    $join->on('latest_move.instrument_id', '=', 'instrument.id'))
                ->joinSub($dailyCloses, 'daily_close', fn ($join) =>
                    $join->on('daily_close.instrument_id', '=', 'instrument.id'))
                ->where('market_index.is_active', true)
                ->where('instrument.is_active', true)
                ->whereNull('instrument.deleted_at')
                ->whereNotNull('market_index.country')
                ->where('market_index.country', '<>', '')
                ->whereNotNull('latest_move.latest_close')
                ->whereNotNull('daily_close.latest_daily_close')
                ->orderBy('market_index.global_rank')
                ->get([
                    'market_index.country',
                    'market_index.name',
                    'market_index.symbol',
                    'market_index.global_rank',
                    'instrument.currency',
                    'latest_move.latest_close',
                    'latest_move.market_date',
                    'latest_move.observed_at',
                    'latest_move.latest_interval',
                    'daily_close.latest_daily_close',
                    'daily_close.latest_daily_date',
                    'daily_close.previous_daily_close',
                ]);

            return $indices
                ->groupBy(fn ($index): string => strtoupper((string) $index->country))
                ->reject(fn ($countryIndices, string $country): bool => $country === 'EU')
                ->map(function ($countryIndices): array {
                    $representative = $countryIndices->first();
                    $changes = $countryIndices->map(function ($index): ?float {
                        $latestDate = \Carbon\Carbon::parse($index->market_date)->toDateString();
                        $dailyDate = \Carbon\Carbon::parse($index->latest_daily_date)->toDateString();
                        $previousClose = $latestDate > $dailyDate
                            ? (float) $index->latest_daily_close
                            : (float) ($index->previous_daily_close ?? 0);

                        return $previousClose > 0
                            ? (((float) $index->latest_close - $previousClose) / $previousClose) * 100
                            : null;
                    })->filter(fn ($change) => $change !== null);

                    return [
                        'change' => $changes->isNotEmpty() ? round((float) $changes->avg(), 2) : null,
                        'indices' => $countryIndices->count(),
                        'index_name' => (string) $representative->name,
                        'index_symbol' => (string) $representative->symbol,
                        'price' => round((float) $representative->latest_close, 2),
                        'currency' => (string) ($representative->currency ?? ''),
                        'market_date' => $representative->market_date
                            ? \Carbon\Carbon::parse($representative->market_date)->format('d.m.Y')
                            : null,
                        'latest_at' => $representative->observed_at
                            ? \Carbon\Carbon::parse($countryIndices->max('observed_at'))->timezone(config('app.timezone'))->format('d.m.Y, H:i')
                            : null,
                    ];
                })
                ->all();
        });
    }

    public function dailyAverages(int $days = 14): array
    {
        return Cache::remember("dashboard_daily_ai_averages_{$days}", now()->addMinutes(2), fn (): array => DB::table('predictions')
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
            ->all());
    }

    public function scores(): array
    {
        return Cache::remember('dashboard_index_ai_scores', now()->addMinutes(2), function (): array {
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
        });
    }
}
