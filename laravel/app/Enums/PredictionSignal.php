<?php

namespace App\Enums;

enum PredictionSignal: string
{
    case Buy = 'buy';
    case Hold = 'hold';
    case Sell = 'sell';

    public function label(): string
    {
        return match ($this) {
            self::Buy => 'Positiv',
            self::Hold => 'Halten',
            self::Sell => 'Verkaufen',
        };
    }
}
