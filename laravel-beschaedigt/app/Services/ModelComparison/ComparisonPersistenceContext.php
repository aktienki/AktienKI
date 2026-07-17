<?php

namespace App\Services\ModelComparison;

use InvalidArgumentException;

final readonly class ComparisonPersistenceContext
{
    public function __construct(
        public int $strategyProfileId,
        public ?int $instrumentId,
        public int $championRecordId,
        public int $challengerRecordId,
        public int $championModelId,
        public int $challengerModelId,
        public int $predictionCount,
        public ModelMetrics $championMetrics,
        public ModelMetrics $challengerMetrics,
        public array $metadata = [],
    ) {
        foreach ([
            'strategyProfileId' => $strategyProfileId,
            'championRecordId' => $championRecordId,
            'challengerRecordId' => $challengerRecordId,
            'championModelId' => $championModelId,
            'challengerModelId' => $challengerModelId,
        ] as $name => $value) {
            if ($value <= 0) {
                throw new InvalidArgumentException(
                    "{$name} muss größer als null sein."
                );
            }
        }

        if ($instrumentId !== null && $instrumentId <= 0) {
            throw new InvalidArgumentException(
                'instrumentId muss null oder größer als null sein.'
            );
        }

        if ($predictionCount < 0) {
            throw new InvalidArgumentException(
                'predictionCount darf nicht negativ sein.'
            );
        }
    }
}
