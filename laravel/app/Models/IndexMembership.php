<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IndexMembership extends Model
{
    protected $guarded = [];

    protected $casts = [
        'joined_at_date' => 'date',
        'left_at_date' => 'date',
        'weight' => 'float',
        'active' => 'boolean',
        'meta' => 'array',
    ];

    public function stockIndex(): BelongsTo
    {
        return $this->belongsTo(StockIndex::class);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}
