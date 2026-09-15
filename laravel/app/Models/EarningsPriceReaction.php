<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One row per corporate_events earnings entry: the forecast (eps_estimate)
 * vs. the real reported figure (eps_actual), and the price move 3 trading
 * days before publication vs. 3 trading days after, computed from
 * price_bars by EarningsPriceReactionCalculator. This is the data layer
 * the post-earnings-drift study (earnings:drift-report) reads.
 */
class EarningsPriceReaction extends Model
{
    protected $guarded = [];

    protected $casts = [
        'event_date' => 'date',
        'surprise_percent' => 'float',
        'eps_estimate' => 'float',
        'eps_actual' => 'float',
        'close_at_event' => 'float',
        'return_pre_3d' => 'float',
        'return_post_3d' => 'float',
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
