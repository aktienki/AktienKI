<?php

namespace App\Data;

final readonly class FinalEntrySignalDecisionResult
{
    /**
     * @param  array<string, mixed>  $decision
     * @param  array<string, mixed>|null  $lifecycle
     */
    public function __construct(
        public array $decision,
        public ?array $lifecycle = null,
        public ?int $decisionId = null,
        public ?int $lifecycleId = null,
        public bool $idempotentReplay = false,
        public bool $persistenceReady = true,
    ) {}

    public function isAccepted(): bool
    {
        return ($this->decision['decision_status'] ?? null) === 'ACCEPTED'
            && ($this->decision['decision_signal'] ?? null) === 'BUY'
            && ($this->decision['raw_signal'] ?? null) === 'BUY'
            && ($this->decision['filters_passed'] ?? null) === true;
    }

    /** A replay is audit evidence, never a fresh executable entry. */
    public function isActionableBuy(): bool
    {
        return $this->isAccepted()
            && ! $this->idempotentReplay
            && $this->decisionId !== null && $this->decisionId > 0
            && $this->lifecycleId !== null && $this->lifecycleId > 0
            && ($this->lifecycle['status'] ?? null) === 'ACTIVE'
            && (int) ($this->lifecycle['id'] ?? 0) === $this->lifecycleId
            && (int) ($this->lifecycle['entry_signal_decision_id'] ?? 0)
                === $this->decisionId;
    }

    /** @param list<string> $reasonCodes */
    public static function failClosed(array $reasonCodes): self
    {
        return new self(
            decision: [
                'raw_signal' => 'UNKNOWN',
                'filters_passed' => false,
                'decision_signal' => 'HOLD',
                'decision_status' => 'ERROR',
                'reason_codes' => array_values(array_unique($reasonCodes)),
            ],
            persistenceReady: false,
        );
    }
}
