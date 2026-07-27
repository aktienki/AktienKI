<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SubscriptionPlan extends Model
{
    protected $guarded = [];

    protected $casts = [
        'price_monthly' => 'float',
        'price_yearly' => 'float',
        'has_premium_indices' => 'boolean',
        'has_advanced_signals' => 'boolean',
        'has_exports' => 'boolean',
        'has_email_alerts' => 'boolean',
        'active' => 'boolean',
        'features' => 'array',
        'meta' => 'array',
    ];

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }
}
