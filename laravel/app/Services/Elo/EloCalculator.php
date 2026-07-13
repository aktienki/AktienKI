<?php

namespace App\Services\Elo;

use InvalidArgumentException;

final class EloCalculator
{
    public const RESULT_CHAMPION = 'champion';
    public const RESULT_CHALLENGER = 'challenger';
    public const RESULT_DRAW = 'draw';

    public function expectedScore(float $rating, float $opponentRating): float
    {
        return 1.0 / (1.0 + pow(10.0, ($opponentRating - $rating) / 400.0));
    }

    public function actualScores(string $winner): array
    {
        return match ($winner) {
            self::RESULT_CHAMPION => [1.0, 0.0],
            self::RESULT_CHALLENGER => [0.0, 1.0],
            self::RESULT_DRAW => [0.5, 0.5],
            default => throw new InvalidArgumentException(
                "Ungültiges Ergebnis '{$winner}'. Erlaubt sind champion, challenger oder draw."
            ),
        };
    }

    public function calculateNewRating(
        float $rating,
        float $actualScore,
        float $expectedScore,
        int $kFactor,
    ): float {
        if ($kFactor <= 0) {
            throw new InvalidArgumentException('Der K-Faktor muss größer als null sein.');
        }

        return $rating + $kFactor * ($actualScore - $expectedScore);
    }
}
