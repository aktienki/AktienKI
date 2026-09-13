<?php

namespace Tests\Unit;

use App\Models\User;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class UserTaxSettingsTest extends TestCase
{
    #[Test]
    public function it_casts_tax_simulation_values_as_fixed_precision_decimals(): void
    {
        $user = new User([
            'tax_allowance_eur' => 1000,
            'tax_rate_percent' => 25,
        ]);

        self::assertSame('1000.00', $user->tax_allowance_eur);
        self::assertSame('25.00', $user->tax_rate_percent);
    }
}
