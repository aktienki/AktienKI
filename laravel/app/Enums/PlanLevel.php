<?php

namespace App\Enums;

enum PlanLevel: string
{
    case Free = 'free';
    case Plus = 'plus';
    case Pro = 'pro';
    case Premium = 'premium';

    public function rank(): int
    {
        return match ($this) {
            self::Free => 0,
            self::Plus => 1,
            self::Pro => 2,
            self::Premium => 3,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Free => 'Free',
            self::Plus => 'Plus',
            self::Pro => 'Pro',
            self::Premium => 'Premium',
        };
    }

    public function includes(self|string|null $required): bool
    {
        if ($required === null || $required === '') {
            return true;
        }

        $requiredLevel = $required instanceof self ? $required : self::tryFrom(strtolower((string) $required));

        if (! $requiredLevel) {
            return false;
        }

        return $this->rank() >= $requiredLevel->rank();
    }
}
