<?php

namespace App\Data;

/**
 * Untrusted command boundary. It deliberately carries identifiers only:
 * source contents, clocks, filters, rule results and feed state are resolved
 * by server-side services.
 */
final readonly class FinalEntrySignalDecisionInput
{
    public function __construct(
        public int $userId,
        public int $instrumentId,
        public int $sourcePredictionId,
        public string $contextType = 'user_profile',
        public ?int $savedPredictionFilterId = null,
    ) {}

    public function contextKey(): string
    {
        return $this->contextType === 'saved_filter'
            ? 'strategy:'.(string) $this->savedPredictionFilterId
            : 'user';
    }
}
