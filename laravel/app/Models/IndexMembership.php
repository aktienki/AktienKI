<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IndexMembership extends Model
{
    protected $fillable=[
        'market_index_id','instrument_id','weight','added_at','removed_at'
    ];

    protected $casts=[
        'weight'=>'decimal:6',
        'added_at'=>'date',
        'removed_at'=>'date'
    ];

    public function index(): BelongsTo
    {
        return $this->belongsTo(MarketIndex::class,'market_index_id');
    }

    public function instrument(): BelongsTo
    {
        return $this->belongsTo(Instrument::class);
    }
}
