<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WatchlistItem extends Model
{
    protected $guarded = [];

    protected $casts = [
        'added_at' => 'datetime',
        'entry_price' => 'float',
        'entry_price_at' => 'datetime',
        'alert_price_above' => 'float',
        'alert_price_below' => 'float',
        'meta' => 'array',
    ];

    public function watchlist(): BelongsTo
    {
        return $this->belongsTo(Watchlist::class);
    }

    public function instrument(): BelongsTo
    {
        return $this->belongsTo(Instrument::class);
    }

    public function prediction(): BelongsTo
    {
        return $this->belongsTo(Prediction::class);
    }
}
