<?php

namespace App\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Canonical read layer for every stock/model table shown by the website.
 *
 * The lean serving database is the only source for instruments, active model
 * releases, model/horizon performance and published predictions. User-owned
 * records (accounts, watchlists and portfolios) intentionally remain on the
 * primary connection and must never be used as a fallback for model data.
 */
final class ServingReadService
{
    public function activeStocks(): Collection
    {
        return Cache::remember('serving.read.active-stocks.v2', now()->addMinute(), function (): Collection {
            $predictions = $this->latestPredictions()->groupBy('instrument_id');

            return DB::connection('serving')
                ->table('serving_active_models as active_model')
                ->join('serving_releases as release', 'release.id', '=', 'active_model.release_id')
                ->join('serving_instruments as instrument', 'instrument.id', '=', 'active_model.instrument_id')
                ->leftJoin('serving_instrument_fundamentals as fundamental', 'fundamental.instrument_id', '=', 'instrument.id')
                ->where('instrument.is_active', true)
                ->orderBy('instrument.name')
                ->get([
                    'instrument.id as instrument_id', 'instrument.symbol', 'instrument.provider_symbol',
                    'instrument.exchange', 'instrument.name', 'instrument.short_name', 'instrument.currency',
                    'instrument.country_code', 'instrument.sector_code', 'instrument.industry',
                    'instrument.instrument_type', 'instrument.is_tradeable', 'instrument.is_german_tradeable',
                    'instrument.isin', 'instrument.display_metadata', 'instrument.risk_status',
                    'instrument.risk_profit_factor', 'instrument.risk_confidence',
                    'instrument.risk_max_drawdown', 'instrument.risk_profit_per_trade',
                    'release.id as release_id', 'release.pipeline_version', 'release.dataset_cutoff',
                    'release.quality_class', 'release.evidence_level', 'release.release_reason',
                    'release.completed_horizons', 'release.score_calibration', 'release.filter_summary',
                    'release.compact_metrics', 'release.tracking_comment', 'release.recommended_signal',
                    'release.buy_horizons', 'release.strong_buy_horizons', 'release.created_at as released_at',
                    'active_model.activated_at',
                    'fundamental.snapshot_date as fundamental_snapshot_date', 'fundamental.market_cap',
                    'fundamental.trailing_pe', 'fundamental.forward_pe', 'fundamental.peg_ratio',
                    'fundamental.price_to_book', 'fundamental.price_to_sales',
                    'fundamental.dividend_yield', 'fundamental.payout_ratio',
                    'fundamental.profit_margin', 'fundamental.operating_margin',
                    'fundamental.return_on_assets', 'fundamental.return_on_equity',
                    'fundamental.revenue', 'fundamental.revenue_growth', 'fundamental.ebitda',
                    'fundamental.net_income', 'fundamental.total_cash', 'fundamental.total_debt',
                    'fundamental.debt_to_equity', 'fundamental.current_ratio',
                    'fundamental.quick_ratio', 'fundamental.operating_cash_flow',
                    'fundamental.free_cash_flow',
                ])
                ->map(fn (object $row): object => $this->normalizeStock(
                    $row,
                    collect($predictions->get((int) $row->instrument_id, collect()))
                ));
        });
    }

    public function stock(string $symbol): ?object
    {
        $needle = mb_strtoupper(trim($symbol));

        return $this->activeStocks()->first(
            fn (object $stock): bool => mb_strtoupper((string) $stock->symbol) === $needle
                || mb_strtoupper((string) $stock->provider_symbol) === $needle
        );
    }

    public function latestPredictions(): Collection
    {
        return Cache::remember('serving.read.latest-predictions.v2', now()->addMinute(), function (): Collection {
            $connection = DB::connection('serving');
            $ranked = $connection->table('serving_predictions as prediction')
                ->join('serving_prediction_scopes as scope', function ($join): void {
                    $join->on('scope.instrument_id', '=', 'prediction.instrument_id')
                        ->on('scope.release_id', '=', 'prediction.release_id')
                        ->on('scope.horizon', '=', 'prediction.horizon');
                })
                ->join('serving_instruments as instrument', 'instrument.id', '=', 'prediction.instrument_id')
                ->where('instrument.is_active', true)
                ->select([
                    'prediction.id', 'prediction.batch_id', 'prediction.instrument_id',
                    'prediction.release_id', 'prediction.as_of', 'prediction.horizon',
                    'prediction.expected_return', 'prediction.target_price',
                    'prediction.calibrated_score', 'prediction.risk_score',
                    'prediction.signal', 'prediction.confidence', 'prediction.compact_context',
                    'instrument.symbol', 'instrument.name', 'instrument.country_code',
                    'instrument.currency', 'instrument.exchange',
                ])
                ->selectRaw('ROW_NUMBER() OVER (PARTITION BY prediction.instrument_id, prediction.horizon ORDER BY prediction.as_of DESC, prediction.id DESC) AS scope_rank');

            return $connection->query()
                ->fromSub($ranked, 'ranked_prediction')
                ->where('scope_rank', 1)
                ->get()
                ->map(function (object $row): object {
                    $row->horizon = (int) $row->horizon;
                    $row->expected_return_percent = is_numeric($row->expected_return)
                        ? (float) $row->expected_return * 100.0
                        : null;
                    $row->target_price = is_numeric($row->target_price) ? (float) $row->target_price : null;
                    $row->confidence_percent = is_numeric($row->confidence)
                        ? ((float) $row->confidence <= 1.0 ? (float) $row->confidence * 100.0 : (float) $row->confidence)
                        : null;
                    $row->risk_score = is_numeric($row->risk_score) ? (int) $row->risk_score : null;
                    $row->signal = strtoupper((string) $row->signal);
                    $row->context = $this->json($row->compact_context);
                    $row->current_price = $this->currentPrice($row);

                    return $row;
                });
        });
    }

    public function signalTransitions(): Collection
    {
        return Cache::remember('serving.read.signal-transitions.v2', now()->addMinute(), fn (): Collection =>
            DB::connection('serving')
                ->table('serving_signal_transitions as transition')
                ->join('serving_active_models as active_model', function ($join): void {
                    $join->on('active_model.instrument_id', '=', 'transition.instrument_id')
                        ->on('active_model.release_id', '=', 'transition.release_id');
                })
                ->join('serving_instruments as instrument', 'instrument.id', '=', 'transition.instrument_id')
                ->where('instrument.is_active', true)
                ->orderByDesc('transition.changed_at')
                ->get([
                    'transition.id', 'transition.instrument_id', 'transition.release_id',
                    'transition.from_signal', 'transition.to_signal', 'transition.changed_at',
                    'transition.price_at_change', 'transition.score_at_change',
                    'transition.risk_at_change', 'instrument.symbol', 'instrument.name',
                    'instrument.country_code', 'instrument.currency',
                ])
                ->map(function (object $row): object {
                    $row->from_signal = strtoupper((string) $row->from_signal);
                    $row->to_signal = strtoupper((string) $row->to_signal);
                    $row->price_at_change = is_numeric($row->price_at_change) ? (float) $row->price_at_change : null;
                    $row->risk_at_change = is_numeric($row->risk_at_change) ? (int) $row->risk_at_change : null;

                    return $row;
                })
        );
    }

    public function currentStrategyTrades()
    {
        return DB::connection('serving')
            ->table('serving_strategy_trades as trade')
            ->join('serving_strategy_runs as strategy_run', 'strategy_run.id', '=', 'trade.strategy_run_id')
            ->join('serving_active_models as active_model', function ($join): void {
                $join->on('active_model.instrument_id', '=', 'trade.instrument_id')
                    ->whereRaw("strategy_run.source_metadata->>'release_id' = active_model.release_id::text");
            })
            ->join('serving_instruments as instrument', 'instrument.id', '=', 'trade.instrument_id')
            ->where('strategy_run.status', 'complete')
            ->where('instrument.is_active', true);
    }

    private function normalizeStock(object $row, Collection $predictions): object
    {
        $compact = $this->json($row->compact_metrics);
        $filters = $this->json($row->filter_summary);
        $calibration = $this->json($row->score_calibration);
        $predictionByHorizon = $predictions->keyBy(fn (object $prediction): int => (int) $prediction->horizon);
        $horizonPayload = (array) data_get($compact, 'horizons', []);
        $configuredHorizons = collect(array_keys($horizonPayload))
            ->merge($this->pgArray($row->completed_horizons))
            ->map(fn ($horizon): int => (int) $horizon)
            ->filter(fn (int $horizon): bool => in_array($horizon, [10, 20, 40], true))
            ->unique()
            ->sort()
            ->values();

        $horizons = $configuredHorizons->mapWithKeys(function (int $horizon) use ($compact, $calibration, $predictionByHorizon): array {
            $prefix = "horizons.{$horizon}";
            $variant = strtolower((string) data_get($compact, "{$prefix}.active_variant", data_get($compact, "active_models.{$horizon}.variant", 'standard')));
            $variantKey = $variant === 'pure_tcn' ? 'pure_tcn' : 'standard';
            $activeMetrics = (array) data_get($compact, "{$prefix}.{$variantKey}.metrics", []);
            $standardMetrics = (array) data_get($compact, "{$prefix}.standard.metrics", []);
            $tcnMetrics = (array) data_get($compact, "{$prefix}.pure_tcn.metrics", []);
            $activeConfig = (array) data_get($compact, "active_models.{$horizon}", []);
            $predictionStatusPayload = (array) data_get(
                $compact,
                "{$prefix}.{$variantKey}.prediction_status",
                []
            );
            $qualityGate = (array) data_get($predictionStatusPayload, 'quality_gate', []);
            $status = (string) ($activeConfig['prediction_status']
                ?? ($predictionStatusPayload['status'] ?? 'not_evaluated'));
            $enabled = (bool) ($activeConfig['prediction_enabled']
                ?? ($predictionStatusPayload['prediction_enabled'] ?? false));
            $prediction = $predictionByHorizon->get($horizon);

            return [$horizon => (object) [
                'horizon' => $horizon,
                'variant' => $variantKey,
                'variant_label' => $variantKey === 'pure_tcn' ? 'TCN' : 'Standard',
                'champion' => data_get($compact, "{$prefix}.standard.champion"),
                'quality_class' => strtolower((string) data_get($compact, "{$prefix}.quality.quality_class", 'open')),
                'quality_name' => (string) data_get($compact, "{$prefix}.quality.display_name", 'Offen'),
                'prediction_status' => $status,
                'prediction_enabled' => $enabled,
                'quality_gate_passed' => (bool) ($qualityGate['passed'] ?? false),
                'quality_gate_status' => (string) ($qualityGate['status'] ?? 'not_qualified'),
                'quality_gate_failed_gates' => array_values((array) ($qualityGate['failed_gates'] ?? [])),
                'skip_reason' => array_values((array) ($activeConfig['skip_reason'] ?? [])),
                'entry_threshold' => data_get($calibration, "{$horizon}.threshold"),
                'pure_tcn_entry_threshold' => data_get($calibration, "{$horizon}.pure_tcn_entry.threshold"),
                'metrics' => $this->metrics($activeMetrics),
                'standard_metrics' => $this->metrics($standardMetrics),
                'tcn_metrics' => $this->metrics($tcnMetrics),
                'prediction' => $prediction,
            ]];
        });

        $latestPrediction = $predictionByHorizon->sortByDesc('as_of')->first();
        $row->display_metadata = $this->json($row->display_metadata);
        $row->compact_metrics = $compact;
        $row->filter_summary = $filters;
        $row->score_calibration = $calibration;
        $row->completed_horizons = $configuredHorizons->all();
        $row->buy_horizons = $this->pgArray($row->buy_horizons);
        $row->strong_buy_horizons = $this->pgArray($row->strong_buy_horizons);
        $row->quality_class = strtolower(trim((string) ($row->quality_class ?: 'open')));
        $row->recommended_signal = strtoupper(trim((string) ($row->recommended_signal ?: 'WATCH')));
        $row->horizons = $horizons;
        $row->latest_prediction = $latestPrediction;
        $row->prediction_count = $predictionByHorizon->count();
        $row->eligible_horizon_count = $horizons->where('prediction_enabled', true)->count();
        $row->has_eligible_prediction = $latestPrediction !== null;

        return $row;
    }

    private function metrics(array $metrics): object
    {
        $decimalPercent = static fn (mixed $value): ?float => is_numeric($value) ? (float) $value * 100.0 : null;

        return (object) [
            'trades' => is_numeric($metrics['trades'] ?? null) ? (int) $metrics['trades'] : 0,
            'hit_rate' => $decimalPercent($metrics['hit_rate'] ?? null),
            'profit_factor' => is_numeric($metrics['profit_factor'] ?? null) ? (float) $metrics['profit_factor'] : null,
            'average_return' => $decimalPercent($metrics['average_net_trade'] ?? null),
            'median_return' => $decimalPercent($metrics['median_net_trade'] ?? null),
            'cumulative_return' => $decimalPercent($metrics['cumulative_return'] ?? null),
            'max_drawdown' => $decimalPercent($metrics['max_drawdown'] ?? null),
            'average_holding_days' => is_numeric($metrics['average_holding_days'] ?? null)
                ? (float) $metrics['average_holding_days']
                : null,
        ];
    }

    private function currentPrice(object $prediction): ?float
    {
        $current = data_get($prediction->context, 'current_price');
        if (is_numeric($current)) {
            return (float) $current;
        }
        if (is_numeric($prediction->target_price) && is_numeric($prediction->expected_return)) {
            $factor = 1.0 + (float) $prediction->expected_return;

            return $factor !== 0.0 ? (float) $prediction->target_price / $factor : null;
        }

        return null;
    }

    private function json(mixed $value): array
    {
        if (is_array($value)) {
            return $value;
        }
        if (is_object($value)) {
            return (array) $value;
        }
        if (! is_string($value) || trim($value) === '') {
            return [];
        }

        return (array) (json_decode($value, true) ?: []);
    }

    private function pgArray(mixed $value): array
    {
        if (is_array($value)) {
            return array_values($value);
        }
        if (! is_string($value) || trim($value, '{} ') === '') {
            return [];
        }

        return array_values(array_filter(str_getcsv(trim($value, '{}'), ',', '"', '')));
    }
}
