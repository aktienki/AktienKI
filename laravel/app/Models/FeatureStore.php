<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FeatureStore extends Model
{
    protected $table = 'feature_store';

    protected $guarded = [];

    protected $casts = [
        'date' => 'date',
        'rsi' => 'float',
        'macd' => 'float',
        'macd_signal' => 'float',
        'macd_histogram' => 'float',
        'sma_5' => 'float',
        'sma_10' => 'float',
        'sma_20' => 'float',
        'sma_50' => 'float',
        'sma_100' => 'float',
        'sma_200' => 'float',
        'ema_5' => 'float',
        'ema_10' => 'float',
        'ema_20' => 'float',
        'ema_50' => 'float',
        'ema_100' => 'float',
        'ema_200' => 'float',
        'bollinger_upper' => 'float',
        'bollinger_middle' => 'float',
        'bollinger_lower' => 'float',
        'atr' => 'float',
        'volatility_10' => 'float',
        'volatility_20' => 'float',
        'volatility_50' => 'float',
        'momentum_5' => 'float',
        'momentum_10' => 'float',
        'momentum_20' => 'float',
        'volume_sma_20' => 'float',
        'volume_ratio' => 'float',
        'features' => 'array',
        'meta' => 'array',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}
