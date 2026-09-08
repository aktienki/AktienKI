<?php

namespace Tests\Feature;

use App\Http\Controllers\ServingModelOverviewController;
use App\Http\Controllers\ServingStockController;
use Tests\TestCase;

final class ServingStockRouteTest extends TestCase
{
    public function test_stock_detail_uses_the_serving_controller(): void
    {
        $route = app('router')->getRoutes()->getByName('stocks.show');

        $this->assertNotNull($route);
        $this->assertSame(ServingStockController::class, $route->getActionName());
        $this->assertNotContains('plan:pro', $route->gatherMiddleware());
    }

    public function test_stock_chart_uses_the_serving_cache_endpoint(): void
    {
        $route = app('router')->getRoutes()->getByName('stocks.serving-chart-data');

        $this->assertNotNull($route);
        $this->assertSame(ServingStockController::class.'@chartData', $route->getActionName());
        $this->assertContains('plan:pro', $route->gatherMiddleware());
    }

    public function test_model_overview_is_restricted_to_pro_tariffs(): void
    {
        $route = app('router')->getRoutes()->getByName('stocks.models');

        $this->assertNotNull($route);
        $this->assertSame(ServingModelOverviewController::class, $route->getActionName());
        $this->assertContains('plan:pro', $route->gatherMiddleware());
    }

    public function test_serving_model_configuration_can_be_added_to_a_personal_strategy(): void
    {
        $route = app('router')->getRoutes()->getByName('stocks.models.strategy.store');

        $this->assertNotNull($route);
        $this->assertSame(ServingModelOverviewController::class.'@storeStrategy', $route->getActionName());
        $this->assertContains('POST', $route->methods());
        $this->assertContains('plan:pro', $route->gatherMiddleware());
    }
}
