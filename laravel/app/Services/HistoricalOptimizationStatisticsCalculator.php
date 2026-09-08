<?php

namespace App\Services;

use Illuminate\Support\Collection;
use InvalidArgumentException;

final class HistoricalOptimizationStatisticsCalculator
{
    /**
     * Calculate fee-adjusted statistics for one instrument/horizon trade group.
     *
     * A position remains open through its exit date. Source net returns are
     * intentionally ignored because the supplied fixed fee defines the costs
     * for this optimization scenario.
     *
     * @param  iterable<int|string, array<string, mixed>|object>  $trades
     * @return array{
     *     trades: int,
     *     hit_rate: float,
     *     profit_factor: float,
     *     average_return: float,
     *     signal_quality: float,
     *     drawdown: float,
     *     returns: Collection<int, float>,
     *     selected_trades: Collection<int, array<string, mixed>|object>,
     *     skipped_overlap: int,
     *     skipped_unaffordable: int
     * }
     */
    public function calculate(iterable $trades, float $entryBudget, float $fixedFee): array
    {
        if (! is_finite($entryBudget)
            || ! is_finite($fixedFee)
            || $fixedFee < 0
            || $entryBudget <= $fixedFee) {
            throw new InvalidArgumentException('The entry budget must be greater than the fixed fee.');
        }

        $ordered = collect($trades)
            ->values()
            ->sort(function (array|object $left, array|object $right): int {
                $dateComparison = $this->date($left, 'signal_date') <=> $this->date($right, 'signal_date');

                if ($dateComparison !== 0) {
                    return $dateComparison;
                }

                return $this->tradeId($left) <=> $this->tradeId($right);
            })
            ->values();

        $selected = collect();
        $openUntil = null;
        $skippedOverlap = 0;
        $skippedUnaffordable = 0;
        $investableBudget = $entryBudget - $fixedFee;

        foreach ($ordered as $trade) {
            $signalDate = $this->date($trade, 'signal_date');

            if ($openUntil !== null && $signalDate <= $openUntil) {
                $skippedOverlap++;

                continue;
            }

            $entryPrice = $this->entryPrice($trade);
            $quantity = (int) floor($investableBudget / $entryPrice);

            if ($quantity < 1) {
                $skippedUnaffordable++;

                continue;
            }

            $selected->push($trade);
            $openUntil = $this->date($trade, 'exit_date', $signalDate);
        }

        $returns = $selected->map(function (array|object $trade) use ($investableBudget, $entryBudget, $fixedFee): float {
            $grossReturn = data_get($trade, 'gross_return');

            if (! is_numeric($grossReturn) || ! is_finite((float) $grossReturn)) {
                throw new InvalidArgumentException('Every trade must have a finite gross return.');
            }

            $entryPrice = $this->entryPrice($trade);
            $quantity = (int) floor($investableBudget / $entryPrice);
            $positionNotional = $quantity * $entryPrice;

            return (($positionNotional * (float) $grossReturn) - (2 * $fixedFee)) / $entryBudget;
        })->values();

        $wins = $returns->filter(fn (float $return): bool => $return > 0);
        $losses = $returns->filter(fn (float $return): bool => $return < 0);
        $grossLoss = abs((float) $losses->sum());
        $profitFactor = $grossLoss > 0
            ? min(3.0, (float) $wins->sum() / $grossLoss)
            : ($wins->isNotEmpty() ? 3.0 : 0.0);

        $equity = 1.0;
        $peak = 1.0;
        $drawdown = 0.0;

        foreach ($returns as $return) {
            $equity *= max(0.0, 1.0 + $return);
            $peak = max($peak, $equity);
            $drawdown = max($drawdown, $peak > 0 ? ($peak - $equity) / $peak : 0.0);
        }

        $qualityScores = $selected
            ->pluck('historical_action_score')
            ->filter(fn (mixed $score): bool => is_numeric($score));

        return [
            'trades' => $selected->count(),
            'hit_rate' => $returns->isNotEmpty() ? (float) ($wins->count() / $returns->count() * 100) : 0.0,
            'profit_factor' => $profitFactor,
            'average_return' => $returns->isNotEmpty() ? (float) $returns->avg() * 100 : 0.0,
            'signal_quality' => $qualityScores->isNotEmpty() ? (float) $qualityScores->avg() : 0.0,
            'drawdown' => $drawdown * 100,
            'returns' => $returns,
            'selected_trades' => $selected->values(),
            'skipped_overlap' => $skippedOverlap,
            'skipped_unaffordable' => $skippedUnaffordable,
        ];
    }

    /** @param array<string, mixed>|object $trade */
    private function date(array|object $trade, string $key, string $fallback = ''): string
    {
        $value = data_get($trade, $key);

        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d');
        }

        if (is_string($value) && strlen($value) >= 10) {
            return substr($value, 0, 10);
        }

        return $fallback;
    }

    /** @param array<string, mixed>|object $trade */
    private function tradeId(array|object $trade): int
    {
        $tradeId = data_get($trade, 'trade_id');

        return is_numeric($tradeId) ? (int) $tradeId : PHP_INT_MAX;
    }

    /** @param array<string, mixed>|object $trade */
    private function entryPrice(array|object $trade): float
    {
        $entryPrice = data_get($trade, 'entry_price');

        if (! is_numeric($entryPrice)
            || ! is_finite((float) $entryPrice)
            || (float) $entryPrice <= 0) {
            throw new InvalidArgumentException('Every trade must have a finite positive entry price.');
        }

        return (float) $entryPrice;
    }
}
