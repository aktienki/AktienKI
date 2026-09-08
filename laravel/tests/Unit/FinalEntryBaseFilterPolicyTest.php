<?php

namespace Tests\Unit;

use App\Models\User;
use App\Services\FinalEntryBaseFilterPolicy;
use App\Services\FinalEntryFilterRuleRegistry;
use Tests\TestCase;

final class FinalEntryBaseFilterPolicyTest extends TestCase
{
    public function test_every_profile_compiles_to_mandatory_post_model_entry_rules(): void
    {
        config()->set('aktienki.signals.round_trip_cost_percent', 0.5);
        config()->set('aktienki.signals.minimum_net_return_percent', 1.0);

        $policy = app(FinalEntryBaseFilterPolicy::class);
        $compiled = app(FinalEntryFilterRuleRegistry::class)->compile(
            $policy->settings(new User([
                'meta' => ['risk_profile' => ['level' => 'normal']],
            ])),
        );
        $rules = collect($compiled['active_rules'])->keyBy('key');

        $this->assertSame('BUY', $rules->get('signal')['value']);
        $this->assertSame(6.2, $rules->get('score_min')['value']);
        $this->assertSame(55.0, $rules->get('confidence_min')['value']);
        $this->assertSame(60.0, $rules->get('risk_max')['value']);
        $this->assertSame(1.5, $rules->get('predicted_return_min')['value']);
        $this->assertSame(1.05, $rules->get('profit_factor_min')['value']);
        $this->assertSame(5.0, $rules->get('minimum_trades')['value']);
        $this->assertTrue($rules->get('positive_prediction_required')['value']);
        $this->assertFalse($rules->has('quality_tier'));
        $this->assertFalse($rules->has('service_quality_gate'));
    }

    public function test_risk_profile_changes_thresholds_without_making_quality_gate_mandatory(): void
    {
        $policy = app(FinalEntryBaseFilterPolicy::class);
        $cautious = $policy->settings(new User([
            'meta' => ['risk_profile' => ['level' => 'cautious']],
        ]));
        $risk = $policy->settings(new User([
            'meta' => ['risk_profile' => ['level' => 'risk']],
        ]));

        $this->assertSame(6.8, $cautious['score_min']);
        $this->assertSame(35.0, $cautious['risk_max']);
        $this->assertSame(2.0, $cautious['predicted_return_min']);
        $this->assertSame(5.7, $risk['score_min']);
        $this->assertSame(80.0, $risk['risk_max']);
        $this->assertSame(1.5, $risk['predicted_return_min']);
        $this->assertArrayNotHasKey('quality_tier', $risk);
        $this->assertArrayNotHasKey('service_quality_gate', $risk);
    }
}
