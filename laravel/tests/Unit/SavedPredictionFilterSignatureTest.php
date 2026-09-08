<?php

namespace Tests\Unit;

use App\Http\Controllers\SavedPredictionFilterController;
use Tests\TestCase;

final class SavedPredictionFilterSignatureTest extends TestCase
{
    public function test_array_filters_are_normalized_without_string_conversion(): void
    {
        $controller = app(SavedPredictionFilterController::class);
        $signature = \Closure::bind(
            fn (array $filters): string => $this->filterSignature($filters),
            $controller,
            $controller,
        );

        $this->assertSame(
            $signature(['quality_horizons' => [10, 20, 40]]),
            $signature(['quality_horizons' => [10, 20, 40]]),
        );
        $this->assertNotSame(
            $signature(['quality_horizons' => [10, 20, 40]]),
            $signature(['quality_horizons' => [10, 40]]),
        );
    }

    public function test_inactive_median_and_zero_median_are_distinct_strategies(): void
    {
        $controller = app(SavedPredictionFilterController::class);
        $signature = \Closure::bind(
            fn (array $filters): string => $this->filterSignature($filters),
            $controller,
            $controller,
        );

        $this->assertNotSame(
            $signature(['median_return_min' => null]),
            $signature(['median_return_min' => 0.0]),
        );
    }
}
