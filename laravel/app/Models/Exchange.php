<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Exchange extends Model
{
    protected $fillable=[
        'code','mic','name','country','currency','timezone','is_active'
    ];

    protected $casts=[
        'is_active'=>'boolean'
    ];

    public function instruments(): HasMany
    {
        return $this->hasMany(Instrument::class);
    }
}
