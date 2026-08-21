<?php

namespace App\Services;

use App\Models\Prediction;
use App\Support\ProfitFactor;
use Illuminate\Support\Facades\DB;

class ActionScoreService
{
    public const VERSION = 'action-v5-market-phase';

    public function __construct(private readonly MacdStochasticMarketPhaseService $marketPhaseService)
    {
    }

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

        $profitFactor = ProfitFactor::cap($trade?->profit_factor);
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

        $values = [
            'profit_factor' => $profitFactor !== null ? max(0, min(100, (($profitFactor - .5) / 2) * 100)) : 0,
            'average_profit_per_trade' => $averageTrade !== null ? max(0, min(100, 50 + ($averageTrade * 12.5))) : 0,
            'confidence' => $confidence,
            'expected_return_20d' => max(0, min(100, 50 + ($expectedReturn * 5))),
            'drawdown' => $drawdownPercent !== null ? max(0, min(100, 100 - ($drawdownPercent * 2))) : 0,
            'hit_rate' => $hitRate !== null ? max(0, min(100, $hitRate)) : 0,
            'stability' => $stability !== null ? max(0, min(100, $stability)) : 0,
            'quality_gate' => $prediction->quality_gate_passed === true ? 100 : 0,
        ];
        $weights = ['profit_factor' => 20, 'average_profit_per_trade' => 10, 'confidence' => 20, 'expected_return_20d' => 15, 'drawdown' => 15, 'hit_rate' => 10, 'stability' => 5, 'quality_gate' => 5];
        $score = round(collect($values)->sum(fn (float $value, string $key): float => $value * $weights[$key]) / 100, 2);
        $marketPhase = $this->marketPhaseService->forPrediction($prediction);
        if ($marketPhase !== null) {
            $score = round(max(0, min(100, $score + $marketPhase['score_adjustment'])), 2);
        }

        $blockers = is_array($prediction->quality_gate_blockers)
            ? $prediction->quality_gate_blockers
            : (json_decode((string) ($prediction->quality_gate_blockers ?? '[]'), true) ?: []);
        $hardBlockers = array_values(array_filter($blockers, fn ($blocker): bool => (string) $blocker !== 'volatility'));
        $blocked = $tradeCount < 10
            || $averageTrade === null
            || $averageTrade < 0
            || $prediction->quality_gate_passed !== true
            || $hardBlockers !== [];
        if ($blocked) {
            $score = min(64.0, $score);
        }
        if (($marketPhase['buy_veto'] ?? false) === true) {
            $score = min(64.0, $score);
        }

        $signal = match (true) {
            $score >= 65 => 'BUY',
            $score >= 55 => 'WATCH',
            $score >= 40 => 'HOLD',
            default => 'SELL',
        };

        return [
            'score' => $score,
            'signal' => $signal,
            'blocked' => $blocked,
            'components' => [
                'version' => self::VERSION, 'values' => $values, 'weights' => $weights,
                'metrics' => compact('profitFactor', 'averageTrade', 'hitRate', 'tradeCount', 'expectedReturn', 'drawdownPercent', 'stability'),
                'market_phase' => $marketPhase,
                'hard_blockers' => $hardBlockers, 'blocked' => $blocked, 'walk_forward_run_ids' => $runIds->values()->all(),
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
