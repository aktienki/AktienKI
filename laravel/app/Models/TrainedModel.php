<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TrainedModel extends Model
{
    protected $guarded = [];

    protected $casts = [
        'trained_from' => 'date',
        'trained_to' => 'date',
        'accuracy' => 'float',
        'precision' => 'float',
        'recall' => 'float',
        'f1_score' => 'float',
        'hitrate' => 'float',
        'rmse' => 'float',
        'mae' => 'float',
        'active' => 'boolean',
        'parameters' => 'array',
        'metrics' => 'array',
        'features' => 'array',
        'meta' => 'array',
    ];

    public function modelRuns(): HasMany
    {
        return $this->hasMany(ModelRun::class);
    }

    public function backtests(): HasMany
    {
        return $this->hasMany(Backtest::class);
    }
}
