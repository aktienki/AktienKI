<?php

namespace App\Services;

use App\Models\Portfolio;
use App\Models\PortfolioPosition;
use App\Models\PortfolioTransaction;
use App\Models\SavedPredictionFilter;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

final class AutomatedPortfolioService
{
    public function __construct(
        private readonly PersonalizedSignalService $signals,
        private readonly VariableExitStrategyService $exitStrategies,
        private readonly TechnicalPriceLevelService $priceLevels,
    ) {}

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

        $this->processDynamicExits($strategy, $portfolio);
        $candidates = $this->candidates($strategy);
        $reservedInstrumentIds = $this->processEntryReservations($strategy, $portfolio, $candidates);
        $candidates = $candidates->reject(fn (object $candidate): bool => $reservedInstrumentIds->contains((int) $candidate->instrument_id))->values();
        $sectorRotation = (bool) data_get($strategy->filters, 'sector_score_rotation', false);
        $indexRotation = (bool) data_get($strategy->filters, 'index_score_rotation', false);
        $sectorAverages = $candidates->groupBy(fn (object $row) => (string) ($row->sector ?: 'Other'))
            ->map(fn (Collection $rows): float => (float) $rows->avg('score_10'));
        $memberships = $indexRotation
            ? DB::table('index_memberships')->whereNull('removed_at')
                ->whereIn('instrument_id', $candidates->pluck('instrument_id')->unique())
                ->get(['instrument_id', 'market_index_id'])->groupBy('instrument_id')
            : collect();
        $candidateByInstrument = $candidates->keyBy('instrument_id');
        $indexAverages = $indexRotation
            ? $memberships->flatten(1)->groupBy('market_index_id')->map(function (Collection $rows) use ($candidateByInstrument): float {
                return (float) $rows->map(fn (object $membership): float => (float) ($candidateByInstrument->get($membership->instrument_id)?->score_10 ?? 0))->avg();
            })
            : collect();
        if (Schema::hasTable('market_context_predictions') && ($sectorRotation || $indexRotation)) {
            $snapshotDate = DB::table('market_context_predictions')->max('prediction_date');
            if ($snapshotDate) {
                if ($sectorRotation) {
                    $storedSectorAverages = DB::table('market_context_predictions')
                        ->where('prediction_date', $snapshotDate)->where('scope_type', 'sector')
                        ->pluck('score', 'scope_key')->map(fn ($score): float => (float) $score);
                    if ($storedSectorAverages->isNotEmpty()) $sectorAverages = $storedSectorAverages;
                }
                if ($indexRotation) {
                    $storedIndexAverages = DB::table('market_context_predictions')
                        ->where('prediction_date', $snapshotDate)->where('scope_type', 'index')
                        ->pluck('score', 'scope_key')->mapWithKeys(fn ($score, $key): array => [(int) $key => (float) $score]);
                    if ($storedIndexAverages->isNotEmpty()) $indexAverages = $storedIndexAverages;
                }
            }
        }

        $candidates = $candidates->sortByDesc(function (object $row) use ($sectorRotation, $indexRotation, $sectorAverages, $memberships, $indexAverages): string {
            $sectorScore = $sectorRotation ? (float) $sectorAverages->get((string) ($row->sector ?: 'Other'), 0) : 0;
            $indexScore = $indexRotation
                ? (float) collect($memberships->get($row->instrument_id, collect()))
                    ->map(fn (object $membership): float => (float) $indexAverages->get($membership->market_index_id, 0))->max()
                : 0;
            $rotationScores = array_filter([
                $sectorRotation ? $sectorScore : null,
                $indexRotation ? $indexScore : null,
            ], fn ($score): bool => $score !== null);
            $rotationScore = $rotationScores === [] ? 0 : array_sum($rotationScores) / count($rotationScores);

            // The stock score remains primary. Rotation only resolves equal scores.
            return sprintf('%09.4f:%09.4f:%09.4f', (float) $row->score_10, $rotationScore, (float) $row->confidence_percent);
        })->values();

        $purchases = 0;
        $skipped = 0;
        foreach ($candidates as $candidate) {
            $candidateIndexScore = $indexRotation
                ? collect($memberships->get($candidate->instrument_id, collect()))
                    ->map(fn (object $membership): float => (float) $indexAverages->get($membership->market_index_id, 0))->max()
                : null;
            $bought = DB::transaction(fn (): bool => $this->buyCandidate(
                $strategy,
                $portfolio,
                $candidate,
                (float) $sectorAverages->get((string) ($candidate->sector ?: 'Other'), 0),
                $candidateIndexScore !== null ? (float) $candidateIndexScore : null,
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
            ->selectRaw('COUNT(*) AS historical_trades')
            ->groupBy('instrument_id');
        $signalSql = $this->signals->sql('prediction', $strategy->user);
        $scoreSql = '(CASE WHEN prediction.prediction_score <= 1 THEN prediction.prediction_score * 10 WHEN prediction.prediction_score <= 10 THEN prediction.prediction_score ELSE prediction.prediction_score / 10 END)';
        $confidenceSql = '(CASE WHEN prediction.confidence <= 1 THEN prediction.confidence * 100 ELSE prediction.confidence END)';
        $riskSql = '(CASE WHEN COALESCE(prediction.risk_score, prediction.drawdown_risk_factor) <= 1 THEN COALESCE(prediction.risk_score, prediction.drawdown_risk_factor) * 100 ELSE COALESCE(prediction.risk_score, prediction.drawdown_risk_factor) END)';
        $predictedReturnSql = '((prediction.predicted_price_20d - prediction.current_price) / NULLIF(prediction.current_price, 0)) * 100';
        $fundamentalNumber = static fn (string $key): string => match ($key) {
            'trailingPE' => "COALESCE(fundamental.trailing_pe, CASE WHEN NULLIF(fundamental.data::jsonb->>'trailingPE', '') ~ '^-?[0-9]+([.][0-9]+)?$' THEN (fundamental.data::jsonb->>'trailingPE')::numeric END)",
            'dividendYield' => "COALESCE(fundamental.dividend_yield, CASE WHEN NULLIF(fundamental.data::jsonb->>'dividendYield', '') ~ '^-?[0-9]+([.][0-9]+)?$' THEN (fundamental.data::jsonb->>'dividendYield')::numeric END)",
            'marketCap' => "COALESCE(fundamental.market_cap, CASE WHEN NULLIF(fundamental.data::jsonb->>'marketCap', '') ~ '^-?[0-9]+([.][0-9]+)?$' THEN (fundamental.data::jsonb->>'marketCap')::numeric END)",
            'revenueGrowth' => "COALESCE(fundamental.revenue_growth, CASE WHEN NULLIF(fundamental.data::jsonb->>'revenueGrowth', '') ~ '^-?[0-9]+([.][0-9]+)?$' THEN (fundamental.data::jsonb->>'revenueGrowth')::numeric END)",
        };
        $modelIds = collect((array) ($filters['model'] ?? []))->map(fn ($id) => (int) $id)->filter()->all();
        $minimumTiers = ['top' => ['strong'], 'strong' => ['strong'], 'solid' => ['strong', 'solid'], 'test' => ['strong', 'solid', 'test']];
        $tier = (string) ($filters['quality_tier'] ?? '');
        $profileLimits = match ($this->signals->riskLevel($strategy->user)) {
            'cautious' => ['risk' => 35.0, 'volatility' => 45.0, 'drawdown' => 25.0, 'confidence' => 65.0, 'trades' => 20],
            'opportunity_oriented' => ['risk' => 80.0, 'volatility' => 100.0, 'drawdown' => 50.0, 'confidence' => 45.0, 'trades' => 10],
            default => ['risk' => 60.0, 'volatility' => 65.0, 'drawdown' => 40.0, 'confidence' => 55.0, 'trades' => 15],
        };

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
            ->where('instrument.is_german_tradeable', true)
            ->whereNull('instrument.deleted_at')
            ->whereRaw("({$signalSql}) = 'BUY'")
            ->whereRaw('COALESCE(prediction.predicted_price_20d, prediction.current_price) >= prediction.current_price')
            // Hard pre-selection by the user's risk profile. Rotation and ranking only see this reduced universe.
            ->whereRaw("({$riskSql} IS NULL OR {$riskSql} <= ?)", [$profileLimits['risk']])
            ->whereRaw("{$confidenceSql} >= ?", [$profileLimits['confidence']])
            ->whereRaw('(technical.volatility_20 IS NULL OR technical.volatility_20 * 100 <= ?)', [$profileLimits['volatility']])
            ->whereRaw('(backtest_stat.drawdown_percent IS NULL OR backtest_stat.drawdown_percent <= ?)', [$profileLimits['drawdown']])
            ->whereRaw('COALESCE(backtest_stat.historical_trades, 0) >= ?', [$profileLimits['trades']])
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
            ->when(($filters['dividend_yield_operator'] ?? 'gte') === 'lte' || (float) ($filters['dividend_yield_min'] ?? 0) > 0, function ($query) use ($filters, $fundamentalNumber) {
                $operator = ($filters['dividend_yield_operator'] ?? 'gte') === 'lte' ? '<=' : '>=';
                return $query->whereRaw($fundamentalNumber('dividendYield').' '.$operator.' ?', [(float) ($filters['dividend_yield_min'] ?? 0) / 100]);
            })
            ->when((float) ($filters['market_cap_min'] ?? 0) > 0, fn ($query) =>
                $query->whereRaw($fundamentalNumber('marketCap').' >= ?', [(float) $filters['market_cap_min'] * 1_000_000_000]))
            ->when(in_array($filters['market_cap_group'] ?? 'all', ['small', 'mid', 'large'], true), function ($query) use ($filters, $fundamentalNumber) {
                $value = $fundamentalNumber('marketCap');
                return match ($filters['market_cap_group']) {
                    'small' => $query->whereRaw($value.' < ?', [2_000_000_000]),
                    'mid' => $query->whereRaw($value.' >= ? AND '.$value.' < ?', [2_000_000_000, 10_000_000_000]),
                    'large' => $query->whereRaw($value.' >= ?', [10_000_000_000]),
                };
            })
            ->when((float) ($filters['revenue_growth_min'] ?? -50) > -50, fn ($query) =>
                $query->whereRaw($fundamentalNumber('revenueGrowth').' >= ?', [(float) $filters['revenue_growth_min'] / 100]))
            ->select([
                'prediction.id as prediction_id', 'prediction.instrument_id', 'prediction.prediction_time',
                'prediction.current_price', 'prediction.predicted_price_5d', 'prediction.predicted_price_10d',
                'prediction.predicted_price_15d', 'prediction.predicted_price_20d', 'instrument.symbol', 'instrument.name',
                'instrument.sector', 'instrument.currency', 'quote.price as quote_price',
            ])
            ->selectRaw("{$scoreSql} AS score_10")
            ->selectRaw("{$confidenceSql} AS confidence_percent")
            ->get();
    }

    private function buyCandidate(SavedPredictionFilter $strategy, Portfolio $assignedPortfolio, object $candidate, float $sectorAverage, ?float $indexAverage = null, bool $reservationReleased = false): bool
    {
        if (DB::table('portfolio_automation_executions')->where('saved_prediction_filter_id', $strategy->id)->where('prediction_id', $candidate->prediction_id)->exists()) return false;

        /** @var Portfolio|null $portfolio */
        $portfolio = Portfolio::query()->lockForUpdate()->find($assignedPortfolio->id);
        if (! $portfolio || ! $portfolio->active || ! data_get($portfolio->meta, 'automation.live_enabled', false)) return false;

        $meta = (array) $portfolio->meta;
        $initialCapital = max(1000.0, (float) data_get(
            $strategy->filters,
            'initial_capital',
            data_get($meta, 'automation.initial_capital', 10000),
        ));
        $maxUnits = max(1, min(50, (int) data_get($strategy->filters, 'max_positions', 5)));
        $tradeCost = max(0.0, (float) data_get(
            $strategy->filters,
            'trade_cost',
            data_get($meta, 'automation.trade_cost', 10),
        ));
        $baseCapital = $initialCapital / $maxUnits;
        $cashAccount = DB::table('portfolio_cash_accounts')
            ->where('portfolio_id', $portfolio->id)
            ->where('currency', $portfolio->currency)
            ->lockForUpdate()->first();
        if (! $cashAccount) return false;
        $cash = max(0.0, (float) $cashAccount->balance - (float) $cashAccount->reserved_balance);
        $usedUnits = PortfolioPosition::query()->where('portfolio_id', $portfolio->id)->get()
            ->sum(fn (PortfolioPosition $position): int => max(1, (int) data_get($position->meta, 'automation.position_factor', 1)));
        $usedUnits += (int) DB::table('portfolio_strategy_reservations')->where('portfolio_id', $portfolio->id)
            ->where('status', 'active')->where('instrument_id', '<>', $candidate->instrument_id)->sum('position_factor');
        $availableUnits = max(0, $maxUnits - $usedUnits);
        $maximumFactor = max(1, min($maxUnits, (int) data_get($strategy->filters, 'position_factor', 1)));
        $affordableUnits = (int) floor(max(0, $cash - $tradeCost) / $baseCapital);
        $factor = min($maximumFactor, $availableUnits, $affordableUnits);
        if ($factor < 1) return false;

        $price = (float) ($candidate->quote_price ?: $candidate->current_price);
        if ($price <= 0) return false;
        $exitStrategy = (bool) data_get($strategy->filters, 'dynamic_horizon_exit_enabled', false)
            ? $this->exitStrategies->resolveForPrediction((int) $candidate->instrument_id, $candidate)
            : $this->exitStrategies->resolve((int) $candidate->instrument_id);
        $allocated = min($cash - $tradeCost, $baseCapital * $factor);
        if (! $reservationReleased && (bool) data_get($strategy->filters, 'entry_wait_5d_enabled', false)) {
            $targets = collect([5, 10, 15, 20])->map(fn (int $days): float => (float) data_get($candidate, 'predicted_price_'.$days.'d', 0))->filter(fn (float $target): bool => $target > 0);
            $highestTarget = (float) ($targets->max() ?? 0);
            if ($highestTarget > 0 && $price > $highestTarget) {
                $reservedCapital = min((float) $cashAccount->balance - (float) $cashAccount->reserved_balance, $allocated + $tradeCost);
                if ($reservedCapital <= 0 || DB::table('portfolio_strategy_reservations')->where('saved_prediction_filter_id', $strategy->id)
                    ->where('instrument_id', $candidate->instrument_id)->where('status', 'active')->exists()) return false;
                DB::table('portfolio_strategy_reservations')->insert([
                    'saved_prediction_filter_id' => $strategy->id, 'portfolio_id' => $portfolio->id,
                    'prediction_id' => $candidate->prediction_id, 'instrument_id' => $candidate->instrument_id,
                    'reserved_capital' => $reservedCapital, 'position_factor' => $factor, 'status' => 'active',
                    'expires_at' => now()->addDays(5),
                    'details' => json_encode(['reason' => 'current_performance_above_prediction', 'quote_price' => $price,
                        'highest_target_price' => $highestTarget, 'symbol' => $candidate->symbol], JSON_THROW_ON_ERROR),
                    'created_at' => now(), 'updated_at' => now(),
                ]);
                DB::table('portfolio_cash_accounts')->where('id', $cashAccount->id)->update([
                    'reserved_balance' => (float) $cashAccount->reserved_balance + $reservedCapital, 'updated_at' => now(),
                ]);
                return false;
            }
        }
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
        data_set($positionMeta, 'automation.exit_strategy', 'variable_instrument_horizon');
        data_set($positionMeta, 'automation.exit_holding_days', $exitStrategy['holding_days']);
        data_set($positionMeta, 'automation.exit_profile_id', $exitStrategy['profile_id']);
        data_set($positionMeta, 'automation.exit_model_signature', $exitStrategy['model_signature']);
        data_set($positionMeta, 'automation.exit_target_price', $exitStrategy['target_price'] ?? null);
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
                'index_average_score' => $indexAverage !== null ? round($indexAverage, 4) : null,
                'base_position_capital' => round($baseCapital, 2),
                'target_position_capital' => round($baseCapital * $factor, 2),
                'calculated_quantity' => (int) $quantity,
                'exit_strategy' => 'variable_instrument_horizon',
                'exit_holding_days' => $exitStrategy['holding_days'],
                'exit_profile_id' => $exitStrategy['profile_id'],
                'exit_profile_source' => $exitStrategy['source'],
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
            'details' => json_encode([
                'symbol' => $candidate->symbol,
                'score' => $candidate->score_10,
                'confidence' => $candidate->confidence_percent,
                'index_average_score' => $indexAverage !== null ? round($indexAverage, 4) : null,
                'base_position_capital' => round($baseCapital, 2),
                'target_position_capital' => round($baseCapital * $factor, 2),
                'calculated_quantity' => (int) $quantity,
                'exit_holding_days' => $exitStrategy['holding_days'],
                'exit_profile_id' => $exitStrategy['profile_id'],
            ], JSON_THROW_ON_ERROR),
            'created_at' => now(), 'updated_at' => now(),
        ]);

        return true;
    }

    private function processDynamicExits(SavedPredictionFilter $strategy, Portfolio $portfolio): void
    {
        $fixed20dExit = (bool) data_get($strategy->filters, 'fixed_20d_exit_enabled', false);
        $dynamicHorizonExit = (bool) data_get($strategy->filters, 'dynamic_horizon_exit_enabled', false);
        $supportStop = (bool) data_get($strategy->filters, 'support_stop_enabled', false);
        $resistanceExit = (bool) data_get($strategy->filters, 'resistance_trailing_stop_enabled', false);
        $forecastBelowPriceExit = (string) data_get($strategy->filters, 'exit_strategy', '') === 'forecast_below_price'
            || (bool) data_get($strategy->filters, 'forecast_below_price_exit_enabled', false);
        if (! $fixed20dExit && ! $dynamicHorizonExit && ! $supportStop && ! $resistanceExit && ! $forecastBelowPriceExit) return;

        PortfolioPosition::query()->where('portfolio_id', $portfolio->id)->get()
            ->filter(fn (PortfolioPosition $position): bool => (int) data_get($position->meta, 'automation.strategy_id', 0) === (int) $strategy->id)
            ->each(function (PortfolioPosition $position) use ($strategy, $portfolio, $fixed20dExit, $dynamicHorizonExit, $supportStop, $resistanceExit, $forecastBelowPriceExit): void {
                $quote = DB::table('current_stock_quotes')->where('instrument_id', $position->instrument_id)
                    ->whereIn('status', ['ok', 'current'])->orderByDesc('quote_time')->orderByDesc('id')->value('price');
                $price = is_numeric($quote) ? (float) $quote : (float) $position->current_price;
                if ($price <= 0 || $position->quantity <= 0) return;

                $levels = $this->priceLevels->levels((int) $position->instrument_id);
                $tradingDaysHeld = $position->opened_at_date
                    ? DB::table('price_bars')->where('instrument_id', $position->instrument_id)->where('interval', '1d')
                        ->whereDate('bar_time', '>=', $position->opened_at_date->toDateString())->distinct('bar_time')->count('bar_time')
                    : 0;
                $fixedExitTrigger = $fixed20dExit && $tradingDaysHeld >= 20;
                $dynamicExitDays = max(1, (int) data_get($position->meta, 'automation.exit_holding_days', 20));
                $dynamicExitTrigger = $dynamicHorizonExit && $tradingDaysHeld >= $dynamicExitDays;
                $supportTrigger = $supportStop && is_numeric($levels['support']) && $price < (float) $levels['support'] * .99;
                $latestForecast = $forecastBelowPriceExit
                    ? DB::table('predictions')->where('instrument_id', $position->instrument_id)
                        ->orderByDesc('prediction_time')->orderByDesc('id')->value('predicted_price_20d')
                    : null;
                $forecastBelowPriceTrigger = $forecastBelowPriceExit && is_numeric($latestForecast)
                    && (float) $latestForecast > 0 && (float) $latestForecast < $price;
                $positionMeta = (array) $position->meta;
                $trailingStop = data_get($positionMeta, 'automation.resistance_trailing_stop');
                $profitable = $price > (float) $position->average_buy_price;
                if ($resistanceExit && $profitable && is_numeric($levels['broken_resistance'])) {
                    $newStop = (float) $levels['broken_resistance'] * .99;
                    if (! is_numeric($trailingStop) || $newStop > (float) $trailingStop) {
                        data_set($positionMeta, 'automation.resistance_trailing_stop', $newStop);
                        data_set($positionMeta, 'automation.resistance_broken_at', now()->toIso8601String());
                        $position->forceFill(['meta' => $positionMeta, 'current_price' => $price])->save();
                        $trailingStop = $newStop;
                    }
                }
                $trailingTrigger = is_numeric($trailingStop) && $price < (float) $trailingStop;
                if (! $fixedExitTrigger && ! $dynamicExitTrigger && ! $supportTrigger && ! $trailingTrigger && ! $forecastBelowPriceTrigger) return;

                $reason = $fixedExitTrigger ? 'fixed_20_trading_days'
                    : ($dynamicExitTrigger ? 'dynamic_prediction_horizon'
                    : ($supportTrigger ? 'support_stop_1_percent'
                    : ($trailingTrigger ? 'resistance_trailing_stop' : 'forecast_below_current_price')));
                DB::transaction(function () use ($strategy, $position, $portfolio, $price, $levels, $reason): void {
                    $locked = PortfolioPosition::query()->lockForUpdate()->find($position->id);
                    if (! $locked) return;
                    $quantity = (float) $locked->quantity;
                    $proceeds = $quantity * $price;
                    $costBasis = $quantity * (float) $locked->average_buy_price;
                    $account = DB::table('portfolio_cash_accounts')->where('portfolio_id', $portfolio->id)
                        ->where('currency', $portfolio->currency)->lockForUpdate()->first();
                    if (! $account) return;
                    $transactionId = DB::table('portfolio_transactions')->insertGetId([
                        'portfolio_id' => $portfolio->id, 'instrument_id' => $locked->instrument_id,
                        'type' => 'sell', 'transaction_date' => now()->toDateString(), 'quantity' => $quantity,
                        'price' => $price, 'fees' => 0, 'currency' => $portfolio->currency,
                        'meta' => json_encode(['source' => 'strategy_automation', 'exit_reason' => $reason,
                            'support' => $levels['support'], 'resistance' => $levels['resistance'],
                            'realized_profit' => $proceeds - $costBasis], JSON_THROW_ON_ERROR),
                        'created_at' => now(), 'updated_at' => now(),
                    ]);
                    $balance = (float) $account->balance + $proceeds;
                    DB::table('portfolio_cash_accounts')->where('id', $account->id)->update(['balance' => $balance, 'updated_at' => now()]);
                    DB::table('portfolio_cash_ledger')->insert([
                        'portfolio_cash_account_id' => $account->id, 'portfolio_transaction_id' => $transactionId,
                        'type' => 'sale_credit', 'amount' => $proceeds, 'balance_after' => $balance,
                        'currency' => $portfolio->currency, 'occurred_at' => now(),
                        'meta' => json_encode(['source' => 'strategy_automation', 'exit_reason' => $reason], JSON_THROW_ON_ERROR),
                        'created_at' => now(), 'updated_at' => now(),
                    ]);
                    $predictionId = (int) DB::table('predictions')->where('instrument_id', $locked->instrument_id)->latest('id')->value('id');
                    if ($predictionId > 0) DB::table('portfolio_automation_executions')->insert([
                        'saved_prediction_filter_id' => $strategy->id, 'portfolio_id' => $portfolio->id,
                        'prediction_id' => $predictionId, 'instrument_id' => $locked->instrument_id,
                        'portfolio_transaction_id' => $transactionId, 'action' => 'sell',
                        'position_factor' => max(1, (int) data_get($locked->meta, 'automation.position_factor', 1)),
                        'allocated_capital' => $proceeds, 'details' => json_encode(['exit_reason' => $reason,
                            'support' => $levels['support'], 'resistance' => $levels['resistance']], JSON_THROW_ON_ERROR),
                        'created_at' => now(), 'updated_at' => now(),
                    ]);
                    $locked->delete();
                }, 3);
            });
    }

    private function processEntryReservations(SavedPredictionFilter $strategy, Portfolio $portfolio, Collection $candidates): Collection
    {
        $active = DB::table('portfolio_strategy_reservations')->where('saved_prediction_filter_id', $strategy->id)
            ->where('portfolio_id', $portfolio->id)->where('status', 'active')->orderBy('id')->get();
        $remaining = collect();
        foreach ($active as $reservation) {
            $candidate = $candidates->firstWhere('instrument_id', $reservation->instrument_id);
            $expired = now()->greaterThanOrEqualTo($reservation->expires_at);
            if ($expired || ! $candidate) {
                $this->releaseReservation($reservation, $expired ? 'expired' : 'signal_invalid');
                continue;
            }
            $price = (float) ($candidate->quote_price ?: $candidate->current_price);
            $highestTarget = (float) collect([5, 10, 15, 20])->map(fn (int $days): float => (float) data_get($candidate, 'predicted_price_'.$days.'d', 0))->max();
            if ($price > 0 && $highestTarget > 0 && $price <= $highestTarget) {
                $this->releaseReservation($reservation, 'converted');
                DB::transaction(fn (): bool => $this->buyCandidate($strategy, $portfolio, $candidate, 0.0, null, true), 3);
                continue;
            }
            $remaining->push((int) $reservation->instrument_id);
        }
        return $remaining;
    }

    private function releaseReservation(object $reservation, string $status): void
    {
        DB::transaction(function () use ($reservation, $status): void {
            $locked = DB::table('portfolio_strategy_reservations')->where('id', $reservation->id)->where('status', 'active')->lockForUpdate()->first();
            if (! $locked) return;
            $account = DB::table('portfolio_cash_accounts')->where('portfolio_id', $locked->portfolio_id)->lockForUpdate()->first();
            if ($account) DB::table('portfolio_cash_accounts')->where('id', $account->id)->update([
                'reserved_balance' => max(0, (float) $account->reserved_balance - (float) $locked->reserved_capital), 'updated_at' => now(),
            ]);
            DB::table('portfolio_strategy_reservations')->where('id', $locked->id)->update([
                'status' => $status, 'released_at' => now(), 'updated_at' => now(),
            ]);
        }, 3);
    }
}
