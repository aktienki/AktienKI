<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ApplyHorizonFusion extends Command
{
    protected $signature = 'predictions:apply-horizon-fusion {--pipeline-version=horizon-fusion-v2-complete} {--feature-version=triple_daily_macro_v1} {--dry-run} {--instrument=}';
    protected $description = 'Aggregiert 5/10/15/20-Tage-Prognosen und schreibt das Horizon-Fusion-Gate.';

    private const HORIZONS = [7200 => 5, 14400 => 10, 21600 => 15, 28800 => 20];
    private const WEIGHTS = [5 => 0.15, 10 => 0.25, 15 => 0.25, 20 => 0.35];

    public function handle(): int
    {
        $version = (string) $this->option('pipeline-version');
        $featureVersion = (string) $this->option('feature-version');
        $rows = DB::table('predictions')->where('ai_type', 'horizon')->where('timeframe', '1d')
            ->whereIn('prediction_horizon_minutes', array_keys(self::HORIZONS))
            ->whereExists(function ($query) use ($featureVersion): void {
                $query->selectRaw('1')->from('trained_models as fusion_model')
                    ->whereColumn('fusion_model.id', 'predictions.trained_model_id')
                    ->where('fusion_model.feature_set_version', $featureVersion);
            })
            ->when($this->option('instrument'), fn ($q, $id) => $q->where('instrument_id', (int) $id))
            ->orderByDesc('prediction_time')->get()->groupBy('instrument_id');
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
            $stabilityScore = max(0.0, min(1.0,
                0.40 * $directionConsistency + 0.30 * (1.0 - $dispersion)
                + 0.20 * $slopeAlignment + 0.10 * $magnitudeSupport
            ));
            $points = [0 => 0.0, 5 => $returns[5], 10 => $returns[10], 15 => $returns[15], 20 => $returns[20]];
            $positiveArea = 0.0; $negativeArea = 0.0; $segments = [];
            foreach ([[0, 5], [5, 10], [10, 15], [15, 20]] as [$from, $to]) {
                [$segmentPositive, $segmentNegative] = $this->segmentArea($points[$from] * 100, $points[$to] * 100, $to - $from);
                $positiveArea += $segmentPositive; $negativeArea += $segmentNegative;
                $segments[] = ['from_days' => $from, 'to_days' => $to, 'start_return' => $points[$from], 'end_return' => $points[$to], 'positive_area' => $segmentPositive, 'negative_area' => $segmentNegative];
            }
            $netArea = $positiveArea + $negativeArea;
            $noisePassed = $netArea > 0.0;
            $stabilityPassed = $noisePassed && $weighted >= 0.0 && $stabilityScore >= 0.55;
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
                'points_return' => $points, 'weights' => self::WEIGHTS, 'segments' => $segments,
                'thresholds' => ['positive_net_area_required' => true, 'minimum_stability_score' => 0.55],
                'segment_slopes_per_day' => $segmentSlopes,
                'calculated_at' => now()->toIso8601String(),
            ];
            $signals = $latest->pluck('signal')->map(fn ($s) => strtoupper((string) $s));
            $rawSignal = $signals->contains('BUY') ? 'BUY' : ($signals->contains('SELL') ? 'SELL' : 'HOLD');
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
