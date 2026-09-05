<?php

namespace Tests\Unit;

use App\Support\TimeAdjustedSignalRating;
use Carbon\CarbonImmutable;
use Tests\TestCase;

class TimeAdjustedSignalRatingTest extends TestCase
{
    public function test_it_deducts_costs_and_marks_an_unprofitable_forecast_as_not_viable(): void
    {
        CarbonImmutable::setTestNow('2026-09-07 09:00:00');

        $rating = TimeAdjustedSignalRating::calculate([10 => .8, 20 => .9, 40 => 1.0], '2026-09-07', 90, .5, 1.0);

        $this->assertFalse($rating['viable']);
        $this->assertLessThan(50, $rating['percent']);
        CarbonImmutable::setTestNow();
    }

    public function test_expired_horizons_are_removed_from_the_rating(): void
    {
        CarbonImmutable::setTestNow('2026-09-22 09:00:00');

        $rating = TimeAdjustedSignalRating::calculate([10 => 12, 20 => 4, 40 => 6], '2026-09-07', 80, .5, 1.0);

        $this->assertArrayNotHasKey(10, $rating['remaining_horizons']);
        $this->assertSame(9, $rating['remaining_horizons'][20]);
        $this->assertTrue($rating['viable']);
        CarbonImmutable::setTestNow();
    }
}
