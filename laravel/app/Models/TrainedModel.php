<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TrainedModel extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'model_definition_id',
        'owner_user_id',
        'instrument_id',
        'scope',
        'version',
        'status',
        'storage_disk',
        'artifact_path',
        'checksum',
        'trained_at',
        'training_period_start',
        'training_period_end',
        'training_rows',
        'validation_rows',
        'test_rows',
        'parameters',
        'metrics',
        'feature_names',
        'metadata',
    ];

    protected $casts = [
        'trained_at' => 'datetime',
        'training_period_start' => 'datetime',
        'training_period_end' => 'datetime',
        'parameters' => 'array',
        'metrics' => 'array',
        'feature_names' => 'array',
        'metadata' => 'array',
    ];

    public function definition(): BelongsTo
    {
        return $this->belongsTo(ModelDefinition::class, 'model_definition_id');
    }

    public function instrument(): BelongsTo
    {
        return $this->belongsTo(Instrument::class);
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }

    public function trainingRuns(): HasMany
    {
        return $this->hasMany(TrainingRun::class);
    }
}
