<?php

namespace App\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

final class ServingModelOverviewService
{
    private const VARIANTS = ['standard', 'pure_tcn'];

    /** @return array<string, mixed> */
    public function data(string $symbol): array
    {
        $normalizedSymbol = strtoupper(trim($symbol));
        $connection = DB::connection('serving');

        $instrument = $connection->table('serving_instruments')
            ->whereRaw('UPPER(symbol) = ?', [$normalizedSymbol])
            ->where('instrument_type', 'stock')
            ->where('is_active', true)
            ->where('is_tradeable', true)
            ->first([
                'id', 'symbol', 'name', 'exchange', 'currency', 'country_code',
                'sector_code', 'home_index_symbol', 'display_metadata', 'risk_status',
            ]);
        abort_unless($instrument, 404);

        $release = $connection->table('serving_active_models as active')
            ->join('serving_releases as release', 'release.id', '=', 'active.release_id')
            ->where('active.instrument_id', $instrument->id)
            ->first([
                'release.id', 'release.pipeline_version', 'release.source_commit',
                'release.dataset_cutoff', 'release.quality_class', 'release.evidence_level',
                'release.release_reason', 'release.score_calibration', 'release.filter_summary',
                'release.compact_metrics', 'release.artifact_manifest', 'release.tracking_comment',
                'release.created_at', 'release.recommended_signal', 'release.buy_horizons',
                'release.strong_buy_horizons', 'active.activated_at', 'active.updated_at',
            ]);
        abort_unless($release, 404);

        $compact = $this->json($release->compact_metrics);
        $calibration = $this->json($release->score_calibration);
        $filterSummary = $this->json($release->filter_summary);
        $artifactManifest = $this->json($release->artifact_manifest);
        $currentSignal = $connection->table('serving_current_stock_signals')
            ->where('instrument_id', $instrument->id)
            ->first();
        $predictions = $connection->table('serving_predictions')
            ->where('instrument_id', $instrument->id)
            ->where('release_id', $release->id)
            ->orderByDesc('as_of')
            ->orderByDesc('id')
            ->get()
            ->unique(fn (object $prediction): string => (int) $prediction->horizon.'|'.(string) $prediction->variant)
            ->values();

        $horizonPayloads = collect($compact['horizons'] ?? [])
            ->filter(fn ($payload, $horizon): bool => is_numeric($horizon) && is_array($payload))
            ->mapWithKeys(fn (array $payload, $horizon): array => [(int) $horizon => $payload])
            ->sortKeys();
        $activeModels = collect($compact['active_models'] ?? []);

        $horizons = $horizonPayloads->map(function (array $payload, int $days) use (
            $activeModels,
            $predictions,
            $calibration,
            $artifactManifest,
        ): array {
            $active = (array) ($activeModels->get((string) $days) ?? $activeModels->get($days) ?? []);
            $activeVariant = (string) ($payload['active_variant'] ?? $active['variant'] ?? 'standard');
            $recommendation = (array) ($payload['recommendation'] ?? []);
            $variants = collect(self::VARIANTS)->mapWithKeys(function (string $variant) use (
                $payload,
                $days,
                $active,
                $activeVariant,
                $predictions,
                $calibration,
                $artifactManifest,
            ): array {
                $variantPayload = (array) ($payload[$variant] ?? []);
                $status = (array) ($variantPayload['prediction_status'] ?? []);
                $qualityGate = (array) ($status['quality_gate'] ?? []);
                $quality = (array) ($status['model_quality'] ?? $payload['quality'] ?? []);
                $entryPolicy = (array) ($variantPayload['entry_policy'] ?? []);
                $metrics = $this->numericMetrics((array) ($variantPayload['metrics'] ?? []));
                $prediction = $predictions->first(fn (object $row): bool => (int) $row->horizon === $days
                    && (string) $row->variant === $variant);
                $artifacts = collect($artifactManifest)
                    ->filter(fn ($artifact): bool => is_array($artifact)
                        && (int) ($artifact['horizon'] ?? 0) === $days
                        && (string) ($artifact['variant'] ?? '') === $variant)
                    ->map(fn (array $artifact, string $name): array => [
                        'name' => $name,
                        'kind' => (string) ($artifact['kind'] ?? 'model'),
                        'format' => strtoupper((string) ($artifact['format'] ?? '')),
                        'size_bytes' => is_numeric($artifact['size_bytes'] ?? null) ? (int) $artifact['size_bytes'] : null,
                        'size' => $this->humanBytes($artifact['size_bytes'] ?? null),
                        'sha256' => (string) ($artifact['sha256'] ?? ''),
                    ])->values()->all();

                return [$variant => [
                    'key' => $variant,
                    'label' => $variant === 'pure_tcn' ? 'Pure TCN' : 'Standard',
                    'model_name' => $variant === 'pure_tcn'
                        ? 'Temporal Convolutional Network'
                        : (string) ($variantPayload['champion'] ?? $active['standard_champion'] ?? 'Standard-Ensemble'),
                    'selected' => $activeVariant === $variant,
                    'prediction_enabled' => (bool) ($status['prediction_enabled'] ?? false),
                    'prediction_status' => (string) ($status['status'] ?? 'not_evaluated'),
                    'evaluation' => (string) ($status['evaluation'] ?? ''),
                    'storage_policy' => (string) ($status['storage_policy'] ?? ''),
                    'quality_class' => (string) ($quality['quality_class'] ?? 'unknown'),
                    'quality_label' => (string) ($quality['display_name'] ?? ucfirst((string) ($quality['quality_class'] ?? 'Unbekannt'))),
                    'quality_gate_passed' => (bool) ($qualityGate['passed'] ?? false),
                    'quality_gate_status' => (string) ($qualityGate['status'] ?? 'not_evaluated'),
                    'gates' => (array) ($status['gates'] ?? []),
                    'failed_gates' => array_values((array) ($status['failed_gates'] ?? [])),
                    'quality_gates' => (array) ($qualityGate['gates'] ?? []),
                    'quality_failed_gates' => array_values((array) ($qualityGate['failed_gates'] ?? [])),
                    'metrics' => $metrics,
                    'entry_policy' => $entryPolicy,
                    'threshold' => $this->threshold($entryPolicy, (array) ($calibration[(string) $days] ?? $calibration[$days] ?? []), $variant),
                    'selected_exit_drop' => $this->number(data_get($calibration, $days.'.selected_exit_drop')),
                    'prediction' => $this->prediction($prediction),
                    'artifacts' => $artifacts,
                ]];
            })->all();

            return [
                'days' => $days,
                'active_variant' => $activeVariant,
                'active_prediction_enabled' => (bool) ($active['prediction_enabled'] ?? false),
                'active_prediction_status' => (string) ($active['prediction_status'] ?? 'not_evaluated'),
                'preferred_variant' => (string) ($recommendation['preferred_variant'] ?? $activeVariant),
                'automatically_activated' => (bool) ($recommendation['automatically_activated'] ?? false),
                'recommendation_gates' => (array) ($recommendation['gates'] ?? []),
                'skip_reason' => array_values((array) ($active['skip_reason'] ?? [])),
                'variants' => $variants,
            ];
        })->values();

        $chart = $this->chart($horizons);
        $initialHorizon = (int) ($horizons->firstWhere('active_prediction_enabled', true)['days']
            ?? $horizons->first()['days']
            ?? 10);
        $tradeChart = $this->tradeChart(
            $connection,
            (int) $instrument->id,
            (string) $release->id,
            $horizons,
            $initialHorizon,
        );
        $predictionEnabledCount = $horizons->filter(fn (array $horizon): bool => $horizon['active_prediction_enabled'])->count();
        $qualityGateCount = $horizons->filter(function (array $horizon): bool {
            $active = $horizon['variants'][$horizon['active_variant']] ?? null;

            return (bool) ($active['quality_gate_passed'] ?? false);
        })->count();

        return [
            'instrument' => $instrument,
            'currentSignal' => $currentSignal,
            'release' => [
                'id' => (string) $release->id,
                'pipeline_version' => (string) $release->pipeline_version,
                'source_commit' => (string) $release->source_commit,
                'dataset_cutoff' => $release->dataset_cutoff,
                'quality_class' => (string) $release->quality_class,
                'evidence_level' => (string) $release->evidence_level,
                'release_reason' => (string) $release->release_reason,
                'tracking_comment' => (string) $release->tracking_comment,
                'created_at' => $release->created_at,
                'activated_at' => $release->activated_at,
                'recommended_signal' => (string) ($release->recommended_signal ?: $currentSignal?->signal ?: 'HOLD'),
            ],
            'predictionPolicy' => (array) ($filterSummary['prediction_policy'] ?? []),
            'horizons' => $horizons,
            'chart' => $chart,
            'tradeChart' => $tradeChart,
            'predictionEnabledCount' => $predictionEnabledCount,
            'qualityGateCount' => $qualityGateCount,
            'initialHorizon' => $initialHorizon,
        ];
    }

    private function prediction(?object $prediction): ?array
    {
        if (! $prediction) {
            return null;
        }

        return [
            'as_of' => $prediction->as_of,
            'signal' => (string) $prediction->signal,
            'score' => (string) $prediction->calibrated_score,
            'expected_return_percent' => $this->number($prediction->expected_return) !== null
                ? (float) $prediction->expected_return * 100 : null,
            'target_price' => $this->number($prediction->target_price),
            'confidence_percent' => $this->number($prediction->confidence) !== null
                ? (float) $prediction->confidence * 100 : null,
            'risk_score' => is_numeric($prediction->risk_score) ? (int) $prediction->risk_score : null,
        ];
    }

    /** @return array<string, float|int|null> */
    private function numericMetrics(array $metrics): array
    {
        return collect([
            'trades', 'hit_rate', 'profit_factor', 'average_net_trade', 'median_net_trade',
            'cumulative_return', 'max_drawdown', 'stddev_net_trade', 'average_holding_days',
        ])->mapWithKeys(fn (string $key): array => [$key => $this->number($metrics[$key] ?? null)])->all();
    }

    private function threshold(array $entryPolicy, array $calibration, string $variant): ?float
    {
        $value = $entryPolicy['threshold'] ?? $entryPolicy['confirmation_threshold'] ?? null;
        if (! is_numeric($value)) {
            $value = $variant === 'pure_tcn'
                ? data_get($calibration, 'pure_tcn_entry.threshold')
                : data_get($calibration, 'standard_tcn_confirmation.threshold', $calibration['threshold'] ?? null);
        }

        return $this->number($value);
    }

    private function chart(Collection $horizons): array
    {
        $rows = $horizons->flatMap(function (array $horizon): array {
            return collect(self::VARIANTS)->map(function (string $variant) use ($horizon): array {
                $model = $horizon['variants'][$variant];
                $return = $model['metrics']['cumulative_return'];

                return [
                    'days' => $horizon['days'],
                    'variant' => $variant,
                    'label' => $model['label'],
                    'selected' => $model['selected'],
                    'return_percent' => $return !== null ? $return * 100 : null,
                    'drawdown_percent' => $model['metrics']['max_drawdown'] !== null
                        ? abs($model['metrics']['max_drawdown'] * 100) : null,
                ];
            })->all();
        })->filter(fn (array $row): bool => $row['return_percent'] !== null)->values();
        $maximum = max(5.0, (float) $rows->max(fn (array $row): float => abs((float) $row['return_percent'])));
        $scale = ceil($maximum / 10) * 10;

        return [
            'scale' => $scale,
            'rows' => $rows->map(fn (array $row): array => [
                ...$row,
                'bar_percent' => min(100, (abs((float) $row['return_percent']) / $scale) * 100),
            ])->all(),
        ];
    }

    /** @return array<string, mixed> */
    private function tradeChart(
        mixed $connection,
        int $instrumentId,
        string $releaseId,
        Collection $horizons,
        int $preferredHorizon,
    ): array {
        return Cache::remember(
            'serving:model-overview:trade-chart:v1:'.$instrumentId.':'.$releaseId,
            now()->addHours(6),
            fn (): array => $this->buildTradeChart(
                $connection,
                $instrumentId,
                $releaseId,
                $horizons,
                $preferredHorizon,
            ),
        );
    }

    /** @return array<string, mixed> */
    private function buildTradeChart(
        mixed $connection,
        int $instrumentId,
        string $releaseId,
        Collection $horizons,
        int $preferredHorizon,
    ): array {
        $rows = $connection->table('serving_strategy_trades as trade')
            ->join('serving_strategy_runs as run', 'run.id', '=', 'trade.strategy_run_id')
            ->where('trade.instrument_id', $instrumentId)
            ->where('run.status', 'complete')
            ->whereIn('trade.horizon', $horizons->pluck('days')->all())
            ->orderBy('trade.entry_date')
            ->orderBy('trade.exit_date')
            ->get([
                'trade.id', 'trade.strategy_run_id', 'trade.horizon', 'trade.strategy',
                'trade.entry_signal', 'trade.entry_date', 'trade.entry_close_eur',
                'trade.exit_date', 'trade.exit_close_eur', 'trade.holding_days',
                'trade.net_return', 'trade.transaction_cost', 'trade.exit_reason',
                'run.calculation_date', 'run.source_metadata',
            ]);

        $candidates = $rows
            ->groupBy(fn (object $row): string => implode('|', [
                (int) $row->horizon,
                (string) $row->strategy,
                (string) $row->strategy_run_id,
            ]))
            ->map(function (Collection $trades) use ($releaseId): ?array {
                $first = $trades->first();
                $variant = $this->strategyVariant((string) $first->strategy);
                if ($variant === null) {
                    return null;
                }
                $metrics = $this->metricsFromTrades($trades);
                $source = $this->json($first->source_metadata);

                return [
                    'days' => (int) $first->horizon,
                    'variant' => $variant,
                    'strategy' => (string) $first->strategy,
                    'calculation_date' => (string) $first->calculation_date,
                    'release_linked' => (string) ($source['release_id'] ?? '') === $releaseId,
                    'metrics' => $metrics,
                    'trades' => $trades->values(),
                ];
            })
            ->filter()
            ->groupBy(fn (array $candidate): string => $candidate['days'].'|'.$candidate['variant']);

        $series = [];
        $availability = [];

        foreach ($horizons as $horizon) {
            $days = (int) $horizon['days'];
            foreach (self::VARIANTS as $variant) {
                $publishedMetrics = (array) ($horizon['variants'][$variant]['metrics'] ?? []);
                $matching = collect($candidates->get($days.'|'.$variant, []))
                    ->filter(fn (array $candidate): bool => $this->tradeMetricsMatch(
                        (array) $candidate['metrics'],
                        $publishedMetrics,
                    ))
                    ->sort(function (array $left, array $right): int {
                        if ($left['release_linked'] !== $right['release_linked']) {
                            return $left['release_linked'] ? -1 : 1;
                        }

                        return strcmp((string) $right['calculation_date'], (string) $left['calculation_date']);
                    });
                $candidate = $matching->first();
                if (! $candidate) {
                    continue;
                }

                $series[$days][$variant] = $this->equitySeries($candidate);
                $availability[$days][] = $variant;
            }
        }

        $initialDays = isset($series[$preferredHorizon])
            ? $preferredHorizon
            : (int) (array_key_first($series) ?? $preferredHorizon);
        $preferredVariant = (string) ($horizons->firstWhere('days', $initialDays)['active_variant'] ?? 'standard');
        $initialVariant = isset($series[$initialDays][$preferredVariant])
            ? $preferredVariant
            : (string) (array_key_first($series[$initialDays] ?? []) ?? 'standard');

        return [
            'series' => $series,
            'availability' => $availability,
            'initial' => ['days' => $initialDays, 'variant' => $initialVariant],
        ];
    }

    private function strategyVariant(string $strategy): ?string
    {
        $normalized = strtolower(str_replace('_', '-', $strategy));
        if (str_contains($normalized, 'pure-tcn')) {
            return 'pure_tcn';
        }
        if (str_contains($normalized, 'standard')) {
            return 'standard';
        }

        return null;
    }

    /** @return array<string, float|int> */
    private function metricsFromTrades(Collection $trades): array
    {
        $equity = 1.0;
        $peak = 1.0;
        $maxDrawdown = 0.0;
        $wins = 0;

        foreach ($trades->sortBy('exit_date') as $trade) {
            $return = (float) $trade->net_return;
            $equity *= 1.0 + $return;
            $peak = max($peak, $equity);
            $maxDrawdown = min($maxDrawdown, ($equity / $peak) - 1.0);
            $wins += $return > 0 ? 1 : 0;
        }
        $count = $trades->count();

        return [
            'trades' => $count,
            'hit_rate' => $count > 0 ? $wins / $count : 0.0,
            'cumulative_return' => $equity - 1.0,
            'max_drawdown' => $maxDrawdown,
        ];
    }

    /** @param array<string, float|int|null> $actual @param array<string, float|int|null> $published */
    private function tradeMetricsMatch(array $actual, array $published): bool
    {
        if (! is_numeric($published['trades'] ?? null)
            || (int) $actual['trades'] !== (int) $published['trades']) {
            return false;
        }

        foreach (['hit_rate', 'cumulative_return', 'max_drawdown'] as $metric) {
            if (! is_numeric($published[$metric] ?? null)) {
                continue;
            }
            if (abs((float) $actual[$metric] - (float) $published[$metric]) > 0.00001) {
                return false;
            }
        }

        return true;
    }

    /** @param array<string, mixed> $candidate @return array<string, mixed> */
    private function equitySeries(array $candidate): array
    {
        /** @var Collection<int, object> $trades */
        $trades = $candidate['trades']->sortBy('entry_date')->values();
        $equity = 1.0;
        $points = [];

        foreach ($trades as $trade) {
            $points[] = [
                'action' => 'buy',
                'date' => (string) $trade->entry_date,
                'timestamp' => strtotime((string) $trade->entry_date),
                'price' => (float) $trade->entry_close_eur,
                'equity_percent' => ($equity - 1.0) * 100,
                'net_return_percent' => (float) $trade->net_return * 100,
            ];
            $equity *= 1.0 + (float) $trade->net_return;
            $points[] = [
                'action' => 'sell',
                'date' => (string) $trade->exit_date,
                'timestamp' => strtotime((string) $trade->exit_date),
                'price' => (float) $trade->exit_close_eur,
                'equity_percent' => ($equity - 1.0) * 100,
                'net_return_percent' => (float) $trade->net_return * 100,
            ];
        }

        $timestamps = collect($points)->pluck('timestamp');
        $start = (int) ($timestamps->min() ?: time());
        $end = (int) ($timestamps->max() ?: $start + 86400);
        if ($start === $end) {
            $end += 86400;
        }
        $values = collect($points)->pluck('equity_percent')->push(0.0);
        $rawMin = (float) $values->min();
        $rawMax = (float) $values->max();
        $rawRange = max(1.0, $rawMax - $rawMin);
        $padding = max(1.0, $rawRange * 0.08);
        $minimum = $rawMin - $padding;
        $maximum = $rawMax + $padding;
        $range = $maximum - $minimum;
        $left = 54.0;
        $top = 18.0;
        $plotWidth = 728.0;
        $plotHeight = 198.0;

        $points = collect($points)->map(function (array $point) use (
            $start,
            $end,
            $minimum,
            $maximum,
            $range,
            $left,
            $top,
            $plotWidth,
            $plotHeight,
        ): array {
            $point['x'] = $left + (($point['timestamp'] - $start) / ($end - $start)) * $plotWidth;
            $point['y'] = $top + (($maximum - $point['equity_percent']) / $range) * $plotHeight;

            return $point;
        })->all();

        $grid = collect(range(0, 4))->map(function (int $index) use (
            $maximum,
            $range,
            $top,
            $plotHeight,
        ): array {
            return [
                'y' => $top + ($index / 4) * $plotHeight,
                'value' => $maximum - ($index / 4) * $range,
            ];
        })->all();

        $latestTrades = $trades->sortByDesc('exit_date')->take(3)->map(fn (object $trade): array => [
            'entry_date' => (string) $trade->entry_date,
            'entry_price' => (float) $trade->entry_close_eur,
            'exit_date' => (string) $trade->exit_date,
            'exit_price' => (float) $trade->exit_close_eur,
            'holding_days' => (int) $trade->holding_days,
            'net_return_percent' => (float) $trade->net_return * 100,
            'cost_percent' => (float) $trade->transaction_cost * 100,
            'exit_reason' => (string) ($trade->exit_reason ?: 'MAX_HOLDING_DAYS'),
        ])->values()->all();

        return [
            'days' => (int) $candidate['days'],
            'variant' => (string) $candidate['variant'],
            'label' => $candidate['variant'] === 'pure_tcn' ? 'Pure TCN' : 'Standard',
            'strategy' => (string) $candidate['strategy'],
            'release_linked' => (bool) $candidate['release_linked'],
            'line_points' => collect($points)->map(fn (array $point): string => sprintf('%.2f,%.2f', $point['x'], $point['y']))->implode(' '),
            'points' => $points,
            'grid' => $grid,
            'zero_y' => $top + (($maximum - 0.0) / $range) * $plotHeight,
            'date_labels' => [
                ['x' => $left, 'label' => date('m/Y', $start)],
                ['x' => $left + $plotWidth / 2, 'label' => date('m/Y', (int) (($start + $end) / 2))],
                ['x' => $left + $plotWidth, 'label' => date('m/Y', $end)],
            ],
            'summary' => [
                'trades' => (int) $candidate['metrics']['trades'],
                'hit_rate_percent' => (float) $candidate['metrics']['hit_rate'] * 100,
                'cumulative_return_percent' => (float) $candidate['metrics']['cumulative_return'] * 100,
                'max_drawdown_percent' => abs((float) $candidate['metrics']['max_drawdown'] * 100),
                'start_date' => date('Y-m-d', $start),
                'end_date' => date('Y-m-d', $end),
            ],
            'latest_trades' => $latestTrades,
        ];
    }

    /** @return array<string, mixed> */
    private function json(mixed $value): array
    {
        if (is_array($value)) {
            return $value;
        }
        if (is_object($value)) {
            return (array) $value;
        }

        $decoded = json_decode((string) $value, true);

        return is_array($decoded) ? $decoded : [];
    }

    private function number(mixed $value): float|int|null
    {
        return is_numeric($value) ? (float) $value : null;
    }

    private function humanBytes(mixed $value): ?string
    {
        if (! is_numeric($value)) {
            return null;
        }
        $bytes = max(0, (int) $value);
        if ($bytes < 1024) {
            return $bytes.' B';
        }
        if ($bytes < 1024 * 1024) {
            return number_format($bytes / 1024, 1, ',', '.').' KB';
        }

        return number_format($bytes / (1024 * 1024), 1, ',', '.').' MB';
    }
}
