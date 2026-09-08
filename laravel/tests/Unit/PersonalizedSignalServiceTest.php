<?php

namespace Tests\Unit;

use App\Models\User;
use App\Services\PersonalizedSignalService;
use Tests\TestCase;

final class PersonalizedSignalServiceTest extends TestCase
{
    public function test_prediction_signal_sql_uses_the_users_risk_profile(): void
    {
        $service = app(PersonalizedSignalService::class);
        $cautious = new User(['meta' => ['risk_profile' => ['level' => 'cautious']]]);
        $risk = new User(['meta' => ['risk_profile' => ['level' => 'risk']]]);

        $cautiousRules = $service->profileThresholds($cautious);
        $riskRules = $service->profileThresholds($risk);

        $this->assertSame(68, $cautiousRules['buy_score']);
        $this->assertSame(0.35, $cautiousRules['buy_risk']);
        $this->assertSame(57, $riskRules['buy_score']);
        $this->assertSame(0.80, $riskRules['buy_risk']);
        $this->assertNotSame($cautiousRules, $riskRules);
    }
}
