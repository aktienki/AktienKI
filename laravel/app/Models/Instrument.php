<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Instrument extends Model
{
    use SoftDeletes;

    protected $guarded = [];

    protected $casts = [
        'market_cap' => 'float',
        'is_active' => 'boolean',
        'is_tradeable' => 'boolean',
        'meta' => 'array',
    ];

    public function watchlistItems(): HasMany
    {
        return $this->hasMany(WatchlistItem::class);
    }
}
