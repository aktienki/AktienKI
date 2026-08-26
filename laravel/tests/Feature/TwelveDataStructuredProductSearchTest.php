<?php

namespace Tests\Feature;

use App\Services\TwelveDataService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class TwelveDataStructuredProductSearchTest extends TestCase
{
    public function test_it_returns_only_supported_structured_product_types_without_ranking(): void
    {
        Cache::flush();
        Http::fake([
            '*' => Http::response([
                'status' => 'ok',
                'data' => [
                    ['symbol' => 'DBK', 'instrument_name' => 'Deutsche Bank AG', 'instrument_type' => 'Common Stock', 'exchange' => 'XETRA', 'currency' => 'EUR'],
                    ['symbol' => 'DEMO1', 'instrument_name' => 'DBK demo warrant', 'instrument_type' => 'Warrant', 'exchange' => 'Frankfurt', 'mic_code' => 'XFRA', 'currency' => 'EUR', 'access' => ['plan' => 'Pro']],
                    ['symbol' => 'DEMO2', 'instrument_name' => 'DBK demo structure', 'instrument_type' => 'Structured Product', 'exchange' => 'Stuttgart', 'mic_code' => 'XSTU', 'currency' => 'EUR'],
                ],
            ], 200),
        ]);

        $items = app(TwelveDataService::class)->structuredProducts('DBK.DE', 'Deutsche Bank AG');

        $this->assertSame(['DEMO1', 'DEMO2'], collect($items)->pluck('symbol')->all());
        $this->assertArrayNotHasKey('score', $items[0]);
        $this->assertSame('Pro', $items[0]['access']);
    }
}
