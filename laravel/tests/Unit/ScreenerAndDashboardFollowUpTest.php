<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class ScreenerAndDashboardFollowUpTest extends TestCase
{
    public function test_serving_prediction_strategy_uses_an_immutable_model_selection(): void
    {
        $root = dirname(__DIR__, 2);
        $controller = (string) file_get_contents($root.'/app/Http/Controllers/ServingPredictionTableController.php');
        $predictionController = (string) file_get_contents($root.'/app/Http/Controllers/PredictionController.php');
        $backtestJob = (string) file_get_contents($root.'/app/Jobs/RunFilteredBacktest.php');
        $servingView = (string) file_get_contents($root.'/resources/views/predictions/serving-index.blade.php');
        $heatmapView = (string) file_get_contents($root.'/resources/views/predictions/heatmap.blade.php');

        $this->assertStringContainsString("'symbol' => strtoupper((string) \$stock->symbol)", $controller);
        $this->assertStringContainsString("'release_id' => (string) \$stock->release_id", $controller);
        $this->assertStringContainsString("'release_policy' => 'active'", $controller);
        $this->assertStringContainsString("'release_policy' => strtolower(trim((string) (\$row['release_policy'] ?? 'pinned')))", $backtestJob);
        $this->assertStringContainsString("serving_active_models as active_model", $backtestJob);
        $this->assertStringContainsString("if (\$configuration['release_policy'] === 'active')", $backtestJob);
        $this->assertStringContainsString("'horizon_days' => (int) \$model->horizon", $controller);
        $this->assertStringContainsString("'horizon' => (int) \$model->horizon", $controller);
        $this->assertStringContainsString("'variant' => (string) \$model->variant", $controller);
        $this->assertStringContainsString("'selection_active' => (bool) \$model->is_active", $controller);
        $this->assertStringContainsString('executableServingModelKeys()', $controller);
        $this->assertStringContainsString("'historical_trade_available' => (bool) (\$model->strategy_executable ?? false)", $controller);
        $this->assertStringNotContainsString('&& $model->strategy_executable)', $controller);
        $this->assertStringContainsString("\$available->where('selection_active', true)", $controller);
        $this->assertStringContainsString("Arr::except(\$configuration, ['selection_active'])", $controller);
        $this->assertStringContainsString('serving_strategy_selections', $controller);
        $this->assertStringContainsString('serving_strategy_selection_filters', $controller);
        $this->assertStringContainsString("'restore_selection'", $controller);
        $this->assertStringContainsString("if (\$request->filled('serving_selection'))", $predictionController);
        $this->assertStringContainsString('$filters[\'serving_model_configurations\'] = $lockedServingConfigurations;', $predictionController);
        $this->assertStringContainsString('catch (\\RuntimeException $exception)', $predictionController);
        $this->assertStringContainsString("__('Strategie berechnen')", $servingView);
        $this->assertStringContainsString('id="serving-select-all"', $servingView);
        $this->assertStringContainsString('data-serving-model-checkbox', $servingView);
        $this->assertStringContainsString('data-serving-select-all-eligible="{{ $modelIsActive ?', $servingView);
        $this->assertStringContainsString("checkbox.checked = selectAll.checked && checkbox.dataset.servingSelectAllEligible === 'true';", $servingView);
        $this->assertStringContainsString("selectAll.addEventListener('change'", $servingView);
        $this->assertStringContainsString('form="serving-model-selection-form"', $servingView);
        $this->assertStringContainsString('name="select_all"', $servingView);
        $this->assertStringContainsString('name="models[]"', $servingView);
        $this->assertStringContainsString("'serving_selection',", $heatmapView);
        $this->assertStringContainsString("__('Modellauswahl gesperrt:", $heatmapView);
        $this->assertStringContainsString("name=\"return_to_models\" value=\"1\"", $heatmapView);
        $this->assertStringContainsString("__('Zurück zur Modellauswahl')", $heatmapView);
        $this->assertStringContainsString("selection_filters.serving_model_configurations", $heatmapView);
        $this->assertStringContainsString('$modelsReturnParameters', $heatmapView);
        $this->assertStringContainsString('$backtestOriginUrl', $heatmapView);
        $this->assertStringContainsString('name="backtest_return_to"', $heatmapView);
        $this->assertStringContainsString('window.location.assign(@js($backtestOriginUrl))', $heatmapView);
        $this->assertStringContainsString('name="models_return_token"', $heatmapView);
        $this->assertStringContainsString('filtered-backtest-average-capital-binding', $heatmapView);
        $this->assertStringContainsString('filtered-backtest-maximum-capital-binding', $heatmapView);
        $this->assertStringContainsString("@error('backtest')", $heatmapView);

        $savedFilterController = (string) file_get_contents($root.'/app/Http/Controllers/SavedPredictionFilterController.php');
        $this->assertStringContainsString("selection_filters.serving_model_configurations", $savedFilterController);
        $this->assertStringContainsString("if (\$request->boolean('return_to_models'))", $savedFilterController);
        $this->assertStringContainsString("redirect()->route('setup.models', \$returnParameters)", $savedFilterController);
        $this->assertStringContainsString('serving_strategy_selection_filters', $controller);
        $this->assertStringContainsString("'restore_selection' => \$modelsReturnToken", $heatmapView);

        $routes = (string) file_get_contents($root.'/routes/web.php');
        $navigation = (string) file_get_contents($root.'/resources/views/components/app-topbar.blade.php');
        $this->assertStringContainsString("Route::get('/setup/models'", $routes);
        $this->assertStringContainsString("name('setup.models')", $routes);
        $this->assertStringContainsString("route('setup.models')", $navigation);
    }

    public function test_commodity_screener_uses_the_stock_table_columns_and_scales(): void
    {
        $root = dirname(__DIR__, 2);
        $view = (string) file_get_contents($root.'/resources/views/commodities/index.blade.php');
        $controller = (string) file_get_contents($root.'/app/Http/Controllers/CommodityScreenerController.php');

        $this->assertStringContainsString('screener-desktop-watchlist-column', $view);
        $this->assertStringContainsString('screener-desktop-price', $view);
        $this->assertStringContainsString('screener-price-sparkline', $view);
        $this->assertStringContainsString('screener-desktop-panel', $view);
        $this->assertSame(2, substr_count($view, '<span class="screener-desktop-grade screener-desktop-scale-grade">'));
        $this->assertSame(2, substr_count($view, 'role="meter"'));
        $this->assertStringContainsString('grid-template-columns:42px minmax(360px,1fr) 190px 54px 78px 112px 112px repeat(3,62px) 30px', $view);
        $this->assertStringContainsString("DB::table('watchlist_items')", $controller);
    }

    public function test_screener_no_longer_renders_the_composite_total_column(): void
    {
        $view = (string) file_get_contents(dirname(__DIR__, 2).'/resources/views/screener/index.blade.php');

        $this->assertStringNotContainsString('screener-desktop-gesamt', $view);
        $this->assertStringNotContainsString('>Gesamt</span>', $view);
        $this->assertStringNotContainsString('Gesamt {{ $compositeScore }}', $view);
        $this->assertStringContainsString('grid-template-columns:42px minmax(360px,1fr) 190px', $view);
        $this->assertStringContainsString('grid-template-columns:minmax(360px,1fr) 190px 54px 78px 112px 112px repeat(3,62px)', $view);
        $this->assertStringNotContainsString("<span>{{ __('Modell') }}</span>", $view);
        $this->assertStringNotContainsString('screener-trigger-model', $view);
        $this->assertStringContainsString("route('tutorial.index')", $view);
        $this->assertStringContainsString('x-heroicon-o-question-mark-circle', $view);
        $this->assertStringContainsString('$signalRemainingTradingDays', $view);
        $this->assertStringContainsString('screener-signal-validity', $view);
        $this->assertStringContainsString('data-trade-status-label', $view);
    }

    public function test_strategy_manager_handles_nested_filter_metadata_without_array_conversion(): void
    {
        $view = (string) file_get_contents(dirname(__DIR__, 2).'/resources/views/setup/saved-filters.blade.php');

        $this->assertStringContainsString("['serving_model_configurations', 'optimizer_result']", $view);
        $this->assertStringContainsString('json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)', $view);
        $this->assertStringNotContainsString('is_array($value) ? implode(\', \', $value)', $view);
    }

    public function test_right_hand_candidates_include_a_distinct_best_new_stock(): void
    {
        $root = dirname(__DIR__, 2);
        $controller = (string) file_get_contents($root.'/app/Http/Controllers/DashboardController.php');
        $view = (string) file_get_contents($root.'/resources/views/dashboard.blade.php');

        $this->assertStringContainsString('$this->bestNewBuyStock($remoteDashboardStocks', $controller);
        $this->assertStringContainsString('...$usedAlternativeInstrumentIds', $controller);
        $this->assertStringContainsString("strtoupper((string) (\$change['to'] ?? '')) === 'BUY'", $controller);
        $this->assertStringContainsString('@if($bestNewStock)', $view);
        $this->assertStringContainsString("__('Beste neue BUY-Aktie')", $view);
        $this->assertStringContainsString('dashboard-opportunity-card', $view);
    }

    public function test_desktop_index_tiles_use_one_unified_status_surface(): void
    {
        $view = (string) file_get_contents(dirname(__DIR__, 2).'/resources/views/indices/index.blade.php');

        $this->assertStringContainsString('data-signal="{{ strtolower($index->view_signal) }}"', $view);
        $this->assertStringContainsString('index-tile-forecast-label', $view);
        $this->assertStringContainsString('grid-template-columns:repeat(3,1fr);overflow:hidden', $view);
        $this->assertStringContainsString('.index-tile-metrics>i+ i{border-left:', $view);
        $this->assertStringContainsString(':root[data-theme="light"] .index-tile-metrics', $view);
        $this->assertStringNotContainsString('.index-tile-metrics>i{display:flex;flex-direction:column;gap:2px;border:', $view);

        $theme = (string) file_get_contents(dirname(__DIR__, 2).'/resources/views/components/global-light-theme.blade.php');
        $this->assertStringContainsString('#aggregate-screener .index-tile-head', $theme);
        $this->assertStringContainsString('background:var(--ak-table-head-background) !important;', $theme);
    }

    public function test_screener_rows_expose_the_watchlist_picker_on_desktop_and_mobile(): void
    {
        $root = dirname(__DIR__, 2);
        $view = (string) file_get_contents($root.'/resources/views/screener/index.blade.php');
        $picker = (string) file_get_contents($root.'/resources/views/components/screener/watchlist-picker.blade.php');

        $this->assertSame(2, substr_count($view, '<x-screener.watchlist-picker'));
        $this->assertStringContainsString('screener-desktop-row hidden md:grid', $view);
        $this->assertStringContainsString('screener-desktop-watchlist-column', $view);
        $this->assertStringContainsString('screener-watchlist-head', $view);
        $this->assertStringContainsString('screener-row-watchlist-picker-mobile md:hidden', $view);
        $this->assertStringContainsString('grid-template-columns:42px minmax(0,1fr)', $view);
        $this->assertStringContainsString('width:42px;height:100%;min-height:100%', $view);
        $this->assertStringContainsString('align-self:center;justify-self:center;margin:auto', $view);
        $this->assertStringContainsString('screener-watchlist-head{display:grid;width:42px;height:100%;place-items:center', $view);
        $this->assertStringContainsString('padding-left:3.35rem!important', $view);
        $this->assertStringNotContainsString('screener-row-watchlist-picker hidden md:block', $view);
        $this->assertStringContainsString("__('Zu Watchlist oder Musterdepot hinzufügen')", $picker);
        $this->assertStringContainsString("route('watchlists.items.toggle'", $picker);
        $this->assertStringContainsString("route('paper-depots.instruments.store'", $picker);
        $this->assertStringContainsString('x-teleport="body"', $picker);
        $this->assertStringContainsString('role="dialog"', $picker);
        $this->assertStringContainsString('x-heroicon-s-star', $picker);
        $this->assertStringContainsString('x-heroicon-o-star', $picker);

        $css = (string) file_get_contents($root.'/resources/css/app.css');
        $this->assertStringContainsString('.screener-stock-card:has(.screener-watchlist-picker[open])', $css);
        $this->assertStringContainsString('.screener-page .screener-watchlist-picker .screener-watchlist-menu', $css);
        $this->assertStringContainsString('position:fixed !important;', $css);
    }

    public function test_light_theme_is_loaded_once_from_the_shared_application_layout(): void
    {
        $root = dirname(__DIR__, 2);
        $themeHead = (string) file_get_contents($root.'/resources/views/components/preference-head.blade.php');
        $theme = (string) file_get_contents($root.'/resources/views/components/global-light-theme.blade.php');

        $this->assertStringContainsString('<x-global-light-theme />', $themeHead);
        $this->assertStringContainsString('id="ak-global-light-theme"', $theme);
        $this->assertStringContainsString('@layer ak-global-light', $theme);
        $this->assertStringContainsString('--ak-card:#ffffff !important;', $theme);
        $this->assertStringContainsString('--ak-border:#d4d4d8 !important;', $theme);
        $this->assertStringContainsString('--ak-table-head-background:', $theme);
        $this->assertStringContainsString('background-color:var(--ak-bg) !important;', $theme);
        $this->assertStringContainsString('background:var(--ak-table-head-background) !important;', $theme);
        $this->assertStringContainsString('--ak-scale-opacity:.24 !important;', $theme);
        $this->assertStringContainsString('Stock screener: the same neutral workspace', $theme);
        $this->assertStringContainsString('.screener-page .screener-table-head', $theme);
        $this->assertStringContainsString('.screener-page .screener-stock-card:nth-of-type(even)', $theme);
        $this->assertStringContainsString('Semantic finance colours are deliberately preserved.', $theme);
    }

    public function test_right_dashboard_candidate_header_has_no_decorative_sparkles_below_help(): void
    {
        $view = (string) file_get_contents(dirname(__DIR__, 2).'/resources/views/dashboard.blade.php');
        $cardStart = strpos($view, 'id="dashboard-daily-tips-card"');
        $rowsStart = strpos($view, 'id="dashboard-best-stocks-row"', $cardStart ?: 0);
        $header = substr($view, $cardStart ?: 0, ($rowsStart ?: 0) - ($cardStart ?: 0));

        $this->assertStringNotContainsString('x-heroicon-o-sparkles', $header);
    }

    public function test_dashboard_no_longer_defines_a_separate_light_colour_system(): void
    {
        $view = (string) file_get_contents(dirname(__DIR__, 2).'/resources/views/dashboard.blade.php');

        $this->assertStringNotContainsString('Dashboard skin matching the index screener', $view);
        $this->assertStringNotContainsString('--dbx-', $view);
        $this->assertStringNotContainsString('#f3efe6', $view);
    }
}
