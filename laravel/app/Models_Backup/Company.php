<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Company extends Model
{
    use HasFactory;

    protected $fillable = [
        'symbol','isin','wkn','name','short_name','exchange','country','currency','sector','industry','active',
    ];

    protected $casts = ['active' => 'boolean'];

    public function profile(): HasOne { return $this->hasOne(CompanyProfile::class); }
    public function metrics(): HasMany { return $this->hasMany(CompanyMetric::class); }
    public function predictions(): HasMany { return $this->hasMany(Prediction::class); }
    public function latestPrediction(): HasOne { return $this->hasOne(Prediction::class)->latestOfMany('prediction_date'); }
    public function trainedModels(): HasMany { return $this->hasMany(TrainedModel::class); }
    public function features(): HasMany { return $this->hasMany(FeatureStore::class); }
    public function watchlistItems(): HasMany { return $this->hasMany(WatchlistItem::class); }
    public function portfolioPositions(): HasMany { return $this->hasMany(Portfolio::class); }
    public function prices(): HasMany { return $this->hasMany(StockPrice::class); }

    public function marketIndexes(): BelongsToMany
    {
        return $this->belongsToMany(MarketIndex::class, 'company_market_index')->withTimestamps();
    }

    public function news(): BelongsToMany
    {
        return $this->belongsToMany(News::class, 'company_news')->withTimestamps();
    }

    public function scopeActive(Builder $query): Builder { return $query->where('active', true); }
    public function scopeSymbol(Builder $query, string $symbol): Builder { return $query->where('symbol', strtoupper(trim($symbol))); }
    public function scopeSector(Builder $query, string $sector): Builder { return $query->where('sector', $sector); }
    public function scopeCountry(Builder $query, string $country): Builder { return $query->where('country', strtoupper($country)); }

    public function getDisplayNameAttribute(): string
    {
        return $this->short_name ?: $this->name;
    }

    public function getSlugAttribute(): string
    {
        return str($this->symbol . '-' . $this->name)->slug()->toString();
    }
}
