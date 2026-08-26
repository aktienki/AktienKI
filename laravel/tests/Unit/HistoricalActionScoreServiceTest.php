<?php

namespace Tests\Unit;

use App\Services\HistoricalActionScoreService;
use Tests\TestCase;

final class HistoricalActionScoreServiceTest extends TestCase
{
    public function test_score_uses_only_outcomes_available_before_signal_date(): void
    {
        $history = collect(range(1, 12))->map(fn (int $day): object => $this->trade(
            $day,
            sprintf('2026-01-%02d', $day),
            sprintf('2026-01-%02d', $day + 1),
            .02,
        ));
        $target = $this->trade(99, '2026-02-01', '2026-02-21', .01);
        $futureLoss = $this->trade(100, '2026-02-02', '2026-02-22', -.95);

        $withoutFuture = app(HistoricalActionScoreService::class)->score($history->concat([clone $target]))
            ->firstWhere('trade_id', 99);
        $withFuture = app(HistoricalActionScoreService::class)->score($history->concat([clone $target, $futureLoss]))
            ->firstWhere('trade_id', 99);

        $this->assertSame($withoutFuture->historical_action_score, $withFuture->historical_action_score);
        $this->assertSame('2026-01-31', $withFuture->historical_action_components['evidence_cutoff']);
        $this->assertTrue($withFuture->historical_action_components['point_in_time']);
    }

    public function test_score_uses_the_same_buy_threshold_as_the_live_action_score(): void
    {
        $rows = collect(range(1, 12))->map(fn (int $day): object => $this->trade(
            $day,
            sprintf('2026-01-%02d', $day),
            sprintf('2026-01-%02d', $day + 1),
            .03,
        ));
        $rows->push($this->trade(99, '2026-02-01', '2026-02-21', .02));

        $target = app(HistoricalActionScoreService::class)->score($rows)->firstWhere('trade_id', 99);

        $this->assertGreaterThanOrEqual(6.5, $target->historical_action_score / 10);
        $this->assertSame('BUY', $target->historical_action_signal);
        $this->assertSame(HistoricalActionScoreService::VERSION, $target->historical_action_components['version']);
    }

    public function test_overlapping_forecasts_do_not_create_a_fictitious_total_drawdown(): void
    {
        $rows = collect(range(1, 20))->map(fn (int $day): object => $this->trade(
            $day,
            sprintf('2026-01-%02d', $day),
            sprintf('2026-02-%02d', $day),
            -.10,
        ));
        $rows->push($this->trade(99, '2026-03-01', '2026-03-21', .02));

        $target = app(HistoricalActionScoreService::class)->score($rows)->firstWhere('trade_id', 99);

        $this->assertLessThan(20, $target->historical_action_components['metrics']['drawdown']);
    }

    private function trade(int $id, string $signalDate, string $exitDate, float $return): object
    {
        return (object) [
            'trade_id' => $id,
            'instrument_id' => 1,
            'signal_date' => $signalDate,
            'exit_date' => $exitDate,
            'net_return' => $return,
            'predicted_return' => .04,
            'validation_direction_accuracy' => .80,
            'validation_profit_factor' => 2.0,
            'validation_trade_count' => 40,
        ];
    }
}
