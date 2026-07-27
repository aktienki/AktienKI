<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PredictionModel extends Model
{
    use HasFactory;

    protected $fillable = [
        'prediction_id',
        'model',
        'score',
        'accuracy',
        'precision',
        'recall',
        'f1_score',
        'hitrate',
        'predicted_return',
        'confidence',
        'metrics',
        // legacy fields; harmless when not used by current table
        'prediction',
        'weight',
        'rank',
    ];

    protected $casts = [
        'rank' => 'integer',
        'metrics' => 'array',
    ];

    public function prediction(): BelongsTo
    {
        return $this->belongsTo(Prediction::class);
    }
}
