<?php

namespace App\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

final class TradeEligibilityStatusService
{
    public const ACTIONABLE = 'actionable';
    public const PAUSED_LOW_RETURN = 'paused_low_return';
    public const NOT_BUY = 'not_buy';

    public function apply(Collection $stocks): void
    {
        if ($stocks->isEmpty() || ! Schema::hasTable('stock_trade_eligibility_states')) return;

        $ids = $stocks->pluck('instrument_id')->map(fn ($id): int => (int) $id)->all();
        $states = DB::table('stock_trade_eligibility_states')->whereIn('instrument_id', $ids)->get()->keyBy('instrument_id');
        $cost = max(0.0, (float) config('aktienki.signals.round_trip_cost_percent', .5));
        $pauseBelow = max(0.0, (float) config('trade_eligibility.pause_below_net_return_percent', 1.0));
        $resumeAt = max($pauseBelow, (float) config('trade_eligibility.resume_at_net_return_percent', 2.0));
        $timestamp = now();
        $rows = [];

        foreach ($stocks as $stock) {
            $id = (int) $stock->instrument_id;
            $signal = strtoupper((string) ($stock->personalized_signal ?: $stock->model_signal ?: 'HOLD'));
            $horizon = is_numeric($stock->trigger_model_horizon ?? null) ? (int) $stock->trigger_model_horizon : 20;
            $gross = $stock->{"expected_return_{$horizon}d"} ?? null;
            $net = is_numeric($gross) ? (float) $gross - $cost : null;
            $previous = $states->get($id);

            [$status, $reason] = $this->resolveStatus($signal, $net, $previous?->status, $pauseBelow, $resumeAt);

            $stock->trade_status = $status;
            $stock->trade_status_reason = $reason;
            $stock->trade_status_net_return = $net;

            $changed = ! $previous || $previous->status !== $status || $previous->model_signal !== $signal;
            $rows[$id] = [
                'instrument_id' => $id,
                'model_signal' => $signal,
                'status' => $status,
                'horizon_days' => $horizon,
                'net_return_percent' => $net,
                'reason' => $reason,
                'transitioned_at' => $changed ? $timestamp : $previous->transitioned_at,
                'created_at' => $previous?->created_at ?? $timestamp,
                'updated_at' => $timestamp,
            ];
        }

        DB::table('stock_trade_eligibility_states')->upsert(
            array_values($rows),
            ['instrument_id'],
            ['model_signal', 'status', 'horizon_days', 'net_return_percent', 'reason', 'transitioned_at', 'updated_at'],
        );
    }

    /** @return array{0: string, 1: ?string} */
    public function resolveStatus(string $signal, ?float $net, ?string $previousStatus, float $pauseBelow, float $resumeAt): array
    {
        if (strtoupper($signal) !== 'BUY') return [self::NOT_BUY, 'model_signal_not_buy'];

        if ($previousStatus === self::ACTIONABLE) {
            $status = $net !== null && $net < $pauseBelow ? self::PAUSED_LOW_RETURN : self::ACTIONABLE;

            return [$status, $status === self::ACTIONABLE ? null : 'net_return_below_pause_threshold'];
        }

        if ($previousStatus === self::PAUSED_LOW_RETURN) {
            $status = $net !== null && $net >= $resumeAt ? self::ACTIONABLE : self::PAUSED_LOW_RETURN;

            return [$status, $status === self::ACTIONABLE ? null : 'waiting_for_resume_threshold'];
        }

        $status = $net !== null && $net >= $resumeAt ? self::ACTIONABLE : self::PAUSED_LOW_RETURN;

        return [$status, $status === self::ACTIONABLE ? null : 'initial_net_return_below_resume_threshold'];
    }
}
