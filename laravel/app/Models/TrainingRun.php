<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TrainingRun extends Model
{
    protected $fillable = [
        'public_id',
        'model_definition_id',
        'trained_model_id',
        'instrument_id',
        'status',
        'feature_version',
        'target_name',
        'started_at',
        'finished_at',
        'parameters',
        'metrics',
        'error_message',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
        'parameters' => 'array',
        'metrics' => 'array',
    ];

    public function definition(): BelongsTo
    {
        return $this->belongsTo(ModelDefinition::class, 'model_definition_id');
    }

    public function trainedModel(): BelongsTo
    {
        return $this->belongsTo(TrainedModel::class);
    }

    public function instrument(): BelongsTo
    {
        return $this->belongsTo(Instrument::class);
    }
}
