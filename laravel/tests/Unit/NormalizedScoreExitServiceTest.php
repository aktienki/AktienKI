<?php

namespace Tests\Unit;

use App\Services\NormalizedScoreExitService;
use PHPUnit\Framework\TestCase;

final class NormalizedScoreExitServiceTest extends TestCase
{
    public function test_rating_quality_is_ordered_from_worst_to_best(): void
    {
        $this->assertSame(1, NormalizedScoreExitService::ratingQuality('5−'));
        $this->assertSame(5, NormalizedScoreExitService::ratingQuality('3−'));
        $this->assertSame(9, NormalizedScoreExitService::ratingQuality('1−'));
        $this->assertSame(10, NormalizedScoreExitService::ratingQuality('1+'));
        $this->assertNull(NormalizedScoreExitService::ratingQuality(null));
    }
}
