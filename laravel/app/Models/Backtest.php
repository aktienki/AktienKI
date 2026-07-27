<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Backtest extends Model
{
    protected $guarded = [];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'initial_capital' => 'float',
        'final_capital' => 'float',
        'total_return' => 'float',
        'annualized_return' => 'float',
        'max_drawdown' => 'float',
        'sharpe_ratio' => 'float',
        'win_rate' => 'float',
        'settings' => 'array',
        'results' => 'array',
        'meta' => 'array',
    ];

    public function trainedModel(): BelongsTo
    {
        return $this->belongsTo(TrainedModel::class);
    }
}
