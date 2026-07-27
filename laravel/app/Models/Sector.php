<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Sector extends Model
{
    protected $guarded = [];

    protected $casts = [
        'active' => 'boolean',
    ];

    public function industries(): HasMany
    {
        return $this->hasMany(Industry::class);
    }

    public function companies(): HasMany
    {
        return $this->hasMany(Company::class);
    }
}
