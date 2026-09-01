<?php

namespace App\Services;

use InvalidArgumentException;

final class ServingPortfolioCalculator
{
    public const ALLOCATION_FULL_INVESTMENT = 'full_investment';

    public const ALLOCATION_EQUAL_WEIGHT = 'equal_weight';

    /**
     * @param  array<int, array<string, mixed>>  $sources
     * @return array<string, mixed>
     */
    public function calculate(
        array $sources,
        float $initialCapital,
        int $maximumPositions = 5,
        string $allocationMode = self::ALLOCATION_EQUAL_WEIGHT,
        float $maxStockAllocationRate = 0.30,
        float $feeRate = 0.003,
        float $minimumFee = 10.0,
    ): array {
        if ($sources === []) {
            throw new InvalidArgumentException('Keine Serving-Modellkonfigurationen vorhanden.');
        }
        if ($initialCapital <= 0 || $maximumPositions < 1
            || ! in_array($allocationMode, [self::ALLOCATION_FULL_INVESTMENT, self::ALLOCATION_EQUAL_WEIGHT], true)
            || $maxStockAllocationRate <= 0 || $maxStockAllocationRate > 1) {
            throw new InvalidArgumentException('Ungültige Kapitalparameter für die Depotsimulation.');
        }

        $requestedMaximumPositions = min(50, $maximumPositions);
        $maximumPositions = $allocationMode === self::ALLOCATION_FULL_INVESTMENT
            ? 1
            : $requestedMaximumPositions;
        $feeRate = max(0.0, $feeRate);
        $minimumFee = max(0.0, $minimumFee);
        $candidatesByDate = [];
        $timeline = [];
        $modelReferences = [];

        foreach ($sources as $source) {
            $trades = array_values((array) ($source['trades'] ?? []));
            if ($trades === []) {
                throw new InvalidArgumentException('Eine Serving-Modellkonfiguration enthält keine Trades.');
            }
            $reference = $this->modelReference($source, $trades, $initialCapital);
            $modelReferences[] = $reference;
            foreach ($trades as $trade) {
                $entryDate = (string) ($trade['entry_date'] ?? '');
                $exitDate = (string) ($trade['exit_date'] ?? '');
                if ($entryDate === '' || $exitDate === '' || $exitDate < $entryDate) {
                    throw new InvalidArgumentException('Ungültige Ein- oder Ausstiegsdaten in den Serving-Trades.');
                }
                $entryPrice = (float) ($trade['entry_price'] ?? 0);
                $exitPrice = (float) ($trade['exit_price'] ?? 0);
                if ($entryPrice <= 0 || $exitPrice <= 0) {
                    throw new InvalidArgumentException('Serving-Trades ohne gültigen EUR-Kurs können nicht simuliert werden.');
                }
                if (! is_numeric($trade['net_return'] ?? null)
                    || ! is_numeric($trade['transaction_cost'] ?? null)) {
                    throw new InvalidArgumentException('Ein Serving-Trade enthält keine vollständige Modellrendite.');
                }
                $netReturn = (float) $trade['net_return'];
                $reconciledNetReturn = ($exitPrice / $entryPrice) - 1 - (float) $trade['transaction_cost'];
                $returnTolerance = max(0.00000001, abs($netReturn) * 0.0000001);
                if (abs($reconciledNetReturn - $netReturn) > $returnTolerance) {
                    throw new InvalidArgumentException(
                        'Kurse, Modellkosten und Nettoertrag eines Serving-Trades stimmen nicht überein.',
                    );
                }
                $candidate = [
                    ...$trade,
                    'configuration_key' => (string) $source['configuration_key'],
                    'release_id' => (string) $source['release_id'],
                    'symbol' => (string) $source['symbol'],
                    'instrument_id' => (int) $source['instrument_id'],
                    'strategy_id' => (int) $source['strategy_id'],
                    'strategy_name' => (string) $source['strategy_name'],
                    'priority' => (int) $source['priority'],
                    'horizon_days' => (int) $source['horizon_days'],
                    'variant' => (string) $source['variant'],
                    'variant_label' => (string) $source['variant_label'],
                    'model_name' => (string) $source['model_name'],
                    'quality_label' => (string) $source['quality_label'],
                    'model_profit_factor' => (float) data_get($source, 'source_metrics.profit_factor', 0),
                    'model_hit_rate' => (float) data_get($source, 'source_metrics.hit_rate', 0),
                    'model_max_drawdown' => (float) data_get($source, 'source_metrics.max_drawdown', 0),
                    'model_cumulative_return' => (float) data_get($source, 'source_metrics.cumulative_return', 0),
                ];
                $candidatesByDate[$entryDate][] = $candidate;
                $timeline[$entryDate] = true;
                $timeline[$exitDate] = true;
            }
        }

        ksort($timeline);
        $cash = $initialCapital;
        $positions = [];
        $completed = [];
        $equityCurve = [];
        $utilizationSamples = [];
        $totalCosts = 0.0;
        $peak = $initialCapital;
        $maxDrawdown = 0.0;
        $skippedCapacity = 0;
        $skippedCash = 0;
        $skippedOverlap = 0;

        foreach (array_keys($timeline) as $date) {
            $closingKeys = array_keys(array_filter(
                $positions,
                fn (array $position): bool => $position['exit_date'] <= $date,
            ));
            usort($closingKeys, fn (string|int $left, string|int $right): int => [$positions[$left]['exit_date'], $positions[$left]['serving_strategy_trade_id']]
                <=> [$positions[$right]['exit_date'], $positions[$right]['serving_strategy_trade_id']]
            );
            foreach ($closingKeys as $key) {
                $position = $positions[$key];
                $grossProceeds = $position['quantity'] * $position['exit_price'];
                $sellFee = $this->fee($grossProceeds, $feeRate, $minimumFee);
                $netProceeds = $grossProceeds - $sellFee;
                $cash += $netProceeds;
                $totalCosts += $sellFee;
                $profit = $netProceeds - $position['position_notional'] - $position['buy_fee'];
                $completed[] = [
                    ...$position,
                    'sell_fee' => $sellFee,
                    'gross_proceeds' => round($grossProceeds, 6),
                    'profit' => round($profit, 6),
                    'performance_percent' => round(
                        $profit / ($position['position_notional'] + $position['buy_fee']) * 100,
                        6,
                    ),
                ];
                unset($positions[$key]);
            }

            $candidates = $candidatesByDate[$date] ?? [];
            $equityBeforeEntries = $cash + array_sum(array_column($positions, 'position_notional'));
            $equalWeightBudget = $equityBeforeEntries / $maximumPositions;
            usort($candidates, static fn (array $left, array $right): int => [
                $left['priority'],
                -$left['model_profit_factor'],
                -$left['model_hit_rate'],
                $left['symbol'],
                $left['horizon_days'],
                $left['serving_strategy_trade_id'],
            ] <=> [
                $right['priority'],
                -$right['model_profit_factor'],
                -$right['model_hit_rate'],
                $right['symbol'],
                $right['horizon_days'],
                $right['serving_strategy_trade_id'],
            ]);

            foreach ($candidates as $candidate) {
                if (count($positions) >= $maximumPositions) {
                    $skippedCapacity++;

                    continue;
                }
                if (collect($positions)->contains(
                    fn (array $position): bool => $position['symbol'] === $candidate['symbol'],
                )) {
                    $skippedOverlap++;

                    continue;
                }
                $availableBudget = $allocationMode === self::ALLOCATION_EQUAL_WEIGHT
                    ? min($cash, $equalWeightBudget)
                    : $cash;
                $maximumAffordableNotional = $this->affordableNotional(
                    $availableBudget,
                    $feeRate,
                    $minimumFee,
                );
                $maximumStockNotional = $allocationMode === self::ALLOCATION_EQUAL_WEIGHT
                    ? $equityBeforeEntries * $maxStockAllocationRate
                    : $maximumAffordableNotional;
                $positionNotional = min($maximumAffordableNotional, $maximumStockNotional);
                if ($positionNotional <= 0.000001) {
                    $skippedCash++;

                    continue;
                }
                $buyFee = $this->fee($positionNotional, $feeRate, $minimumFee);
                if ($cash + 0.000001 < $positionNotional + $buyFee) {
                    $skippedCash++;

                    continue;
                }
                $quantity = $positionNotional / (float) $candidate['entry_price'];
                $cash -= $positionNotional + $buyFee;
                $totalCosts += $buyFee;
                $positionKey = $candidate['configuration_key'].'#'.$candidate['serving_strategy_trade_id'];
                $positions[$positionKey] = [
                    ...$candidate,
                    'quantity' => $quantity,
                    'position_notional' => $positionNotional,
                    'buy_fee' => $buyFee,
                    'allocation_mode' => $allocationMode,
                    'maximum_positions' => $maximumPositions,
                    'max_stock_allocation_percent' => $maxStockAllocationRate * 100,
                    'effective_max_stock_allocation_percent' => $allocationMode === self::ALLOCATION_EQUAL_WEIGHT
                        ? $maxStockAllocationRate * 100
                        : 100.0,
                ];
            }

            $invested = array_sum(array_column($positions, 'position_notional'));
            $equity = $cash + $invested;
            $peak = max($peak, $equity);
            $maxDrawdown = max($maxDrawdown, $peak > 0 ? ($peak - $equity) / $peak * 100 : 0.0);
            $utilizationSamples[] = $equity > 0 ? $invested / $equity * 100 : 0.0;
            $equityCurve[] = [
                'date' => $date,
                'equity' => round($equity, 2),
                'cash' => round($cash, 2),
                'positions' => count($positions),
            ];
        }

        if ($positions !== []) {
            throw new InvalidArgumentException('Nicht alle Serving-Positionen konnten am Backtestende geschlossen werden.');
        }

        $profits = array_map(fn (array $trade): float => (float) $trade['profit'], $completed);
        $grossWins = array_sum(array_filter($profits, fn (float $profit): bool => $profit > 0));
        $grossLosses = abs(array_sum(array_filter($profits, fn (float $profit): bool => $profit < 0)));
        $winners = count(array_filter($profits, fn (float $profit): bool => $profit > 0));
        $positionNotionals = array_map(fn (array $trade): float => (float) $trade['position_notional'], $completed);
        $finalCapital = $cash;
        $singleReference = count($modelReferences) === 1 ? $modelReferences[0] : null;

        return [
            'source_type' => 'serving_model_configurations',
            'start_date' => array_key_first($timeline),
            'end_date' => array_key_last($timeline),
            'initial_capital' => round($initialCapital, 2),
            'final_capital' => round($finalCapital, 2),
            'net_profit' => round($finalCapital - $initialCapital, 2),
            'performance_percent' => round(($finalCapital / $initialCapital - 1) * 100, 2),
            'max_drawdown_percent' => round($maxDrawdown, 2),
            'trades' => count($completed),
            'winners' => $winners,
            'losers' => count($completed) - $winners,
            'hit_rate_percent' => $completed !== [] ? round($winners / count($completed) * 100, 2) : 0.0,
            'profit_factor' => $grossLosses > 0 ? round($grossWins / $grossLosses, 3) : null,
            'total_costs' => round($totalCosts, 2),
            'average_capital_utilization_percent' => $utilizationSamples !== []
                ? round(array_sum($utilizationSamples) / count($utilizationSamples), 2)
                : 0.0,
            'maximum_capital_utilization_percent' => $utilizationSamples !== []
                ? round(max($utilizationSamples), 2)
                : 0.0,
            'allocation_mode' => $allocationMode,
            'allocation_mode_label' => $allocationMode === self::ALLOCATION_FULL_INVESTMENT
                ? 'Voll investieren'
                : 'Balancing',
            'initial_equal_weight_budget' => $allocationMode === self::ALLOCATION_EQUAL_WEIGHT
                ? round($initialCapital / $maximumPositions, 2)
                : null,
            'average_position_notional' => $positionNotionals !== []
                ? round(array_sum($positionNotionals) / count($positionNotionals), 2)
                : 0.0,
            'minimum_position_notional' => $positionNotionals !== [] ? round(min($positionNotionals), 2) : 0.0,
            'maximum_position_notional' => $positionNotionals !== [] ? round(max($positionNotionals), 2) : 0.0,
            'max_stock_allocation_rate' => $maxStockAllocationRate,
            'max_stock_allocation_percent' => round($maxStockAllocationRate * 100, 2),
            'effective_max_stock_allocation_percent' => $allocationMode === self::ALLOCATION_EQUAL_WEIGHT
                ? round($maxStockAllocationRate * 100, 2)
                : 100.0,
            'maximum_positions' => $maximumPositions,
            'requested_maximum_positions' => $requestedMaximumPositions,
            'fee_rate' => $feeRate,
            'minimum_fee' => round($minimumFee, 2),
            'skipped_due_capacity' => $skippedCapacity,
            'skipped_due_cash' => $skippedCash,
            'skipped_due_same_stock_open' => $skippedOverlap,
            'model_references' => $modelReferences,
            'model_reference_performance_percent' => $singleReference['cumulative_return_percent'] ?? null,
            'model_reference_final_capital' => $singleReference['final_capital_on_full_compounding'] ?? null,
            'model_reference_equity_curve' => $singleReference['equity_curve'] ?? [],
            'trade_log' => array_values($completed),
            'equity_curve' => $equityCurve,
        ];
    }

    private function fee(float $gross, float $rate, float $minimum): float
    {
        return round(max($minimum, $gross * $rate), 2);
    }

    private function affordableNotional(float $cashBudget, float $rate, float $minimumFee): float
    {
        if ($cashBudget <= $minimumFee) {
            return 0.0;
        }

        $notional = max(0.0, min(
            $cashBudget - $minimumFee,
            $cashBudget / (1.0 + $rate),
        ));
        for ($attempt = 0; $attempt < 3; $attempt++) {
            $excess = $notional + $this->fee($notional, $rate, $minimumFee) - $cashBudget;
            if ($excess <= 0.000001) {
                break;
            }
            $notional = max(0.0, $notional - $excess - 0.000001);
        }

        return $notional;
    }

    /**
     * @param  array<string, mixed>  $source
     * @param  array<int, array<string, mixed>>  $trades
     * @return array<string, mixed>
     */
    private function modelReference(array $source, array $trades, float $initialCapital): array
    {
        usort($trades, static fn (array $left, array $right): int => [
            $left['exit_date'],
            $left['serving_strategy_trade_id'],
        ] <=> [
            $right['exit_date'],
            $right['serving_strategy_trade_id'],
        ]);
        $equityFactor = 1.0;
        $curve = [];
        foreach ($trades as $trade) {
            $equityFactor *= 1 + (float) $trade['net_return'];
            $curve[] = [
                'date' => (string) $trade['exit_date'],
                'equity' => round($initialCapital * $equityFactor, 2),
            ];
        }
        $metrics = (array) ($source['source_metrics'] ?? []);

        return [
            'configuration_key' => (string) $source['configuration_key'],
            'release_id' => (string) $source['release_id'],
            'symbol' => (string) $source['symbol'],
            'horizon_days' => (int) $source['horizon_days'],
            'variant' => (string) $source['variant'],
            'variant_label' => (string) $source['variant_label'],
            'model_name' => (string) $source['model_name'],
            'strategy_run_id' => (string) $source['strategy_run_id'],
            'strategy' => (string) $source['strategy'],
            'trades' => (int) ($metrics['trades'] ?? count($trades)),
            'hit_rate_percent' => round((float) ($metrics['hit_rate'] ?? 0) * 100, 2),
            'profit_factor' => isset($metrics['profit_factor']) ? round((float) $metrics['profit_factor'], 3) : null,
            'average_net_trade_percent' => round((float) ($metrics['average_net_trade'] ?? 0) * 100, 2),
            'cumulative_return_percent' => round((float) ($metrics['cumulative_return'] ?? ($equityFactor - 1)) * 100, 2),
            'max_drawdown_percent' => round(abs((float) ($metrics['max_drawdown'] ?? 0)) * 100, 2),
            'stored_transaction_cost_percent' => round((float) ($source['stored_transaction_cost'] ?? 0) * 100, 4),
            'start_date' => min(array_column($trades, 'entry_date')),
            'end_date' => max(array_column($trades, 'exit_date')),
            'final_capital_on_full_compounding' => round($initialCapital * $equityFactor, 2),
            'equity_curve' => $curve,
        ];
    }
}
