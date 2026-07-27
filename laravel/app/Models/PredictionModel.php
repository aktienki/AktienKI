<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PredictionModel extends Model
{
    protected $guarded = [];

    protected $casts = [
        'score' => 'float',
        'accuracy' => 'float',
        'precision' => 'float',
        'recall' => 'float',
        'f1_score' => 'float',
        'hitrate' => 'float',
        'predicted_return' => 'float',
        'confidence' => 'float',
        'prediction' => 'float',
        'weight' => 'float',
        'metrics' => 'array',
        'meta' => 'array',
    ];

    public function prediction(): BelongsTo
    {
        return $this->belongsTo(Prediction::class);
    }
}
