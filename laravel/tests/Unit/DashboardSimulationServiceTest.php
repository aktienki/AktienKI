<?php

namespace Tests\Unit;

use App\Services\DashboardSimulationService;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\TestCase;

final class DashboardSimulationServiceTest extends TestCase
{
    public function test_payload_is_a_consistent_read_only_strategy_scenario(): void
    {
        $now = Carbon::parse('2026-09-06 12:00:00', 'Europe/Berlin');
        $payload = (new DashboardSimulationService)->payload($now);
        $portfolio = $payload['strategyPortfolio'];

        $positionsValue = $portfolio->positions->sum(
            fn (object $position): float => $position->quantity * $position->current_price,
        );

        $this->assertEqualsWithDelta($positionsValue, $portfolio->dashboard_positions_value, 0.05);
        $this->assertSame(11000.0, $portfolio->dashboard_total_value);
        $this->assertSame(10.0, $portfolio->dashboard_performance);
        $this->assertTrue((bool) data_get($portfolio->meta, 'automation.live_enabled'));
        $this->assertTrue((bool) data_get($portfolio->meta, 'automation.transaction_email_enabled'));
        $this->assertCount(3, $portfolio->positions);
        $this->assertSame(['PUM', 'HOT', 'SAP'], $portfolio->positions->pluck('instrument.symbol')->all());
    }

    public function test_signal_scenario_contains_three_entries_and_one_valid_exit(): void
    {
        $payload = (new DashboardSimulationService)->payload(Carbon::parse('2026-09-06 12:00:00', 'Europe/Berlin'));
        $changes = collect($payload['signalCockpit']['signalChanges']);
        $sell = $changes->firstWhere('to', 'SELL');

        $this->assertCount(3, $changes->where('to', 'BUY'));
        $this->assertCount(1, $changes->where('to', 'SELL'));
        $this->assertLessThanOrEqual(0, data_get($sell, 'horizons.20'));
        $this->assertSame(3, $payload['recentSignalOverview']['buy_count']);
        $this->assertSame(1, $payload['recentSignalOverview']['sell_count']);

        foreach ($changes as $change) {
            $this->assertTrue(Carbon::parse($change['at'])->isValid());
            $this->assertArrayHasKey(20, $change['horizons']);
        }
    }

    public function test_activities_and_schedule_cover_ui_states_without_real_action_types(): void
    {
        $now = Carbon::parse('2026-09-06 12:00:00', 'Europe/Berlin');
        $payload = (new DashboardSimulationService)->payload($now);
        $activities = $payload['activities'];
        $schedule = $payload['scheduleItems'];

        $this->assertEqualsCanonicalizing(
            ['portfolio', 'signal', 'email', 'calendar'],
            $activities->pluck('type')->unique()->all(),
        );
        $this->assertEqualsCanonicalizing(
            ['positive', 'negative', 'info', 'warning'],
            $activities->pluck('tone')->unique()->all(),
        );
        $this->assertCount(4, $schedule);

        foreach ($schedule as $item) {
            $this->assertContains($item['type'], ['simulation_email', 'simulation_event']);
            $this->assertTrue($item['simulated']);
            $this->assertTrue($item['active']);
            $this->assertFalse($item['expired']);
            $this->assertTrue(Carbon::parse($item['sort_at'])->isAfter($now));
        }
    }
}
