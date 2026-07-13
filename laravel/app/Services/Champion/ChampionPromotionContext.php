<?php

namespace App\Services\Champion;

use InvalidArgumentException;

final readonly class ChampionPromotionContext
{
    public function __construct(
        public int $championRecordId,
        public int $challengerRecordId,
        public int $comparisonId,
        public array $metrics = [],
        public array $metadata = [],
    ) {
        foreach ([
            'championRecordId' => $championRecordId,
            'challengerRecordId' => $challengerRecordId,
            'comparisonId' => $comparisonId,
        ] as $name => $value) {
            if ($value <= 0) {
                throw new InvalidArgumentException(
                    "{$name} muss größer als null sein."
                );
            }
        }
    }
}
