<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Prediction extends Model
{
    protected $guarded = [];

    protected $casts = [
        'prediction_date' => 'date',
        'target_date' => 'date',
        'current_price' => 'float',
        'predicted_price' => 'float',
        'predicted_return' => 'float',
        'buy_probability' => 'float',
        'sell_probability' => 'float',
        'hold_probability' => 'float',
        'prediction_score' => 'float',
        'confidence' => 'float',
        'rsi' => 'float',
        'macd' => 'float',
        'macd_signal' => 'float',
        'sma_20' => 'float',
        'sma_50' => 'float',
        'sma_200' => 'float',
        'features' => 'array',
        'raw_output' => 'array',
        'meta' => 'array',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function predictionRun(): BelongsTo
    {
        return $this->belongsTo(PredictionRun::class);
    }

    public function models(): HasMany
    {
        return $this->hasMany(PredictionModel::class);
    }

    public function predictionModels(): HasMany
    {
        return $this->models();
    }

    public function getExpectedGainAttribute(): ?float
    {
        if ($this->current_price === null || $this->predicted_price === null || (float) $this->current_price === 0.0) {
            return null;
        }

        return (($this->predicted_price - $this->current_price) / $this->current_price) * 100;
    }

    public function getSignalColorAttribute(): string
    {
        return match (strtoupper((string) $this->signal)) {
            'BUY' => 'green',
            'SELL' => 'red',
            default => 'yellow',
        };
    }
}
