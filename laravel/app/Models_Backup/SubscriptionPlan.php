<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SubscriptionPlan extends Model
{
    use HasFactory;

    protected $fillable = ['code','name','level','price_cents','currency','features','allowed_index_codes','active'];
    protected $casts = ['features' => 'array', 'allowed_index_codes' => 'array', 'active' => 'boolean', 'price_cents' => 'integer'];

    public function subscriptions(): HasMany { return $this->hasMany(UserSubscription::class); }

    public function scopeActive(Builder $query): Builder { return $query->where('active', true); }
    public function scopeCode(Builder $query, string $code): Builder { return $query->where('code', strtolower(trim($code))); }

    public function allowsIndex(?string $indexCode): bool
    {
        if (! $indexCode) return false;
        $codes = $this->allowed_index_codes ?? [];
        return in_array('*', $codes, true) || in_array(strtoupper($indexCode), array_map('strtoupper', $codes), true);
    }

    public function getPriceEuroAttribute(): float
    {
        return $this->price_cents / 100;
    }
}
