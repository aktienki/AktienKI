<?php

namespace Tests\Feature;

use App\Http\Controllers\ServingModelOverviewController;
use App\Http\Controllers\ServingPredictionTableController;
use App\Http\Controllers\ServingStockController;
use App\Services\ServingReadService;
use Illuminate\Http\Request;
use Illuminate\Session\ArraySessionHandler;
use Illuminate\Session\Store;
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

    /**
     * The Strategietester picker validates only against the external serving
     * database - AutomatedPortfolioService reads a completely separate,
     * local pipeline. Without this guard, a strategy could be saved that
     * never fires, with no indication why (this was the actual root cause
     * behind saved_prediction_filters #83 and #102 never producing a real
     * buy). See LocalModelFeasibilityService.
     */
    public function test_store_strategy_rejects_a_locally_infeasible_configuration_before_saving(): void
    {
        $controller = (string) file_get_contents(app_path('Http/Controllers/ServingModelOverviewController.php'));

        $this->assertStringContainsString('LocalModelFeasibilityService $feasibility', $controller);
        $this->assertStringContainsString("if (! \$check['feasible'])", $controller);
        // The rejection happens before the strategy is ever persisted.
        $rejectPosition = strpos($controller, "if (! \$check['feasible'])");
        $persistPosition = strpos($controller, 'DB::transaction(function ()');
        $this->assertNotFalse($rejectPosition);
        $this->assertNotFalse($persistPosition);
        $this->assertLessThan($persistPosition, $rejectPosition);
    }

    /**
     * The picker page itself must also surface why a configuration is dead,
     * not just refuse it silently on submit.
     */
    public function test_model_overview_view_warns_about_infeasible_configurations(): void
    {
        $view = (string) file_get_contents(resource_path('views/stocks/model-overview.blade.php'));

        $this->assertStringContainsString("! (\$variant['local_feasible'] ?? true)", $view);
        $this->assertStringContainsString('Automatisierung kann diese Konfiguration nie ausführen', $view);
    }

    public function test_an_individually_checked_model_is_kept_in_the_strategy_selection(): void
    {
        $sourceToken = str_repeat('A', 40);
        $visiblePageToken = str_repeat('B', 40);
        $configuration = [
            'symbol' => 'HDPHA.DE',
            'horizon' => 20,
            'variant' => 'standard',
            'selection_active' => true,
        ];
        $session = new Store('model-selection-test', new ArraySessionHandler(120));
        $session->put('serving_strategy_selections', [
            $sourceToken => [[
                'symbol' => 'TEG.DE',
                'horizon' => 40,
                'variant' => 'pure_tcn',
                'selection_active' => true,
            ]],
            $visiblePageToken => [$configuration],
        ]);
        $request = Request::create('/setup/models/strategy', 'POST', [
            'selection_token' => $sourceToken,
            'models' => ['HDPHA.DE|20|standard'],
        ]);
        $request->setLaravelSession($session);

        $response = app(ServingPredictionTableController::class)->storeStrategySelection(
            $request,
            app(ServingReadService::class),
        );

        parse_str((string) parse_url($response->getTargetUrl(), PHP_URL_QUERY), $query);
        $stored = $session->get('serving_strategy_selections.'.$query['serving_selection']);
        $this->assertCount(1, $stored);
        $this->assertSame('HDPHA.DE', $stored[0]['symbol']);
        $this->assertSame(20, $stored[0]['horizon']);
        $this->assertArrayNotHasKey('selection_active', $stored[0]);
    }

    public function test_select_all_uses_the_complete_filtered_snapshot_across_pages(): void
    {
        $sourceToken = str_repeat('C', 40);
        $configurations = [
            ['symbol' => 'AAA.DE', 'horizon' => 10, 'variant' => 'standard'],
            ['symbol' => 'BBB.DE', 'horizon' => 40, 'variant' => 'pure_tcn'],
        ];
        $session = new Store('model-select-all-test', new ArraySessionHandler(120));
        $session->put('serving_strategy_selections', [$sourceToken => $configurations]);
        $request = Request::create('/setup/models/strategy', 'POST', [
            'selection_token' => $sourceToken,
            'select_all' => '1',
            // A paginated table only mirrors the currently visible boxes.
            'models' => ['AAA.DE|10|standard'],
        ]);
        $request->setLaravelSession($session);

        $response = app(ServingPredictionTableController::class)->storeStrategySelection(
            $request,
            app(ServingReadService::class),
        );

        parse_str((string) parse_url($response->getTargetUrl(), PHP_URL_QUERY), $query);
        $stored = $session->get('serving_strategy_selections.'.$query['serving_selection']);
        $this->assertCount(2, $stored);
        $this->assertSame(['AAA.DE', 'BBB.DE'], array_column($stored, 'symbol'));
    }
}
