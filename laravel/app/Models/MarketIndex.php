<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MarketIndex extends Model
{
    protected $fillable=[
        'symbol','name','country','currency','description','is_active'
    ];

    protected $casts=['is_active'=>'boolean'];

    public function memberships(): HasMany
    {
        return $this->hasMany(IndexMembership::class);
    }
}
