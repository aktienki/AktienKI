<?php

namespace App\Services;

use Carbon\CarbonImmutable;
use InvalidArgumentException;
use RuntimeException;

final class PumExitMarketSeriesExportService
{
    public const SCHEMA_VERSION = 1;

    public const DEFAULT_DAYS = 800;

    private const MINIMUM_DAYS = 2;

    private const MAXIMUM_DAYS = 5000;

    private const XETRA_TIMEZONE = 'Europe/Berlin';

    private const EXCHANGE_SERIES = [
        'pum' => [
            'symbol' => 'PUM',
            'provider_symbol' => 'PUM:XETR',
            'exchange' => 'XETR',
            'currency' => 'EUR',
            'provider' => 'twelve_data',
            'isin' => 'DE0006969603',
        ],
        'home_index' => [
            'symbol' => 'EXS1',
            'provider_symbol' => 'EXS1:XETR',
            'exchange' => 'XETR',
            'currency' => 'EUR',
            'provider' => 'twelve_data',
            'isin' => 'DE0005933931',
        ],
    ];

    private const FRED_SERIES = [
        'interest_rate_2y' => [
            'symbol' => 'DGS2',
            'provider_symbol' => 'DGS2:FRED',
            'exchange' => 'FRED',
            'currency' => 'INDEX',
            'provider' => 'fred',
        ],
        'vix' => [
            'symbol' => 'VIXCLS',
            'provider_symbol' => 'VIXCLS:FRED',
            'exchange' => 'FRED',
            'currency' => 'INDEX',
            'provider' => 'fred',
        ],
    ];

    public function __construct(
        private readonly TwelveDataService $twelveData,
        private readonly FredDailySeriesService $fred,
    ) {}

    /**
     * Build the complete, read-only input envelope expected by the PUM forward scorer.
     *
     * No partial envelope is returned: every canonical series must provide exactly
     * the requested number of valid, unique and chronologically sorted daily bars.
     */
    public function export(int $days = self::DEFAULT_DAYS): array
    {
        if ($days < self::MINIMUM_DAYS || $days > self::MAXIMUM_DAYS) {
            throw new InvalidArgumentException(sprintf(
                'days must be between %d and %d',
                self::MINIMUM_DAYS,
                self::MAXIMUM_DAYS,
            ));
        }

        $series = [];
        foreach (self::EXCHANGE_SERIES as $key => $identity) {
            $history = $this->twelveData->dailyHistory(
                $identity['provider_symbol'],
                $days,
            );
            $series[$key] = [
                ...$identity,
                'adjustment' => 'all',
                'bars' => $this->exchangeBars($key, $history, $days),
            ];
        }

        foreach (self::FRED_SERIES as $key => $identity) {
            $series[$key] = [
                ...$identity,
                'adjustment' => null,
                'bars' => $this->fredBars($key, $identity['symbol'], $days),
            ];
        }

        if (array_keys($series) !== [
            'pum',
            'home_index',
            'interest_rate_2y',
            'vix',
        ]) {
            throw new RuntimeException('canonical PUM market series are incomplete');
        }

        return [
            'schema_version' => self::SCHEMA_VERSION,
            'generated_at' => CarbonImmutable::now('UTC')->format('Y-m-d\TH:i:s\Z'),
            'series' => $series,
        ];
    }

    private function exchangeBars(
        string $key,
        array $history,
        int $days,
    ): array {
        $bars = [];
        foreach ($history as $rowNumber => $row) {
            if (! is_array($row) || ! $this->numeric($row['timestamp'] ?? null)) {
                throw new RuntimeException("{$key} row {$rowNumber} has no valid timestamp");
            }

            $date = CarbonImmutable::createFromTimestampUTC((int) $row['timestamp'])
                ->setTimezone(self::XETRA_TIMEZONE)
                ->toDateString();
            $bars[] = $this->validatedBar($key, $date, $row, requireVolume: true);
        }

        return $this->finalizeBars($key, $bars, $days);
    }

    private function fredBars(string $key, string $symbol, int $days): array
    {
        $startDate = CarbonImmutable::now('UTC')
            ->subDays(max(365, $days * 2))
            ->toDateString();
        $endDate = CarbonImmutable::now('UTC')->toDateString();
        $observations = $this->fredObservations(
            $key,
            $symbol,
            $this->fred->csv($symbol, $startDate, $endDate),
        );
        $bars = array_map(
            fn (array $row): array => $this->validatedBar(
                $key,
                $row['date'],
                [
                    'open' => $row['value'],
                    'high' => $row['value'],
                    'low' => $row['value'],
                    'close' => $row['value'],
                    'adjusted_close' => $row['value'],
                    'volume' => 0.0,
                ],
                requireVolume: false,
            ),
            $observations,
        );

        return $this->finalizeBars($key, $bars, $days);
    }

    private function fredObservations(
        string $key,
        string $symbol,
        string $csv,
    ): array {
        $lines = preg_split('/\R/', trim($csv));
        if (! is_array($lines) || $lines === []) {
            throw new RuntimeException("{$key} provider returned an empty CSV");
        }

        $header = str_getcsv((string) array_shift($lines));
        if (
            count($header) < 2
            || ! in_array(strtolower(trim((string) $header[0])), ['date', 'observation_date'], true)
            || strtoupper(trim((string) $header[1])) !== $symbol
        ) {
            throw new RuntimeException("{$key} provider returned an unexpected CSV schema");
        }

        $rows = [];
        foreach ($lines as $lineNumber => $line) {
            if (trim((string) $line) === '') {
                continue;
            }
            $columns = str_getcsv((string) $line);
            if (count($columns) < 2) {
                throw new RuntimeException("{$key} CSV row ".($lineNumber + 2).' is incomplete');
            }
            $date = trim((string) $columns[0]);
            $value = trim((string) $columns[1]);
            // FRED represents missing observations either as "." or, in newer
            // CSV responses, as an empty value (for example on US holidays).
            if ($value === '' || $value === '.') {
                continue;
            }
            if (! $this->date($date) || ! $this->numeric($value)) {
                throw new RuntimeException("{$key} CSV row ".($lineNumber + 2).' is invalid');
            }
            $number = (float) $value;
            if (! is_finite($number)) {
                throw new RuntimeException("{$key} CSV row ".($lineNumber + 2).' is not finite');
            }
            $rows[] = ['date' => $date, 'value' => $number];
        }

        return $rows;
    }

    private function validatedBar(
        string $key,
        string $date,
        array $row,
        bool $requireVolume,
    ): array {
        if (! $this->date($date)) {
            throw new RuntimeException("{$key} contains an invalid trading date");
        }

        $values = [];
        foreach (['open', 'high', 'low', 'close', 'adjusted_close'] as $field) {
            if (! $this->numeric($row[$field] ?? null)) {
                throw new RuntimeException("{$key} {$date} has no valid {$field}");
            }
            $values[$field] = (float) $row[$field];
            if (! is_finite($values[$field])) {
                throw new RuntimeException("{$key} {$date} has a non-finite {$field}");
            }
        }

        if (
            $values['high'] < max($values['open'], $values['low'], $values['close'])
            || $values['low'] > min($values['open'], $values['high'], $values['close'])
        ) {
            throw new RuntimeException("{$key} {$date} has inconsistent OHLC values");
        }
        if (in_array($key, ['pum', 'home_index'], true)
            && min($values) <= 0.0) {
            throw new RuntimeException("{$key} {$date} has a non-positive price");
        }

        $volume = $row['volume'] ?? null;
        if ($requireVolume && ! $this->numeric($volume)) {
            throw new RuntimeException("{$key} {$date} has no valid volume");
        }
        $volume = $this->numeric($volume) ? (float) $volume : 0.0;
        if (! is_finite($volume) || $volume < 0.0) {
            throw new RuntimeException("{$key} {$date} has invalid volume");
        }

        return [
            'date' => $date,
            ...$values,
            'volume' => $volume,
        ];
    }

    private function finalizeBars(string $key, array $bars, int $days): array
    {
        usort($bars, static fn (array $left, array $right): int => $left['date'] <=> $right['date']);
        $dates = array_column($bars, 'date');
        if (count($dates) !== count(array_unique($dates))) {
            throw new RuntimeException("{$key} contains duplicate trading dates");
        }
        if (count($bars) < $days) {
            throw new RuntimeException(sprintf(
                '%s has only %d valid bars; %d required',
                $key,
                count($bars),
                $days,
            ));
        }

        return array_values(array_slice($bars, -$days));
    }

    private function date(string $value): bool
    {
        $date = CarbonImmutable::createFromFormat('!Y-m-d', $value, 'UTC');

        return $date !== false && $date->toDateString() === $value;
    }

    private function numeric(mixed $value): bool
    {
        return ! is_bool($value) && is_numeric($value);
    }
}
