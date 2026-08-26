<?php

namespace Tests\Unit;

use App\Support\SignalStrength;
use PHPUnit\Framework\TestCase;

final class SignalStrengthTest extends TestCase
{
    public function test_it_maps_returns_to_a_symmetric_directional_scale(): void
    {
        self::assertSame('+5', SignalStrength::label(25.0));
        self::assertSame('+3', SignalStrength::label(8.0));
        self::assertSame('0', SignalStrength::label(0.2));
        self::assertSame('−3', SignalStrength::label(-8.0));
        self::assertSame('−5', SignalStrength::label(-25.0));
        self::assertSame('—', SignalStrength::label(null));
    }
}
