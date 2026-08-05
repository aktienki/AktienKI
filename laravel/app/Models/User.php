<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $guarded = [];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'active' => 'boolean',
            'is_admin' => 'boolean',

            'accepted_terms' => 'boolean',
            'accepted_terms_at' => 'datetime',
            'accepted_privacy' => 'boolean',
            'accepted_privacy_at' => 'datetime',
            'accepted_risk_notice' => 'boolean',
            'accepted_risk_notice_at' => 'datetime',
            'accepted_cookie_notice' => 'boolean',
            'accepted_cookie_notice_at' => 'datetime',

            'legal_accepted' => 'boolean',
            'legal_accepted_at' => 'datetime',

            'trial_ends_at' => 'datetime',
            'subscription_ends_at' => 'datetime',
            'tariff_ends_at' => 'datetime',
            'last_login_at' => 'datetime',

            'preferences' => 'array',
            'meta' => 'array',
            'risk_profile' => 'array',
        ];
    }

    public function watchlists(): HasMany
    {
        return $this->hasMany(Watchlist::class);
    }

    public function portfolios(): HasMany
    {
        return $this->hasMany(Portfolio::class);
    }

    public function communityPosts(): HasMany
    {
        return $this->hasMany(CommunityPost::class);
    }

    public function savedPredictionFilters(): HasMany
    {
        return $this->hasMany(SavedPredictionFilter::class);
    }

    public function qualityGateProfile(): HasOne
    {
        return $this->hasOne(UserQualityGateProfile::class);
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    public function legalAcceptances(): HasMany
    {
        return $this->hasMany(UserLegalAcceptance::class);
    }

    public function priceAlerts(): HasMany
    {
        return $this->hasMany(PriceAlert::class);
    }

    public function notificationsLog(): HasMany
    {
        return $this->hasMany(NotificationLog::class);
    }

    public function contactMessages(): HasMany
    {
        return $this->hasMany(ContactMessage::class);
    }

    public function auditLogs(): HasMany
    {
        return $this->hasMany(AuditLog::class);
    }
}
