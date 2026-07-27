<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockPrice extends Model
{
    use HasFactory;

    protected $fillable = ['company_id','price_time','interval','open','high','low','close','adj_close','volume'];
    protected $casts = ['price_time' => 'datetime', 'volume' => 'integer'];

    public function company(): BelongsTo { return $this->belongsTo(Company::class); }
}
