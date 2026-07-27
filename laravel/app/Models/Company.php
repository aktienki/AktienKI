<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Company extends Model
{
    protected $guarded = [];

    protected $casts = [
        'active' => 'boolean',
        'market_cap' => 'float',
        'enterprise_value' => 'float',
        'beta' => 'float',
        'last_profile_update' => 'datetime',
        'last_price_update' => 'datetime',
        'last_fundamental_update' => 'datetime',
        'meta' => 'array',
    ];

    public function stockIndex(): BelongsTo
    {
        return $this->belongsTo(StockIndex::class);
    }

    public function sectorRef(): BelongsTo
    {
        return $this->belongsTo(Sector::class, 'sector_id');
    }

    public function industryRef(): BelongsTo
    {
        return $this->belongsTo(Industry::class, 'industry_id');
    }

    public function indexMemberships(): HasMany
    {
        return $this->hasMany(IndexMembership::class);
    }

    public function fundamentals(): HasMany
    {
        return $this->hasMany(CompanyFundamental::class);
    }

    public function marketData(): HasMany
    {
        return $this->hasMany(MarketData::class);
    }

    public function featureStore(): HasMany
    {
        return $this->hasMany(FeatureStore::class);
    }

    public function predictions(): HasMany
    {
        return $this->hasMany(Prediction::class);
    }

    public function news(): HasMany
    {
        return $this->hasMany(News::class);
    }

    public function watchlistItems(): HasMany
    {
        return $this->hasMany(WatchlistItem::class);
    }

    public function portfolioPositions(): HasMany
    {
        return $this->hasMany(PortfolioPosition::class);
    }

    public function portfolioTransactions(): HasMany
    {
        return $this->hasMany(PortfolioTransaction::class);
    }

    public function priceAlerts(): HasMany
    {
        return $this->hasMany(PriceAlert::class);
    }

    public function latestPrediction()
    {
        return $this->hasOne(Prediction::class)->latestOfMany('prediction_date');
    }

    public function latestMarketData()
    {
        return $this->hasOne(MarketData::class)->latestOfMany('date');
    }
}
