<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SavedPredictionFilter extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['filters' => 'array', 'email_notification_enabled' => 'boolean'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function portfolio(): BelongsTo
    {
        return $this->belongsTo(Portfolio::class);
    }

    public function watchlist(): BelongsTo
    {
        return $this->belongsTo(Watchlist::class);
    }
}
