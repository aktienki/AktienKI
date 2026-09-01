<?php

namespace App\Services;

use App\Models\Portfolio;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final class ServingPortfolioSimulationService
{
    public const MAXIMUM_POSITIONS = 5;

    public const DEFAULT_MAX_STOCK_ALLOCATION_PERCENT = 30.0;

    public const FEE_RATE = 0.003;

    public const MINIMUM_FEE = 10.0;

    public function __construct(private readonly ServingPortfolioCalculator $calculator) {}

    public function hasServingConfigurations(Collection $assignments): bool
    {
        return $assignments->contains(fn (object $assignment): bool => $this->configurations($assignment) !== []);
    }

    public function allAssignmentsUseServingConfigurations(Collection $assignments): bool
    {
        return $assignments->isNotEmpty()
            && $assignments->every(fn (object $assignment): bool => $this->configurations($assignment) !== []);
    }

    /** @return array<string, mixed> */
    public function calculate(
        Portfolio $portfolio,
        Collection $assignments,
        float $initialCapital,
        string $allocationMode = ServingPortfolioCalculator::ALLOCATION_EQUAL_WEIGHT,
        int $maximumPositions = self::MAXIMUM_POSITIONS,
        float $maxStockAllocationPercent = self::DEFAULT_MAX_STOCK_ALLOCATION_PERCENT,
    ): array {
        if (strtoupper((string) $portfolio->currency) !== 'EUR') {
            throw new RuntimeException('Serving-Depotsimulationen verwenden derzeit ausschließlich EUR-Kurse.');
        }

        $requests = $assignments->flatMap(fn (object $assignment): array => collect($this->configurations($assignment))
            ->map(fn (array $configuration): array => [
                'assignment' => $assignment,
                'configuration' => $configuration,
            ])->all());
        $sources = collect($this->resolveSources($requests))
            ->unique('configuration_key')
            ->values()
            ->all();

        return $this->calculator->calculate(
            $sources,
            $initialCapital,
            $maximumPositions,
            $allocationMode,
            $maxStockAllocationPercent / 100,
            self::FEE_RATE,
            self::MINIMUM_FEE,
        );
    }

    /** @param array<string, mixed> $result */
    public function persist(Portfolio $portfolio, int $simulationRunId, array $result): void
    {
        $account = DB::table('portfolio_cash_accounts')
            ->where('portfolio_id', $portfolio->id)
            ->where('currency', $portfolio->currency)
            ->lockForUpdate()
            ->first();
        if (! $account) {
            throw new RuntimeException('Das Verrechnungskonto des Musterdepots fehlt.');
        }
        if (DB::table('portfolio_transactions')->where('portfolio_id', $portfolio->id)->exists()) {
            throw new RuntimeException('Das Musterdepot enthält vor der Serving-Simulation noch Transaktionen.');
        }
        if (DB::table('portfolio_positions')->where('portfolio_id', $portfolio->id)->exists()) {
            throw new RuntimeException('Das Musterdepot enthält vor der Serving-Simulation noch Positionen.');
        }

        $events = collect($result['trade_log'] ?? [])->flatMap(fn (array $trade): array => [
            [
                ...$trade,
                'action' => 'buy',
                'date' => $trade['entry_date'],
                'price' => $trade['entry_price'],
                'fee' => $trade['buy_fee'],
            ],
            [
                ...$trade,
                'action' => 'sell',
                'date' => $trade['exit_date'],
                'price' => $trade['exit_price'],
                'fee' => $trade['sell_fee'],
            ],
        ])->sortBy(fn (array $event): string => implode('|', [
            $event['date'],
            $event['action'] === 'sell' ? '0' : '1',
            str_pad((string) $event['serving_strategy_trade_id'], 20, '0', STR_PAD_LEFT),
        ]))->values();

        $balance = (float) $account->balance;
        $positions = [];
        $transactionRows = [];
        $ledgerBlueprints = [];
        $persistedAt = now();
        foreach ($events as $eventIndex => $event) {
            $instrumentId = (int) $event['instrument_id'];
            $quantity = (float) $event['quantity'];
            $price = (float) $event['price'];
            $fee = (float) $event['fee'];
            $gross = $quantity * $price;
            $occurredAt = CarbonImmutable::parse($event['date'], 'UTC')->setTime(12, 0);
            $strategyIds = [(int) $event['strategy_id']];
            $meta = [
                'source' => 'portfolio_serving_simulation',
                'simulation_run_id' => $simulationRunId,
                'serving_strategy_trade_id' => (int) $event['serving_strategy_trade_id'],
                'serving_strategy_run_id' => (string) $event['strategy_run_id'],
                'configuration_key' => (string) $event['configuration_key'],
                'release_id' => (string) $event['release_id'],
                'strategy_id' => (int) $event['strategy_id'],
                'strategy_ids' => $strategyIds,
                'strategy_name' => (string) $event['strategy_name'],
                'signal' => (string) ($event['entry_signal'] ?? 'BUY'),
                'horizon_days' => (int) $event['horizon_days'],
                'variant' => (string) $event['variant'],
                'variant_label' => (string) $event['variant_label'],
                'model_name' => (string) $event['model_name'],
                'quality_label' => (string) $event['quality_label'],
                'model_net_return' => (float) $event['net_return'],
                'model_transaction_cost' => (float) $event['transaction_cost'],
                'model_profit_factor' => (float) $event['model_profit_factor'],
                'model_hit_rate' => (float) $event['model_hit_rate'],
                'model_max_drawdown' => (float) $event['model_max_drawdown'],
                'model_cumulative_return' => (float) $event['model_cumulative_return'],
                'entry_signal' => (string) ($event['entry_signal'] ?? 'BUY'),
                'exit_reason' => (string) ($event['exit_reason'] ?? 'MAX_HOLDING_DAYS'),
                'holding_days' => (int) ($event['holding_days'] ?? $event['horizon_days']),
                'position_notional' => (float) $event['position_notional'],
                'allocation_mode' => (string) $event['allocation_mode'],
                'maximum_positions' => (int) $event['maximum_positions'],
                'max_stock_allocation_percent' => (float) $event['max_stock_allocation_percent'],
                'effective_max_stock_allocation_percent' => (float) $event['effective_max_stock_allocation_percent'],
                'fee_rate' => self::FEE_RATE,
                'minimum_fee' => self::MINIMUM_FEE,
            ];

            if ($event['action'] === 'buy') {
                if ($balance + 0.000001 < $gross + $fee) {
                    throw new RuntimeException('Das Guthaben reicht beim Verbuchen der Serving-Simulation nicht aus.');
                }
                $balanceAfterGross = $balance - $gross;
                $balance = $balanceAfterGross - $fee;
                $positions[$instrumentId] = [
                    'quantity' => $quantity,
                    'cost' => $gross + $fee,
                ];
            } else {
                $position = $positions[$instrumentId] ?? null;
                if (! $position) {
                    throw new RuntimeException('Eine simulierte Serving-Position fehlt beim Verkauf.');
                }
                $balanceAfterGross = $balance + $gross;
                $balance = $balanceAfterGross - $fee;
                $realizedProfit = $gross - $fee - (float) $position['cost'];
                $meta['realized_profit'] = round($realizedProfit, 6);
                $meta['performance_percent'] = (float) $position['cost'] > 0
                    ? round($realizedProfit / (float) $position['cost'] * 100, 6)
                    : 0.0;
                unset($positions[$instrumentId]);
            }

            $transactionRows[] = [
                'portfolio_id' => $portfolio->id,
                'instrument_id' => $instrumentId,
                'type' => $event['action'],
                'transaction_date' => $event['date'],
                'quantity' => $quantity,
                'price' => $price,
                'fees' => $fee,
                'currency' => $portfolio->currency,
                'meta' => json_encode($meta, JSON_THROW_ON_ERROR),
                'created_at' => $occurredAt,
                'updated_at' => $occurredAt,
            ];

            if ($event['action'] === 'buy') {
                $ledger = [
                    ['purchase_debit', -$gross, $balanceAfterGross],
                    ['fee', -$fee, $balance],
                ];
            } else {
                $ledger = [
                    ['sale_credit', $gross, $balanceAfterGross],
                    ['fee', -$fee, $balance],
                ];
            }

            foreach ($ledger as [$type, $amount, $balanceAfter]) {
                $ledgerBlueprints[] = [
                    'transaction_index' => (int) $eventIndex,
                    'portfolio_cash_account_id' => $account->id,
                    'type' => $type,
                    'amount' => $amount,
                    'balance_after' => $balanceAfter,
                    'currency' => $account->currency,
                    'occurred_at' => $occurredAt,
                    'meta' => json_encode([
                        'source' => 'portfolio_serving_simulation',
                        'simulation_run_id' => $simulationRunId,
                        'serving_strategy_trade_id' => (int) $event['serving_strategy_trade_id'],
                    ], JSON_THROW_ON_ERROR),
                    'created_at' => $persistedAt,
                    'updated_at' => $persistedAt,
                ];
            }
        }

        if ($positions !== []) {
            throw new RuntimeException('Nach der Serving-Simulation sind unerwartet Positionen offen.');
        }
        if (abs($balance - (float) $result['final_capital']) > 0.02) {
            throw new RuntimeException('Kontostand und Serving-Simulation stimmen nicht überein.');
        }

        if ($transactionRows !== []) {
            $allocatedIds = collect(DB::select(
                "SELECT nextval(pg_get_serial_sequence('portfolio_transactions', 'id')) AS id
                 FROM generate_series(1, CAST(? AS integer))",
                [count($transactionRows)],
            ))->map(fn (object $row): int => (int) $row->id)->values();
            if ($allocatedIds->count() !== count($transactionRows)) {
                throw new RuntimeException('Die Transaktionsnummern für die Serving-Simulation konnten nicht reserviert werden.');
            }
            foreach ($transactionRows as $index => &$transactionRow) {
                $transactionRow['id'] = $allocatedIds[$index];
            }
            unset($transactionRow);
            foreach (array_chunk($transactionRows, 500) as $chunk) {
                DB::table('portfolio_transactions')->insert($chunk);
            }

            $ledgerRows = array_map(function (array $blueprint) use ($allocatedIds): array {
                $transactionIndex = $blueprint['transaction_index'];
                unset($blueprint['transaction_index']);

                return [
                    ...$blueprint,
                    'portfolio_transaction_id' => $allocatedIds[$transactionIndex],
                ];
            }, $ledgerBlueprints);
            foreach (array_chunk($ledgerRows, 1000) as $chunk) {
                DB::table('portfolio_cash_ledger')->insert($chunk);
            }
        }

        DB::table('portfolio_cash_accounts')->where('id', $account->id)->update([
            'balance' => $balance,
            'reserved_balance' => 0,
            'updated_at' => now(),
        ]);
        DB::table('portfolio_simulation_runs')->where('id', $simulationRunId)->update([
            'status' => 'completed',
            'finished_at' => now(),
            'simulation_start_date' => $result['start_date'],
            'simulation_end_date' => $result['end_date'],
            'final_capital' => $balance,
            'trades_count' => (int) $result['trades'],
            'summary' => json_encode($result, JSON_THROW_ON_ERROR),
            'updated_at' => now(),
        ]);
    }

    /** @return array<int, array<string, mixed>> */
    private function configurations(object $assignment): array
    {
        $filters = $this->json($assignment->filters ?? null);

        return collect(data_get($filters, 'serving_model_configurations', []))
            ->filter(fn (mixed $configuration): bool => is_array($configuration)
                && ($configuration['source'] ?? null) === 'serving_model_overview')
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, array{assignment: object, configuration: array<string, mixed>}>  $requests
     * @return array<int, array<string, mixed>>
     */
    private function resolveSources(Collection $requests): array
    {
        $descriptors = $requests->map(function (array $request): array {
            $configuration = $request['configuration'];
            $symbol = strtoupper(trim((string) ($configuration['symbol'] ?? '')));
            $releaseId = (string) ($configuration['release_id'] ?? '');
            $horizon = (int) ($configuration['horizon_days'] ?? 0);
            $variant = (string) ($configuration['variant'] ?? '');
            if ($symbol === '' || $releaseId === '' || ! in_array($horizon, [10, 20, 40], true)
                || ! in_array($variant, ['standard', 'pure_tcn'], true)) {
                throw new RuntimeException('Eine persönliche Serving-Modellkonfiguration ist unvollständig.');
            }

            return [
                ...$request,
                'symbol' => $symbol,
                'release_id' => $releaseId,
                'horizon_days' => $horizon,
                'variant' => $variant,
            ];
        })->values();
        if ($descriptors->isEmpty()) {
            return [];
        }

        $symbols = $descriptors->pluck('symbol')->unique()->values();
        $serving = DB::connection('serving');
        $servingInstruments = $serving->table('serving_instruments')
            ->whereIn(DB::raw('UPPER(symbol)'), $symbols->all())
            ->where('instrument_type', 'stock')
            ->where('is_active', true)->get(['id', 'symbol', 'name', 'currency'])
            ->keyBy(fn (object $instrument): string => strtoupper((string) $instrument->symbol));
        foreach ($symbols as $symbol) {
            if (! $servingInstruments->has($symbol)) {
                throw new RuntimeException("Die Serving-Aktie {$symbol} wurde nicht gefunden.");
            }
        }

        $localInstruments = DB::table('instruments')
            ->whereIn(DB::raw('UPPER(symbol)'), $symbols->all())
            ->where('type', 'stock')
            ->whereNull('deleted_at')
            ->get(['id', 'symbol'])
            ->keyBy(fn (object $instrument): string => strtoupper((string) $instrument->symbol));
        foreach ($symbols as $symbol) {
            if (! $localInstruments->has($symbol)) {
                throw new RuntimeException("Die Benutzer-Datenbank enthält noch keine Referenz für {$symbol}.");
            }
        }

        $instrumentIds = $servingInstruments->pluck('id')->map(fn (mixed $id): int => (int) $id)->values();
        $releaseKeys = $serving->table('serving_releases')
            ->whereIn('id', $descriptors->pluck('release_id')->unique()->all())
            ->whereIn('instrument_id', $instrumentIds->all())
            ->get(['id', 'instrument_id'])
            ->mapWithKeys(fn (object $release): array => [
                (string) $release->id.'|'.(int) $release->instrument_id => true,
            ]);
        foreach ($descriptors as $descriptor) {
            $instrument = $servingInstruments->get($descriptor['symbol']);
            if (! $releaseKeys->has($descriptor['release_id'].'|'.(int) $instrument->id)) {
                throw new RuntimeException("Das ausgewählte Serving-Release für {$descriptor['symbol']} existiert nicht mehr.");
            }
        }

        $pairs = $descriptors->map(function (array $descriptor) use ($servingInstruments): array {
            $instrument = $servingInstruments->get($descriptor['symbol']);

            return [(int) $instrument->id, (int) $descriptor['horizon_days']];
        })->unique(fn (array $pair): string => $pair[0].'|'.$pair[1])->values();
        $rows = $serving->table('serving_strategy_trades as trade')
            ->join('serving_strategy_runs as run', 'run.id', '=', 'trade.strategy_run_id')
            ->where(function ($query) use ($pairs): void {
                foreach ($pairs as [$instrumentId, $horizon]) {
                    $query->orWhere(function ($pairQuery) use ($instrumentId, $horizon): void {
                        $pairQuery->where('trade.instrument_id', $instrumentId)
                            ->where('trade.horizon', $horizon);
                    });
                }
            })
            ->where('run.status', 'complete')
            ->orderBy('trade.entry_date')
            ->orderBy('trade.exit_date')
            ->get([
                'trade.id', 'trade.instrument_id', 'trade.horizon', 'trade.strategy_run_id',
                'trade.strategy', 'trade.entry_signal',
                'trade.entry_date', 'trade.entry_close_eur', 'trade.exit_date', 'trade.exit_close_eur',
                'trade.holding_days', 'trade.net_return', 'trade.transaction_cost', 'trade.exit_reason',
                'run.calculation_date', 'run.source_metadata',
            ]);
        $rowsByPair = $rows->groupBy(fn (object $row): string => (int) $row->instrument_id.'|'.(int) $row->horizon);

        return $descriptors->map(function (array $descriptor) use (
            $servingInstruments,
            $localInstruments,
            $rowsByPair,
        ): array {
            $instrument = $servingInstruments->get($descriptor['symbol']);
            $localInstrument = $localInstruments->get($descriptor['symbol']);
            $rows = $rowsByPair->get(
                (int) $instrument->id.'|'.(int) $descriptor['horizon_days'],
                collect(),
            );

            return $this->resolveSourceFromRows(
                $descriptor['assignment'],
                $descriptor['configuration'],
                $descriptor['symbol'],
                $descriptor['release_id'],
                $descriptor['horizon_days'],
                $descriptor['variant'],
                $localInstrument,
                $rows,
            );
        })->all();
    }

    /**
     * @param  array<string, mixed>  $configuration
     * @return array<string, mixed>
     */
    private function resolveSourceFromRows(
        object $assignment,
        array $configuration,
        string $symbol,
        string $releaseId,
        int $horizon,
        string $variant,
        object $localInstrument,
        Collection $rows,
    ): array {
        $publishedMetrics = (array) ($configuration['metrics'] ?? []);
        $candidates = $rows
            ->groupBy(fn (object $row): string => $row->strategy_run_id.'|'.$row->strategy)
            ->map(function (Collection $trades) use ($variant, $releaseId, $publishedMetrics): ?array {
                $first = $trades->first();
                if ($this->strategyVariant((string) $first->strategy) !== $variant) {
                    return null;
                }
                if ($trades->contains(fn (object $trade): bool => strtoupper((string) $trade->entry_signal) !== 'BUY')) {
                    return null;
                }
                $sourceMetadata = $this->json($first->source_metadata);
                if (! hash_equals($releaseId, (string) ($sourceMetadata['release_id'] ?? ''))) {
                    return null;
                }
                $metrics = $this->metrics($trades);
                if (! $this->metricsMatch($metrics, $publishedMetrics)) {
                    return null;
                }

                return [
                    'strategy_run_id' => (string) $first->strategy_run_id,
                    'strategy' => (string) $first->strategy,
                    'calculation_date' => (string) $first->calculation_date,
                    'metrics' => $metrics,
                    'trades' => $trades->values(),
                ];
            })
            ->filter()
            ->sort(function (array $left, array $right): int {
                $leftCalibrated = str_contains($left['strategy'], 'calibrated');
                $rightCalibrated = str_contains($right['strategy'], 'calibrated');
                if ($leftCalibrated !== $rightCalibrated) {
                    return $leftCalibrated ? -1 : 1;
                }

                return strcmp($right['calculation_date'], $left['calculation_date']);
            });
        $candidate = $candidates->first();
        if (! $candidate) {
            throw new RuntimeException("Für {$symbol} {$horizon}T {$variant} wurden keine exakt passenden Serving-Trades gefunden.");
        }

        $storedCosts = $candidate['trades']->pluck('transaction_cost')
            ->filter(fn (mixed $cost): bool => is_numeric($cost))
            ->map(fn (mixed $cost): float => (float) $cost);

        return [
            'configuration_key' => (string) ($configuration['key'] ?? "{$releaseId}:{$horizon}:{$variant}"),
            'release_id' => $releaseId,
            'symbol' => $symbol,
            'instrument_id' => (int) $localInstrument->id,
            'strategy_id' => (int) $assignment->saved_prediction_filter_id,
            'strategy_name' => (string) $assignment->strategy_name,
            'priority' => (int) $assignment->priority,
            'horizon_days' => $horizon,
            'variant' => $variant,
            'variant_label' => (string) ($configuration['variant_label'] ?? ($variant === 'pure_tcn' ? 'Pure TCN' : 'Standard')),
            'model_name' => (string) ($configuration['model_name'] ?? $variant),
            'quality_label' => (string) ($configuration['quality_label'] ?? '—'),
            'strategy_run_id' => $candidate['strategy_run_id'],
            'strategy' => $candidate['strategy'],
            'source_metrics' => $candidate['metrics'],
            'stored_transaction_cost' => $storedCosts->isNotEmpty() ? (float) $storedCosts->avg() : 0.0,
            'trades' => $candidate['trades']->map(fn (object $trade): array => [
                'serving_strategy_trade_id' => (int) $trade->id,
                'entry_signal' => strtoupper((string) $trade->entry_signal),
                'entry_date' => (string) $trade->entry_date,
                'entry_price' => (float) $trade->entry_close_eur,
                'exit_date' => (string) $trade->exit_date,
                'exit_price' => (float) $trade->exit_close_eur,
                'holding_days' => (int) $trade->holding_days,
                'net_return' => (float) $trade->net_return,
                'transaction_cost' => (float) $trade->transaction_cost,
                'exit_reason' => (string) ($trade->exit_reason ?: 'MAX_HOLDING_DAYS'),
                'strategy_run_id' => (string) $trade->strategy_run_id,
                'strategy' => (string) $trade->strategy,
            ])->all(),
        ];
    }

    /** @return array<string, float|int|null> */
    private function metrics(Collection $trades): array
    {
        $equity = 1.0;
        $peak = 1.0;
        $maxDrawdown = 0.0;
        $wins = 0;
        $grossWins = 0.0;
        $grossLosses = 0.0;
        foreach ($trades->sortBy('exit_date') as $trade) {
            $return = (float) $trade->net_return;
            $equity *= 1 + $return;
            $peak = max($peak, $equity);
            $maxDrawdown = min($maxDrawdown, $equity / $peak - 1);
            $wins += $return > 0 ? 1 : 0;
            $grossWins += max(0, $return);
            $grossLosses += abs(min(0, $return));
        }
        $count = $trades->count();

        return [
            'trades' => $count,
            'hit_rate' => $count > 0 ? $wins / $count : 0.0,
            'profit_factor' => $grossLosses > 0 ? $grossWins / $grossLosses : null,
            'average_net_trade' => $count > 0 ? (float) $trades->avg(fn (object $trade): float => (float) $trade->net_return) : 0.0,
            'cumulative_return' => $equity - 1,
            'max_drawdown' => $maxDrawdown,
            'average_holding_days' => $count > 0 ? (float) $trades->avg('holding_days') : 0.0,
        ];
    }

    /** @param array<string, mixed> $actual @param array<string, mixed> $expected */
    private function metricsMatch(array $actual, array $expected): bool
    {
        if ((int) ($actual['trades'] ?? -1) !== (int) ($expected['trades'] ?? -2)) {
            return false;
        }
        foreach (['hit_rate', 'cumulative_return', 'max_drawdown'] as $metric) {
            if (! is_numeric($expected[$metric] ?? null) || ! is_numeric($actual[$metric] ?? null)) {
                return false;
            }
            $expectedValue = (float) $expected[$metric];
            $tolerance = max(0.0000001, abs($expectedValue) * 0.000001);
            if (abs((float) $actual[$metric] - $expectedValue) > $tolerance) {
                return false;
            }
        }
        foreach (['profit_factor', 'average_net_trade'] as $metric) {
            if (! is_numeric($expected[$metric] ?? null)) {
                continue;
            }
            if (! is_numeric($actual[$metric] ?? null)) {
                return false;
            }
            $expectedValue = (float) $expected[$metric];
            $tolerance = max(0.0000001, abs($expectedValue) * 0.000001);
            if (abs((float) $actual[$metric] - $expectedValue) > $tolerance) {
                return false;
            }
        }

        return true;
    }

    private function strategyVariant(string $strategy): ?string
    {
        $normalized = strtolower(str_replace('_', '-', $strategy));
        if (str_contains($normalized, 'pure-tcn')) {
            return 'pure_tcn';
        }
        if (str_contains($normalized, 'standard')) {
            return 'standard';
        }

        return null;
    }

    /** @return array<string, mixed> */
    private function json(mixed $value): array
    {
        if (is_array($value)) {
            return $value;
        }
        if (is_object($value)) {
            return (array) $value;
        }
        $decoded = json_decode((string) $value, true);

        return is_array($decoded) ? $decoded : [];
    }
}
