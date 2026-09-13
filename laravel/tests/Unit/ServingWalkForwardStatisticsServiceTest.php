<?php

namespace Tests\Unit;

use App\Services\ServingWalkForwardStatisticsService;
use Illuminate\Support\Collection;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ServingWalkForwardStatisticsServiceTest extends TestCase
{
    #[Test]
    public function it_calculates_net_trade_metrics_in_percent(): void
    {
        $metrics = (new ServingWalkForwardStatisticsService)->metrics(new Collection([
            (object) ['exit_date' => '2026-01-10', 'net_return' => 0.10],
            (object) ['exit_date' => '2026-02-10', 'net_return' => -0.05],
            (object) ['exit_date' => '2026-03-10', 'net_return' => 0.20],
        ]), collect([0.02, 0.04, 0.03]));

        self::assertSame(3, $metrics->trades);
        self::assertEqualsWithDelta(66.6667, $metrics->hit_rate, 0.001);
        self::assertEqualsWithDelta(6.0, $metrics->profit_factor, 0.001);
        self::assertEqualsWithDelta(8.3333, $metrics->average_return, 0.001);
        self::assertEqualsWithDelta(10.0, $metrics->median_return, 0.001);
        self::assertEqualsWithDelta(-5.0, $metrics->max_drawdown, 0.001);
        self::assertEqualsWithDelta(10.2740, $metrics->volatility, 0.001);
        self::assertEqualsWithDelta(0.03, $metrics->average_entry_score, 0.0001);
    }

    #[Test]
    public function it_returns_empty_metrics_without_fabricating_evidence(): void
    {
        $metrics = (new ServingWalkForwardStatisticsService)->metrics(collect());

        self::assertSame(0, $metrics->trades);
        self::assertNull($metrics->hit_rate);
        self::assertNull($metrics->average_return);
        self::assertNull($metrics->max_drawdown);
        self::assertNull($metrics->volatility);
        self::assertNull($metrics->average_entry_score);
    }

    #[Test]
    public function it_calculates_metrics_from_streamed_numeric_returns(): void
    {
        $metrics = (new ServingWalkForwardStatisticsService)->metrics(collect([0.10, -0.05, 0.02]));

        self::assertSame(3, $metrics->trades);
        self::assertEqualsWithDelta(66.6667, $metrics->hit_rate, 0.001);
        self::assertEqualsWithDelta(2.4, $metrics->profit_factor, 0.001);
        self::assertEqualsWithDelta(2.0, $metrics->median_return, 0.001);
    }

    #[Test]
    public function walk_forward_uses_only_entries_opened_after_the_split(): void
    {
        $service = (string) file_get_contents(dirname(__DIR__, 2).'/app/Services/ServingWalkForwardStatisticsService.php');

        self::assertStringContainsString("'trade.entry_date'", $service);
        self::assertStringContainsString("'walk_forward_start' => \$cutoff->copy()->subYear()", $service);
        self::assertStringContainsString("'statistics_start' => \$cutoff->copy()->subYears(3)", $service);
        self::assertStringContainsString("\$entry->gte(\$bucket['walk_forward_start']) && \$exit->lte(\$bucket['cutoff'])", $service);
    }
}
