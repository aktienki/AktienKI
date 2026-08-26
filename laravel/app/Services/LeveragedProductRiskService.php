<?php

namespace App\Services;

use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

final class LeveragedProductRiskService
{
    public const VERSION = 'leveraged-product-risk-pit-v2';

    /** @var list<int> */
    public const BARRIER_DISTANCES_BPS = [500, 1000, 1500, 2000];

    public const MINIMUM_HISTORY = 60;

    public const MINIMUM_MATRIX_SAMPLE = 20;

    /**
     * Build auditable 20-trading-day loss and barrier-risk observations.
     * The score percentile and volatility class only use earlier observations.
     *
     * @return array{observations:int,matrix_cells:int,first_date:?string,last_date:?string}
     */
    public function calculate(int $instrumentId, int $horizonDays = 20): array
    {
        $forecasts = DB::table('walk_forward_horizon_forecasts')
            ->where('instrument_id', $instrumentId)
            ->where('horizon_days', $horizonDays)
            ->orderBy('signal_date')
            ->get(['signal_date', 'predicted_return']);

        if ($forecasts->isEmpty()) {
            throw new RuntimeException("No {$horizonDays}T walk-forward forecasts for instrument {$instrumentId}.");
        }

        $bars = DB::table('price_bars')
            ->where('instrument_id', $instrumentId)
            ->where('interval', '1d')
            ->orderBy('bar_time')
            ->get(['bar_time', 'high', 'low', 'close'])
            ->map(fn ($bar) => [
                'date' => CarbonImmutable::parse($bar->bar_time)->toDateString(),
                'high' => (float) $bar->high,
                'low' => (float) $bar->low,
                'close' => (float) $bar->close,
            ])->values();

        $barIndex = $bars->mapWithKeys(fn (array $bar, int $index) => [$bar['date'] => $index]);
        $volatility = DB::table('technical_indicators')
            ->where('instrument_id', $instrumentId)
            ->where('interval', '1d')
            ->whereNotNull('volatility_20')
            ->get(['bar_time', 'volatility_20'])
            ->mapWithKeys(fn ($row) => [CarbonImmutable::parse($row->bar_time)->toDateString() => (float) $row->volatility_20]);

        $priorForecasts = [];
        $priorVolatility = [];
        $rows = [];

        foreach ($forecasts as $forecast) {
            $signalDate = CarbonImmutable::parse($forecast->signal_date)->toDateString();
            $prediction = (float) $forecast->predicted_return;
            $index = $barIndex->get($signalDate);
            $vol = $volatility->get($signalDate);

            if ($index === null || count($priorForecasts) < self::MINIMUM_HISTORY) {
                $priorForecasts[] = $prediction;
                if (is_numeric($vol)) {
                    $priorVolatility[] = (float) $vol;
                }
                continue;
            }

            $future = $bars->slice($index + 1, $horizonDays)->values();
            $entry = (float) $bars[$index]['close'];
            if ($entry <= 0 || $future->count() !== $horizonDays) {
                $priorForecasts[] = $prediction;
                if (is_numeric($vol)) {
                    $priorVolatility[] = (float) $vol;
                }
                continue;
            }

            $score = $this->percentileRank($prediction, $priorForecasts);
            $scoreBucket = min(5, max(1, (int) floor(min($score, 99.9999) / 20) + 1));
            $volatilityBucket = is_numeric($vol) && count($priorVolatility) >= self::MINIMUM_HISTORY
                ? $this->volatilityBucket((float) $vol, $priorVolatility)
                : null;
            $lastClose = (float) $future->last()['close'];
            $minimumLow = (float) $future->min('low');
            $maximumHigh = (float) $future->max('high');

            foreach (['long', 'short'] as $side) {
                $realizedReturn = $this->positionReturn($side, $entry, $lastClose);
                $adverse = $side === 'long'
                    ? min(0.0, ($minimumLow / $entry) - 1)
                    : min(0.0, 1 - ($maximumHigh / $entry));
                $favorable = $side === 'long'
                    ? max(0.0, ($maximumHigh / $entry) - 1)
                    : max(0.0, 1 - ($minimumLow / $entry));
                $breaches = collect(self::BARRIER_DISTANCES_BPS)->mapWithKeys(
                    fn (int $bps) => [(string) $bps => $adverse <= -($bps / 10000)]
                )->all();

                $rows[] = [
                    'instrument_id' => $instrumentId,
                    'signal_date' => $signalDate,
                    'horizon_days' => $horizonDays,
                    'position_side' => $side,
                    'predicted_return' => $prediction,
                    'point_in_time_score' => $score,
                    'score_bucket' => $scoreBucket,
                    'volatility' => $vol,
                    'volatility_bucket' => $volatilityBucket,
                    'realized_return' => $realizedReturn,
                    'maximum_adverse_excursion' => $adverse,
                    'maximum_favorable_excursion' => $favorable,
                    'barrier_breaches' => json_encode($breaches, JSON_THROW_ON_ERROR),
                    'calculation_version' => self::VERSION,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }

            $priorForecasts[] = $prediction;
            if (is_numeric($vol)) {
                $priorVolatility[] = (float) $vol;
            }
        }

        DB::transaction(function () use ($instrumentId, $horizonDays, $rows): void {
            DB::table('leveraged_product_risk_observations')
                ->where('instrument_id', $instrumentId)
                ->where('horizon_days', $horizonDays)
                ->delete();
            foreach (array_chunk($rows, 500) as $chunk) {
                DB::table('leveraged_product_risk_observations')->insert($chunk);
            }
            $this->rebuildMatrix($instrumentId, $horizonDays, collect($rows));
        });

        return [
            'observations' => count($rows),
            'matrix_cells' => DB::table('leveraged_product_risk_matrices')
                ->where('instrument_id', $instrumentId)->where('horizon_days', $horizonDays)->count(),
            'first_date' => collect($rows)->min('signal_date'),
            'last_date' => collect($rows)->max('signal_date'),
        ];
    }

    /** @return array<string,mixed>|null */
    public function currentProfile(int $instrumentId, int $horizonDays = 20): ?array
    {
        if (! Schema::hasTable('leveraged_product_risk_matrices')) {
            return null;
        }

        $prediction = DB::table('predictions')->where('instrument_id', $instrumentId)
            ->orderByDesc('prediction_time')->orderByDesc('id')
            ->first(['prediction_time', 'current_price', 'predicted_price_20d', 'market_return_20d', 'horizon_fusion_consensus_return']);
        if (! $prediction) {
            return null;
        }

        $predictedReturn = is_numeric($prediction->market_return_20d)
            ? (float) $prediction->market_return_20d
            : (is_numeric($prediction->horizon_fusion_consensus_return)
                ? (float) $prediction->horizon_fusion_consensus_return
                : (is_numeric($prediction->predicted_price_20d) && is_numeric($prediction->current_price) && (float) $prediction->current_price > 0
                    ? ((float) $prediction->predicted_price_20d / (float) $prediction->current_price) - 1
                    : null));
        if ($predictedReturn === null) {
            return null;
        }

        $forecastHistory = DB::table('walk_forward_horizon_forecasts')
            ->where('instrument_id', $instrumentId)->where('horizon_days', $horizonDays)
            ->pluck('predicted_return')->map(fn ($value) => (float) $value)->all();
        if (count($forecastHistory) < self::MINIMUM_HISTORY) {
            return null;
        }

        $latestIndicator = DB::table('technical_indicators')->where('instrument_id', $instrumentId)
            ->where('interval', '1d')->whereNotNull('volatility_20')->orderByDesc('bar_time')
            ->first(['bar_time', 'volatility_20']);
        $volatilityHistory = DB::table('technical_indicators')->where('instrument_id', $instrumentId)
            ->where('interval', '1d')->whereNotNull('volatility_20')
            ->pluck('volatility_20')->map(fn ($value) => (float) $value)->all();

        $score = $this->percentileRank($predictedReturn, $forecastHistory);
        $scoreBucket = min(5, max(1, (int) floor(min($score, 99.9999) / 20) + 1));
        $volatility = is_numeric($latestIndicator?->volatility_20) ? (float) $latestIndicator->volatility_20 : null;
        $volatilityBucket = $volatility !== null && count($volatilityHistory) >= self::MINIMUM_HISTORY
            ? $this->volatilityBucket($volatility, $volatilityHistory)
            : null;
        $cells = DB::table('leveraged_product_risk_matrices')
            ->where('instrument_id', $instrumentId)->where('horizon_days', $horizonDays)
            ->where('score_bucket', $scoreBucket)->where('volatility_bucket', $volatilityBucket)
            ->orderBy('position_side')->orderBy('barrier_distance_bps')
            ->get()->map(fn ($row) => [
                'side' => $row->position_side,
                'barrier_distance_percent' => (float) $row->barrier_distance_bps / 100,
                'sample_size' => (int) $row->sample_size,
                'loss_probability_percent' => round((float) $row->loss_probability * 100, 1),
                'barrier_breach_probability_percent' => round((float) $row->barrier_breach_probability * 100, 1),
                'gain_probability_percent' => round((float) $row->gain_probability * 100, 1),
                'target_hit_probability_percent' => round((float) $row->target_hit_probability * 100, 1),
                'average_return_percent' => round((float) $row->average_return * 100, 1),
                'expected_shortfall_10_percent' => round((float) $row->expected_shortfall_10 * 100, 1),
                'expected_upside_10_percent' => round((float) $row->expected_upside_10 * 100, 1),
                'average_favorable_excursion_percent' => round((float) $row->average_favorable_excursion * 100, 1),
                'sample_size_sufficient' => (bool) $row->sample_size_sufficient,
            ])->values();

        return [
            'horizon_days' => $horizonDays,
            'prediction_time' => $prediction->prediction_time,
            'predicted_return_percent' => round($predictedReturn * 100, 1),
            'direction' => $predictedReturn > 0 ? 'long' : ($predictedReturn < 0 ? 'short' : 'neutral'),
            'point_in_time_score' => round($score, 1),
            'score_bucket' => $scoreBucket,
            'volatility_percent' => $volatility !== null ? round($volatility * 100, 1) : null,
            'volatility_bucket' => $volatilityBucket,
            'cells' => $cells,
            'calculation_version' => self::VERSION,
        ];
    }

    /** @param list<float|int> $history */
    public function percentileRank(float $value, array $history): float
    {
        if ($history === []) {
            return 50.0;
        }
        $below = count(array_filter($history, fn ($item) => (float) $item < $value));
        $equal = count(array_filter($history, fn ($item) => (float) $item === $value));

        return 100 * ($below + 0.5 * $equal) / count($history);
    }

    public function positionReturn(string $side, float $entry, float $exit): float
    {
        if ($entry <= 0) {
            throw new RuntimeException('Entry price must be positive.');
        }

        return $side === 'short' ? 1 - ($exit / $entry) : ($exit / $entry) - 1;
    }

    /**
     * Historical-bootstrap Monte Carlo. Daily log returns are sampled from the
     * supplied history; no normal-distribution assumption is made.
     *
     * @param list<float|int> $closingPrices
     * @return array<string,mixed>
     */
    public function simulateHistoricalPaths(
        array $closingPrices,
        int $horizonDays = 20,
        int $simulations = 10000,
        int $seed = 20260825,
        ?float $forecastTargetDistancePercent = null,
    ): array {
        $prices = array_values(array_filter(array_map('floatval', $closingPrices), fn (float $price) => $price > 0));
        $returns = [];
        for ($index = 1, $count = count($prices); $index < $count; $index++) {
            $returns[] = log($prices[$index] / $prices[$index - 1]);
        }
        if (count($returns) < 60 || $horizonDays < 1 || $simulations < 1) {
            throw new RuntimeException('Monte Carlo requires at least 60 historical returns and one simulation.');
        }

        $state = $seed & 0x7fffffff;
        $randomIndex = static function (int $maximum) use (&$state): int {
            $state = (int) (($state * 1103515245 + 12345) & 0x7fffffff);

            return $state % $maximum;
        };
        $results = ['long' => [], 'short' => []];
        $pathReturnsByDay = array_fill(0, $horizonDays, []);
        $barriers = array_map(fn (int $bps): float => $bps / 10000, self::BARRIER_DISTANCES_BPS);
        $breaches = ['long' => array_fill_keys(self::BARRIER_DISTANCES_BPS, 0), 'short' => array_fill_keys(self::BARRIER_DISTANCES_BPS, 0)];
        $forecastTargetHits = ['long' => 0, 'short' => 0];
        $matrixLeverages = [2, 5, 10, 15, 20];
        $matrixLossThresholds = range(10, 100, 10);
        $leveragedLossCounts = [];
        foreach (['long', 'short'] as $side) {
            foreach ($matrixLeverages as $leverage) {
                $leveragedLossCounts[$side][$leverage] = array_fill_keys($matrixLossThresholds, 0);
            }
        }
        $productReturns = ['long' => [], 'short' => []];
        foreach (['long', 'short'] as $side) {
            foreach (self::BARRIER_DISTANCES_BPS as $bps) {
                $productReturns[$side][$bps] = [];
            }
        }

        for ($simulation = 0; $simulation < $simulations; $simulation++) {
            $path = 1.0;
            $minimum = 1.0;
            $maximum = 1.0;
            for ($day = 0; $day < $horizonDays; $day++) {
                $path *= exp($returns[$randomIndex(count($returns))]);
                $minimum = min($minimum, $path);
                $maximum = max($maximum, $path);
                $pathReturnsByDay[$day][] = $path - 1;
            }
            foreach (['long', 'short'] as $side) {
                $positionReturn = $side === 'long' ? $path - 1 : 1 - $path;
                $results[$side][] = $positionReturn;
                if ($forecastTargetDistancePercent !== null && $forecastTargetDistancePercent > 0) {
                    $targetDistance = $forecastTargetDistancePercent / 100;
                    $targetHit = $side === 'long' ? $maximum >= 1 + $targetDistance : $minimum <= 1 - $targetDistance;
                    if ($targetHit) {
                        $forecastTargetHits[$side]++;
                    }
                }
                foreach ($matrixLeverages as $leverage) {
                    $impliedBarrierDistance = 1 / $leverage;
                    $knockedOut = $side === 'long'
                        ? $minimum <= 1 - $impliedBarrierDistance
                        : $maximum >= 1 + $impliedBarrierDistance;
                    $leveragedReturn = $knockedOut ? -1.0 : max(-1.0, $positionReturn * $leverage);
                    foreach ($matrixLossThresholds as $lossThreshold) {
                        if ($leveragedReturn <= -($lossThreshold / 100)) {
                            $leveragedLossCounts[$side][$leverage][$lossThreshold]++;
                        }
                    }
                }
                foreach (self::BARRIER_DISTANCES_BPS as $barrierIndex => $bps) {
                    $distance = $barriers[$barrierIndex];
                    $breached = $side === 'long' ? $minimum <= 1 - $distance : $maximum >= 1 + $distance;
                    if ($breached) {
                        $breaches[$side][$bps]++;
                    }
                    $leverage = 1 / $distance;
                    $productReturns[$side][$bps][] = $breached ? -1.0 : max(-1.0, $positionReturn * $leverage);
                }
            }
        }

        $percentile = static function (array $values, float $probability): float {
            sort($values, SORT_NUMERIC);
            $position = ($probability / 100) * (count($values) - 1);
            $lower = (int) floor($position);
            $upper = (int) ceil($position);
            $weight = $position - $lower;

            return $values[$lower] * (1 - $weight) + $values[$upper] * $weight;
        };
        $output = [];
        foreach (['long', 'short'] as $side) {
            $sorted = $results[$side];
            sort($sorted, SORT_NUMERIC);
            $tailCount = max(1, (int) ceil(count($sorted) * .10));
            $cells = [];
            foreach (self::BARRIER_DISTANCES_BPS as $bps) {
                $product = $productReturns[$side][$bps];
                $cells[] = [
                    'barrier_distance_percent' => $bps / 100,
                    'indicative_leverage' => 10000 / $bps,
                    'barrier_breach_probability_percent' => 100 * $breaches[$side][$bps] / $simulations,
                    'profit_probability_percent' => 100 * count(array_filter($product, fn (float $value) => $value > 0)) / $simulations,
                    'median_product_return_percent' => 100 * $percentile($product, 50),
                ];
            }
            $output[$side] = [
                'loss_probability_percent' => 100 * count(array_filter($results[$side], fn (float $value) => $value < 0)) / $simulations,
                'p10_return_percent' => 100 * $percentile($results[$side], 10),
                'median_return_percent' => 100 * $percentile($results[$side], 50),
                'p90_return_percent' => 100 * $percentile($results[$side], 90),
                'expected_shortfall_10_percent' => 100 * (array_sum(array_slice($sorted, 0, $tailCount)) / $tailCount),
                'forecast_target_probability_percent' => $forecastTargetDistancePercent !== null && $forecastTargetDistancePercent > 0
                    ? 100 * $forecastTargetHits[$side] / $simulations
                    : null,
                'cells' => $cells,
            ];
        }

        $quantilePath = [];
        foreach ($pathReturnsByDay as $day => $dayReturns) {
            $quantilePath[] = [
                'day' => $day + 1,
                'p10_percent' => 100 * $percentile($dayReturns, 10),
                'p25_percent' => 100 * $percentile($dayReturns, 25),
                'median_percent' => 100 * $percentile($dayReturns, 50),
                'p75_percent' => 100 * $percentile($dayReturns, 75),
                'p90_percent' => 100 * $percentile($dayReturns, 90),
            ];
        }
        $lossProbabilityMatrix = [];
        foreach (['long', 'short'] as $side) {
            $lossProbabilityMatrix[$side] = collect($matrixLeverages)->map(fn (int $leverage): array => [
                'leverage' => $leverage,
                'implied_barrier_distance_percent' => 100 / $leverage,
                'probabilities' => collect($matrixLossThresholds)->mapWithKeys(
                    fn (int $threshold): array => [(string) $threshold => 100 * $leveragedLossCounts[$side][$leverage][$threshold] / $simulations]
                )->all(),
            ])->all();
        }

        return [
            'method' => 'historical_bootstrap_daily_close_v1',
            'horizon_days' => $horizonDays,
            'simulations' => $simulations,
            'history_returns' => count($returns),
            'seed' => $seed,
            'forecast_target_distance_percent' => $forecastTargetDistancePercent,
            'quantile_path' => $quantilePath,
            'loss_thresholds_percent' => $matrixLossThresholds,
            'loss_probability_matrix' => $lossProbabilityMatrix,
            'sides' => $output,
        ];
    }

    /** @param list<float|int> $history */
    private function volatilityBucket(float $value, array $history): string
    {
        $percentile = $this->percentileRank($value, $history);

        return $percentile < 33.3333 ? 'low' : ($percentile < 66.6667 ? 'medium' : 'high');
    }

    private function rebuildMatrix(int $instrumentId, int $horizonDays, Collection $rows): void
    {
        DB::table('leveraged_product_risk_matrices')
            ->where('instrument_id', $instrumentId)
            ->where('horizon_days', $horizonDays)
            ->delete();

        $matrix = [];
        foreach ($rows->groupBy(fn (array $row) => implode('|', [$row['position_side'], $row['score_bucket'], $row['volatility_bucket'] ?? 'unknown'])) as $group) {
            foreach (self::BARRIER_DISTANCES_BPS as $bps) {
                $returns = $group->pluck('realized_return')->map(fn ($value) => (float) $value)->sort()->values();
                $adverse = $group->pluck('maximum_adverse_excursion')->map(fn ($value) => (float) $value);
                $favorable = $group->pluck('maximum_favorable_excursion')->map(fn ($value) => (float) $value);
                $tailCount = max(1, (int) ceil($returns->count() * 0.10));
                $matrix[] = [
                    'instrument_id' => $instrumentId,
                    'horizon_days' => $horizonDays,
                    'position_side' => $group->first()['position_side'],
                    'score_bucket' => $group->first()['score_bucket'],
                    'volatility_bucket' => $group->first()['volatility_bucket'],
                    'barrier_distance_bps' => $bps,
                    'sample_size' => $group->count(),
                    'loss_count' => $returns->filter(fn (float $value) => $value < 0)->count(),
                    'barrier_breach_count' => $adverse->filter(fn (float $value) => $value <= -($bps / 10000))->count(),
                    'gain_count' => $returns->filter(fn (float $value) => $value > 0)->count(),
                    'target_hit_count' => $favorable->filter(fn (float $value) => $value >= ($bps / 10000))->count(),
                    'loss_probability' => $returns->filter(fn (float $value) => $value < 0)->count() / $returns->count(),
                    'barrier_breach_probability' => $adverse->filter(fn (float $value) => $value <= -($bps / 10000))->count() / $adverse->count(),
                    'gain_probability' => $returns->filter(fn (float $value) => $value > 0)->count() / $returns->count(),
                    'target_hit_probability' => $favorable->filter(fn (float $value) => $value >= ($bps / 10000))->count() / $favorable->count(),
                    'average_return' => $returns->avg(),
                    'expected_shortfall_10' => $returns->take($tailCount)->avg(),
                    'expected_upside_10' => $returns->reverse()->take($tailCount)->avg(),
                    'average_adverse_excursion' => $adverse->avg(),
                    'average_favorable_excursion' => $favorable->avg(),
                    'sample_size_sufficient' => $group->count() >= self::MINIMUM_MATRIX_SAMPLE,
                    'calculation_version' => self::VERSION,
                    'calculated_at' => now(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }
        }

        foreach (array_chunk($matrix, 500) as $chunk) {
            DB::table('leveraged_product_risk_matrices')->insert($chunk);
        }
    }
}
