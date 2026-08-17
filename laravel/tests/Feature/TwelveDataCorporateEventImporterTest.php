<?php

namespace Tests\Feature;

use App\Models\Instrument;
use App\Services\TwelveDataCorporateEventImporter;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class TwelveDataCorporateEventImporterTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_stores_only_events_for_active_german_tradeable_universe_stocks(): void
    {
        $included = Instrument::create(['type' => 'stock', 'symbol' => 'AAPL', 'provider_symbol' => 'AAPL', 'name' => 'Apple', 'is_active' => true, 'is_tradeable' => true, 'is_german_tradeable' => true]);
        Instrument::create(['type' => 'stock', 'symbol' => 'MSFT', 'provider_symbol' => 'MSFT', 'name' => 'Microsoft', 'is_active' => true, 'is_tradeable' => true, 'is_german_tradeable' => false]);

        Http::fake(['*/earnings_calendar*' => Http::response(['earnings' => [
            '2026-09-01' => [
                ['symbol' => 'AAPL', 'name' => 'Apple', 'time' => 'After Hours', 'eps_estimate' => 1.25, 'currency' => 'USD'],
                ['symbol' => 'MSFT', 'name' => 'Microsoft', 'time' => 'After Hours', 'eps_estimate' => 2.5, 'currency' => 'USD'],
                ['symbol' => 'UNKNOWN', 'name' => 'Unknown'],
            ],
        ], 'status' => 'ok'], 200, ['api-credits-used' => '40'])]);

        $result = app(TwelveDataCorporateEventImporter::class)->syncEarnings(
            CarbonImmutable::parse('2026-08-01'), CarbonImmutable::parse('2026-10-01')
        );

        $this->assertSame(3, $result['received']);
        $this->assertSame(1, $result['matched']);
        $this->assertDatabaseHas('corporate_events', ['instrument_id' => $included->id, 'event_type' => 'earnings', 'event_date' => '2026-09-01']);
        $this->assertDatabaseCount('corporate_events', 1);
        $this->assertDatabaseHas('corporate_event_imports', ['status' => 'completed', 'records_received' => 3, 'records_matched' => 1, 'api_credits_used' => 40]);
    }
}
