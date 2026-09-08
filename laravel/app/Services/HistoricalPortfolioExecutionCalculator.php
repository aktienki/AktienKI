<?php

namespace App\Services;

use DateTimeImmutable;
use DateTimeInterface;
use InvalidArgumentException;

final class HistoricalPortfolioExecutionCalculator
{
    /**
     * Execute historical trade candidates with a fixed fee on entry and exit.
     *
     * The target position budget includes the entry fee. Positions exiting on a
     * date remain open while that date's entry candidates are evaluated.
     *
     * @param  iterable<int|string, array<string, mixed>|object>  $candidates
     * @return array<string, mixed>
     */
    public function calculate(
        iterable $candidates,
        float $initialCapital,
        int $maxPositions,
        float $targetPositionBudget,
        float $fixedFee = 10.0,
        bool $dynamicCapitalWeighting = false,
    ): array {
        $this->validateConfiguration(
            $initialCapital,
            $maxPositions,
            $targetPositionBudget,
            $fixedFee,
        );

        $candidatesByEntryDate = [];
        $skipCounts = [
            'invalid_date' => 0,
            'invalid_price' => 0,
            'invalid_gross_return' => 0,
            'capacity' => 0,
            'same_instrument_open' => 0,
            'cash' => 0,
        ];
        $sequence = 0;

        foreach ($candidates as $candidate) {
            $candidate = $this->candidateToArray($candidate);
            $entryDate = $this->date($candidate['entry_date'] ?? null);
            $exitDate = $this->date($candidate['exit_date'] ?? null);

            if ($entryDate === null || $exitDate === null || $exitDate < $entryDate) {
                $skipCounts['invalid_date']++;

                continue;
            }

            $entryPrice = $this->positiveFloat($candidate['entry_price'] ?? null);
            $exitPrice = $this->positiveFloat($candidate['exit_price'] ?? null);

            if ($entryPrice === null || $exitPrice === null) {
                $skipCounts['invalid_price']++;

                continue;
            }

            if (! is_numeric($candidate['gross_return'] ?? null)
                || ! is_finite((float) $candidate['gross_return'])) {
                $skipCounts['invalid_gross_return']++;

                continue;
            }

            $candidatesByEntryDate[$entryDate][] = [
                ...$candidate,
                'id' => $candidate['id'] ?? null,
                'instrument_id' => $candidate['instrument_id'] ?? null,
                'entry_date' => $entryDate,
                'exit_date' => $exitDate,
                'entry_price' => $entryPrice,
                'exit_price' => $exitPrice,
                'gross_return' => (float) $candidate['gross_return'],
                '_execution_sequence' => $sequence++,
            ];
        }

        ksort($candidatesByEntryDate);

        $cash = $initialCapital;
        $openPositions = [];
        $completedTrades = [];
        $totalCosts = 0.0;

        foreach ($candidatesByEntryDate as $entryDate => $entryCandidates) {
            $this->closePositionsBefore(
                $openPositions,
                $entryDate,
                $cash,
                $totalCosts,
                $completedTrades,
                $fixedFee,
            );

            usort($entryCandidates, static fn (array $left, array $right): int => $left['_execution_sequence']
                <=> $right['_execution_sequence']);

            foreach ($entryCandidates as $candidate) {
                if ($this->hasOpenPositionForInstrument($openPositions, $candidate['instrument_id'])) {
                    $skipCounts['same_instrument_open']++;

                    continue;
                }

                if (count($openPositions) >= $maxPositions) {
                    $skipCounts['capacity']++;

                    continue;
                }

                // Entry fees are already spent and therefore not part of the
                // investable portfolio value used for dynamic sizing.
                $realizedPortfolioValue = $cash + array_sum(array_column($openPositions, 'allocated_capital_eur'));
                $dynamicFactor = $dynamicCapitalWeighting
                    ? max(0.0, $realizedPortfolioValue / $initialCapital)
                    : 1.0;
                $effectiveTargetBudget = $targetPositionBudget * $dynamicFactor;
                $availableBudget = min($effectiveTargetBudget, $cash);
                $quantity = (int) floor(($availableBudget - $fixedFee) / $candidate['entry_price']);

                if ($quantity < 1) {
                    $skipCounts['cash']++;

                    continue;
                }

                $allocatedCapital = $quantity * $candidate['entry_price'];
                $entryDebit = $allocatedCapital + $fixedFee;

                if ($entryDebit > $cash + 0.00000001) {
                    $skipCounts['cash']++;

                    continue;
                }

                $cash -= $entryDebit;
                $totalCosts += $fixedFee;
                $openPositions[] = [
                    ...$candidate,
                    'quantity' => $quantity,
                    'allocated_capital_eur' => $allocatedCapital,
                    'entry_debit' => $entryDebit,
                    'effective_target_position_budget' => $effectiveTargetBudget,
                    'dynamic_capital_factor' => $dynamicFactor,
                ];
            }

            // An exit on the entry date is intentionally processed only after
            // all of that date's entries, so its slot and capital remain blocked.
            $this->closePositionsThrough(
                $openPositions,
                $entryDate,
                $cash,
                $totalCosts,
                $completedTrades,
                $fixedFee,
            );
        }

        $this->closeAllPositions(
            $openPositions,
            $cash,
            $totalCosts,
            $completedTrades,
            $fixedFee,
        );

        usort($completedTrades, static fn (array $left, array $right): int => $left['_execution_sequence']
            <=> $right['_execution_sequence']);
        foreach ($completedTrades as &$trade) {
            unset($trade['_execution_sequence']);
        }
        unset($trade);
        [$equityCurve, $maxDrawdownPercent] = $this->equityCurve($completedTrades, $initialCapital);

        return [
            'initial_capital' => $initialCapital,
            'final_cash' => $cash,
            'total_costs' => $totalCosts,
            'trade_count' => count($completedTrades),
            'trade_log' => $completedTrades,
            'equity_curve' => $equityCurve,
            'max_drawdown_percent' => $maxDrawdownPercent,
            'skip_counts' => $skipCounts,
            'skipped_due_invalid_date' => $skipCounts['invalid_date'],
            'skipped_due_invalid_price' => $skipCounts['invalid_price'],
            'skipped_due_invalid_gross_return' => $skipCounts['invalid_gross_return'],
            'skipped_due_capacity' => $skipCounts['capacity'],
            'skipped_due_same_instrument_open' => $skipCounts['same_instrument_open'],
            'skipped_due_cash' => $skipCounts['cash'],
        ];
    }

    private function validateConfiguration(
        float $initialCapital,
        int $maxPositions,
        float $targetPositionBudget,
        float $fixedFee,
    ): void {
        if (! is_finite($initialCapital) || $initialCapital <= 0
            || $maxPositions < 1
            || ! is_finite($targetPositionBudget) || $targetPositionBudget <= 0
            || ! is_finite($fixedFee) || $fixedFee < 0) {
            throw new InvalidArgumentException('Invalid historical portfolio execution parameters.');
        }
    }

    /** @param array<string, mixed>|object $candidate */
    private function candidateToArray(array|object $candidate): array
    {
        if (is_array($candidate)) {
            return $candidate;
        }

        if (method_exists($candidate, 'toArray')) {
            return (array) $candidate->toArray();
        }

        return get_object_vars($candidate);
    }

    private function date(mixed $value): ?string
    {
        if ($value instanceof DateTimeInterface) {
            return $value->format('Y-m-d');
        }

        if (! is_string($value) || strlen($value) < 10) {
            return null;
        }

        $date = substr($value, 0, 10);
        $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $date);

        return $parsed instanceof DateTimeImmutable && $parsed->format('Y-m-d') === $date
            ? $date
            : null;
    }

    private function positiveFloat(mixed $value): ?float
    {
        if (! is_numeric($value)) {
            return null;
        }

        $value = (float) $value;

        return is_finite($value) && $value > 0 ? $value : null;
    }

    /**
     * @param  array<int, array<string, mixed>>  $positions
     * @param  array<int, array<string, mixed>>  $completedTrades
     */
    private function closePositionsBefore(
        array &$positions,
        string $date,
        float &$cash,
        float &$totalCosts,
        array &$completedTrades,
        float $fixedFee,
    ): void {
        $this->closeMatchingPositions(
            $positions,
            static fn (array $position): bool => $position['exit_date'] < $date,
            $cash,
            $totalCosts,
            $completedTrades,
            $fixedFee,
        );
    }

    /**
     * @param  array<int, array<string, mixed>>  $positions
     * @param  array<int, array<string, mixed>>  $completedTrades
     */
    private function closePositionsThrough(
        array &$positions,
        string $date,
        float &$cash,
        float &$totalCosts,
        array &$completedTrades,
        float $fixedFee,
    ): void {
        $this->closeMatchingPositions(
            $positions,
            static fn (array $position): bool => $position['exit_date'] <= $date,
            $cash,
            $totalCosts,
            $completedTrades,
            $fixedFee,
        );
    }

    /**
     * @param  array<int, array<string, mixed>>  $positions
     * @param  array<int, array<string, mixed>>  $completedTrades
     */
    private function closeAllPositions(
        array &$positions,
        float &$cash,
        float &$totalCosts,
        array &$completedTrades,
        float $fixedFee,
    ): void {
        $this->closeMatchingPositions(
            $positions,
            static fn (array $position): bool => true,
            $cash,
            $totalCosts,
            $completedTrades,
            $fixedFee,
        );
    }

    /**
     * @param  array<int, array<string, mixed>>  $positions
     * @param  callable(array<string, mixed>): bool  $shouldClose
     * @param  array<int, array<string, mixed>>  $completedTrades
     */
    private function closeMatchingPositions(
        array &$positions,
        callable $shouldClose,
        float &$cash,
        float &$totalCosts,
        array &$completedTrades,
        float $fixedFee,
    ): void {
        $closing = [];
        $remaining = [];

        foreach ($positions as $position) {
            if ($shouldClose($position)) {
                $closing[] = $position;
            } else {
                $remaining[] = $position;
            }
        }

        usort($closing, static fn (array $left, array $right): int => [
            $left['exit_date'],
            (string) $left['id'],
            $left['_execution_sequence'],
        ] <=> [
            $right['exit_date'],
            (string) $right['id'],
            $right['_execution_sequence'],
        ]);

        foreach ($closing as $position) {
            $exitCredit = ($position['quantity'] * $position['exit_price']) - $fixedFee;
            $profit = $exitCredit - $position['entry_debit'];
            $netReturn = $profit / $position['entry_debit'];
            $cash += $exitCredit;
            $totalCosts += $fixedFee;
            $completedTrades[] = [
                ...$position,
                'exit_credit' => $exitCredit,
                'profit_eur' => $profit,
                'net_return_after_cost' => $netReturn,
                'transaction_cost_return' => $position['gross_return'] - $netReturn,
            ];
        }

        $positions = $remaining;
    }

    /** @param array<int, array<string, mixed>> $positions */
    private function hasOpenPositionForInstrument(array $positions, mixed $instrumentId): bool
    {
        foreach ($positions as $position) {
            if ((string) $position['instrument_id'] === (string) $instrumentId) {
                return true;
            }
        }

        return false;
    }

    /**
     * Realized event equity. Open positions remain at entry notional because
     * this executor has no daily mark-to-market price series.
     *
     * @param  array<int, array<string, mixed>>  $trades
     * @return array{0: array<int, array{date: string, equity: float}>, 1: float}
     */
    private function equityCurve(array $trades, float $initialCapital): array
    {
        $events = [];
        foreach ($trades as $trade) {
            $events[$trade['entry_date']]['entries'][] = $trade;
            $events[$trade['exit_date']]['exits'][] = $trade;
        }
        ksort($events);

        $cash = $initialCapital;
        $invested = 0.0;
        $peak = $initialCapital;
        $maxDrawdown = 0.0;
        $curve = [];
        foreach ($events as $date => $event) {
            foreach ($event['exits'] ?? [] as $trade) {
                $invested -= (float) $trade['allocated_capital_eur'];
                $cash += (float) $trade['exit_credit'];
            }
            foreach ($event['entries'] ?? [] as $trade) {
                $cash -= (float) $trade['entry_debit'];
                $invested += (float) $trade['allocated_capital_eur'];
            }
            $equity = $cash + $invested;
            $peak = max($peak, $equity);
            $maxDrawdown = max($maxDrawdown, $peak > 0 ? ($peak - $equity) / $peak * 100 : 0.0);
            $curve[] = ['date' => (string) $date, 'equity' => $equity];
        }

        return [$curve, $maxDrawdown];
    }
}
