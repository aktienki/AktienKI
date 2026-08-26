<?php

namespace Tests\Unit;

use App\Support\SignalQualityCalibration;
use PHPUnit\Framework\TestCase;

class SignalQualityCalibrationTest extends TestCase
{
    public function test_adidas_quality_is_not_derived_from_its_raw_entry_threshold(): void
    {
        $result = SignalQualityCalibration::calculate(
            ['trades' => 45, 'hit_rate' => 73.33, 'profit_factor' => 3.287, 'average_return_percent' => 2.649],
            ['trades' => 5, 'hit_rate' => 80.0, 'profit_factor' => 11.514, 'average_return_percent' => 2.706],
        );

        $this->assertSame('2−', $result['grade']);
        $this->assertSame('provisional', $result['status']);
    }

    public function test_one_plus_requires_strong_stock_specific_oos_performance(): void
    {
        $evidence = ['trades' => 30, 'hit_rate' => 72.0, 'profit_factor' => 1.8, 'average_return_percent' => 3.2];

        $result = SignalQualityCalibration::calculate($evidence, $evidence, true);

        $this->assertSame('1+', $result['grade']);
        $this->assertSame('validated', $result['status']);
    }

    public function test_negative_average_return_cannot_receive_a_good_grade(): void
    {
        $evidence = ['trades' => 40, 'hit_rate' => 80.0, 'profit_factor' => 2.0, 'average_return_percent' => -0.2];

        $result = SignalQualityCalibration::calculate($evidence, $evidence, true);

        $this->assertContains($result['grade'], ['5+', '5−']);
    }

    public function test_one_plus_requires_all_four_horizons_to_confirm(): void
    {
        $evidence = ['trades' => 120, 'hit_rate' => 95.0, 'profit_factor' => 8.0, 'average_return_percent' => 5.0];

        $withoutConfirmation = SignalQualityCalibration::calculate($evidence, $evidence, false);
        $withConfirmation = SignalQualityCalibration::calculate($evidence, $evidence, true);

        $this->assertNotSame('1+', $withoutConfirmation['grade']);
        $this->assertLessThan(90.0, $withoutConfirmation['quality_percent']);
        $this->assertSame('1+', $withConfirmation['grade']);
        $this->assertTrue($withConfirmation['all_four_horizons_confirmed']);
    }
}
