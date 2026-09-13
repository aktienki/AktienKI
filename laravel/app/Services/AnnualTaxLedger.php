<?php

namespace App\Services;

final class AnnualTaxLedger
{
    private ?int $year = null;
    private float $yearProfit = 0.0;
    private float $yearTax = 0.0;
    private float $lossCarry = 0.0;
    private float $openingLossCarry = 0.0;
    private float $totalTax = 0.0;

    /** @var array<int, array<string, float>> */
    private array $years = [];

    public function __construct(
        private readonly float $annualAllowance,
        private readonly float $ratePercent,
    ) {}

    public function book(string $date, float $realizedProfit): float
    {
        if ($this->ratePercent <= 0) {
            return 0.0;
        }

        $year = (int) substr($date, 0, 4);
        if ($this->year !== null && $this->year !== $year) {
            $this->closeYear();
            $this->yearProfit = 0.0;
            $this->yearTax = 0.0;
        }
        if ($this->year !== $year) {
            $this->year = $year;
            $this->openingLossCarry = $this->lossCarry;
        }

        $this->yearProfit += $realizedProfit;
        $taxable = max(0.0, $this->yearProfit - $this->openingLossCarry - max(0.0, $this->annualAllowance));
        $newTax = $taxable * max(0.0, min(100.0, $this->ratePercent)) / 100;
        $delta = $newTax - $this->yearTax;
        $this->yearTax = $newTax;
        $this->totalTax += $delta;

        return $delta;
    }

    /** @return array<int, array<string, float>> */
    public function years(): array
    {
        $this->closeYear();

        return $this->years;
    }

    public function totalTax(): float
    {
        return round($this->totalTax, 2);
    }

    private function closeYear(): void
    {
        if ($this->year === null || $this->ratePercent <= 0) {
            return;
        }

        $afterLosses = max(0.0, $this->yearProfit - $this->openingLossCarry);
        $allowance = min(max(0.0, $this->annualAllowance), $afterLosses);
        $this->years[$this->year] = [
            'realized_profit_eur' => round($this->yearProfit, 2),
            'loss_offset_eur' => round(min($this->openingLossCarry, max(0.0, $this->yearProfit)), 2),
            'allowance_used_eur' => round($allowance, 2),
            'taxable_profit_eur' => round(max(0.0, $afterLosses - $allowance), 2),
            'tax_eur' => round($this->yearTax, 2),
        ];
        $this->lossCarry = max(0.0, -($this->yearProfit - $this->openingLossCarry));
    }
}
