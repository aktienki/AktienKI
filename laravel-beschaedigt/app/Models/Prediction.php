<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Prediction extends Model
{
    protected $fillable = [
        'instrument_id',
        'trained_model_id',
        'prediction_time',
        'interval',
        'current_price',
        'predicted_price_5d',
        'predicted_price_10d',
        'predicted_price_20d',
        'price_difference_5d',
        'price_difference_10d',
        'price_difference_20d',
        'market_return_5d',
        'market_return_10d',
        'market_return_20d',
        'long_return_5d',
        'long_return_10d',
        'long_return_20d',
        'short_return_5d',
        'short_return_10d',
        'short_return_20d',
        'strategy',
        'strategy_return_5d',
        'strategy_return_10d',
        'strategy_return_20d',
        'direction_score',
        'signal_strength',
        'confidence',
        'risk_score',
        'trend_strength',
        'ai_score',
        'signal',
        'status',
        'explanation',
        'metadata',
    ];

    protected $casts = [
        'prediction_time' => 'datetime',
        'explanation' => 'array',
        'metadata' => 'array',
    ];

    public function instrument(): BelongsTo
    {
        return $this->belongsTo(Instrument::class);
    }

    public function trainedModel(): BelongsTo
    {
        return $this->belongsTo(TrainedModel::class);
    }

    public function validations(): HasMany
    {
        return $this->hasMany(PredictionValidation::class);
    }

    public static function calculateDerivedValues(
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
            'price_difference' => $priceDifference,
            'market_return' => $marketReturn,
            'long_return' => $longReturn,
            'short_return' => $shortReturn,
            'strategy' => $strategy,
            'strategy_return' => $strategyReturn,
        ];
    }
}
