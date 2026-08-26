<?php

namespace Tests\Unit;

use App\Services\CorrelationAnalysisService;
use Tests\TestCase;

final class CorrelationAnalysisServiceTest extends TestCase
{
    public function test_it_detects_a_perfect_positive_relationship(): void
    {
        $result = app(CorrelationAnalysisService::class)->analyze(collect([
            ['score' => 1, 'profit_factor' => 1.1],
            ['score' => 2, 'profit_factor' => 1.4],
            ['score' => 3, 'profit_factor' => 1.7],
            ['score' => 4, 'profit_factor' => 2.0],
        ]), 'score', 'profit_factor');

        $this->assertSame(4, $result['samples']);
        $this->assertSame(1.0, $result['pearson']);
        $this->assertSame(1.0, $result['spearman']);
        $this->assertSame('positiver', $result['direction']);
    }

    public function test_spearman_uses_average_ranks_for_ties(): void
    {
        $result = app(CorrelationAnalysisService::class)->analyze(collect([
            ['score' => 1, 'profit_factor' => 1],
            ['score' => 1, 'profit_factor' => 1],
            ['score' => 2, 'profit_factor' => 2],
            ['score' => 3, 'profit_factor' => 3],
        ]), 'score', 'profit_factor');

        $this->assertSame(1.0, $result['spearman']);
    }

    public function test_it_returns_null_for_too_few_samples(): void
    {
        $result = app(CorrelationAnalysisService::class)->analyze(collect([
            ['score' => 1, 'profit_factor' => 1],
            ['score' => 2, 'profit_factor' => 2],
        ]), 'score', 'profit_factor');

        $this->assertNull($result['pearson']);
        $this->assertNull($result['spearman']);
    }
}
