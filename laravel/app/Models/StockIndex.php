<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StockIndex extends Model
{
    protected $guarded = [];

    protected $casts = [
        'active' => 'boolean',
        'meta' => 'array',
    ];

    public function companies(): HasMany
    {
        return $this->hasMany(Company::class);
    }

    public function memberships(): HasMany
    {
        return $this->hasMany(IndexMembership::class);
    }
}
