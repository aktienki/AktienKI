<?php

namespace App\Services\Elo;

use InvalidArgumentException;

final class EloRatingService
{
    public function __construct(
        private readonly EloCalculator $calculator,
    ) {
    }

    public function update(
        float $championRating,
        float $challengerRating,
        string $winner,
        int $kFactor = 32,
        int $precision = 4,
    ): RatingResult {
        $this->validateRating($championRating, 'Champion');
        $this->validateRating($challengerRating, 'Challenger');

        if ($precision < 0 || $precision > 8) {
            throw new InvalidArgumentException('Die Genauigkeit muss zwischen 0 und 8 liegen.');
        }

        $championExpected = $this->calculator->expectedScore($championRating, $challengerRating);
        $challengerExpected = $this->calculator->expectedScore($challengerRating, $championRating);
        [$championActual, $challengerActual] = $this->calculator->actualScores($winner);

        $championAfter = round($this->calculator->calculateNewRating(
            $championRating,
            $championActual,
            $championExpected,
            $kFactor,
        ), $precision);

        $challengerAfter = round($this->calculator->calculateNewRating(
            $challengerRating,
            $challengerActual,
            $challengerExpected,
            $kFactor,
        ), $precision);

        return new RatingResult(
            championBefore: round($championRating, $precision),
            challengerBefore: round($challengerRating, $precision),
            championAfter: $championAfter,
            challengerAfter: $challengerAfter,
            championChange: round($championAfter - $championRating, $precision),
            challengerChange: round($challengerAfter - $challengerRating, $precision),
            championExpectedScore: round($championExpected, $precision),
            challengerExpectedScore: round($challengerExpected, $precision),
            winner: $winner,
            kFactor: $kFactor,
        );
    }

    private function validateRating(float $rating, string $label): void
    {
        if (! is_finite($rating)) {
            throw new InvalidArgumentException("{$label}-Rating muss eine endliche Zahl sein.");
        }

        if ($rating < 0) {
            throw new InvalidArgumentException("{$label}-Rating darf nicht negativ sein.");
        }
    }
}
