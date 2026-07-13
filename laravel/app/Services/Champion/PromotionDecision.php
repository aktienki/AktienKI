<?php

namespace App\Services\Champion;

use DateTimeImmutable;
use InvalidArgumentException;

final readonly class PromotionDecision
{
    public function __construct(
        public bool $promote,
        public string $reason,
        public int $championModelId,
        public int $challengerModelId,
        public DateTimeImmutable $decidedAt,
        public ?DateTimeImmutable $cooldownUntil = null,
        public array $checks = [],
        public array $metadata = [],
    ) {
        if ($championModelId <= 0 || $challengerModelId <= 0) {
            throw new InvalidArgumentException(
                'Modell-IDs müssen größer als null sein.'
            );
        }

        if ($championModelId === $challengerModelId) {
            throw new InvalidArgumentException(
                'Champion und Challenger müssen unterschiedliche Modelle sein.'
            );
        }

        if (trim($reason) === '') {
            throw new InvalidArgumentException(
                'reason darf nicht leer sein.'
            );
        }

        if ($cooldownUntil !== null && $cooldownUntil < $decidedAt) {
            throw new InvalidArgumentException(
                'cooldownUntil darf nicht vor decidedAt liegen.'
            );
        }
    }

    public static function approved(
        int $championModelId,
        int $challengerModelId,
        string $reason,
        DateTimeImmutable $decidedAt,
        array $checks = [],
        array $metadata = [],
    ): self {
        return new self(
            true,
            $reason,
            $championModelId,
            $challengerModelId,
            $decidedAt,
            null,
            $checks,
            $metadata,
        );
    }

    public static function rejected(
        int $championModelId,
        int $challengerModelId,
        string $reason,
        DateTimeImmutable $decidedAt,
        ?DateTimeImmutable $cooldownUntil = null,
        array $checks = [],
        array $metadata = [],
    ): self {
        return new self(
            false,
            $reason,
            $championModelId,
            $challengerModelId,
            $decidedAt,
            $cooldownUntil,
            $checks,
            $metadata,
        );
    }

    public function toArray(): array
    {
        return [
            'promote' => $this->promote,
            'reason' => $this->reason,
            'champion_model_id' => $this->championModelId,
            'challenger_model_id' => $this->challengerModelId,
            'decided_at' => $this->decidedAt->format(DATE_ATOM),
            'cooldown_until' => $this->cooldownUntil?->format(DATE_ATOM),
            'checks' => $this->checks,
            'metadata' => $this->metadata,
        ];
    }
}
