<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ApplyHorizonFusion extends Command
{
    protected $signature = 'predictions:apply-horizon-fusion {--pipeline-version=horizon-fusion-v4-primary20-confirmation} {--feature-version=triple_daily_macro_v1} {--dry-run} {--instrument=}';
    protected $description = 'Aggregiert 5/10/15/20-Tage-Prognosen und schreibt das Horizon-Fusion-Gate.';

    private const HORIZONS = [7200 => 5, 14400 => 10, 21600 => 15, 28800 => 20];
    private const WEIGHTS = [5 => 0.15, 10 => 0.25, 15 => 0.25, 20 => 0.35];
    private const MODEL_ROLE_POLICY = [
        'forecast_sources' => ['horizon_5d', 'horizon_10d', 'horizon_15d', 'horizon_20d'],
        'filter_only' => ['stock_phase', 'long_horizon_60d', 'sector', 'index', 'indicator'],
    ];

    public function handle(): int
    {
        $version = (string) $this->option('pipeline-version');
        $featureVersion = (string) $this->option('feature-version');
        $latestIds = DB::table('predictions as latest_prediction')
            ->selectRaw('DISTINCT ON (latest_prediction.instrument_id, latest_prediction.prediction_horizon_minutes) latest_prediction.id')
            ->where('latest_prediction.ai_type', 'horizon')->where('latest_prediction.timeframe', '1d')
            ->whereIn('latest_prediction.prediction_horizon_minutes', array_keys(self::HORIZONS))
            ->when($this->option('instrument'), fn ($q, $id) => $q->where('latest_prediction.instrument_id', (int) $id))
            ->orderBy('latest_prediction.instrument_id')
            ->orderBy('latest_prediction.prediction_horizon_minutes')
            ->orderByDesc('latest_prediction.prediction_time')
            ->orderByDesc('latest_prediction.id');
        $rows = DB::table('predictions')
            ->joinSub($latestIds, 'latest_scope', fn ($join) => $join->on('latest_scope.id', '=', 'predictions.id'))
            ->where('predictions.ai_type', 'horizon')->where('predictions.timeframe', '1d')
            ->whereIn('predictions.prediction_horizon_minutes', array_keys(self::HORIZONS))
            ->whereExists(function ($query) use ($featureVersion): void {
                $query->selectRaw('1')->from('trained_models as fusion_model')
                    ->whereColumn('fusion_model.id', 'predictions.trained_model_id')
                    ->where('fusion_model.feature_set_version', $featureVersion);
            })
            ->select([
                'predictions.id', 'predictions.instrument_id', 'predictions.prediction_horizon_minutes',
                'predictions.prediction_time', 'predictions.direction', 'predictions.market_return_5d',
                'predictions.market_return_10d', 'predictions.market_return_15d', 'predictions.market_return_20d',
            ])
            ->orderByDesc('predictions.prediction_time')->get()->groupBy('instrument_id');
        $updated = 0; $skipped = 0;
        foreach ($rows as $instrumentRows) {
            $latest = $instrumentRows->groupBy('prediction_horizon_minutes')->map(fn ($items) => $items->first());
            if ($latest->count() !== 4) { $skipped++; continue; }
            $returns = [];
            foreach (self::HORIZONS as $minutes => $days) {
                $column = "market_return_{$days}d"; $row = $latest->get($minutes);
                if (! $row || ! is_numeric($row->{$column} ?? null)) { $returns = []; break; }
                $returns[$days] = (float) $row->{$column};
            }
            if (count($returns) !== 4) { $skipped++; continue; }
            $weighted = array_sum(array_map(fn ($days, $value) => self::WEIGHTS[$days] * $value, array_keys($returns), $returns));
            $sorted = array_values($returns); sort($sorted); $median = ($sorted[1] + $sorted[2]) / 2.0;
            $dev = array_map(fn ($v) => abs($v - $median), $sorted); sort($dev); $mad = ($dev[1] + $dev[2]) / 2.0;
            $scale = max(0.05, abs($weighted) + 0.02);
            $dispersion = min(1.0, $mad / $scale);
            $sign = $weighted >= 0 ? 1 : -1;
            $directionConsistency = count(array_filter($returns, fn ($value) => $value * $sign >= 0)) / 4.0;
            $slope = ($returns[20] - $returns[5]) / 3.0;
            $slopeAlignment = $slope * $sign >= 0 ? 1.0 : max(0.0, 1.0 - min(1.0, abs($slope) / $scale));
            $magnitudeSupport = min(1.0, abs($weighted) / 0.10);
            $primaryDirection = $returns[20] > 0 ? 1 : ($returns[20] < 0 ? -1 : 0);
            $baseStabilityScore = max(0.0, min(1.0,
                0.40 * $directionConsistency + 0.30 * (1.0 - $dispersion)
                + 0.20 * $slopeAlignment + 0.10 * $magnitudeSupport
            ));
            $longHorizonContext = null;
            $longHorizonAdjustment = 0.0;
            $phaseFilterContext = null;
            $phaseFilterPassed = true;
            if (Schema::hasTable('market_context_predictions')) {
                $instrument = DB::table('instruments')->where('id', (int) $instrumentRows->first()->instrument_id)
                    ->first(['sector']);
                $contextItems = [];
                if ($instrument && filled($instrument->sector)) {
                    $context = DB::table('market_context_predictions')
                        ->where('scope_type', 'sector60')->where('scope_key', (string) $instrument->sector)
                        ->orderByDesc('prediction_date')->orderByDesc('id')->first();
                    if ($context) {
                        $contextMeta = json_decode((string) ($context->meta ?? '{}'), true) ?: [];
                        $eligibleInstrumentIds = array_map('intval', (array) ($contextMeta['eligible_instrument_ids'] ?? []));
                        if (! in_array((int) $instrumentRows->first()->instrument_id, $eligibleInstrumentIds, true)) {
                            $context = null;
                        }
                    }
                    if ($context) {
                        $probabilityUp = max(0.0, min(1.0, ((float) $context->score) / 10.0));
                        $decisive = abs($probabilityUp - 0.5) >= 0.05;
                        $contextDirection = $probabilityUp >= 0.5 ? 1 : -1;
                        $aligned = $primaryDirection !== 0 && $contextDirection === $primaryDirection;
                        // Context only: it may strengthen or veto an existing fusion
                        // result, but can never create BUY/SELL on its own.
                        $contextItems[] = [
                            'source' => 'pytorch_sector_gru_60t',
                            'scope' => (string) $instrument->sector,
                            'prediction_date' => (string) $context->prediction_date,
                            'probability_up' => $probabilityUp,
                            'signal' => (string) $context->signal,
                            'decisive' => $decisive,
                            'aligned_with_primary_20d' => $aligned,
                            'context_only' => true,
                        ];
                    }
                }
                $primaryIndexId = DB::table('index_memberships as membership')
                    ->join('market_indices as market_index', 'market_index.id', '=', 'membership.market_index_id')
                    ->where('membership.instrument_id', (int) $instrumentRows->first()->instrument_id)
                    ->whereNull('membership.removed_at')->where('market_index.is_active', true)
                    ->orderByRaw('market_index.global_rank IS NULL, market_index.global_rank')
                    ->orderBy('market_index.id')->value('market_index.id');
                if ($primaryIndexId) {
                    $indexContext = DB::table('market_context_predictions')
                        ->where('scope_type', 'index60')->where('scope_key', (string) $primaryIndexId)
                        ->orderByDesc('prediction_date')->orderByDesc('id')->first();
                    if ($indexContext) {
                        $probabilityUp = max(0.0, min(1.0, ((float) $indexContext->score) / 10.0));
                        $decisive = abs($probabilityUp - 0.5) >= 0.05;
                        $contextDirection = $probabilityUp >= 0.5 ? 1 : -1;
                        $aligned = $primaryDirection !== 0 && $contextDirection === $primaryDirection;
                        $contextItems[] = [
                            'source' => 'pytorch_index_60t',
                            'scope' => (string) $primaryIndexId,
                            'prediction_date' => (string) $indexContext->prediction_date,
                            'probability_up' => $probabilityUp,
                            'signal' => (string) $indexContext->signal,
                            'decisive' => $decisive,
                            'aligned_with_primary_20d' => $aligned,
                            'context_only' => true,
                        ];
                    }
                }
                if ($contextItems !== []) {
                    $opposed = collect($contextItems)->contains(
                        fn (array $item): bool => $item['decisive'] && ! $item['aligned_with_primary_20d']
                    );
                    $supportive = collect($contextItems)->contains(
                        fn (array $item): bool => $item['decisive'] && $item['aligned_with_primary_20d']
                    );
                    $longHorizonAdjustment = $opposed ? -0.10 : ($supportive ? 0.05 : 0.0);
                    $longHorizonContext = [
                        'source' => 'combined_index_sector_60t',
                        'contexts' => $contextItems,
                        'decisive' => $opposed || $supportive,
                        'aligned_with_primary_20d' => ! $opposed,
                        'stability_adjustment' => $longHorizonAdjustment,
                        'context_only' => true,
                    ];
                }
                $phaseContext = DB::table('market_context_predictions')
                    ->where('scope_type', 'stock_phase20')
                    ->where('scope_key', (string) $instrumentRows->first()->instrument_id)
                    ->orderByDesc('prediction_date')->orderByDesc('id')->first();
                if ($phaseContext) {
                    $phaseMeta = json_decode((string) ($phaseContext->meta ?? '{}'), true) ?: [];
                    $enabled = filter_var($phaseMeta['enabled'] ?? false, FILTER_VALIDATE_BOOL);
                    $probabilityUp = max(0.0, min(1.0, ((float) $phaseContext->score) / 10.0));
                    $phaseFilterPassed = ! $enabled || $primaryDirection === 0
                        || ($primaryDirection > 0 ? $probabilityUp >= 0.5 : $probabilityUp < 0.5);
                    $phaseFilterContext = [
                        'source' => 'pytorch_stock_three_phase_gru_20t',
                        'prediction_date' => (string) $phaseContext->prediction_date,
                        'phase' => $phaseMeta['phase'] ?? null,
                        'probability_up' => $probabilityUp,
                        'enabled' => $enabled,
                        'passed' => $phaseFilterPassed,
                        'filter_only' => true,
                        'quality_gate' => $phaseMeta['quality_gate'] ?? null,
                    ];
                }
            }
            $stabilityScore = max(0.0, min(1.0, $baseStabilityScore + $longHorizonAdjustment));
            $points = [0 => 0.0, 5 => $returns[5], 10 => $returns[10], 15 => $returns[15], 20 => $returns[20]];
            $positiveArea = 0.0; $negativeArea = 0.0; $segments = [];
            foreach ([[0, 5], [5, 10], [10, 15], [15, 20]] as [$from, $to]) {
                [$segmentPositive, $segmentNegative] = $this->segmentArea($points[$from] * 100, $points[$to] * 100, $to - $from);
                $positiveArea += $segmentPositive; $negativeArea += $segmentNegative;
                $segments[] = ['from_days' => $from, 'to_days' => $to, 'start_return' => $points[$from], 'end_return' => $points[$to], 'positive_area' => $segmentPositive, 'negative_area' => $segmentNegative];
            }
            $netArea = $positiveArea + $negativeArea;
            $noisePassed = $primaryDirection > 0 ? $netArea > 0.0 : ($primaryDirection < 0 && $netArea < 0.0);
            $consensusAligned = $primaryDirection > 0 ? $weighted >= 0.0 : ($primaryDirection < 0 && $weighted <= 0.0);
            $stabilityPassed = $noisePassed && $consensusAligned && $stabilityScore >= 0.55 && $phaseFilterPassed;
            $segmentSlopes = [
                ($returns[10] - $returns[5]) / 5.0,
                ($returns[15] - $returns[10]) / 5.0,
                ($returns[20] - $returns[15]) / 5.0,
            ];
            $curvatureFirst = ($segmentSlopes[1] - $segmentSlopes[0]) / 5.0;
            $curvatureSecond = ($segmentSlopes[2] - $segmentSlopes[1]) / 5.0;
            $maxAbsCurvature = max(abs($curvatureFirst), abs($curvatureSecond));
            $directionReversals = 0;
            for ($index = 1; $index < count($segmentSlopes); $index++) {
                if ($segmentSlopes[$index - 1] * $segmentSlopes[$index] < 0) $directionReversals++;
            }
            $zeroCrossings = 0;
            $pointValues = array_values($points);
            for ($index = 1; $index < count($pointValues); $index++) {
                if ($pointValues[$index - 1] * $pointValues[$index] < 0) $zeroCrossings++;
            }
            $details = [
                'method' => 'signed_forecast_area_and_weighted_consensus_mad_direction_slope_curvature',
                // Only the four production horizons may supply forecast returns or
                // raw signals. Every auxiliary model is context/filter-only and
                // therefore may at most adjust confidence or veto that raw signal.
                'model_role_policy' => self::MODEL_ROLE_POLICY,
                'points_return' => $points, 'weights' => self::WEIGHTS, 'segments' => $segments,
                'thresholds' => ['positive_net_area_required' => true, 'minimum_stability_score' => 0.55],
                'segment_slopes_per_day' => $segmentSlopes,
                'base_stability_score' => $baseStabilityScore,
                'long_horizon_context' => $longHorizonContext,
                'phase_filter_context' => $phaseFilterContext,
                'horizon_confirmation' => [
                    'primary_horizon_days' => 20,
                    'primary_direction' => $primaryDirection > 0 ? 'up' : ($primaryDirection < 0 ? 'down' : 'neutral'),
                    'confirmation_horizons_days' => [5, 10, 15],
                    'positive_confirmation_count' => count(array_filter([5, 10, 15], fn (int $days): bool => $returns[$days] > 0)),
                    'all_four_positive' => count(array_filter($returns, fn (float $value): bool => $value > 0)) === 4,
                    'policy' => '20d_primary_other_horizons_confirmation_only',
                ],
                'calculated_at' => now()->toIso8601String(),
            ];
            $primary20 = $latest->get(28800);
            $rawSignal = match (strtolower((string) ($primary20?->direction ?? ''))) {
                'up', 'long', 'bullish' => 'BUY',
                'down', 'short', 'bearish' => 'SELL',
                default => $primaryDirection > 0 ? 'BUY' : ($primaryDirection < 0 ? 'SELL' : 'HOLD'),
            };
            $finalSignal = in_array($rawSignal, ['BUY', 'SELL'], true) && ! $stabilityPassed ? 'HOLD' : $rawSignal;
            $veto = $finalSignal !== $rawSignal;
            if (! $this->option('dry-run')) {
                DB::transaction(function () use ($latest, $version, $weighted, $median, $mad, $dispersion, $directionConsistency, $slope, $slopeAlignment, $magnitudeSupport, $positiveArea, $negativeArea, $netArea, $curvatureFirst, $curvatureSecond, $maxAbsCurvature, $directionReversals, $zeroCrossings, $details, $stabilityScore, $noisePassed, $stabilityPassed, $rawSignal, $veto, $finalSignal): void {
                    foreach ($latest as $row) DB::table('predictions')->where('id', $row->id)->update([
                        'signal' => $finalSignal, 'horizon_fusion_version' => $version, 'horizon_fusion_consensus_return' => $weighted,
                        'horizon_fusion_dispersion' => $dispersion, 'horizon_fusion_direction_consistency' => $directionConsistency,
                        'horizon_fusion_stability_score' => $stabilityScore, 'horizon_fusion_noise_passed' => $noisePassed,
                        'horizon_fusion_stability_passed' => $stabilityPassed, 'horizon_fusion_veto_used' => $veto,
                        'horizon_fusion_raw_signal' => $rawSignal,
                        'horizon_fusion_positive_area' => $positiveArea, 'horizon_fusion_negative_area' => $negativeArea,
                        'horizon_fusion_net_area' => $netArea, 'horizon_fusion_median_return' => $median,
                        'horizon_fusion_mad_return' => $mad, 'horizon_fusion_slope' => $slope,
                        'horizon_fusion_slope_alignment' => $slopeAlignment, 'horizon_fusion_magnitude_support' => $magnitudeSupport,
                        'horizon_fusion_curvature_5_10_15' => $curvatureFirst,
                        'horizon_fusion_curvature_10_15_20' => $curvatureSecond,
                        'horizon_fusion_max_abs_curvature' => $maxAbsCurvature,
                        'horizon_fusion_direction_reversals' => $directionReversals,
                        'horizon_fusion_zero_crossings' => $zeroCrossings,
                        'horizon_fusion_details' => json_encode($details, JSON_THROW_ON_ERROR), 'updated_at' => now(),
                    ]);
                });
            }
            $updated++;
        }
        $this->info("{$updated} Instrumente verarbeitet, {$skipped} übersprungen" . ($this->option('dry-run') ? ' (Dry-Run).' : '.'));
        return self::SUCCESS;
    }

    private function segmentArea(float $start, float $end, float $width): array
    {
        if ($start >= 0 && $end >= 0) return [($start + $end) * $width / 2.0, 0.0];
        if ($start <= 0 && $end <= 0) return [0.0, ($start + $end) * $width / 2.0];
        $crossing = $width * abs($start) / (abs($start) + abs($end));
        if ($start > 0) return [$start * $crossing / 2.0, $end * ($width - $crossing) / 2.0];

        return [$end * ($width - $crossing) / 2.0, $start * $crossing / 2.0];
    }
}
