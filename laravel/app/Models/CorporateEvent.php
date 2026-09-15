<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class CorporateEvent extends Model
{
    protected $guarded = [];

    protected $casts = [
        'event_date' => 'date',
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

    public function import(): BelongsTo
    {
        return $this->belongsTo(CorporateEventImport::class, 'import_id');
    }

    public function reaction(): HasOne
    {
        return $this->hasOne(EarningsPriceReaction::class);
    }
}
