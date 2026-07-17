<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PriceBar extends Model
{
    protected $fillable=[
        'instrument_id','interval','bar_time','open','high','low','close',
        'adjusted_close','volume','source'
    ];

    protected $casts=[
        'bar_time'=>'datetime',
        'open'=>'decimal:10',
        'high'=>'decimal:10',
        'low'=>'decimal:10',
        'close'=>'decimal:10',
        'adjusted_close'=>'decimal:10',
        'volume'=>'decimal:4',
    ];

    public function instrument(): BelongsTo
    {
        return $this->belongsTo(Instrument::class);
    }
}
