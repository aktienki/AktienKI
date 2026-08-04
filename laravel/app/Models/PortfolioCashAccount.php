<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class PortfolioCashAccount extends Model
{
    protected $guarded = [];
    protected $casts = ['balance' => 'float', 'reserved_balance' => 'float'];
    public function portfolio(): BelongsTo { return $this->belongsTo(Portfolio::class); }
    public function ledgerEntries(): HasMany { return $this->hasMany(PortfolioCashLedger::class); }
}
