<?php

namespace App\Services;

use App\Models\Portfolio;
use App\Models\PortfolioPosition;
use App\Models\PortfolioTransaction;
use App\Models\SavedPredictionFilter;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final class AutomatedPortfolioService
{
    public function __construct(private readonly PersonalizedSignalService $signals) {}

    public function scan(): array
    {
        $stats = ['strategies' => 0, 'candidates' => 0, 'purchases' => 0, 'skipped' => 0];

        DB::table('portfolio_strategy_assignments as assignment')
            ->join('portfolios as portfolio', 'portfolio.id', '=', 'assignment.portfolio_id')
            ->join('saved_prediction_filters as strategy', 'strategy.id', '=', 'assignment.saved_prediction_filter_id')
            ->where('assignment.enabled', true)
            ->where('portfolio.active', true)
            ->where('portfolio.type', 'paper')
            ->where('strategy.automatic_portfolio_enabled', true)
            ->select('assignment.id', 'assignment.portfolio_id', 'assignment.saved_prediction_filter_id')
            ->orderBy('assignment.id')
            ->chunkById(50, function ($assignments) use (&$stats): void {
                foreach ($assignments as $assignment) {
                    $portfolio = Portfolio::query()->with('positions')->find($assignment->portfolio_id);
                    $strategy = SavedPredictionFilter::query()->with('user')->find($assignment->saved_prediction_filter_id);
                    if (! $portfolio || ! $strategy || ! data_get($portfolio->meta, 'automation.live_enabled', false)) {
                        $stats['skipped']++;
                        continue;
                    }
                    $stats['strategies']++;
                    $result = $this->execute($strategy, $portfolio);
                    $stats['candidates'] += $result['candidates'];
                    $stats['purchases'] += $result['purchases'];
                    $stats['skipped'] += $result['skipped'];
                }
            }, 'assignment.id', 'id');

        return $stats;
    }

    public function execute(SavedPredictionFilter $strategy, ?Portfolio $portfolio = null): array
    {
        $strategy->loadMissing('user');
        $portfolio ??= $strategy->portfolio;
        if (! $strategy->user || ! $portfolio || ! $portfolio->active || ! data_get($portfolio->meta, 'automation.live_enabled', false)) {
            return ['candidates' => 0, 'purchases' => 0, 'skipped' => 1];
        }

        $candidates = $this->candidates($strategy);
        $sectorRotation = (bool) data_get($strategy->filters, 'sector_score_rotation', false);
        $sectorAverages = $candidates->groupBy(fn (object $row) => (string) ($row->sector ?: 'Other'))
            ->map(fn (Collection $rows): float => (float) $rows->avg('score_10'));

        $candidates = $candidates->sortByDesc(function (object $row) use ($sectorRotation, $sectorAverages): string {
            $sectorScore = $sectorRotation ? (float) $sectorAverages->get((string) ($row->sector ?: 'Other'), 0) : 0;
            return sprintf('%09.4f:%09.4f:%09.4f', $sectorScore, (float) $row->score_10, (float) $row->confidence_percent);
        })->values();

        $purchases = 0;
        $skipped = 0;
        foreach ($candidates as $candidate) {
            $bought = DB::transaction(fn (): bool => $this->buyCandidate(
                $strategy,
                $portfolio,
                $candidate,
                (float) $sectorAverages->get((string) ($candidate->sector ?: 'Other'), 0),
            ), 3);
            $bought ? $purchases++ : $skipped++;
        }

        return ['candidates' => $candidates->count(), 'purchases' => $purchases, 'skipped' => $skipped];
    }

    private function candidates(SavedPredictionFilter $strategy): Collection
    {
        $filters = (array) $strategy->filters;
        $latestPredictionIds = DB::table('predictions')
            ->selectRaw('instrument_id, MAX(id) AS prediction_id')
            ->groupBy('instrument_id');
        $latestQuoteIds = DB::table('current_stock_quotes')
            ->where('status', 'ok')
            ->selectRaw('instrument_id, MAX(id) AS quote_id')
            ->groupBy('instrument_id');
        $latestQualityIds = DB::table('model_quality_rankings')
            ->selectRaw('trained_model_id, MAX(id) AS ranking_id')
            ->groupBy('trained_model_id');
        $latestTechnicalIds = DB::table('technical_indicators')
            ->where('interval', '1d')
            ->selectRaw('instrument_id, MAX(id) AS technical_id')
            ->groupBy('instrument_id');
        $latestFundamentalIds = DB::table('instrument_fundamentals')
            ->selectRaw('instrument_id, MAX(id) AS fundamental_id')
            ->groupBy('instrument_id');
        $backtestRunId = (int) DB::table('backtest_runs')
            ->whereIn('status', ['completed', 'completed_with_errors'])
            ->whereRaw("COALESCE(settings->>'run_type', 'system') <> 'user_filter'")
            ->orderByDesc('id')
            ->value('id');
        $backtestStats = DB::table('backtest_trades')
            ->where('backtest_run_id', $backtestRunId)
            ->select('instrument_id')
            ->selectRaw('MAX(ABS(max_drawdown)) * 100 AS drawdown_percent')
            ->selectRaw('SUM(CASE WHEN net_return > 0 THEN net_return ELSE 0 END) / NULLIF(ABS(SUM(CASE WHEN net_return < 0 THEN net_return ELSE 0 END)), 0) AS profit_factor')
            ->selectRaw('AVG(CASE WHEN net_return > 0 THEN 1.0 ELSE 0.0 END) * 100 AS hit_rate')
            ->groupBy('instrument_id');
        $signalSql = $this->signals->sql('prediction', $strategy->user);
        $scoreSql = '(CASE WHEN prediction.prediction_score <= 1 THEN prediction.prediction_score * 10 WHEN prediction.prediction_score <= 10 THEN prediction.prediction_score ELSE prediction.prediction_score / 10 END)';
        $confidenceSql = '(CASE WHEN prediction.confidence <= 1 THEN prediction.confidence * 100 ELSE prediction.confidence END)';
        $riskSql = '(CASE WHEN COALESCE(prediction.risk_score, prediction.drawdown_risk_factor) <= 1 THEN COALESCE(prediction.risk_score, prediction.drawdown_risk_factor) * 100 ELSE COALESCE(prediction.risk_score, prediction.drawdown_risk_factor) END)';
        $predictedReturnSql = '((prediction.predicted_price_20d - prediction.current_price) / NULLIF(prediction.current_price, 0)) * 100';
        $fundamentalNumber = static fn (string $key): string =>
            "(CASE WHEN NULLIF(fundamental.data::jsonb->>'{$key}', '') ~ '^-?[0-9]+([.][0-9]+)?$' THEN (fundamental.data::jsonb->>'{$key}')::numeric END)";
        $modelIds = collect((array) ($filters['model'] ?? []))->map(fn ($id) => (int) $id)->filter()->all();
        $minimumTiers = ['top' => ['top'], 'strong' => ['top', 'strong'], 'solid' => ['top', 'strong', 'solid'], 'test' => ['top', 'strong', 'solid', 'test']];
        $tier = (string) ($filters['quality_tier'] ?? '');

        return DB::table('predictions as prediction')
            ->joinSub($latestPredictionIds, 'latest_prediction', fn ($join) => $join->on('latest_prediction.prediction_id', '=', 'prediction.id'))
            ->join('instruments as instrument', 'instrument.id', '=', 'prediction.instrument_id')
            ->leftJoin('exchanges as exchange', 'exchange.id', '=', 'instrument.exchange_id')
            ->leftJoin('trained_models as trained_model', 'trained_model.id', '=', 'prediction.trained_model_id')
            ->leftJoin('model_definitions as model_definition', 'model_definition.id', '=', 'trained_model.model_definition_id')
            ->leftJoinSub($latestQualityIds, 'latest_quality', fn ($join) => $join->on('latest_quality.trained_model_id', '=', 'trained_model.id'))
            ->leftJoin('model_quality_rankings as quality_ranking', 'quality_ranking.id', '=', 'latest_quality.ranking_id')
            ->leftJoin('model_quality_tiers as quality_tier', 'quality_tier.id', '=', 'quality_ranking.tier_id')
            ->leftJoinSub($latestTechnicalIds, 'latest_technical', fn ($join) => $join->on('latest_technical.instrument_id', '=', 'instrument.id'))
            ->leftJoin('technical_indicators as technical', 'technical.id', '=', 'latest_technical.technical_id')
            ->leftJoinSub($latestFundamentalIds, 'latest_fundamental', fn ($join) => $join->on('latest_fundamental.instrument_id', '=', 'instrument.id'))
            ->leftJoin('instrument_fundamentals as fundamental', 'fundamental.id', '=', 'latest_fundamental.fundamental_id')
            ->leftJoinSub($backtestStats, 'backtest_stat', fn ($join) => $join->on('backtest_stat.instrument_id', '=', 'instrument.id'))
            ->leftJoinSub($latestQuoteIds, 'latest_quote', fn ($join) => $join->on('latest_quote.instrument_id', '=', 'instrument.id'))
            ->leftJoin('current_stock_quotes as quote', 'quote.id', '=', 'latest_quote.quote_id')
            ->where('instrument.type', 'stock')
            ->where('instrument.is_active', true)
            ->whereNull('instrument.deleted_at')
            ->whereRaw("({$signalSql}) = 'BUY'")
            ->whereRaw('COALESCE(prediction.predicted_price_20d, prediction.current_price) >= prediction.current_price')
            ->whereNotExists(fn (Builder $query) => $query->selectRaw('1')
                ->from('portfolio_automation_executions as execution')
                ->where('execution.saved_prediction_filter_id', $strategy->id)
                ->whereColumn('execution.prediction_id', 'prediction.id'))
            ->when(($filters['q'] ?? '') !== '', function ($query) use ($filters): void {
                $term = '%'.strtolower(trim((string) $filters['q'])).'%';
                $query->where(fn ($nested) => $nested
                    ->whereRaw('LOWER(instrument.symbol) LIKE ?', [$term])
                    ->orWhereRaw('LOWER(instrument.name) LIKE ?', [$term]));
            })
            ->when(($filters['country'] ?? '') !== '', fn ($query) => $query->where('instrument.country', strtoupper((string) $filters['country'])))
            ->when(($filters['sector'] ?? '') !== '', fn ($query) => $query->where('instrument.sector', (string) $filters['sector']))
            ->when(($filters['exchange'] ?? '') !== '', fn ($query) => $query->where('exchange.code', strtoupper((string) $filters['exchange'])))
            ->when(in_array($filters['ai_type'] ?? null, ['horizon', 'pulse'], true), fn ($query) => $query->where('prediction.ai_type', $filters['ai_type']))
            ->when($modelIds !== [], fn ($query) => $query->whereIn('model_definition.id', $modelIds))
            ->when(isset($minimumTiers[$tier]), fn ($query) => $query->whereIn('quality_tier.code', $minimumTiers[$tier]))
            ->when($tier === 'unqualified', fn ($query) => $query->whereNull('quality_tier.code'))
            ->whereRaw("{$scoreSql} >= ?", [(float) ($filters['score_min'] ?? 0)])
            ->whereRaw("{$confidenceSql} >= ?", [(float) ($filters['confidence_min'] ?? 0)])
            ->when((float) ($filters['predicted_return_min'] ?? -50) > -50, fn ($query) =>
                $query->whereRaw("{$predictedReturnSql} >= ?", [(float) $filters['predicted_return_min']]))
            ->when((float) ($filters['risk_max'] ?? 100) < 100, fn ($query) =>
                $query->whereRaw("{$riskSql} <= ?", [(float) $filters['risk_max']]))
            ->when((float) ($filters['drawdown_max'] ?? 50) < 50, fn ($query) =>
                $query->where('backtest_stat.drawdown_percent', '<=', (float) $filters['drawdown_max']))
            ->when((float) ($filters['profit_factor_min'] ?? 0) > 0, fn ($query) =>
                $query->where('backtest_stat.profit_factor', '>=', (float) $filters['profit_factor_min']))
            ->when((float) ($filters['hit_rate_min'] ?? 0) > 0, fn ($query) =>
                $query->where('backtest_stat.hit_rate', '>=', (float) $filters['hit_rate_min']))
            ->when((float) ($filters['volatility_max'] ?? 100) < 100, fn ($query) =>
                $query->whereRaw('technical.volatility_20 * 100 <= ?', [(float) $filters['volatility_max']]))
            ->when((float) ($filters['pe_max'] ?? 100) < 100, fn ($query) =>
                $query->whereRaw($fundamentalNumber('trailingPE').' <= ?', [(float) $filters['pe_max']]))
            ->when((float) ($filters['dividend_yield_min'] ?? 0) > 0, fn ($query) =>
                $query->whereRaw($fundamentalNumber('dividendYield').' >= ?', [(float) $filters['dividend_yield_min']]))
            ->when((float) ($filters['market_cap_min'] ?? 0) > 0, fn ($query) =>
                $query->whereRaw($fundamentalNumber('marketCap').' >= ?', [(float) $filters['market_cap_min'] * 1_000_000_000]))
            ->when((float) ($filters['revenue_growth_min'] ?? -50) > -50, fn ($query) =>
                $query->whereRaw($fundamentalNumber('revenueGrowth').' >= ?', [(float) $filters['revenue_growth_min'] / 100]))
            ->select([
                'prediction.id as prediction_id', 'prediction.instrument_id', 'prediction.prediction_time',
                'prediction.current_price', 'prediction.predicted_price_20d', 'instrument.symbol', 'instrument.name',
                'instrument.sector', 'instrument.currency', 'quote.price as quote_price',
            ])
            ->selectRaw("{$scoreSql} AS score_10")
            ->selectRaw("{$confidenceSql} AS confidence_percent")
            ->get();
    }

    private function buyCandidate(SavedPredictionFilter $strategy, Portfolio $assignedPortfolio, object $candidate, float $sectorAverage): bool
    {
        if (DB::table('portfolio_automation_executions')->where('saved_prediction_filter_id', $strategy->id)->where('prediction_id', $candidate->prediction_id)->exists()) return false;

        /** @var Portfolio|null $portfolio */
        $portfolio = Portfolio::query()->lockForUpdate()->find($assignedPortfolio->id);
        if (! $portfolio || ! $portfolio->active || ! data_get($portfolio->meta, 'automation.live_enabled', false)) return false;

        $meta = (array) $portfolio->meta;
        $initialCapital = max(1000.0, (float) data_get($meta, 'automation.initial_capital', 10000));
        $maxUnits = max(1, min(50, (int) data_get($strategy->filters, 'max_positions', 5)));
        $tradeCost = max(0.0, (float) data_get($meta, 'automation.trade_cost', 10));
        $baseCapital = $initialCapital / $maxUnits;
        $cashAccount = DB::table('portfolio_cash_accounts')
            ->where('portfolio_id', $portfolio->id)
            ->where('currency', $portfolio->currency)
            ->lockForUpdate()->first();
        if (! $cashAccount) return false;
        $cash = max(0.0, (float) $cashAccount->balance - (float) $cashAccount->reserved_balance);
        $usedUnits = PortfolioPosition::query()->where('portfolio_id', $portfolio->id)->get()
            ->sum(fn (PortfolioPosition $position): int => max(1, (int) data_get($position->meta, 'automation.position_factor', 1)));
        $availableUnits = max(0, $maxUnits - $usedUnits);
        $maximumFactor = max(1, min($maxUnits, (int) data_get($strategy->filters, 'position_factor', 1)));
        $affordableUnits = (int) floor(max(0, $cash - $tradeCost) / $baseCapital);
        $factor = min($maximumFactor, $availableUnits, $affordableUnits);
        if ($factor < 1) return false;

        $price = (float) ($candidate->quote_price ?: $candidate->current_price);
        if ($price <= 0) return false;
        $allocated = min($cash - $tradeCost, $baseCapital * $factor);
        $quantity = floor($allocated / $price);
        if ($quantity < 1) return false;
        $allocated = $quantity * $price;

        $position = PortfolioPosition::query()->firstOrNew([
            'portfolio_id' => $portfolio->id,
            'instrument_id' => $candidate->instrument_id,
        ]);
        $oldQuantity = (float) ($position->quantity ?? 0);
        $newQuantity = $oldQuantity + $quantity;
        $positionMeta = (array) $position->meta;
        data_set($positionMeta, 'automation.position_factor', max(1, (int) data_get($positionMeta, 'automation.position_factor', 0)) + ($position->exists ? $factor : $factor - 1));
        data_set($positionMeta, 'automation.strategy_id', $strategy->id);
        $position->fill([
            'quantity' => $newQuantity,
            'average_buy_price' => $newQuantity > 0
                ? (($oldQuantity * (float) ($position->average_buy_price ?? 0)) + ($quantity * $price)) / $newQuantity
                : $price,
            'current_price' => $price,
            'opened_at_date' => $position->opened_at_date ?: now()->toDateString(),
            'meta' => $positionMeta,
        ])->save();

        $transaction = PortfolioTransaction::query()->create([
            'portfolio_id' => $portfolio->id,
            'instrument_id' => $candidate->instrument_id,
            'type' => 'buy',
            'transaction_date' => now()->toDateString(),
            'quantity' => $quantity,
            'price' => $price,
            'fees' => $tradeCost,
            'currency' => $candidate->currency ?: $portfolio->currency,
            'meta' => [
                'source' => 'strategy_automation', 'strategy_id' => $strategy->id,
                'prediction_id' => $candidate->prediction_id, 'sector' => $candidate->sector,
                'sector_average_score' => round($sectorAverage, 4), 'position_factor' => $factor,
            ],
        ]);

        $balanceAfterPurchase = (float) $cashAccount->balance - $allocated;
        $balanceAfterFee = $balanceAfterPurchase - $tradeCost;
        if ($balanceAfterFee < 0) throw new \RuntimeException('Verrechnungskonto reicht für Kauf und Gebühren nicht aus.');
        DB::table('portfolio_cash_accounts')->where('id', $cashAccount->id)->update([
            'balance' => $balanceAfterFee, 'updated_at' => now(),
        ]);
        DB::table('portfolio_cash_ledger')->insert([
            [
                'portfolio_cash_account_id' => $cashAccount->id, 'portfolio_transaction_id' => $transaction->id,
                'type' => 'purchase_debit', 'amount' => -$allocated, 'balance_after' => $balanceAfterPurchase,
                'currency' => $cashAccount->currency, 'occurred_at' => now(),
                'meta' => json_encode(['source' => 'laravel_strategy_automation', 'prediction_id' => $candidate->prediction_id], JSON_THROW_ON_ERROR),
                'created_at' => now(), 'updated_at' => now(),
            ],
            [
                'portfolio_cash_account_id' => $cashAccount->id, 'portfolio_transaction_id' => $transaction->id,
                'type' => 'fee', 'amount' => -$tradeCost, 'balance_after' => $balanceAfterFee,
                'currency' => $cashAccount->currency, 'occurred_at' => now(),
                'meta' => json_encode(['source' => 'laravel_strategy_automation', 'prediction_id' => $candidate->prediction_id], JSON_THROW_ON_ERROR),
                'created_at' => now(), 'updated_at' => now(),
            ],
        ]);

        DB::table('portfolio_automation_executions')->insert([
            'saved_prediction_filter_id' => $strategy->id,
            'portfolio_id' => $portfolio->id,
            'prediction_id' => $candidate->prediction_id,
            'instrument_id' => $candidate->instrument_id,
            'portfolio_transaction_id' => $transaction->id,
            'action' => $position->wasRecentlyCreated ? 'buy' : 'increase',
            'sector_average_score' => $sectorAverage,
            'position_factor' => $factor,
            'allocated_capital' => $allocated,
            'details' => json_encode(['symbol' => $candidate->symbol, 'score' => $candidate->score_10, 'confidence' => $candidate->confidence_percent], JSON_THROW_ON_ERROR),
            'created_at' => now(), 'updated_at' => now(),
        ]);

        return true;
    }
}
