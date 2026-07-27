<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LegalDocument extends Model
{
    protected $guarded = [];

    protected $casts = [
        'active' => 'boolean',
        'published_at' => 'datetime',
        'meta' => 'array',
    ];

    public function acceptances(): HasMany
    {
        return $this->hasMany(UserLegalAcceptance::class);
    }

    public function userLegalAcceptances(): HasMany
    {
        return $this->acceptances();
    }
}
