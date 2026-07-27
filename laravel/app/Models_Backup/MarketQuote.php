<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MarketQuote extends Model
{
    use HasFactory;

    protected $fillable = ['symbol','name','price','change','change_percent','day_high','day_low','previous_close','currency','quoted_at'];
    protected $casts = ['quoted_at' => 'datetime'];
}
