<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InstrumentEarning extends Model
{
    protected $guarded = [];

    protected $casts = [
        'earnings_date' => 'date',
        'retrieved_at' => 'datetime',
        'eps_estimate' => 'float',
        'eps_actual' => 'float',
        'surprise_percent' => 'float',
        'data' => 'array',
    ];

    public function instrument(): BelongsTo
    {
        return $this->belongsTo(Instrument::class);
    }
}
