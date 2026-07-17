<?php

namespace App\Services;

class PredictionMathService
{
    public function calculate(
        float $currentPrice,
        float $predictedPrice
    ): array {
        if ($currentPrice <= 0) {
            throw new \InvalidArgumentException(
                'Der aktuelle Kurs muss größer als null sein.'
            );
        }

        $priceDifference = $predictedPrice - $currentPrice;
        $marketReturn = $priceDifference / $currentPrice;

        $longReturn = $marketReturn;
        $shortReturn = -$marketReturn;

        $strategy = $marketReturn < 0 ? 'short' : 'long';
        $strategyReturn = $strategy === 'short'
            ? $shortReturn
            : $longReturn;

        return [
            'current_price' => $currentPrice,
            'predicted_price' => $predictedPrice,
            'price_difference' => $priceDifference,
            'market_return' => $marketReturn,
            'long_return' => $longReturn,
            'short_return' => $shortReturn,
            'strategy' => $strategy,
            'strategy_return' => $strategyReturn,
        ];
    }
}
