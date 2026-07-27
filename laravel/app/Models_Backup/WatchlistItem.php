<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WatchlistItem extends Model
{
    use HasFactory;

    protected $fillable = ['watchlist_id','company_id'];

    public function watchlist(): BelongsTo { return $this->belongsTo(Watchlist::class); }
    public function company(): BelongsTo { return $this->belongsTo(Company::class); }
}
