<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PredictionValidation extends Model
{
    protected $fillable = [
        'prediction_id',
        'validation_horizon_days',
        'target_time',
        'actual_price',
        'actual_market_return',
        'actual_long_return',
        'actual_short_return',
        'actual_strategy_return',
        'prediction_error',
        'prediction_error_pct',
        'direction_correct',
        'strategy_correct',
        'target_hit',
        'future_high',
        'future_low',
        'max_favorable_excursion',
        'max_adverse_excursion',
        'validated_at',
        'metadata',
    ];

    protected $casts = [
        'target_time' => 'datetime',
        'validated_at' => 'datetime',
        'direction_correct' => 'boolean',
        'strategy_correct' => 'boolean',
        'target_hit' => 'boolean',
        'metadata' => 'array',
    ];

    public function prediction(): BelongsTo
    {
        return $this->belongsTo(Prediction::class);
    }
}
