<?php

namespace App\Services;

use Carbon\CarbonImmutable;
use Illuminate\Http\Client\Pool;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class TwelveDataFundamentalImporter
{
    private const ENDPOINTS = [
        'profile', 'statistics', 'income_statement', 'balance_sheet', 'cash_flow', 'earnings', 'dividends',
    ];

    private const ANALYSIS_ENDPOINTS = ['recommendations', 'price_target', 'analyst_ratings/light'];

    public function __construct(private readonly TwelveDataService $marketData) {}

    public function import(object $instrument): array
    {
        $symbol = $this->marketData->providerSymbol((string) ($instrument->provider_symbol ?: $instrument->symbol));
        $baseUrl = (string) config('aktienki.twelve_data.base_url');
        $apiKey = (string) config('aktienki.twelve_data.api_key');
        $responses = Http::pool(fn (Pool $pool): array => collect(self::ENDPOINTS)
            ->mapWithKeys(fn (string $endpoint): array => [
                $endpoint => $pool->as($endpoint)->baseUrl($baseUrl)
                    ->withHeaders(['Authorization' => 'apikey '.$apiKey])
                    ->acceptJson()->timeout(25)
                    ->get($endpoint, ['symbol' => $symbol, 'outputsize' => 1]),
            ])->all());
        $payloads = [];

        foreach (self::ENDPOINTS as $endpoint) {
            $response = $responses[$endpoint];
            if (! $response->successful() || $response->json('status') === 'error' || $response->json('code')) {
                throw new RuntimeException($endpoint.': '.($response->json('message') ?: 'HTTP '.$response->status()));
            }
            $payloads[$endpoint] = $response->json() ?: [];
        }

        $now = now();
        $profile = $payloads['profile'];
        $statistics = $payloads['statistics']['statistics'] ?? $payloads['statistics'];
        $latestIncome = $this->records($payloads['income_statement'], 'income_statement')->first() ?? [];
        $latestBalance = $this->records($payloads['balance_sheet'], 'balance_sheet')->first() ?? [];
        $latestCashFlow = $this->records($payloads['cash_flow'], 'cash_flow')->first() ?? [];
        $searchable = [$statistics, $latestIncome, $latestBalance, $latestCashFlow];

        $metrics = [
            'market_cap' => $this->metric($searchable, ['market_capitalization', 'market_cap']),
            'enterprise_value' => $this->metric($searchable, ['enterprise_value']),
            'trailing_pe' => $this->metric($searchable, ['trailing_pe', 'trailing_pe_ratio', 'price_earnings_ttm']),
            'forward_pe' => $this->metric($searchable, ['forward_pe', 'forward_pe_ratio']),
            'peg_ratio' => $this->metric($searchable, ['peg_ratio']),
            'price_to_book' => $this->metric($searchable, ['price_to_book', 'price_book_mrq']),
            'price_to_sales' => $this->metric($searchable, ['price_to_sales', 'price_sales_ttm']),
            'dividend_rate' => $this->metric($searchable, ['dividend_rate', 'dividend_per_share']),
            'dividend_yield' => $this->ratio($this->metric($searchable, ['dividend_yield', 'dividend_yield_percent'])),
            'payout_ratio' => $this->ratio($this->metric($searchable, ['payout_ratio'])),
            'profit_margin' => $this->ratio($this->metric($searchable, ['profit_margin', 'net_profit_margin'])),
            'operating_margin' => $this->ratio($this->metric($searchable, ['operating_margin', 'operating_margin_ttm'])),
            'return_on_assets' => $this->ratio($this->metric($searchable, ['return_on_assets', 'return_on_assets_ttm'])),
            'return_on_equity' => $this->ratio($this->metric($searchable, ['return_on_equity', 'return_on_equity_ttm'])),
            'revenue' => $this->metric($searchable, ['total_revenue', 'revenue']),
            'revenue_growth' => $this->ratio($this->metric($searchable, ['revenue_growth', 'quarterly_revenue_growth'])),
            'gross_profit' => $this->metric($searchable, ['gross_profit', 'gross_profit_value']),
            'ebitda' => $this->metric($searchable, ['ebitda', 'ebitda_value']),
            'net_income' => $this->metric($searchable, ['net_income', 'net_income_common_stockholders']),
            'total_cash' => $this->metric($searchable, ['cash_and_cash_equivalents', 'total_cash']),
            'total_debt' => $this->metric($searchable, ['total_debt']),
            'debt_to_equity' => $this->metric($searchable, ['debt_to_equity', 'total_debt_to_equity']),
            'current_ratio' => $this->metric($searchable, ['current_ratio']),
            'quick_ratio' => $this->metric($searchable, ['quick_ratio']),
            'operating_cash_flow' => $this->metric($searchable, ['operating_cash_flow']),
            'free_cash_flow' => $this->metric($searchable, ['free_cash_flow']),
            'shares_outstanding' => $this->metric($searchable, ['shares_outstanding']),
            'float_shares' => $this->metric($searchable, ['float_shares', 'shares_float']),
        ];

        $legacy = array_filter([
            'marketCap' => $metrics['market_cap'], 'enterpriseValue' => $metrics['enterprise_value'],
            'trailingPE' => $metrics['trailing_pe'], 'forwardPE' => $metrics['forward_pe'],
            'pegRatio' => $metrics['peg_ratio'], 'priceToBook' => $metrics['price_to_book'],
            'priceToSalesTrailing12Months' => $metrics['price_to_sales'], 'dividendRate' => $metrics['dividend_rate'],
            'dividendYield' => $metrics['dividend_yield'], 'payoutRatio' => $metrics['payout_ratio'],
            'profitMargins' => $metrics['profit_margin'], 'operatingMargins' => $metrics['operating_margin'],
            'returnOnAssets' => $metrics['return_on_assets'], 'returnOnEquity' => $metrics['return_on_equity'],
            'totalRevenue' => $metrics['revenue'], 'revenueGrowth' => $metrics['revenue_growth'],
            'grossProfits' => $metrics['gross_profit'], 'ebitda' => $metrics['ebitda'],
            'netIncomeToCommon' => $metrics['net_income'], 'totalCash' => $metrics['total_cash'],
            'totalDebt' => $metrics['total_debt'], 'debtToEquity' => $metrics['debt_to_equity'],
            'currentRatio' => $metrics['current_ratio'], 'quickRatio' => $metrics['quick_ratio'],
            'operatingCashflow' => $metrics['operating_cash_flow'], 'freeCashflow' => $metrics['free_cash_flow'],
            'sharesOutstanding' => $metrics['shares_outstanding'], 'floatShares' => $metrics['float_shares'],
        ], fn ($value) => $value !== null);

        DB::transaction(function () use ($instrument, $profile, $payloads, $metrics, $legacy, $now): void {
            DB::table('instrument_fundamentals')->updateOrInsert(
                ['instrument_id' => $instrument->id, 'snapshot_date' => $now->toDateString()],
                [...$metrics, 'fiscal_date' => $this->date($this->firstValue($payloads['income_statement'], ['fiscal_date'])),
                    'retrieved_at' => $now, 'data' => json_encode($legacy), 'raw_data' => json_encode($payloads),
                    'source' => 'twelve_data', 'updated_at' => $now, 'created_at' => $now],
            );

            DB::table('instruments')->where('id', $instrument->id)->update(array_filter([
                'name' => $profile['name'] ?? null, 'sector' => $profile['sector'] ?? null,
                'industry' => $profile['industry'] ?? null, 'currency' => $profile['currency'] ?? null,
                'market_cap' => $metrics['market_cap'], 'updated_at' => $now,
            ], fn ($value) => $value !== null && $value !== ''));

            $this->storeStatements($instrument->id, 'income', $payloads['income_statement'], 'income_statement', $now);
            $this->storeStatements($instrument->id, 'balance_sheet', $payloads['balance_sheet'], 'balance_sheet', $now);
            $this->storeStatements($instrument->id, 'cash_flow', $payloads['cash_flow'], 'cash_flow', $now);
            $this->storeEarnings($instrument->id, $payloads['earnings'], $now);
            $this->storeDividends($instrument->id, $payloads['dividends'], $now);
        });

        return ['symbol' => $symbol, 'metrics' => count($legacy)];
    }

    public function importAnalysis(object $instrument): array
    {
        $symbol = $this->marketData->providerSymbol((string) ($instrument->provider_symbol ?: $instrument->symbol));
        $baseUrl = (string) config('aktienki.twelve_data.base_url');
        $apiKey = (string) config('aktienki.twelve_data.api_key');
        $responses = Http::pool(fn (Pool $pool): array => collect(self::ANALYSIS_ENDPOINTS)
            ->mapWithKeys(fn (string $endpoint): array => [
                $endpoint => $pool->as($endpoint)->baseUrl($baseUrl)
                    ->withHeaders(['Authorization' => 'apikey '.$apiKey])
                    ->acceptJson()->timeout(25)
                    ->get($endpoint, ['symbol' => $symbol]),
            ])->all());
        $payloads = [];

        foreach (self::ANALYSIS_ENDPOINTS as $endpoint) {
            $response = $responses[$endpoint];
            $payload = $response->json() ?: [];
            if (! $response->successful() || ($payload['status'] ?? null) === 'error' || isset($payload['code'])) {
                throw new RuntimeException($endpoint.': '.($payload['message'] ?? 'HTTP '.$response->status()));
            }
            $payloads[$endpoint] = $payload;
        }

        $now = now();
        $recommendations = $payloads['recommendations'];
        $trend = (array) data_get($recommendations, 'trends.current_month', []);
        $target = (array) data_get($payloads, 'price_target.price_target', []);
        $ratings = collect(data_get($payloads, 'analyst_ratings/light.ratings', []))->filter(fn ($row) => is_array($row));

        DB::transaction(function () use ($instrument, $payloads, $recommendations, $trend, $target, $ratings, $now): void {
            DB::table('instrument_analyst_consensuses')->updateOrInsert(
                ['instrument_id' => $instrument->id, 'snapshot_date' => $now->toDateString()],
                [
                    'recommendation_score' => $this->numeric($recommendations['rating'] ?? null),
                    'strong_buy' => $this->integer($trend['strong_buy'] ?? null),
                    'buy' => $this->integer($trend['buy'] ?? null),
                    'hold' => $this->integer($trend['hold'] ?? null),
                    'sell' => $this->integer($trend['sell'] ?? null),
                    'strong_sell' => $this->integer($trend['strong_sell'] ?? null),
                    'target_high' => $this->numeric($target['high'] ?? null),
                    'target_median' => $this->numeric($target['median'] ?? null),
                    'target_low' => $this->numeric($target['low'] ?? null),
                    'target_average' => $this->numeric($target['average'] ?? null),
                    'reference_price' => $this->numeric($target['current'] ?? null),
                    'currency' => $target['currency'] ?? data_get($payloads, 'price_target.meta.currency'),
                    'retrieved_at' => $now,
                    'raw_data' => json_encode($payloads, JSON_THROW_ON_ERROR),
                    'source' => 'twelve_data',
                    'updated_at' => $now,
                    'created_at' => $now,
                ],
            );

            foreach ($ratings as $rating) {
                $date = $this->date($rating['date'] ?? null);
                $firm = trim((string) ($rating['firm'] ?? ''));
                if (! $date || $firm === '') continue;
                DB::table('instrument_analyst_ratings')->updateOrInsert(
                    ['instrument_id' => $instrument->id, 'rating_date' => $date, 'firm' => $firm],
                    [
                        'rating_change' => $rating['rating_change'] ?? null,
                        'rating_current' => $rating['rating_current'] ?? null,
                        'rating_prior' => $rating['rating_prior'] ?? null,
                        'retrieved_at' => $now,
                        'data' => json_encode($rating, JSON_THROW_ON_ERROR),
                        'source' => 'twelve_data',
                        'updated_at' => $now,
                        'created_at' => $now,
                    ],
                );
            }
        });

        return ['symbol' => $symbol, 'ratings' => $ratings->count()];
    }

    private function records(array $payload, string $root)
    {
        $records = $payload[$root] ?? [];
        return collect(Arr::isList($records) ? $records : [])->filter(fn ($row) => is_array($row));
    }

    private function storeStatements(int $instrumentId, string $type, array $payload, string $root, $now): void
    {
        foreach ($this->records($payload, $root) as $row) {
            $date = $this->date($row['fiscal_date'] ?? $row['date'] ?? null);
            if (! $date) continue;
            DB::table('instrument_financial_statements')->updateOrInsert(
                ['instrument_id' => $instrumentId, 'statement_type' => $type, 'fiscal_date' => $date, 'period' => (string) ($row['period'] ?? 'unknown')],
                ['currency' => $row['currency'] ?? null, 'reported_at' => $this->date($row['reported_at'] ?? null),
                    'retrieved_at' => $now, 'data' => json_encode($row), 'source' => 'twelve_data', 'updated_at' => $now, 'created_at' => $now],
            );
        }
    }

    private function storeEarnings(int $instrumentId, array $payload, $now): void
    {
        foreach ($this->records($payload, 'earnings') as $row) {
            $date = $this->date($row['date'] ?? $row['fiscal_date'] ?? null);
            if (! $date) continue;
            DB::table('instrument_earnings')->updateOrInsert(
                ['instrument_id' => $instrumentId, 'earnings_date' => $date, 'period' => (string) ($row['period'] ?? 'unknown')],
                ['eps_estimate' => $this->numeric($row['eps_estimate'] ?? null), 'eps_actual' => $this->numeric($row['eps_actual'] ?? null),
                    'surprise_percent' => $this->numeric($row['surprise_prc'] ?? $row['surprise_percent'] ?? null),
                    'retrieved_at' => $now, 'data' => json_encode($row), 'source' => 'twelve_data', 'updated_at' => $now, 'created_at' => $now],
            );
        }
    }

    private function storeDividends(int $instrumentId, array $payload, $now): void
    {
        foreach ($this->records($payload, 'dividends') as $row) {
            $date = $this->date($row['ex_date'] ?? $row['date'] ?? null);
            if (! $date) continue;
            DB::table('instrument_dividends')->updateOrInsert(
                ['instrument_id' => $instrumentId, 'ex_date' => $date],
                ['record_date' => $this->date($row['record_date'] ?? null), 'payment_date' => $this->date($row['payment_date'] ?? null),
                    'amount' => $this->numeric($row['amount'] ?? null), 'currency' => $row['currency'] ?? null,
                    'retrieved_at' => $now, 'data' => json_encode($row), 'source' => 'twelve_data', 'updated_at' => $now, 'created_at' => $now],
            );
        }
    }

    private function metric(array $payloads, array $candidates): ?float
    {
        foreach ($payloads as $payload) {
            $flat = $this->flatten($payload);
            foreach ($candidates as $candidate) {
                $key = strtolower($candidate);
                if (isset($flat[$key]) && is_numeric($flat[$key])) return (float) $flat[$key];
            }
        }
        return null;
    }

    private function flatten(array $payload): array
    {
        $flat = [];
        array_walk_recursive($payload, function ($value, $key) use (&$flat): void {
            if (! array_key_exists(strtolower((string) $key), $flat)) $flat[strtolower((string) $key)] = $value;
        });
        return $flat;
    }

    private function firstValue(array $payload, array $keys): mixed
    {
        $flat = $this->flatten($payload);
        foreach ($keys as $key) if (array_key_exists(strtolower($key), $flat)) return $flat[strtolower($key)];
        return null;
    }

    private function ratio(?float $value): ?float { return $value !== null && abs($value) > 1 ? $value / 100 : $value; }
    private function numeric(mixed $value): ?float { return is_numeric($value) ? (float) $value : null; }
    private function integer(mixed $value): ?int { return is_numeric($value) ? (int) $value : null; }
    private function date(mixed $value): ?string
    {
        if (! $value) return null;
        try { return CarbonImmutable::parse($value)->toDateString(); } catch (\Throwable) { return null; }
    }
}
