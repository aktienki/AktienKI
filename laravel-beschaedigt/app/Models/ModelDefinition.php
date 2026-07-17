<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ModelDefinition extends Model
{
    protected $fillable = [
        'code',
        'name',
        'algorithm',
        'task_type',
        'target_name',
        'interval',
        'feature_version',
        'default_parameters',
        'is_active',
    ];

    protected $casts = [
        'default_parameters' => 'array',
        'is_active' => 'boolean',
    ];

    public function trainedModels(): HasMany
    {
        return $this->hasMany(TrainedModel::class);
    }

    public function trainingRuns(): HasMany
    {
        return $this->hasMany(TrainingRun::class);
    }
}
