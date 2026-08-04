<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InstrumentFinancialStatement extends Model
{
    protected $guarded = [];

    protected $casts = [
        'fiscal_date' => 'date',
        'reported_at' => 'datetime',
        'retrieved_at' => 'datetime',
        'data' => 'array',
    ];

    public function instrument(): BelongsTo
    {
        return $this->belongsTo(Instrument::class);
    }
}
