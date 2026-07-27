<?php

namespace App\Traits;

trait FormatsFinancialNumbers
{
    public function formatNumber(null|int|float|string $value, int $decimals = 2): string
    {
        if ($value === null || $value === '') {
            return '—';
        }

        return number_format((float) $value, $decimals, ',', '.');
    }

    public function formatPercent(null|int|float|string $value, int $decimals = 2): string
    {
        if ($value === null || $value === '') {
            return '—';
        }

        return number_format((float) $value, $decimals, ',', '.') . ' %';
    }

    public function formatCurrency(null|int|float|string $value, string $currency = 'EUR', int $decimals = 2): string
    {
        if ($value === null || $value === '') {
            return '—';
        }

        return number_format((float) $value, $decimals, ',', '.') . ' ' . strtoupper($currency);
    }
}
