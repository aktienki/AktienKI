<?php

namespace App\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class ServingDashboardService
{
    /**
     * Latest eligible prediction for every active instrument/horizon.
     *
     * The scope view is deliberately part of the join: an inactive model,
     * an unselected challenger or a failed performance gate must never leak
     * back into the website through an older prediction row.
     */
    public function latestStocks(): Collection
    {
        return Cache::remember('dashboard.serving.latest-stocks.v3', now()->addMinute(), function (): Collection {
            $connection = DB::connection('serving');
            $ranked = $connection->table('serving_predictions as prediction')
                ->join('serving_prediction_scopes as scope', function ($join): void {
                    $join->on('scope.instrument_id', '=', 'prediction.instrument_id')
                        ->on('scope.release_id', '=', 'prediction.release_id')
                        ->on('scope.horizon', '=', 'prediction.horizon')
                        ->on('scope.variant', '=', 'prediction.variant');
                })
                ->join('serving_instruments as instrument', 'instrument.id', '=', 'prediction.instrument_id')
                ->where('instrument.is_active', true)
                ->where('scope.selected_for_prediction', true)
                ->where('scope.prediction_enabled', true)
                ->select([
                    'prediction.id', 'prediction.instrument_id', 'prediction.release_id',
                    'prediction.as_of', 'prediction.horizon', 'prediction.expected_return',
                    'prediction.target_price', 'prediction.calibrated_score',
                    'prediction.risk_score', 'prediction.signal', 'prediction.confidence',
                    'prediction.compact_context', 'instrument.symbol', 'instrument.name',
                    'instrument.country_code', 'instrument.currency', 'instrument.risk_status',
                    'instrument.risk_max_drawdown',
                ])
                ->selectRaw('ROW_NUMBER() OVER (PARTITION BY prediction.instrument_id, prediction.horizon ORDER BY prediction.as_of DESC, prediction.id DESC) AS scope_rank');

            $rows = $connection->query()
                ->fromSub($ranked, 'ranked_prediction')
                ->where('scope_rank', 1)
                ->get();

            return $rows
                ->groupBy('instrument_id')
                ->map(fn (Collection $instrumentRows): object => $this->stock($instrumentRows))
                ->sortByDesc(fn (object $stock): array => [
                    $stock->ai_score ?? -1,
                    $stock->market_return_20d ?? -INF,
                ])
                ->values();
        });
    }

    public function signalChanges(): array
    {
        return Cache::remember('dashboard.serving.signal-changes.v2', now()->addMinute(), function (): array {
            $rows = DB::connection('serving')
                ->table('serving_predictions as prediction')
                ->join('serving_prediction_scopes as scope', function ($join): void {
                    $join->on('scope.instrument_id', '=', 'prediction.instrument_id')
                        ->on('scope.release_id', '=', 'prediction.release_id')
                        ->on('scope.horizon', '=', 'prediction.horizon')
                        ->on('scope.variant', '=', 'prediction.variant');
                })
                ->join('serving_instruments as instrument', 'instrument.id', '=', 'prediction.instrument_id')
                ->where('instrument.is_active', true)
                ->where('scope.selected_for_prediction', true)
                ->where('scope.prediction_enabled', true)
                ->where('prediction.as_of', '>=', now()->subDays(45))
                ->orderBy('prediction.instrument_id')
                ->orderBy('prediction.as_of')
                ->orderBy('prediction.horizon')
                ->get([
                    'prediction.instrument_id', 'prediction.as_of', 'prediction.horizon',
                    'prediction.expected_return', 'prediction.calibrated_score',
                    'prediction.risk_score', 'prediction.signal', 'prediction.confidence', 'instrument.symbol',
                    'instrument.name', 'instrument.country_code',
                ]);

            return $rows->groupBy('instrument_id')->flatMap(function (Collection $instrumentRows): Collection {
                $snapshots = $instrumentRows
                    ->groupBy(fn (object $row): string => (string) $row->as_of)
                    ->map(function (Collection $scopeRows): array {
                        $primary = $scopeRows->firstWhere('horizon', 20) ?: $scopeRows->first();

                        return [
                            'at' => $primary->as_of,
                            'signal' => strtoupper((string) $primary->signal),
                            'score' => $this->scoreToTen($primary->calibrated_score, $primary->confidence ?? null),
                            'risk' => is_numeric($primary->risk_score) ? (float) $primary->risk_score : null,
                            'symbol' => $primary->symbol,
                            'name' => $primary->name,
                            'country' => $primary->country_code,
                            'horizons' => $scopeRows->mapWithKeys(fn (object $row): array => [
                                (int) $row->horizon => $this->returnPercent($row->expected_return),
                            ])->all(),
                        ];
                    })
                    ->sortBy('at')
                    ->values();

                return $snapshots->map(function (array $snapshot, int $index) use ($snapshots): ?array {
                    $previous = $snapshots->get($index - 1);
                    if (! $previous || $previous['signal'] === $snapshot['signal']) {
                        return null;
                    }

                    return [
                        ...$snapshot,
                        'prediction_id' => null,
                        'from' => $previous['signal'],
                        'to' => $snapshot['signal'],
                    ];
                })->filter();
            })
                ->sortByDesc('at')
                ->unique('symbol')
                ->values()
                ->all();
        });
    }

    public function opportunities(int $limit = 5): Collection
    {
        return $this->latestStocks()
            ->filter(fn (object $stock): bool =>
                is_numeric(data_get($stock, 'horizons.20.return'))
                && (float) data_get($stock, 'horizons.20.return') > 0.0
            )
            ->sortByDesc(fn (object $stock): float => (float) data_get($stock, 'horizons.20.return', -INF))
            ->take($limit)
            ->map(function (object $stock): object {
                return (object) [
                    'prediction_id' => null,
                    'status' => 'open',
                    'snapshot' => [
                        'returns' => collect($stock->horizons)->mapWithKeys(
                            fn (array $scope, int|string $horizon): array => [(int) $horizon => $scope['return']]
                        )->all(),
                        'score' => $stock->ai_score,
                        'confidence' => $stock->confidence,
                        'risk' => $stock->risk_score,
                    ],
                    'instrument' => (object) [
                        'symbol' => $stock->symbol,
                        'name' => $stock->name,
                        'country' => $stock->country,
                        'sector' => null,
                        'currency' => $stock->currency,
                    ],
                ];
            })
            ->values();
    }

    private function stock(Collection $rows): object
    {
        $rows = $rows->sortBy('horizon')->values();
        $primary = $rows->firstWhere('horizon', 20) ?: $rows->first();
        $horizons = $rows->mapWithKeys(function (object $row): array {
            return [(int) $row->horizon => [
                'return' => $this->returnPercent($row->expected_return),
                'target_price' => is_numeric($row->target_price) ? (float) $row->target_price : null,
                'signal' => strtoupper((string) $row->signal),
                'as_of' => $row->as_of,
            ]];
        });
        $primaryContext = $this->context($primary->compact_context ?? null);
        $primaryReturn = is_numeric($primary->expected_return) ? (float) $primary->expected_return : null;
        $currentPrice = data_get($primaryContext, 'current_price');
        if (! is_numeric($currentPrice) && is_numeric($primary->target_price) && $primaryReturn !== null && 1.0 + $primaryReturn !== 0.0) {
            $currentPrice = (float) $primary->target_price / (1.0 + $primaryReturn);
        }
        $score = $this->scoreToTen($primary->calibrated_score, $primary->confidence);
        $confidencePercent = $this->confidencePercent($primary->confidence);

        return (object) [
            'instrument_id' => (int) $primary->instrument_id,
            'serving_prediction_id' => (int) $primary->id,
            'prediction_id' => null,
            'symbol' => $primary->symbol,
            'name' => $primary->name,
            'country' => $primary->country_code,
            'currency' => $primary->currency,
            'risk_status' => $primary->risk_status,
            'risk_max_drawdown' => is_numeric($primary->risk_max_drawdown) ? (float) $primary->risk_max_drawdown : null,
            'signal' => strtoupper((string) $primary->signal),
            'personalized_signal' => strtoupper((string) $primary->signal),
            'ai_score' => $score,
            'prediction_score' => $score,
            'confidence' => $confidencePercent,
            'risk_score' => is_numeric($primary->risk_score) ? (float) $primary->risk_score : null,
            'drawdown_risk_factor' => null,
            'current_price' => is_numeric($currentPrice) ? (float) $currentPrice : null,
            'predicted_price_5d' => null,
            'predicted_price_10d' => data_get($horizons, '10.target_price'),
            'predicted_price_15d' => null,
            'predicted_price_20d' => data_get($horizons, '20.target_price'),
            'predicted_price_40d' => data_get($horizons, '40.target_price'),
            'horizon_fusion_consensus_return' => $this->returnPercent($primary->expected_return),
            'market_return_20d' => data_get($horizons, '20.return'),
            'horizons' => $horizons->all(),
            'as_of' => $primary->as_of,
            'display_price' => is_numeric($currentPrice) ? (float) $currentPrice : null,
            'display_price_time' => $primary->as_of,
            'display_price_live' => false,
            'daily_change_percent' => null,
        ];
    }

    private function returnPercent(mixed $value): ?float
    {
        return is_numeric($value) ? (float) $value * 100.0 : null;
    }

    private function context(mixed $value): array
    {
        if (is_array($value)) {
            return $value;
        }
        if (is_string($value) && $value !== '') {
            return (array) (json_decode($value, true) ?: []);
        }

        return [];
    }

    private function scoreToTen(mixed $score, mixed $confidence): ?float
    {
        if (is_numeric($score)) {
            $numeric = (float) $score;
            return max(0.0, min(10.0, $numeric <= 1.0 ? $numeric * 10.0 : $numeric));
        }
        $grade = strtoupper(trim((string) $score));
        if (preg_match('/^([1-5])([+-])?$/', $grade, $matches)) {
            $base = (6 - (int) $matches[1]) * 2.0 - 1.0;
            $adjustment = ($matches[2] ?? '') === '+' ? 0.75 : (($matches[2] ?? '') === '-' ? -0.75 : 0.0);
            return max(0.0, min(10.0, $base + $adjustment));
        }
        if (is_numeric($confidence)) {
            return $this->confidencePercent($confidence) / 10.0;
        }

        return null;
    }

    private function confidencePercent(mixed $confidence): ?float
    {
        if (! is_numeric($confidence)) {
            return null;
        }

        $numeric = (float) $confidence;
        if ($numeric >= 0.0 && $numeric <= 1.0) {
            $numeric *= 100.0;
        }

        return max(0.0, min(100.0, $numeric));
    }
}
