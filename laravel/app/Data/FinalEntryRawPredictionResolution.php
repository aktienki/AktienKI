<?php

namespace App\Data;

use DateTimeImmutable;

/** One server-verified immutable serving event and its authoritative feed. */
final readonly class FinalEntryRawPredictionResolution
{
    /**
     * @param  array<string, mixed>  $source
     * @param  array<string, mixed>  $metrics
     * @param  array<string, mixed>  $mapping
     * @param  array<string, mixed>  $sessionFeed
     */
    public function __construct(
        public array $source,
        public array $metrics,
        public array $mapping,
        public array $sessionFeed,
        public DateTimeImmutable $cutoverAt,
        public DateTimeImmutable $evaluatedAt,
        public string $sourceEventKey,
        public string $sourcePayloadSha256,
    ) {}
}
