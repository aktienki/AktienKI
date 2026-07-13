<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StrategyExperimentResult extends Model
{
    protected $fillable = [
        'strategy_experiment_variant_id',
        'trained_model_id',
        'algorithm',
        'status',
        'validation_mae',
        'validation_rmse',
        'validation_r2',
        'validation_direction_accuracy',
        'test_mae',
        'test_rmse',
        'test_r2',
        'test_direction_accuracy',
        'stability_score',
        'selection_score',
        'metrics',
        'feature_importance',
        'metadata',
    ];

    protected $casts = [
        'metrics' => 'array',
        'feature_importance' => 'array',
        'metadata' => 'array',
    ];

    public function variant(): BelongsTo
    {
        return $this->belongsTo(
            StrategyExperimentVariant::class,
            'strategy_experiment_variant_id'
        );
    }

    public function trainedModel(): BelongsTo
    {
        return $this->belongsTo(TrainedModel::class);
    }
}
