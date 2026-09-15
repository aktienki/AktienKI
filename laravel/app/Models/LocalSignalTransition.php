<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One row per detected daily-signal change from the local, server-scheduled
 * prediction pipeline (see SignalTransitionRecorder). from_signal is null
 * for an instrument's very first recorded row - that is a baseline, not a
 * real transition.
 */
class LocalSignalTransition extends Model
{
    protected $guarded = [];

    protected $casts = [
        'changed_at' => 'datetime',
        'score_at_change' => 'float',
        'risk_at_change' => 'float',
    ];

    public function instrument(): BelongsTo
    {
        return $this->belongsTo(Instrument::class);
    }
}
