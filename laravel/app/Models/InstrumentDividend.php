<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InstrumentDividend extends Model
{
    protected $guarded = [];

    protected $casts = [
        'ex_date' => 'date',
        'record_date' => 'date',
        'payment_date' => 'date',
        'retrieved_at' => 'datetime',
        'amount' => 'float',
        'data' => 'array',
    ];

    public function instrument(): BelongsTo
    {
        return $this->belongsTo(Instrument::class);
    }
}
