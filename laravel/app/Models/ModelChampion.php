<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ModelChampion extends Model
{
    protected $fillable = [
        'strategy_profile_id',
        'instrument_id',
        'active_trained_model_id',
        'previous_trained_model_id',
        'algorithm',
        'status',
        'elo_rating',
        'validated_predictions_count',
        'direction_accuracy',
        'average_strategy_return',
        'rmse',
        'stability_score',
        'activated_at',
        'activation_reason',
        'activation_metrics',
        'metadata',
    ];

    protected $casts = [
        'activated_at' => 'datetime',
        'activation_metrics' => 'array',
        'metadata' => 'array',
    ];

    public function strategyProfile(): BelongsTo
    {
        return $this->belongsTo(StrategyProfile::class);
    }

    public function instrument(): BelongsTo
    {
        return $this->belongsTo(Instrument::class);
    }

    public function activeModel(): BelongsTo
    {
        return $this->belongsTo(
            TrainedModel::class,
            'active_trained_model_id'
        );
    }

    public function previousModel(): BelongsTo
    {
        return $this->belongsTo(
            TrainedModel::class,
            'previous_trained_model_id'
        );
    }
}
