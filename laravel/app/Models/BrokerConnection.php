<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BrokerConnection extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['credentials' => 'encrypted:array', 'trading_enabled' => 'boolean', 'emergency_stop' => 'boolean', 'last_connected_at' => 'datetime'];
    }

    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function orders(): HasMany { return $this->hasMany(BrokerOrder::class); }
}
