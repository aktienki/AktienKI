<?php

namespace Tests\Unit;

use App\Services\Champion\PromotionDecision;
use DateTimeImmutable;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class PromotionDecisionTest extends TestCase
{
    public function test_it_creates_an_approved_decision(): void
    {
        $decision = PromotionDecision::approved(
            championModelId: 10,
            challengerModelId: 11,
            reason: 'Challenger erfüllt alle Regeln.',
            decidedAt: new DateTimeImmutable('2026-07-12T18:00:00+00:00'),
            checks: ['minimum_predictions' => true],
            metadata: ['comparison_id' => 77],
        );

        $this->assertTrue($decision->promote);
        $this->assertSame(10, $decision->championModelId);
        $this->assertSame(11, $decision->challengerModelId);
        $this->assertNull($decision->cooldownUntil);
        $this->assertSame(77, $decision->metadata['comparison_id']);
    }

    public function test_it_creates_a_rejected_decision_with_cooldown(): void
    {
        $decidedAt = new DateTimeImmutable('2026-07-12T18:00:00+00:00');
        $cooldownUntil = new DateTimeImmutable('2026-08-11T18:00:00+00:00');

        $decision = PromotionDecision::rejected(
            championModelId: 10,
            challengerModelId: 11,
            reason: 'Champion befindet sich noch im Cooldown.',
            decidedAt: $decidedAt,
            cooldownUntil: $cooldownUntil,
            checks: ['cooldown_complete' => false],
        );

        $this->assertFalse($decision->promote);
        $this->assertSame($cooldownUntil, $decision->cooldownUntil);
    }

    public function test_it_rejects_identical_model_ids(): void
    {
        $this->expectException(InvalidArgumentException::class);

        PromotionDecision::approved(
            10,
            10,
            'Ungültig.',
            new DateTimeImmutable(),
        );
    }

    public function test_it_rejects_an_empty_reason(): void
    {
        $this->expectException(InvalidArgumentException::class);

        PromotionDecision::approved(
            10,
            11,
            '   ',
            new DateTimeImmutable(),
        );
    }

    public function test_it_rejects_a_cooldown_before_decision_time(): void
    {
        $this->expectException(InvalidArgumentException::class);

        PromotionDecision::rejected(
            10,
            11,
            'Ungültiger Cooldown.',
            new DateTimeImmutable('2026-07-12T18:00:00+00:00'),
            new DateTimeImmutable('2026-07-11T18:00:00+00:00'),
        );
    }

    public function test_it_is_serializable(): void
    {
        $data = PromotionDecision::approved(
            10,
            11,
            'Promotion erlaubt.',
            new DateTimeImmutable('2026-07-12T18:00:00+00:00'),
        )->toArray();

        $this->assertArrayHasKey('promote', $data);
        $this->assertArrayHasKey('reason', $data);
        $this->assertArrayHasKey('champion_model_id', $data);
        $this->assertArrayHasKey('challenger_model_id', $data);
        $this->assertArrayHasKey('decided_at', $data);
        $this->assertArrayHasKey('cooldown_until', $data);
    }
}
