<?php

namespace App\Jobs;

use App\Services\TwelveDataService;
use App\Services\YahooIndexService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use RuntimeException;
use Symfony\Component\Process\Process;
use Throwable;

final class RunFilteredBacktest implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 1200;

    public function __construct(
        public readonly int $runId,
        public readonly int $sourceRunId,
        public readonly array $filters,
    ) {}

    public function handle(TwelveDataService $marketData, YahooIndexService $fallbackMarketData): void
    {
        if ($this->isCancelled()) {
            $this->clearCancellationMarker();
            return;
        }
        DB::table('backtest_runs')->where('id', $this->runId)->update([
            'status' => 'running',
            'started_at' => now(),
            'updated_at' => now(),
        ]);

        $latestQuality = DB::table('model_quality_rankings')
            ->selectRaw('trained_model_id, MAX(id) AS ranking_id')
            ->groupBy('trained_model_id');
        $latestFundamental = DB::table('instrument_fundamentals')
            ->selectRaw('instrument_id, MAX(id) AS fundamental_id')
            ->groupBy('instrument_id');
        $latestTechnical = DB::table('technical_indicators')
            ->where('interval', '1d')
            ->selectRaw('instrument_id, MAX(id) AS technical_id')
            ->groupBy('instrument_id');
        $fundamentalNumber = static fn (string $key): string =>
            "(CASE WHEN NULLIF(fundamental.data::jsonb->>'{$key}', '') ~ '^-?[0-9]+([.][0-9]+)?$' THEN (fundamental.data::jsonb->>'{$key}')::numeric END)";

        $query = DB::table('backtest_trades as trade')
            ->join('instruments as instrument', 'instrument.id', '=', 'trade.instrument_id')
            ->leftJoin('exchanges as exchange', 'exchange.id', '=', 'instrument.exchange_id')
            ->leftJoinSub($latestQuality, 'latest_quality', fn ($join) =>
                $join->on('latest_quality.trained_model_id', '=', 'trade.trained_model_id'))
            ->leftJoin('model_quality_rankings as model_quality', 'model_quality.id', '=', 'latest_quality.ranking_id')
            ->leftJoin('model_quality_tiers as quality_tier', 'quality_tier.id', '=', 'model_quality.tier_id')
            ->leftJoinSub($latestFundamental, 'latest_fundamental', fn ($join) =>
                $join->on('latest_fundamental.instrument_id', '=', 'instrument.id'))
            ->leftJoin('instrument_fundamentals as fundamental', 'fundamental.id', '=', 'latest_fundamental.fundamental_id')
            ->leftJoinSub($latestTechnical, 'latest_technical', fn ($join) =>
                $join->on('latest_technical.instrument_id', '=', 'instrument.id'))
            ->leftJoin('technical_indicators as technical', 'technical.id', '=', 'latest_technical.technical_id')
            ->where('trade.backtest_run_id', $this->sourceRunId)
            ->where('trade.entry_date', '>=', now()->subYears(3)->toDateString())
            ->where('trade.signal', 'BUY')
            ->whereNotNull('trade.predicted_return')
            ->where('trade.predicted_return', '>', 0)
            ->where('instrument.is_active', true)
            ->whereNull('instrument.deleted_at');

        $drawdownMaximum = is_numeric($this->filters['drawdown_max'] ?? null)
            ? (float) $this->filters['drawdown_max']
            : 50.0;
        $profitFactorMinimum = is_numeric($this->filters['profit_factor_min'] ?? null)
            ? (float) $this->filters['profit_factor_min']
            : 0.0;
        $hitRateMinimum = is_numeric($this->filters['hit_rate_min'] ?? null)
            ? (float) $this->filters['hit_rate_min']
            : 0.0;
        $minimumTrades = is_numeric($this->filters['minimum_trades'] ?? null)
            ? max(1, (int) $this->filters['minimum_trades'])
            : 1;
        if ($drawdownMaximum < 50 || $profitFactorMinimum > 0 || $hitRateMinimum > 0 || $minimumTrades > 1) {
            $eligibleInstruments = DB::table('backtest_trades as eligible_trade')
                ->where('eligible_trade.backtest_run_id', $this->sourceRunId)
                ->where('eligible_trade.entry_date', '>=', now()->subYears(3)->toDateString())
                ->groupBy('eligible_trade.instrument_id')
                ->select('eligible_trade.instrument_id')
                ->when($drawdownMaximum < 50, fn (Builder $query) =>
                    $query->havingRaw('MAX(ABS(eligible_trade.max_drawdown)) <= ?', [max(0, $drawdownMaximum) / 100]))
                ->when($profitFactorMinimum > 0, fn (Builder $query) =>
                    $query->havingRaw(
                        'COALESCE(SUM(CASE WHEN eligible_trade.net_return > 0 THEN eligible_trade.net_return ELSE 0 END) / NULLIF(ABS(SUM(CASE WHEN eligible_trade.net_return < 0 THEN eligible_trade.net_return ELSE 0 END)), 0), 999999) >= ?',
                        [$profitFactorMinimum],
                    ))
                ->when($hitRateMinimum > 0, fn (Builder $query) =>
                    $query->havingRaw(
                        'AVG(CASE WHEN eligible_trade.net_return > 0 THEN 1.0 ELSE 0.0 END) * 100 >= ?',
                        [min(100, $hitRateMinimum)],
                    ))
                ->when($minimumTrades > 1, fn (Builder $query) =>
                    $query->havingRaw('COUNT(*) >= ?', [$minimumTrades]));
            $query->whereIn('trade.instrument_id', $eligibleInstruments);
        }

        $this->applyFilters($query, $fundamentalNumber);

        $candidates = $query->select('trade.*')
            ->orderBy('trade.entry_date')
            ->orderByDesc('trade.ki_score')
            ->orderByDesc('trade.confidence')
            ->orderBy('trade.id')
            ->get();
        $rows = $candidates;
        $initialCapital = $this->initialCapital();
        $positionCapital = $this->positionCapital();
        $tradeCost = $this->tradeCost();
        foreach ($rows->chunk(500) as $chunk) {
            if ($this->isCancelled()) {
                $this->clearCancellationMarker();
                return;
            }
            DB::table('backtest_trades')->insert($chunk->map(function (object $trade) use ($positionCapital, $tradeCost): array {
                $row = (array) $trade;
                unset($row['id']);
                $row['backtest_run_id'] = $this->runId;
                $row['transaction_cost'] = $positionCapital > 0 ? $tradeCost / $positionCapital : 0;
                $row['net_return'] = (float) $trade->gross_return - (float) $row['transaction_cost'];
                $metadata = is_string($row['metadata'] ?? null)
                    ? (json_decode($row['metadata'], true) ?: [])
                    : (array) ($row['metadata'] ?? []);
                $row['metadata'] = json_encode([
                    ...$metadata,
                    'allocated_capital' => $positionCapital,
                    'trade_cost_eur' => $tradeCost,
                    'capital_constrained' => true,
                ], JSON_THROW_ON_ERROR);
                $row['created_at'] = now();
                $row['updated_at'] = now();

                return $row;
            })->all());
        }

        if (! $this->calculateExitStrategies()) {
            $this->clearCancellationMarker();
            return;
        }
        if ($this->isCancelled()) {
            $this->clearCancellationMarker();
            return;
        }

        $summary = DB::table('backtest_trades')->where('backtest_run_id', $this->runId)
            ->selectRaw('COUNT(*) AS trades')
            ->selectRaw('COUNT(DISTINCT instrument_id) AS instruments')
            ->selectRaw('AVG(net_return) * 100 AS average_return')
            ->selectRaw('AVG(CASE WHEN net_return > 0 THEN 1.0 ELSE 0.0 END) * 100 AS hit_rate')
            ->selectRaw('SUM(CASE WHEN net_return > 0 THEN net_return ELSE 0 END) / NULLIF(ABS(SUM(CASE WHEN net_return < 0 THEN net_return ELSE 0 END)), 0) AS profit_factor')
            ->selectRaw('MAX(ABS(max_drawdown)) * 100 AS max_drawdown')
            ->first();

        $completed = DB::table('backtest_runs')->where('id', $this->runId)->where('status', '<>', 'cancelled')->update([
            'status' => 'completed',
            'finished_at' => now(),
            'instruments_total' => (int) ($summary->instruments ?? 0),
            'instruments_completed' => (int) ($summary->instruments ?? 0),
            'trades_count' => (int) ($summary->trades ?? 0),
            'summary' => json_encode([
                ...(array) $summary,
                'candidate_trades' => $candidates->count(),
                'initial_capital' => $initialCapital,
                'position_capital' => $positionCapital,
                'position_factor' => $this->positionFactor(),
                'max_parallel_positions' => $this->maxPositions(),
                'trade_cost_eur' => $tradeCost,
                'total_costs' => round($rows->count() * $tradeCost, 2),
                'exit_strategies' => ['fixed_20d', 'winner_runner', 'prediction_target', 'adaptive_rotation_20d'],
            ], JSON_THROW_ON_ERROR),
            'updated_at' => now(),
        ]);

        if ($completed === 0) {
            return;
        }

        $this->ensureBenchmarkHistory($marketData, $fallbackMarketData);
    }

    public function failed(Throwable $exception): void
    {
        DB::table('backtest_runs')
            ->where('id', $this->runId)
            ->where('status', '<>', 'cancelled')
            ->update([
            'status' => 'failed',
            'finished_at' => now(),
            'error_message' => mb_substr($exception->getMessage(), 0, 4000),
            'updated_at' => now(),
        ]);
    }

    private function applyFilters(Builder $query, callable $fundamentalNumber): void
    {
        $filter = fn (string $key, mixed $default = null): mixed => $this->filters[$key] ?? $default;
        if (trim((string) $filter('q')) !== '') {
            $term = '%'.strtolower(trim((string) $filter('q'))).'%';
            $query->where(fn (Builder $query) => $query
                ->whereRaw('LOWER(instrument.symbol) LIKE ?', [$term])
                ->orWhereRaw('LOWER(instrument.name) LIKE ?', [$term]));
        }
        if ($filter('country')) $query->where('instrument.country', strtoupper(trim((string) $filter('country'))));
        if ($filter('sector')) $query->where('instrument.sector', trim((string) $filter('sector')));
        if ($filter('exchange')) $query->where('exchange.code', strtoupper(trim((string) $filter('exchange'))));
        if (in_array($filter('ai_type'), ['horizon', 'pulse'], true)) $query->where('trade.ai_type', $filter('ai_type'));
        $modelIds = collect(is_array($filter('model')) ? $filter('model') : [$filter('model')])
            ->filter(fn ($id) => is_numeric($id) && (int) $id > 0)
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();
        if ($modelIds !== []) $query->whereIn('trade.model_definition_id', $modelIds);
        $minimumQualityTiers = [
            'top' => ['top'],
            'strong' => ['top', 'strong'],
            'solid' => ['top', 'strong', 'solid'],
            'test' => ['top', 'strong', 'solid', 'test'],
        ];
        if (array_key_exists((string) $filter('quality_tier'), $minimumQualityTiers)) {
            $query->whereIn('quality_tier.code', $minimumQualityTiers[(string) $filter('quality_tier')]);
        }
        if ($filter('quality_tier') === 'unqualified') $query->whereNull('quality_tier.code');
        if (in_array(strtoupper((string) $filter('signal')), ['BUY', 'WATCH', 'HOLD', 'SELL'], true)) $query->where('trade.signal', strtoupper((string) $filter('signal')));
        if (is_numeric($filter('score_min'))) $query->where('trade.ki_score', '>=', max(0, min(10, (float) $filter('score_min'))));
        if (is_numeric($filter('confidence_min'))) $query->where('trade.confidence', '>=', max(0, min(100, (float) $filter('confidence_min'))));
        if (is_numeric($filter('risk_max')) && (float) $filter('risk_max') < 100) {
            $query->whereRaw('ABS(COALESCE(trade.max_drawdown, 0)) <= ?', [max(0, (float) $filter('risk_max')) / 100]);
        }
        $minimumReturn = is_numeric($filter('predicted_return_min')) ? (float) $filter('predicted_return_min') : null;
        if ($minimumReturn !== null) $query->where('trade.predicted_return', '>=', $minimumReturn / 100);
        elseif ((bool) $filter('positive_prediction_required', false)) $query->where('trade.predicted_return', '>', 0);
        if (is_numeric($filter('volatility_max')) && (float) $filter('volatility_max') < 100) $query->where('technical.volatility_20', '<=', max(0, (float) $filter('volatility_max')) / 100);
        if (is_numeric($filter('pe_max')) && (float) $filter('pe_max') < 100) $query->whereRaw($fundamentalNumber('trailingPE').' <= ?', [(float) $filter('pe_max')]);
        if (is_numeric($filter('dividend_yield_min')) && (float) $filter('dividend_yield_min') > 0) $query->whereRaw($fundamentalNumber('dividendYield').' >= ?', [(float) $filter('dividend_yield_min')]);
        if (is_numeric($filter('market_cap_min')) && (float) $filter('market_cap_min') > 0) $query->whereRaw($fundamentalNumber('marketCap').' >= ?', [(float) $filter('market_cap_min') * 1_000_000_000]);
        if (is_numeric($filter('revenue_growth_min')) && (float) $filter('revenue_growth_min') > -50) $query->whereRaw($fundamentalNumber('revenueGrowth').' >= ?', [(float) $filter('revenue_growth_min') / 100]);
    }

    private function capitalConstrainedTrades($candidates): array
    {
        $cash = $this->initialCapital();
        $positionCapital = $this->positionCapital();
        $maxPositions = $this->maxPositions();
        $openPositions = [];
        $executed = collect();

        foreach ($candidates as $trade) {
            $entryDate = (string) $trade->entry_date;
            foreach ($openPositions as $key => $position) {
                if ($position['exit_date'] > $entryDate) continue;
                $cash += $position['capital'] * (1 + $position['return']);
                unset($openPositions[$key]);
            }
            if (count($openPositions) >= $maxPositions || $cash + 0.00001 < $positionCapital) continue;

            $cash -= $positionCapital;
            $openPositions[] = [
                'exit_date' => (string) $trade->exit_date,
                'capital' => $positionCapital,
                'return' => $this->netReturn($trade),
            ];
            $executed->push($trade);
        }
        foreach ($openPositions as $position) {
            $cash += $position['capital'] * (1 + $position['return']);
        }

        return [$executed, ['cash' => $cash]];
    }

    private function calculateExitStrategies(): bool
    {
        $enginePath = rtrim((string) config('aktienki.python_engine.path', '/Users/silviotaubert/Downloads/python-engine'), '/');
        $python = (string) (config('aktienki.python_engine.executable') ?: $enginePath.'/.venv/bin/python');
        $process = new Process([
            $python,
            base_path('scripts/calculate_exit_strategies.py'),
            '--run-id',
            (string) $this->runId,
        ], base_path(), [
            'AKTIENKI_PYTHON_ENGINE_PATH' => $enginePath,
        ]);
        $process->setTimeout(1200);
        $process->start();
        while ($process->isRunning()) {
            if ($this->isCancelled()) {
                $process->stop(3);
                return false;
            }
            usleep(750000);
        }
        if (! $process->isSuccessful()) {
            throw new RuntimeException(trim($process->getErrorOutput() ?: $process->getOutput()));
        }

        return true;
    }

    private function isCancelled(): bool
    {
        if (File::exists($this->cancellationMarker())) {
            return true;
        }

        return DB::table('backtest_runs')->where('id', $this->runId)->value('status') === 'cancelled';
    }

    private function cancellationMarker(): string
    {
        return storage_path('app/backtest-cancellations/'.$this->runId);
    }

    private function clearCancellationMarker(): void
    {
        File::delete($this->cancellationMarker());
    }

    private function initialCapital(): float
    {
        return max(1000.0, min(1000000.0, (float) ($this->filters['initial_capital'] ?? 10000)));
    }

    private function maxPositions(): int
    {
        return max(1, min(50, (int) ($this->filters['max_positions'] ?? 5)));
    }

    private function positionCapital(): float
    {
        return ($this->initialCapital() / $this->maxPositions()) * $this->positionFactor();
    }

    private function positionFactor(): int
    {
        return max(1, min($this->maxPositions(), (int) ($this->filters['position_factor'] ?? 1)));
    }

    private function tradeCost(): float
    {
        return max(0.0, min(1000.0, (float) ($this->filters['trade_cost'] ?? 10)));
    }

    private function netReturn(object $trade): float
    {
        return (float) $trade->gross_return - ($this->tradeCost() / $this->positionCapital());
    }

    private function ensureBenchmarkHistory(TwelveDataService $marketData, YahooIndexService $fallbackMarketData): void
    {
        try {
            $instrumentId = DB::table('instruments')->where('symbol', '^GSPC')->value('id');
            if (! $instrumentId) return;
            $firstBar = DB::table('price_bars')
                ->where('instrument_id', $instrumentId)
                ->where('interval', '1d')
                ->min('bar_time');
            if ($firstBar !== null && strtotime((string) $firstBar) <= now()->subYears(3)->timestamp) return;

            $history = $marketData->dailyHistory('^GSPC', 820);
            $source = 'twelve_data';
            if ($history === []) {
                $history = $fallbackMarketData->dailyHistory('^GSPC', '3y');
                $source = 'yahoo_index_rest';
            }
            $rows = collect($history)->map(fn (array $bar): array => [
                'instrument_id' => $instrumentId,
                'interval' => '1d',
                'bar_time' => date('Y-m-d H:i:sP', (int) $bar['timestamp']),
                'open' => $bar['open'],
                'high' => $bar['high'],
                'low' => $bar['low'],
                'close' => $bar['close'],
                'adjusted_close' => $bar['adjusted_close'],
                'volume' => $bar['volume'],
                'source' => $source,
                'created_at' => now(),
                'updated_at' => now(),
            ])->all();
            foreach (array_chunk($rows, 500) as $chunk) {
                DB::table('price_bars')->upsert($chunk, ['instrument_id', 'interval', 'bar_time'], [
                    'open', 'high', 'low', 'close', 'adjusted_close', 'volume', 'source', 'updated_at',
                ]);
            }
        } catch (Throwable) {
            // The strategy result remains valid if the external benchmark is temporarily unavailable.
        }
    }
}
