<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ModelComparison extends Model
{
    protected $fillable = [
        'strategy_profile_id',
        'instrument_id',
        'champion_model_id',
        'challenger_model_id',
        'prediction_count',
        'champion_direction_accuracy',
        'challenger_direction_accuracy',
        'champion_strategy_return',
        'challenger_strategy_return',
        'champion_rmse',
        'challenger_rmse',
        'champion_stability_score',
        'challenger_stability_score',
        'champion_selection_score',
        'challenger_selection_score',
        'winner',
        'promotion_recommended',
        'compared_at',
        'comparison_rules',
        'metrics',
        'metadata',
    ];

    protected $casts = [
        'promotion_recommended' => 'boolean',
        'compared_at' => 'datetime',
        'comparison_rules' => 'array',
        'metrics' => 'array',
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

    public function championModel(): BelongsTo
    {
        return $this->belongsTo(
            TrainedModel::class,
            'champion_model_id'
        );
    }

    public function challengerModel(): BelongsTo
    {
        return $this->belongsTo(
            TrainedModel::class,
            'challenger_model_id'
        );
    }

    public function eloHistory(): HasMany
    {
        return $this->hasMany(ModelEloHistory::class);
    }
}
