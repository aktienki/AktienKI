<?php

namespace App\Support;

class DashboardFormatter
{
    public static function percent(mixed $value, int $digits = 1): string
    {
        if ($value === null || $value === '') {
            return '—';
        }

        $number = (float) $value;

        if ($number > -1 && $number < 1) {
            $number *= 100;
        }

        return number_format($number, $digits, ',', '.') . '%';
    }

    public static function signedPercent(mixed $value, int $digits = 2): string
    {
        if ($value === null || $value === '') {
            return '—';
        }

        $number = (float) $value;

        if ($number > -1 && $number < 1) {
            $number *= 100;
        }

        $prefix = $number > 0 ? '+' : '';

        return $prefix . number_format($number, $digits, ',', '.') . '%';
    }

    public static function money(mixed $value, ?string $currency = null): string
    {
        if ($value === null || $value === '') {
            return '—';
        }

        return trim(number_format((float) $value, 2, ',', '.') . ' ' . ($currency ?? ''));
    }

    public static function risk(mixed $rawOutput): ?float
    {
        $raw = self::json($rawOutput);
        $value = data_get($raw, 'risk.risk_percent');

        return $value === null ? null : (float) $value;
    }

    public static function explanation(mixed $rawOutput): ?string
    {
        $raw = self::json($rawOutput);

        return data_get($raw, 'explanation.summary');
    }

    public static function json(mixed $value): array
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

        $decoded = json_decode($value, true);

        return is_array($decoded) ? $decoded : [];
    }
}
