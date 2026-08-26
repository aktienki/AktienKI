<?php

namespace Tests\Feature;

use App\Services\EtfHoldingImportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class EtfHoldingImportTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_imports_provider_holdings_by_isin_and_keeps_a_snapshot(): void
    {
        $instrumentId = DB::table('instruments')->insertGetId([
            'type' => 'stock', 'symbol' => 'SAP', 'isin' => 'DE0007164600', 'name' => 'SAP SE',
            'german_listing_symbol' => 'SAP', 'german_listing_exchange' => 'Xetra', 'german_listing_mic' => 'XETR',
            'is_active' => true, 'is_tradeable' => true, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $fundId = DB::table('etf_funds')->insertGetId([
            'provider' => 'Test Issuer', 'symbol' => 'TEST', 'isin' => 'DE000A0F5UH1',
            'name' => 'Test Germany ETF', 'exchange' => 'Xetra', 'mic_code' => 'XETR',
            'is_german_tradeable' => true, 'german_tradeability_verified_at' => now(), 'is_active' => true,
            'source_format' => 'csv', 'created_at' => now(), 'updated_at' => now(),
        ]);
        $fund = DB::table('etf_funds')->find($fundId);
        $csv = "Fondsposition per,\"17.Aug.2026\"\n\nEmittententicker,Name,Gewichtung (%)\nSAP,SAP SE,\"8,75\"\nAAPL,Apple Inc.,\"4,10\"\n";

        $result = app(EtfHoldingImportService::class)->import($fund, $csv);

        $this->assertSame(2, $result['imported']);
        $this->assertSame(1, $result['matched']);
        $this->assertDatabaseHas('etf_holdings', [
            'etf_fund_id' => $fundId, 'instrument_id' => $instrumentId,
            'holding_isin' => 'DE0007164600', 'effective_date' => '2026-08-17',
        ]);
        $this->assertEquals(8.75, (float) DB::table('etf_holdings')->where('instrument_id', $instrumentId)->value('weight_percent'));
    }
}
