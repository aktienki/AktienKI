<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ModelRun extends Model
{
    protected $table = 'training_runs';

    protected $guarded = [];

    protected $casts = [
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
        'metrics' => 'array',
        'metadata' => 'array',
        'meta' => 'array',
    ];

    public function trainedModel(): BelongsTo
    {
        return $this->belongsTo(TrainedModel::class);
    }
}
