<?php

namespace Tests\Feature;

use App\Models\Instrument;
use App\Models\User;
use App\Services\PressReleaseAiAnalyzer;
use App\Services\TwelveDataPressReleaseImporter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class TwelveDataPressReleasePipelineTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_imports_only_active_german_tradeable_stocks_and_deduplicates_releases(): void
    {
        $included = Instrument::create(['type' => 'stock', 'symbol' => 'AAPL', 'provider_symbol' => 'AAPL', 'name' => 'Apple', 'is_active' => true, 'is_tradeable' => true, 'is_german_tradeable' => true]);
        Instrument::create(['type' => 'stock', 'symbol' => 'MSFT', 'provider_symbol' => 'MSFT', 'name' => 'Microsoft', 'is_active' => true, 'is_tradeable' => true, 'is_german_tradeable' => false]);

        Http::fake(['*/press_releases*' => Http::response(['press_releases' => [[
            'id' => 'release-1', 'datetime' => '2026-08-18T06:00:00+00:00',
            'title' => 'Apple publishes an update', 'body' => '<p>Official <b>company</b> update.</p>',
            'language' => ['en'], 'status' => 'ok',
        ]]])]);

        $first = app(TwelveDataPressReleaseImporter::class)->sync(2500);
        $second = app(TwelveDataPressReleaseImporter::class)->sync(2500);

        $this->assertSame(1, $first['checked']);
        $this->assertSame(1, $first['created']);
        $this->assertSame(0, $second['created']);
        $this->assertDatabaseCount('news', 1);
        $this->assertDatabaseHas('news', [
            'instrument_id' => $included->id, 'provider' => 'twelve_data',
            'provider_id' => 'release-1', 'body' => 'Official company update.',
        ]);
        $this->assertDatabaseHas('news_source_sync_states', [
            'instrument_id' => $included->id, 'provider' => 'twelve_data', 'consecutive_failures' => 0,
        ]);
    }

    public function test_it_sends_only_unprocessed_releases_to_openai_and_persists_the_analysis(): void
    {
        $instrument = Instrument::create(['type' => 'stock', 'symbol' => 'AAPL', 'name' => 'Apple', 'is_active' => true, 'is_tradeable' => true, 'is_german_tradeable' => true]);
        $id = \DB::table('news')->insertGetId([
            'instrument_id' => $instrument->id, 'headline' => 'New product', 'body' => 'Apple announced a product.',
            'source' => 'Twelve Data Press Releases', 'provider' => 'twelve_data', 'provider_id' => 'release-2',
            'published_at' => now(), 'created_at' => now(), 'updated_at' => now(),
        ]);
        \DB::table('news')->insert([
            'instrument_id' => $instrument->id, 'headline' => 'Already done', 'body' => 'Old release.',
            'source' => 'Twelve Data Press Releases', 'provider' => 'twelve_data', 'provider_id' => 'release-3',
            'published_at' => now(), 'ai_analyzed_at' => now(), 'created_at' => now(), 'updated_at' => now(),
        ]);
        putenv('OPENAI_API_KEY=test-key');
        Http::fake(['https://api.openai.com/v1/responses' => Http::response([
            'output_text' => json_encode([[
                'id' => $id, 'summary_de' => 'Apple kündigt ein neues Produkt an.',
                'summary_en' => 'Apple announces a new product.', 'sentiment' => 0.4, 'relevance' => 72,
            ]]),
        ])]);

        $result = app(PressReleaseAiAnalyzer::class)->analyzePending(100);

        $this->assertSame(1, $result['pending']);
        $this->assertSame(1, $result['analyzed']);
        $this->assertDatabaseHas('news', [
            'id' => $id, 'ai_summary_de' => 'Apple kündigt ein neues Produkt an.',
            'relevance_score' => 72,
        ]);
        Http::assertSentCount(1);
    }

    public function test_news_screener_filters_stored_releases_and_excludes_non_german_tradeable_stocks(): void
    {
        $included = Instrument::create(['type' => 'stock', 'symbol' => 'SAP', 'name' => 'SAP SE', 'is_active' => true, 'is_tradeable' => true, 'is_german_tradeable' => true]);
        $excluded = Instrument::create(['type' => 'stock', 'symbol' => 'TEST', 'name' => 'Hidden Corp', 'is_active' => true, 'is_tradeable' => true, 'is_german_tradeable' => false]);

        \DB::table('news')->insert([
            ['instrument_id' => $included->id, 'headline' => 'SAP raises outlook', 'provider' => 'twelve_data', 'provider_id' => 'screen-1', 'published_at' => now(), 'sentiment_score' => .72, 'relevance_score' => 88, 'created_at' => now(), 'updated_at' => now()],
            ['instrument_id' => $excluded->id, 'headline' => 'Hidden release', 'provider' => 'twelve_data', 'provider_id' => 'screen-2', 'published_at' => now(), 'sentiment_score' => .8, 'relevance_score' => 99, 'created_at' => now(), 'updated_at' => now()],
        ]);
        \DB::table('predictions')->insert([
            'instrument_id' => $included->id, 'prediction_time' => now(), 'interval' => '1d',
            'current_price' => 100, 'strategy' => 'long', 'signal' => 'BUY',
            'ai_score' => 8.4, 'created_at' => now(), 'updated_at' => now(),
        ]);

        $user = User::factory()->create();
        $watchlistId = \DB::table('watchlists')->insertGetId([
            'user_id' => $user->id, 'name' => 'Meine Aktien', 'active' => true,
            'is_default' => true, 'created_at' => now(), 'updated_at' => now(),
        ]);
        \DB::table('watchlist_items')->insert([
            'watchlist_id' => $watchlistId, 'instrument_id' => $included->id,
            'added_at' => now(), 'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->actingAs($user)
            ->get(route('news.index', ['sentiment' => 'positive', 'relevance_min' => 80]))
            ->assertOk()
            ->assertSee('SAP raises outlook')
            ->assertSee('Score 8,4')
            ->assertSee('Global #1')
            ->assertSee('BUY')
            ->assertSee('data-news-personal="true"', false)
            ->assertDontSee('Hidden release');
    }
}
