<?php

namespace Tests\Feature;

use App\Models\MarketAsset;
use App\Models\MarketSnapshot;
use App\Models\MarketStatistic;
use App\Models\SectorSnapshot;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MarketDashboardApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_latest_endpoint_returns_404_without_snapshot(): void
    {
        $this->getJson('/api/v1/market/snapshot')
            ->assertNotFound()
            ->assertJsonPath('data', null);
    }

    public function test_latest_endpoint_returns_complete_snapshot(): void
    {
        $snapshot = MarketSnapshot::query()->create([
            'snapshot_time' => now(),
            'market_score' => 74.5,
            'risk_mode' => 'RISK_ON',
            'market_trend' => 'BULLISH',
            'breadth_score' => 68.2,
            'buy_signals' => 60,
            'sell_signals' => 20,
            'hold_signals' => 20,
        ]);

        MarketAsset::query()->create([
            'market_snapshot_id' => $snapshot->id,
            'symbol' => '^GSPC',
            'name' => 'S&P 500',
            'category' => 'index',
            'price' => 6000,
            'change_percent' => 1.2,
        ]);

        SectorSnapshot::query()->create([
            'market_snapshot_id' => $snapshot->id,
            'sector' => 'Technology',
            'rank' => 1,
            'average_return' => 1.8,
            'average_score' => 82,
        ]);

        MarketStatistic::query()->create([
            'market_snapshot_id' => $snapshot->id,
            'companies_total' => 100,
            'buy_count' => 60,
            'sell_count' => 20,
            'hold_count' => 20,
        ]);

        $this->getJson('/api/v1/market/snapshot')
            ->assertOk()
            ->assertJsonPath('data.market_score', 74.5)
            ->assertJsonPath('data.risk_mode', 'RISK_ON')
            ->assertJsonCount(1, 'data.assets')
            ->assertJsonCount(1, 'data.sectors')
            ->assertJsonPath('data.statistics.companies_total', 100);
    }
}
