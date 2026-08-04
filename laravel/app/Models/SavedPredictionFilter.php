<?php

namespace App\Models;

use App\Enums\PlanLevel;
use App\Services\PlanAccessService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class SavedPredictionFilter extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'filters' => 'array',
            'email_notification_enabled' => 'boolean',
            'automatic_portfolio_enabled' => 'boolean',
            'published_at' => 'datetime',
        ];
    }

    public function scopeAvailableTo(Builder $query, User $user): Builder
    {
        return $query->where(function (Builder $visible) use ($user): void {
            $visible->where('user_id', $user->id);
            if (app(PlanAccessService::class)->allows($user, PlanLevel::Pro)) {
                $visible->orWhere('visibility', 'pro_public');
            }
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function sourceStrategy(): BelongsTo
    {
        return $this->belongsTo(self::class, 'source_strategy_id');
    }

    public function portfolio(): BelongsTo
    {
        return $this->belongsTo(Portfolio::class);
    }

    public function watchlist(): BelongsTo
    {
        return $this->belongsTo(Watchlist::class);
    }

    public function portfolios(): BelongsToMany
    {
        return $this->belongsToMany(Portfolio::class, 'portfolio_strategy_assignments')
            ->withPivot(['enabled', 'priority', 'capital_weight', 'settings'])
            ->withTimestamps();
    }
}
