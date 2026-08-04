<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Portfolio extends Model
{
    protected $guarded = [];

    protected $casts = [
        'is_default' => 'boolean',
        'active' => 'boolean',
        'meta' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function positions(): HasMany
    {
        return $this->hasMany(PortfolioPosition::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(PortfolioTransaction::class);
    }

    public function cashAccount(): HasOne
    {
        return $this->hasOne(PortfolioCashAccount::class);
    }

    public function strategies(): BelongsToMany
    {
        return $this->belongsToMany(SavedPredictionFilter::class, 'portfolio_strategy_assignments')
            ->withPivot(['enabled', 'priority', 'capital_weight', 'settings'])
            ->withTimestamps()
            ->wherePivot('enabled', true)
            ->orderByPivot('priority')
            ->orderBy('saved_prediction_filters.name');
    }
}
