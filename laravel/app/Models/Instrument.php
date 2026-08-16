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
        'is_german_tradeable' => 'boolean',
        'german_listing_verified_at' => 'datetime',
        'german_listing_checked_at' => 'datetime',
        'meta' => 'array',
    ];

    public function watchlistItems(): HasMany
    {
        return $this->hasMany(WatchlistItem::class);
    }

    public function fundamentals(): HasMany
    {
        return $this->hasMany(InstrumentFundamental::class);
    }

    public function financialStatements(): HasMany
    {
        return $this->hasMany(InstrumentFinancialStatement::class);
    }

    public function earnings(): HasMany
    {
        return $this->hasMany(InstrumentEarning::class);
    }

    public function dividends(): HasMany
    {
        return $this->hasMany(InstrumentDividend::class);
    }

    public function exitProfiles(): HasMany
    {
        return $this->hasMany(InstrumentExitProfile::class);
    }
}
