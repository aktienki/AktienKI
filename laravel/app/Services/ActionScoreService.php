<?php

namespace App\Services;

use App\Models\Prediction;
use Illuminate\Support\Facades\DB;

class ActionScoreService
{
    public const VERSION = 'action-v7-confirm4-60t-filter';

    public function __construct(
        private readonly MacdStochasticMarketPhaseService $marketPhaseService,
        private readonly ActionScoreFormula $formula,
    ) {}

    /** @return array{score: float, signal: string, blocked: bool, components: array<string, mixed>}|null */
    public function calculate(Prediction $prediction): ?array
    {
        $runIds = DB::table('walk_forward_backtest_runs as run')
            ->join('walk_forward_backtest_trades as trade', 'trade.run_id', '=', 'run.id')
            ->where('run.status', 'completed')
            ->where('trade.instrument_id', $prediction->instrument_id)
            ->whereIn('run.horizon_days', [5, 10, 15, 20])
            ->selectRaw('DISTINCT ON (run.horizon_days) run.horizon_days, run.id')
            ->orderBy('run.horizon_days')->orderByDesc('run.finished_at')->orderByDesc('run.id')
            ->pluck('id');

        if ($runIds->isEmpty()) {
            return null;
        }

        $trade = DB::table('walk_forward_backtest_trades')
            ->where('instrument_id', $prediction->instrument_id)->whereIn('run_id', $runIds)
            ->selectRaw('COUNT(*) AS trades')
            ->selectRaw('AVG(net_return) * 100 AS average_return_percent')
            ->selectRaw('AVG(CASE WHEN net_return > 0 THEN 1.0 ELSE 0.0 END) * 100 AS hit_rate')
            ->selectRaw('SUM(CASE WHEN net_return > 0 THEN net_return ELSE 0 END) / NULLIF(ABS(SUM(CASE WHEN net_return < 0 THEN net_return ELSE 0 END)), 0) AS profit_factor')
            ->first();

        $drawdown = DB::table('walk_forward_backtest_year_stats')
            ->where('instrument_id', $prediction->instrument_id)->whereIn('run_id', $runIds)
            ->avg('maximum_drawdown');
        $yearReturns = DB::table('walk_forward_backtest_year_stats')
            ->where('instrument_id', $prediction->instrument_id)->whereIn('run_id', $runIds)
            ->whereNotNull('average_net_return')->pluck('average_net_return')->map(fn ($value): float => (float) $value);
        if ($yearReturns->isEmpty()) {
            $yearReturns = DB::table('walk_forward_backtest_trades')
                ->where('instrument_id', $prediction->instrument_id)->whereIn('run_id', $runIds)
                ->selectRaw('EXTRACT(YEAR FROM signal_date) AS year, AVG(net_return) AS average_return')
                ->groupByRaw('EXTRACT(YEAR FROM signal_date)')->pluck('average_return')
                ->filter(fn ($value): bool => is_numeric($value))->map(fn ($value): float => (float) $value);
        }
        $instrumentRisk = DB::table('instruments')->where('id', $prediction->instrument_id)
            ->first(['risk_max_drawdown']);
        if (! is_numeric($drawdown) && is_numeric($instrumentRisk?->risk_max_drawdown)) {
            $drawdown = (float) $instrumentRisk->risk_max_drawdown;
        }

        $profitFactor = is_numeric($trade?->profit_factor) ? (float) $trade->profit_factor : null;
        $averageTrade = is_numeric($trade?->average_return_percent) ? (float) $trade->average_return_percent : null;
        $hitRate = is_numeric($trade?->hit_rate) ? (float) $trade->hit_rate : null;
        $tradeCount = (int) ($trade?->trades ?? 0);
        $confidence = (float) ($prediction->confidence ?? 0);
        $confidence = max(0, min(100, $confidence <= 1 ? $confidence * 100 : $confidence));
        $expectedReturn = ((float) $prediction->current_price !== 0.0 && is_numeric($prediction->predicted_price_20d))
            ? ((((float) $prediction->predicted_price_20d - (float) $prediction->current_price) / (float) $prediction->current_price) * 100)
                - max(0.0, (float) config('aktienki.signals.round_trip_cost_percent', .5))
            : 0.0;
        $drawdownPercent = is_numeric($drawdown) ? abs((float) $drawdown) : null;
        if ($drawdownPercent !== null && $drawdownPercent <= 1) {
            $drawdownPercent *= 100;
        }

        $stability = is_numeric($prediction->horizon_fusion_stability_score)
            ? (float) $prediction->horizon_fusion_stability_score : null;
        if ($stability !== null && $stability <= 1) {
            $stability *= 100;
        }
        if ($stability === null && $yearReturns->isNotEmpty()) {
            $stability = ($yearReturns->filter(fn (float $value): bool => $value > 0)->count() / $yearReturns->count()) * 100;
        }

        $marketPhase = $this->marketPhaseService->forPrediction($prediction);
        $blockers = is_array($prediction->quality_gate_blockers)
            ? $prediction->quality_gate_blockers
            : (json_decode((string) ($prediction->quality_gate_blockers ?? '[]'), true) ?: []);
        $hardBlockers = array_values(array_filter($blockers, fn ($blocker): bool => (string) $blocker !== 'volatility'));
        $result = $this->formula->calculate([
            'profit_factor' => $profitFactor,
            'average_trade' => $averageTrade,
            'confidence' => $confidence,
            'expected_return' => $expectedReturn,
            'drawdown' => $drawdownPercent,
            'hit_rate' => $hitRate,
            'stability' => $stability,
            'trade_count' => $tradeCount,
            'quality_gate_passed' => $prediction->quality_gate_passed === true,
            'hard_blockers' => $hardBlockers,
        ], $marketPhase);
        ['score' => $score, 'signal' => $signal, 'blocked' => $blocked, 'values' => $values, 'weights' => $weights] = $result;

        $fusionDetails = is_array($prediction->horizon_fusion_details)
            ? $prediction->horizon_fusion_details
            : (json_decode((string) ($prediction->horizon_fusion_details ?? '{}'), true) ?: []);
        $pointReturns = (array) data_get($fusionDetails, 'points_return', []);
        $fourHorizonReturns = collect([5, 10, 15, 20])->mapWithKeys(function (int $days) use ($pointReturns): array {
            $value = $pointReturns[$days] ?? $pointReturns[(string) $days] ?? null;
            return [$days => is_numeric($value) ? (float) $value : null];
        });
        $availableConfirmations = $fourHorizonReturns->filter(fn ($value): bool => $value !== null);
        $allFourPositive = $availableConfirmations->count() === 4
            && $availableConfirmations->every(fn (float $value): bool => $value > 0);
        $positiveShortConfirmations = collect([5, 10, 15])->filter(
            fn (int $days): bool => is_numeric($fourHorizonReturns->get($days)) && (float) $fourHorizonReturns->get($days) > 0
        )->count();
        $primary20Positive = is_numeric($fourHorizonReturns->get(20))
            && (float) $fourHorizonReturns->get(20) > 0;
        // 1+ is reserved for complete positive confirmation across all four
        // production horizons. The underlying raw score remains documented.
        $scoreBeforeConfirmationCap = $score;
        if ($score >= 90.0 && ! $allFourPositive) $score = 89.99;

        $longHorizonContext = data_get($fusionDetails, 'long_horizon_context');
        $longHorizonVeto = $signal === 'BUY'
            && is_array($longHorizonContext)
            && filter_var($longHorizonContext['decisive'] ?? false, FILTER_VALIDATE_BOOL)
            && array_key_exists('aligned_with_primary_20d', $longHorizonContext)
            && ! filter_var($longHorizonContext['aligned_with_primary_20d'], FILTER_VALIDATE_BOOL);
        if ($longHorizonVeto) $signal = 'WATCH';

        $primaryDirectionVeto = $signal === 'BUY' && ! $primary20Positive;
        if ($primaryDirectionVeto) $signal = 'WATCH';

        $individualThreshold = DB::table('stock_individual_thresholds')
            ->where('instrument_id', $prediction->instrument_id)
            ->where('horizon_days', 20)
            ->where('algorithm_version', 'historical-action-v5-per-stock-before-context-filters')
            ->whereNotNull('minimum_ai_score')
            ->orderByDesc('calculated_at')
            ->orderByDesc('id')
            ->first(['id', 'horizon_days', 'algorithm_version', 'status', 'minimum_ai_score']);
        $signalBeforeIndividualThreshold = $signal;
        $individualThresholdApplied = false;
        $individualThresholdMissing = $signal === 'BUY' && $individualThreshold === null;
        if ($individualThresholdMissing || ($signal === 'BUY' && ($score / 10) < (float) $individualThreshold->minimum_ai_score)) {
            $signal = 'WATCH';
            $individualThresholdApplied = true;
        }

        return [
            'score' => $score,
            'signal' => $signal,
            'blocked' => $blocked,
            'components' => [
                'version' => self::VERSION, 'values' => $values, 'weights' => $weights,
                'metrics' => compact('profitFactor', 'averageTrade', 'hitRate', 'tradeCount', 'expectedReturn', 'drawdownPercent', 'stability'),
                'market_phase' => $marketPhase,
                'horizon_confirmation' => [
                    'policy' => '20d_primary_other_horizons_confirmation_only',
                    'returns' => $fourHorizonReturns->all(),
                    'positive_short_confirmation_count' => $positiveShortConfirmations,
                    'all_four_positive' => $allFourPositive,
                    'primary_20d_positive' => $primary20Positive,
                    'primary_direction_veto_applied' => $primaryDirectionVeto,
                    'score_before_confirmation_cap' => $scoreBeforeConfirmationCap,
                    'score_capped_for_missing_full_confirmation' => $scoreBeforeConfirmationCap !== $score,
                ],
                'long_horizon_60d_filter' => [
                    'context' => $longHorizonContext,
                    'veto_applied' => $longHorizonVeto,
                    'filter_only' => true,
                ],
                'hard_blockers' => $hardBlockers, 'blocked' => $blocked, 'walk_forward_run_ids' => $runIds->values()->all(),
                'individual_buy_threshold' => $individualThreshold === null ? null : [
                    'id' => (int) $individualThreshold->id,
                    'algorithm_version' => $individualThreshold->algorithm_version,
                    'status' => $individualThreshold->status,
                    'calibration_horizon_days' => (int) $individualThreshold->horizon_days,
                    'minimum_ai_score' => (float) $individualThreshold->minimum_ai_score,
                    'applied' => $individualThresholdApplied,
                    'decision' => $individualThresholdApplied
                        ? 'BUY_TO_WATCH'
                        : ($signalBeforeIndividualThreshold === 'BUY' ? 'PASSED' : 'NOT_BUY'),
                ],
                'individual_buy_threshold_missing_veto' => $individualThresholdMissing,
            ],
        ];
    }

    public function persist(Prediction $prediction): bool
    {
        $result = $this->calculate($prediction);
        if ($result === null) {
            return false;
        }

        $prediction->model_prediction_score ??= $prediction->prediction_score;
        // prediction_score is a generated compatibility column (ai_score) in
        // production. Persist only its writable source column.
        $prediction->ai_score = $result['score'];
        $prediction->signal = $result['signal'];
        $prediction->action_score_version = self::VERSION;
        $prediction->action_score_components = $result['components'];
        $prediction->action_score_calculated_at = now();
        $prediction->save();

        return true;
    }
}
