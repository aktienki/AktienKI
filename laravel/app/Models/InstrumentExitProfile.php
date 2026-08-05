<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InstrumentExitProfile extends Model
{
    protected $guarded = [];

    protected $casts = [
        'holding_days' => 'integer',
        'window_start_days' => 'integer',
        'window_end_days' => 'integer',
        'is_active' => 'boolean',
        'backtest_from' => 'date',
        'backtest_to' => 'date',
        'trade_count' => 'integer',
        'total_return' => 'float',
        'smoothed_total_return' => 'float',
        'baseline_20d_total_return' => 'float',
        'improvement_over_20d' => 'float',
        'window_stability_score' => 'float',
        'calendar_cagr' => 'float',
        'profit_factor' => 'float',
        'win_rate' => 'float',
        'max_drawdown' => 'float',
        'market_exposure' => 'float',
        'exit_sweep' => 'array',
        'metadata' => 'array',
        'selected_at' => 'datetime',
        'validated_at' => 'datetime',
    ];

    public function instrument(): BelongsTo
    {
        return $this->belongsTo(Instrument::class);
    }

    public function pulseModel(): BelongsTo
    {
        return $this->belongsTo(TrainedModel::class, 'pulse_trained_model_id');
    }

    public function intermediateModel(): BelongsTo
    {
        return $this->belongsTo(TrainedModel::class, 'intermediate_trained_model_id');
    }

    public function horizonModel(): BelongsTo
    {
        return $this->belongsTo(TrainedModel::class, 'horizon_trained_model_id');
    }
}
