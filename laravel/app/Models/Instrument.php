<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Instrument extends Model
{
    use SoftDeletes;

    protected $fillable=[
        'exchange_id','type','symbol','provider_symbol','isin',
        'name','short_name','country','currency',
        'sector','industry','market_cap',
        'is_active','is_tradeable','meta'
    ];

    protected $casts=[
        'meta'=>'array',
        'is_active'=>'boolean',
        'is_tradeable'=>'boolean',
        'market_cap'=>'decimal:2'
    ];

    public function exchange(): BelongsTo
    {
        return $this->belongsTo(Exchange::class);
    }

    public function priceBars(): HasMany
    {
        return $this->hasMany(PriceBar::class);
    }
}
