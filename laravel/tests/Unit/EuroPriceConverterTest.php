<?php

namespace Tests\Unit;

use App\Services\EuroPriceConverter;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

final class EuroPriceConverterTest extends TestCase
{
    public function test_euro_and_minor_currency_units_are_normalized(): void
    {
        Cache::put('eur_conversion_factor_ZAR', 0.05, now()->addHour());
        Cache::put('eur_conversion_factor_GBP', 1.17, now()->addHour());
        $converter = app(EuroPriceConverter::class);

        $this->assertSame(1.0, $converter->factor('EUR'));
        $this->assertEqualsWithDelta(0.0005, $converter->factor('ZAc'), 0.0000001);
        $this->assertEqualsWithDelta(0.0117, $converter->factor('GBp'), 0.0000001);
    }
}
