<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ModelEloHistory extends Model
{
    protected $table = 'model_elo_history';

    protected $fillable = [
        'trained_model_id',
        'model_comparison_id',
        'rating_before',
        'rating_after',
        'rating_change',
        'result',
        'opponent_type',
        'opponent_model_id',
        'rated_at',
        'metadata',
    ];

    protected $casts = [
        'rated_at' => 'datetime',
        'metadata' => 'array',
    ];

    public function trainedModel(): BelongsTo
    {
        return $this->belongsTo(TrainedModel::class);
    }

    public function comparison(): BelongsTo
    {
        return $this->belongsTo(
            ModelComparison::class,
            'model_comparison_id'
        );
    }

    public function opponentModel(): BelongsTo
    {
        return $this->belongsTo(
            TrainedModel::class,
            'opponent_model_id'
        );
    }
}
