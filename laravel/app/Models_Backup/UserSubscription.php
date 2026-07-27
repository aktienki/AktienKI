<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserSubscription extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id','subscription_plan_id','status','starts_at','ends_at','trial_ends_at','provider','provider_subscription_id',
    ];

    protected $casts = ['starts_at' => 'datetime', 'ends_at' => 'datetime', 'trial_ends_at' => 'datetime'];

    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function plan(): BelongsTo { return $this->belongsTo(SubscriptionPlan::class, 'subscription_plan_id'); }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'active')->where(function (Builder $q) {
            $q->whereNull('ends_at')->orWhere('ends_at', '>', now());
        });
    }
}
