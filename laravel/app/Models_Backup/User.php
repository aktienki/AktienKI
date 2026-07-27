<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable implements MustVerifyEmail
{
    use HasFactory, Notifiable;

    protected $fillable = [
        'name','email','password','role','subscription','subscription_expires_at','newsletter_enabled','preferred_language','is_active','last_login_at',
    ];

    protected $hidden = ['password','remember_token'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'newsletter_enabled' => 'boolean',
            'subscription_expires_at' => 'datetime',
            'is_active' => 'boolean',
            'last_login_at' => 'datetime',
        ];
    }

    public function subscriptions(): HasMany { return $this->hasMany(UserSubscription::class); }
    public function activeSubscription(): HasOne { return $this->hasOne(UserSubscription::class)->where('status', 'active')->latestOfMany(); }
    public function watchlists(): HasMany { return $this->hasMany(Watchlist::class); }
    public function portfolios(): HasMany { return $this->hasMany(Portfolio::class); }

    public function isAdmin(): bool { return $this->role === 'admin'; }

    public function currentPlan(): ?SubscriptionPlan
    {
        return $this->activeSubscription?->plan
            ?? SubscriptionPlan::where('code', $this->subscription ?: 'free')->first();
    }

    public function canAccessIndex(string $indexCode): bool
    {
        if ($this->isAdmin()) return true;
        return (bool) $this->currentPlan()?->allowsIndex($indexCode);
    }
}
