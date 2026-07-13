<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StrategyExperimentVariant extends Model
{
    protected $fillable = [
        'strategy_experiment_id',
        'variant_code',
        'status',
        'resolved_configuration',
        'configuration_hash',
        'started_at',
        'finished_at',
        'error_message',
    ];

    protected $casts = [
        'resolved_configuration' => 'array',
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
    ];

    public function experiment(): BelongsTo
    {
        return $this->belongsTo(
            StrategyExperiment::class,
            'strategy_experiment_id'
        );
    }

    public function results(): HasMany
    {
        return $this->hasMany(StrategyExperimentResult::class);
    }
}
