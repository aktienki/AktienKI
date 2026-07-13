<?php

namespace Tests\Unit;

use App\Services\Elo\EloCalculator;
use App\Services\Elo\EloRatingService;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class EloRatingServiceTest extends TestCase
{
    private EloRatingService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new EloRatingService(
            new EloCalculator()
        );
    }

    public function test_champion_wins_with_equal_ratings(): void
    {
        $result = $this->service->update(
            championRating: 1500,
            challengerRating: 1500,
            winner: 'champion',
        );

        $this->assertSame(1516.0, $result->championAfter);
        $this->assertSame(1484.0, $result->challengerAfter);
        $this->assertSame(16.0, $result->championChange);
        $this->assertSame(-16.0, $result->challengerChange);
    }

    public function test_challenger_wins_with_equal_ratings(): void
    {
        $result = $this->service->update(
            championRating: 1500,
            challengerRating: 1500,
            winner: 'challenger',
        );

        $this->assertSame(1484.0, $result->championAfter);
        $this->assertSame(1516.0, $result->challengerAfter);
        $this->assertSame(-16.0, $result->championChange);
        $this->assertSame(16.0, $result->challengerChange);
    }

    public function test_draw_keeps_equal_ratings_unchanged(): void
    {
        $result = $this->service->update(
            championRating: 1500,
            challengerRating: 1500,
            winner: 'draw',
        );

        $this->assertSame(1500.0, $result->championAfter);
        $this->assertSame(1500.0, $result->challengerAfter);
        $this->assertSame(0.0, $result->championChange);
        $this->assertSame(0.0, $result->challengerChange);
    }

    public function test_underdog_win_produces_larger_rating_change(): void
    {
        $favoriteWins = $this->service->update(
            championRating: 1800,
            challengerRating: 1400,
            winner: 'champion',
        );

        $underdogWins = $this->service->update(
            championRating: 1800,
            challengerRating: 1400,
            winner: 'challenger',
        );

        $this->assertGreaterThan(
            abs($favoriteWins->championChange),
            abs($underdogWins->challengerChange),
        );
    }

    public function test_total_rating_points_remain_stable(): void
    {
        $result = $this->service->update(
            championRating: 1725,
            challengerRating: 1580,
            winner: 'challenger',
        );

        $totalAfter = round(
            $result->championAfter
            + $result->challengerAfter,
            4,
        );

        $this->assertSame(3305.0, $totalAfter);
    }

    public function test_custom_k_factor_is_supported(): void
    {
        $result = $this->service->update(
            championRating: 1500,
            challengerRating: 1500,
            winner: 'challenger',
            kFactor: 16,
        );

        $this->assertSame(1492.0, $result->championAfter);
        $this->assertSame(1508.0, $result->challengerAfter);
    }

    public function test_invalid_winner_throws_exception(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->service->update(
            championRating: 1500,
            challengerRating: 1500,
            winner: 'unknown',
        );
    }

    public function test_non_positive_k_factor_throws_exception(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->service->update(
            championRating: 1500,
            challengerRating: 1500,
            winner: 'champion',
            kFactor: 0,
        );
    }

    public function test_result_can_be_converted_to_array(): void
    {
        $result = $this->service->update(
            championRating: 1500,
            challengerRating: 1500,
            winner: 'challenger',
        )->toArray();

        $expectedKeys = [
            'champion_before',
            'challenger_before',
            'champion_after',
            'challenger_after',
            'champion_change',
            'challenger_change',
            'champion_expected_score',
            'challenger_expected_score',
            'winner',
            'k_factor',
        ];

        foreach ($expectedKeys as $key) {
            $this->assertArrayHasKey($key, $result);
        }
    }
}