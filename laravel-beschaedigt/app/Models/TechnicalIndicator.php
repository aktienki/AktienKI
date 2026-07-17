<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TechnicalIndicator extends Model
{
    protected $fillable = [
        'instrument_id',
        'interval',
        'bar_time',
        'sma_20',
        'sma_50',
        'sma_200',
        'ema_12',
        'ema_20',
        'ema_26',
        'ema_50',
        'ema_200',
        'rsi_14',
        'macd',
        'macd_signal',
        'macd_histogram',
        'atr_14',
        'adx_14',
        'bollinger_upper',
        'bollinger_middle',
        'bollinger_lower',
        'bollinger_width',
        'stochastic_k',
        'stochastic_d',
        'roc_12',
        'momentum_10',
        'volatility_20',
        'volume_sma_20',
        'calculation_version',
    ];

    protected $casts = [
        'bar_time' => 'datetime',
    ];

    public function instrument(): BelongsTo
    {
        return $this->belongsTo(Instrument::class);
    }
}
