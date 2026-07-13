<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ModelChallenger extends Model
{
    protected $fillable = [
        'strategy_profile_id',
        'instrument_id',
        'trained_model_id',
        'champion_model_id',
        'algorithm',
        'status',
        'elo_rating',
        'validated_predictions_count',
        'direction_accuracy',
        'average_strategy_return',
        'rmse',
        'stability_score',
        'evaluation_started_at',
        'evaluation_finished_at',
        'status_reason',
        'evaluation_metrics',
        'metadata',
    ];

    protected $casts = [
        'evaluation_started_at' => 'datetime',
        'evaluation_finished_at' => 'datetime',
        'evaluation_metrics' => 'array',
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

    public function trainedModel(): BelongsTo
    {
        return $this->belongsTo(TrainedModel::class);
    }

    public function championModel(): BelongsTo
    {
        return $this->belongsTo(
            TrainedModel::class,
            'champion_model_id'
        );
    }
}
