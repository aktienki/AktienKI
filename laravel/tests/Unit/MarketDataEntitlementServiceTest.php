<?php

namespace Tests\Unit;

use App\Services\MarketDataEntitlementService;
use Tests\TestCase;

class MarketDataEntitlementServiceTest extends TestCase
{
    public function test_historical_charts_are_blocked_for_japan_and_australia(): void
    {
        $service = app(MarketDataEntitlementService::class);

        $this->assertFalse($service->historicalChartsAllowed((object) ['country' => 'JP']));
        $this->assertFalse($service->historicalChartsAllowed((object) ['country' => 'AU']));
        $this->assertNotNull($service->historicalChartRestrictionReason((object) ['country' => 'JP']));
    }

    public function test_historical_charts_remain_available_for_licensed_markets(): void
    {
        $service = app(MarketDataEntitlementService::class);

        $this->assertTrue($service->historicalChartsAllowed((object) ['country' => 'DE']));
        $this->assertTrue($service->historicalChartsAllowed((object) ['country' => 'US']));
        $this->assertNull($service->historicalChartRestrictionReason((object) ['country' => 'US']));
    }
}
