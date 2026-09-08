<?php

namespace Tests\Feature;

use App\Http\Controllers\DashboardController;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use ReflectionMethod;
use Tests\TestCase;

final class DashboardServingUniverseTest extends TestCase
{
    public function test_dashboard_controller_cannot_read_local_market_tables(): void
    {
        $source = file_get_contents(app_path('Http/Controllers/DashboardController.php'));

        $this->assertIsString($source);
        foreach (['predictions', 'price_bars', 'current_stock_quotes', 'chartview_signal_events', 'chartview_signal_statistics', 'daily_market_ai_analyses', 'market_factor_snapshots', 'instrument_earnings', 'corporate_events'] as $table) {
            $this->assertStringNotContainsString("DB::table('{$table}", $source, "Dashboard must not read local {$table}.");
            $this->assertStringNotContainsString("->join('{$table}", $source, "Dashboard must not join local {$table}.");
        }

        $this->assertStringContainsString('ServingReadService::class', $source);
        $this->assertStringContainsString('ServingScreenerService::class', $source);
        $this->assertStringNotContainsString('UserTradeOpportunity', $source);
        $this->assertStringNotContainsString('TradeOpportunityService', $source);
    }

    public function test_dashboard_stock_count_and_signal_bins_come_from_the_serving_snapshot(): void
    {
        config()->set('aktienki.serving.read_cache_store', 'array');
        Cache::store('array')->put('serving.read.active-tradeable-stock-count.v1', 256, now()->addMinute());
        $cacheKey = 'serving.market-snapshot.v1.global.'.app()->getLocale();
        Cache::store('array')->put($cacheKey, [
            'batch_id' => 'serving-batch-1',
            'calculation_date' => '2026-09-01',
            'assessment' => ['score' => 5.2, 'marketCount' => 90],
            'transition_stats' => [
                'transition_count' => 7,
                'positive_count' => 5,
                'negative_count' => 2,
                'distribution' => [
                    'SELL' => 0,
                    'WAIT' => 0,
                    'HOLD' => 31,
                    'WATCH' => 13,
                    'BUY' => 46,
                ],
            ],
        ], now()->addMinute());

        $user = new User;
        $user->forceFill(['meta' => ['risk_profile' => ['level' => 'normal']]]);
        $method = new ReflectionMethod(DashboardController::class, 'profileUniverseStats');

        $stats = $method->invoke(new DashboardController, $user);

        $this->assertSame('serving', $stats['source']);
        $this->assertSame('serving-batch-1', $stats['batch_id']);
        $this->assertSame(256, $stats['active_count']);
        $this->assertSame(256, $stats['total_active_count']);
        $this->assertSame(90, $stats['current_signal_count']);
        $this->assertSame(166, $stats['open_count']);
        $this->assertSame(5.2, $stats['average_score']);
        $this->assertSame([0, 31, 13, 46], array_column($stats['bins'], 'count'));
        $this->assertSame(7, $stats['transition_candidates']);
        $this->assertSame(5, $stats['transition_to_buy']);
        $this->assertSame(2, $stats['transition_to_sell']);

        Cache::store('array')->forget($cacheKey);
    }
}
