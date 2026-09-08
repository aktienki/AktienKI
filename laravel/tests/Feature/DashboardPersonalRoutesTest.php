<?php

namespace Tests\Feature;

use Illuminate\Http\Request;
use Tests\TestCase;

final class DashboardPersonalRoutesTest extends TestCase
{
    public function test_every_personal_dashboard_tile_resolves_to_its_expected_get_route(): void
    {
        $targets = [
            'paper-depots' => ['paper-depots.index', []],
            'watchlists' => ['watchlists.index', []],
            'strategies' => ['setup.saved-filters.index', []],
            'labels' => ['setup.labels.index', []],
            'community-posts' => ['community.index', []],
            'community-members' => ['community.index', []],
            'community-recent' => ['community.index', []],
            'news' => ['news.index', ['days' => 1]],
            'mobile-view' => ['profile.mobile-view', []],
            'chartview' => ['predictions.chartview-signals', []],
            'watchlist-screener' => ['screener.index', ['bestand' => 'watchlists']],
            'predictions' => ['predictions.index', []],
            'smart-screener' => ['screener.index', []],
            'market-report' => ['daily-market-analysis', []],
            'best-buy' => ['stocks.show', ['symbol' => 'ASML.AS', 'prediction' => 123, 'return_to' => '/dashboard']],
            'best-wait' => ['stocks.show', ['symbol' => 'ADS.DE', 'prediction' => 456, 'return_to' => '/dashboard']],
        ];

        foreach ($targets as $tile => [$expectedRoute, $parameters]) {
            $url = route($expectedRoute, $parameters ?? []);
            $parts = parse_url($url);
            $requestTarget = ($parts['path'] ?? '/').(isset($parts['query']) ? '?'.$parts['query'] : '');
            $matched = app('router')->getRoutes()->match(Request::create($requestTarget, 'GET'));

            $this->assertSame($expectedRoute, $matched->getName(), "Dashboard tile {$tile} resolves incorrectly.");
            $this->assertContains('GET', $matched->methods(), "Dashboard tile {$tile} is not a GET target.");
        }
    }

    public function test_personal_dashboard_uses_the_label_manager_route(): void
    {
        $source = file_get_contents(resource_path('views/dashboard.blade.php'));

        $this->assertIsString($source);
        $this->assertStringContainsString(
            "['labels', __('Labels'), \$overview['labels'], 'heroicon-o-tag', route('setup.labels.index')",
            $source,
        );
        $this->assertStringNotContainsString('stock-comparison', $source);
        $this->assertStringNotContainsString("__('Aktien vergleichen')", $source);
    }
}
