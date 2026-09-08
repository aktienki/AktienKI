<?php

namespace Tests\Unit;

use App\Data\FinalEntryRawPredictionResolution;
use App\Data\FinalEntrySignalDecisionInput;
use App\Models\User;
use App\Services\FinalEntryBaseFilterPolicy;
use App\Services\FinalEntryCanonicalizer;
use App\Services\FinalEntryFilterContextResolver;
use App\Services\FinalEntryFilterRuleRegistry;
use App\Services\FinalEntryRawPredictionResolver;
use App\Services\FinalEntrySignalDecisionService;
use DateTimeImmutable;
use Mockery;
use Tests\TestCase;

final class FinalEntrySignalPolicyContractTest extends TestCase
{
    public function test_saved_filter_is_anded_with_the_mandatory_base_policy(): void
    {
        $input = new FinalEntrySignalDecisionInput(7, 11, 101, 'saved_filter', 9);
        $resolution = $this->resolution('solid', confidence: 0.60);
        $base = app(FinalEntryBaseFilterPolicy::class)->settings($this->normalUser());

        $result = $this->service($input, $resolution, [
            [
                'origin' => 'user_profile',
                'reference' => 'user:7:'.FinalEntryBaseFilterPolicy::VERSION,
                'settings' => $base,
            ],
            [
                'origin' => 'saved_filter',
                'reference' => 'saved_filter:9:v1',
                'settings' => ['confidence_min' => 70],
            ],
        ])->assess($input);

        $this->assertSame('FILTERED', $result->decision['decision_status']);
        $this->assertSame('WATCH', $result->decision['decision_signal']);
        $this->assertFalse($result->decision['filters_passed']);
        $this->assertContains(
            'FILTER_REJECTED:confidence_min',
            $result->decision['reason_codes'],
        );
    }

    public function test_every_supported_model_class_can_pass_without_a_quality_gate(): void
    {
        foreach (['basic', 'solid', 'quality', 'top', 'top+'] as $qualityClass) {
            $input = new FinalEntrySignalDecisionInput(7, 11, 101);
            $resolution = $this->resolution($qualityClass, qualityGate: false);
            $base = app(FinalEntryBaseFilterPolicy::class)->settings($this->normalUser());

            $result = $this->service($input, $resolution, [[
                'origin' => 'user_profile',
                'reference' => 'user:7:'.FinalEntryBaseFilterPolicy::VERSION,
                'settings' => $base,
            ]])->assess($input);

            $this->assertTrue($result->isAccepted(), $qualityClass);
        }

        $input = new FinalEntrySignalDecisionInput(7, 11, 101);
        $underperform = $this->service(
            $input,
            $this->resolution('underperform'),
            [[
                'origin' => 'user_profile',
                'reference' => 'user:7:'.FinalEntryBaseFilterPolicy::VERSION,
                'settings' => app(FinalEntryBaseFilterPolicy::class)
                    ->settings($this->normalUser()),
            ]],
        )->assess($input);

        $this->assertSame('FILTERED', $underperform->decision['decision_status']);
        $this->assertContains('MODEL_UNDERPERFORM', $underperform->decision['reason_codes']);
    }

    public function test_saved_median_threshold_gates_a_raw_buy(): void
    {
        $input = new FinalEntrySignalDecisionInput(7, 11, 101, 'saved_filter', 9);
        $base = app(FinalEntryBaseFilterPolicy::class)->settings($this->normalUser());
        $contexts = [
            [
                'origin' => 'user_profile',
                'reference' => 'user:7:'.FinalEntryBaseFilterPolicy::VERSION,
                'settings' => $base,
            ],
            [
                'origin' => 'saved_filter',
                'reference' => 'saved_filter:9:v1',
                'settings' => ['median_return_min' => 0.5],
            ],
        ];

        $accepted = $this->service(
            $input,
            $this->resolution('solid', medianNetTrade: 0.008),
            $contexts,
        )->assess($input);

        $this->assertTrue($accepted->isAccepted());
        $medianRule = collect($accepted->decision['filter_snapshot']['contexts'])
            ->flatMap(fn (array $context): array => $context['evaluation']['results'] ?? [])
            ->firstWhere('key', 'median_return_min');
        $this->assertSame(0.5, $medianRule['configured_value']);
        $this->assertEqualsWithDelta(0.8, $medianRule['observed_value'], .000000001);

        $rejected = $this->service(
            $input,
            $this->resolution('solid', medianNetTrade: 0.004),
            $contexts,
        )->assess($input);

        $this->assertSame('WATCH', $rejected->decision['decision_signal']);
        $this->assertContains(
            'FILTER_REJECTED:median_return_min',
            $rejected->decision['reason_codes'],
        );
    }

    public function test_active_median_threshold_fails_closed_when_metric_is_missing(): void
    {
        $input = new FinalEntrySignalDecisionInput(7, 11, 101, 'saved_filter', 9);
        $result = $this->service($input, $this->resolution('solid'), [
            [
                'origin' => 'user_profile',
                'reference' => 'user:7:'.FinalEntryBaseFilterPolicy::VERSION,
                'settings' => app(FinalEntryBaseFilterPolicy::class)->settings($this->normalUser()),
            ],
            [
                'origin' => 'saved_filter',
                'reference' => 'saved_filter:9:v1',
                'settings' => ['median_return_min' => 0.0],
            ],
        ])->assess($input);

        $this->assertSame('HOLD', $result->decision['decision_signal']);
        $this->assertSame('ERROR', $result->decision['decision_status']);
        $this->assertContains(
            'FILTER_INPUT_MISSING:median_return_min',
            $result->decision['reason_codes'],
        );
    }

    public function test_raw_buy_with_an_empty_base_policy_fails_closed(): void
    {
        $input = new FinalEntrySignalDecisionInput(7, 11, 101);
        $result = $this->service($input, $this->resolution('basic'), [[
            'origin' => 'user_profile',
            'reference' => 'legacy-empty-base',
            'settings' => [],
        ]])->assess($input);

        $this->assertSame('ERROR', $result->decision['decision_status']);
        $this->assertSame('HOLD', $result->decision['decision_signal']);
        $this->assertContains(
            'ENTRY_BASE_POLICY_RULES_MISSING',
            $result->decision['reason_codes'],
        );
    }

    /** @param list<array<string,mixed>> $contexts */
    private function service(
        FinalEntrySignalDecisionInput $input,
        FinalEntryRawPredictionResolution $resolution,
        array $contexts,
    ): FinalEntrySignalDecisionService {
        $rawResolver = Mockery::mock(FinalEntryRawPredictionResolver::class);
        $rawResolver->shouldReceive('resolve')->once()->with($input)->andReturn($resolution);
        $contextResolver = Mockery::mock(FinalEntryFilterContextResolver::class);
        $contextResolver->shouldReceive('resolve')->once()->with($input)->andReturn($contexts);

        return new FinalEntrySignalDecisionService(
            $rawResolver,
            $contextResolver,
            new FinalEntryFilterRuleRegistry,
            new FinalEntryCanonicalizer,
        );
    }

    private function normalUser(): User
    {
        return new User(['meta' => ['risk_profile' => ['level' => 'normal']]]);
    }

    private function resolution(
        string $qualityClass,
        float $confidence = 0.75,
        bool $qualityGate = true,
        ?float $medianNetTrade = null,
    ): FinalEntryRawPredictionResolution {
        $asOf = new DateTimeImmutable('2026-09-07T16:00:00+00:00');
        $evaluatedAt = new DateTimeImmutable('2026-09-07T16:05:00+00:00');

        return new FinalEntryRawPredictionResolution(
            source: [
                'id' => 101,
                'batch_id' => '10000000-0000-4000-8000-000000000001',
                'release_id' => '20000000-0000-4000-8000-000000000001',
                'instrument_id' => 501,
                'as_of' => '2026-09-07 16:00:00.000000+00:00',
                'batch_completed_at' => '2026-09-07 16:03:00.000000+00:00',
                'market_date' => '2026-09-07',
                'horizon' => 20,
                'variant' => 'standard',
                'signal' => 'BUY',
                'model_quality_class' => $qualityClass,
                'quality_gate_passed' => $qualityGate,
            ],
            metrics: [
                'prediction_score_10' => 7.2,
                'confidence_fraction' => $confidence,
                'risk_percent' => 30.0,
                'predicted_return_fraction' => 0.03,
                'performance' => array_filter([
                    'profit_factor' => 2.0,
                    'trades' => 20,
                    'hit_rate' => $confidence,
                    'median_net_trade' => $medianNetTrade,
                ], static fn (mixed $value): bool => $value !== null),
            ],
            mapping: [
                'local_instrument_id' => 11,
                'serving_instrument_id' => 501,
                'session_timezone' => 'Europe/Berlin',
            ],
            sessionFeed: [
                'id' => 3,
                'provider_name' => 'serving_prediction',
                'resolver_version' => FinalEntryRawPredictionResolver::SESSION_RESOLVER_VERSION,
                'mapping_sha256' => str_repeat('b', 64),
            ],
            cutoverAt: new DateTimeImmutable('2026-09-06T10:00:15+00:00'),
            evaluatedAt: $evaluatedAt,
            sourceEventKey: str_repeat('c', 64),
            sourcePayloadSha256: str_repeat('d', 64),
        );
    }
}
