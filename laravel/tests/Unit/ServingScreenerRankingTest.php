<?php

namespace Tests\Unit;

use App\Services\ServingScreenerService;
use Illuminate\Support\Collection;
use ReflectionClass;
use Tests\TestCase;

final class ServingScreenerRankingTest extends TestCase
{
    public function test_composite_score_dominates_the_screener_ranking(): void
    {
        $service = (new ReflectionClass(ServingScreenerService::class))->newInstanceWithoutConstructor();
        $method = (new ReflectionClass(ServingScreenerService::class))->getMethod('rankingPriority');

        $higherCompositeScore = (object) [
            'composite_score' => 81,
            'ranking_score' => 1,
            'expected_return_20d' => -10,
            'risk_percent' => 100,
        ];
        $lowerCompositeScore = (object) [
            'composite_score' => 80,
            'ranking_score' => 100,
            'expected_return_20d' => 100,
            'risk_percent' => 0,
        ];

        $this->assertGreaterThan(
            $method->invoke($service, $lowerCompositeScore),
            $method->invoke($service, $higherCompositeScore),
        );
    }

    public function test_legacy_model_score_only_breaks_equal_composite_scores(): void
    {
        $service = (new ReflectionClass(ServingScreenerService::class))->newInstanceWithoutConstructor();
        $method = (new ReflectionClass(ServingScreenerService::class))->getMethod('rankingPriority');

        $higherModelScore = (object) [
            'composite_score' => 80,
            'ranking_score' => 75,
            'expected_return_20d' => 2,
            'risk_percent' => 30,
        ];
        $lowerModelScore = (object) [
            'composite_score' => 80,
            'ranking_score' => 70,
            'expected_return_20d' => 2,
            'risk_percent' => 30,
        ];

        $this->assertGreaterThan(
            $method->invoke($service, $lowerModelScore),
            $method->invoke($service, $higherModelScore),
        );
    }

    /**
     * percentiles() used to scan the whole sorted value list per stock with
     * two Collection::filter() closures (O(n^2) for the metric) - this
     * verifies the binary-search replacement (lowerBound()) agrees with that
     * original logic exactly, including nulls and real duplicate values.
     */
    public function test_percentiles_matches_the_original_linear_scan_logic(): void
    {
        $service = (new ReflectionClass(ServingScreenerService::class))->newInstanceWithoutConstructor();
        $method = (new ReflectionClass(ServingScreenerService::class))->getMethod('percentiles');

        mt_srand(42);
        $stocks = collect(range(1, 250))->map(fn (int $id): object => (object) [
            'instrument_id' => $id,
            'value' => mt_rand(0, 100) < 5 ? null : round(mt_rand(-10000, 10000) / 100, 4),
        ]);
        // Real duplicates, not just near-misses within the equality epsilon.
        foreach ([5, 6, 7] as $index) {
            $stocks[$index]->value = 42.5;
        }
        $metric = fn (object $stock): mixed => $stock->value;

        $expected = $this->linearScanPercentiles($stocks, $metric);
        $actual = $method->invoke($service, $stocks, $metric);

        $this->assertSame($expected->count(), $actual->count());
        foreach ($expected as $instrumentId => $expectedValue) {
            $this->assertSame($expectedValue, $actual->get($instrumentId), "mismatch for instrument {$instrumentId}");
        }
    }

    public function test_percentiles_returns_empty_when_no_stock_has_a_numeric_value(): void
    {
        $service = (new ReflectionClass(ServingScreenerService::class))->newInstanceWithoutConstructor();
        $method = (new ReflectionClass(ServingScreenerService::class))->getMethod('percentiles');

        $stocks = collect([(object) ['instrument_id' => 1, 'value' => null]]);

        $this->assertTrue($method->invoke($service, $stocks, fn (object $stock): mixed => $stock->value)->isEmpty());
    }

    /** The pre-optimization O(n^2) reference implementation, kept only for this test. */
    private function linearScanPercentiles(Collection $stocks, callable $metric): Collection
    {
        $values = $stocks->map($metric)->filter(fn ($value): bool => is_numeric($value))->map(fn ($value): float => (float) $value)->sort()->values();
        if ($values->isEmpty()) {
            return collect();
        }

        return $stocks->mapWithKeys(function (object $stock) use ($metric, $values): array {
            $value = $metric($stock);
            if (! is_numeric($value)) {
                return [$stock->instrument_id => null];
            }
            $value = (float) $value;
            $below = $values->filter(fn (float $candidate): bool => $candidate < $value)->count();
            $equal = $values->filter(fn (float $candidate): bool => abs($candidate - $value) < 0.0000001)->count();

            return [$stock->instrument_id => round((($below + (($equal + 1) / 2)) / $values->count()) * 100, 1)];
        });
    }
}
