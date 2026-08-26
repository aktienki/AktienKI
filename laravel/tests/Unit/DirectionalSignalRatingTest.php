<?php

namespace Tests\Unit;

use App\Support\DirectionalSignalRating;
use PHPUnit\Framework\TestCase;

class DirectionalSignalRatingTest extends TestCase
{
    public function test_complete_strong_positive_forecast_can_receive_one_plus(): void
    {
        $rating = DirectionalSignalRating::calculate([5 => 8, 10 => 10, 15 => 12, 20 => 15], 90);

        $this->assertSame('1+', $rating['label']);
        $this->assertGreaterThanOrEqual(90, $rating['percent']);
    }

    public function test_complete_strong_negative_forecast_can_receive_five_minus(): void
    {
        $rating = DirectionalSignalRating::calculate([5 => -8, 10 => -10, 15 => -12, 20 => -15], 90);

        $this->assertSame('5−', $rating['label']);
        $this->assertLessThan(10, $rating['percent']);
    }

    public function test_conflicting_horizons_remain_near_neutral(): void
    {
        $rating = DirectionalSignalRating::calculate([5 => 4, 10 => -3, 15 => 2, 20 => -1], 90);

        $this->assertContains($rating['label'], ['3+', '3−']);
    }

    public function test_extreme_grade_requires_all_four_horizons(): void
    {
        $rating = DirectionalSignalRating::calculate([20 => 30], 100);

        $this->assertNotSame('1+', $rating['label']);
    }
}
