<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One row per corporate_events earnings entry: the closing price at/around
 * the event and the trading-day-indexed forward returns computed from
 * price_bars, produced by EarningsPriceReactionCalculator. This is the
 * data layer the post-earnings-drift study (earnings:drift-report) reads.
 */
class EarningsPriceReaction extends Model
{
    protected $guarded = [];

    protected $casts = [
        'event_date' => 'date',
        'surprise_percent' => 'float',
        'close_at_event' => 'float',
        'return_pre_5d' => 'float',
        'return_1d' => 'float',
        'return_5d' => 'float',
        'return_10d' => 'float',
        'return_20d' => 'float',
        'return_40d' => 'float',
        'is_complete' => 'boolean',
        'computed_at' => 'datetime',
    ];

    public function corporateEvent(): BelongsTo
    {
        return $this->belongsTo(CorporateEvent::class);
    }

    public function instrument(): BelongsTo
    {
        return $this->belongsTo(Instrument::class);
    }
}
