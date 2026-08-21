<?php

namespace Tests\Unit;

use App\Support\ProfitFactor;
use PHPUnit\Framework\TestCase;

final class ProfitFactorTest extends TestCase
{
    public function test_it_caps_profit_factor_at_three(): void
    {
        $this->assertSame(3.0, ProfitFactor::cap(9.75));
        $this->assertSame(3.0, ProfitFactor::cap(INF));
        $this->assertSame(1.42, ProfitFactor::cap('1.42'));
        $this->assertSame(0.0, ProfitFactor::cap(-2));
        $this->assertNull(ProfitFactor::cap(null));
    }
}
