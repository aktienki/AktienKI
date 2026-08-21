<?php

namespace App\Services;

use App\Models\User;
use App\Support\ProfitFactor;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

final class StockRiskClassificationService
{
    public function classify(?float $profitFactor, ?float $profitPerTrade, ?float $confidence, ?float $drawdown): ?string
    {
        $profitFactor = ProfitFactor::cap($profitFactor);
        if ($profitFactor === null || $profitPerTrade === null || $confidence === null || $drawdown === null) {
            return null;
        }

        if ($profitPerTrade < 0) {
            return 'sleep';
        }

        if ($profitFactor >= 1.35 && $confidence >= 70 && $drawdown <= 18) {
            return 'defensive';
        }

        if ($profitFactor >= 1.20 && $confidence >= 58 && $drawdown <= 28) {
            return 'balanced';
        }

        if ($profitFactor >= 1.05 && $confidence >= 42 && $drawdown <= 45) {
            return 'opportunity';
        }

        return 'risk';
    }

    public function userLevel(?User $user): string
    {
        return match ((string) data_get($user?->meta, 'risk_profile.level', 'normal')) {
            'cautious', 'conservative', 'defensive' => 'defensive',
            'opportunity_oriented', 'opportunity', 'aggressive' => 'opportunity',
            'risk' => 'risk',
            default => 'balanced',
        };
    }

    public function visibleStatuses(?User $user): array
    {
        return match ($this->userLevel($user)) {
            'defensive' => ['defensive'],
            'balanced' => ['defensive', 'balanced'],
            'opportunity' => ['defensive', 'balanced', 'opportunity'],
            default => ['defensive', 'balanced', 'opportunity', 'risk', 'sleep'],
        };
    }

    public function applyVisibility(Builder $query, ?User $user, string $column = 'instruments.risk_status'): Builder
    {
        return $this->userLevel($user) === 'risk'
            ? $query
            : $query->whereIn($column, $this->visibleStatuses($user));
    }

    public function refresh(?int $instrumentId = null): array
    {
        $modelFilter = $instrumentId ? 'AND model.instrument_id = ?' : '';
        $tradeFilter = $instrumentId ? 'AND trade.instrument_id = ?' : '';
        $targetFilter = $instrumentId ? 'AND instrument.id = ?' : '';
        $bindings = $instrumentId ? [$instrumentId, $instrumentId, $instrumentId] : [];

        DB::update(<<<SQL
WITH model_metric AS (
    SELECT model.instrument_id,
        MIN(LEAST(3.0, GREATEST(0, (model.metrics->>'profit_factor')::numeric))) AS profit_factor,
        MAX(ABS((model.metrics->>'max_drawdown')::numeric) * 100) AS max_drawdown
    FROM trained_models model
    WHERE model.status='active' AND model.instrument_id IS NOT NULL
        AND (model.metadata->>'prediction_horizon')::int IN (5,10,15,20)
        AND jsonb_exists(model.metrics, 'profit_factor') AND jsonb_exists(model.metrics, 'max_drawdown') {$modelFilter}
    GROUP BY model.instrument_id
    HAVING COUNT(DISTINCT (model.metadata->>'prediction_horizon')::int)=4
), latest_walk_forward AS (
    SELECT trade.instrument_id, run.horizon_days, MAX(run.id) AS run_id
    FROM walk_forward_backtest_runs run
    JOIN walk_forward_backtest_trades trade ON trade.run_id=run.id
    WHERE run.status='completed' AND run.horizon_days IN (5,10,15,20) {$tradeFilter}
    GROUP BY trade.instrument_id, run.horizon_days
), trade_metric AS (
    SELECT latest_walk_forward.instrument_id, AVG(trade.net_return) * 100 AS profit_per_trade
    FROM latest_walk_forward
    JOIN walk_forward_backtest_trades trade ON trade.run_id=latest_walk_forward.run_id
        AND trade.instrument_id=latest_walk_forward.instrument_id
    GROUP BY latest_walk_forward.instrument_id
    HAVING COUNT(DISTINCT latest_walk_forward.horizon_days)=4
), prediction AS (
    SELECT DISTINCT ON (instrument_id) instrument_id,
        LEAST(100, GREATEST(0, confidence * CASE WHEN confidence <= 1 THEN 100 ELSE 1 END)) AS confidence
    FROM predictions WHERE confidence IS NOT NULL ORDER BY instrument_id, prediction_time DESC, id DESC
), classified AS (
    SELECT instrument.id, model_metric.profit_factor, trade_metric.profit_per_trade, prediction.confidence, model_metric.max_drawdown,
        CASE WHEN model_metric.profit_factor IS NULL OR trade_metric.profit_per_trade IS NULL OR prediction.confidence IS NULL OR model_metric.max_drawdown IS NULL THEN NULL
             WHEN trade_metric.profit_per_trade < 0 THEN 'sleep'
             WHEN model_metric.profit_factor >= 1.35 AND prediction.confidence >= 70 AND model_metric.max_drawdown <= 18 THEN 'defensive'
             WHEN model_metric.profit_factor >= 1.20 AND prediction.confidence >= 58 AND model_metric.max_drawdown <= 28 THEN 'balanced'
             WHEN model_metric.profit_factor >= 1.05 AND prediction.confidence >= 42 AND model_metric.max_drawdown <= 45 THEN 'opportunity'
             ELSE 'risk' END AS status
    FROM instruments instrument LEFT JOIN model_metric ON model_metric.instrument_id=instrument.id
    LEFT JOIN trade_metric ON trade_metric.instrument_id=instrument.id
    LEFT JOIN prediction ON prediction.instrument_id=instrument.id
    WHERE instrument.type='stock' {$targetFilter}
)
UPDATE instruments SET risk_status=classified.status, risk_profit_factor=classified.profit_per_trade,
    risk_confidence=classified.confidence, risk_max_drawdown=classified.max_drawdown, risk_status_updated_at=NOW()
FROM classified WHERE instruments.id=classified.id
SQL, $bindings);

        $counts = DB::table('instruments')->where('type', 'stock')->when($instrumentId, fn (Builder $query) => $query->where('id', $instrumentId))
            ->selectRaw("COALESCE(risk_status, 'unclassified') AS status, COUNT(*) AS aggregate")
            ->groupByRaw("COALESCE(risk_status, 'unclassified')")->pluck('aggregate', 'status')->map(fn ($count) => (int) $count)->all();

        return array_replace(['defensive'=>0, 'balanced'=>0, 'opportunity'=>0, 'risk'=>0, 'sleep'=>0, 'unclassified'=>0], $counts);
    }
}
