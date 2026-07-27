<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class MarketIndex extends Model
{
    use HasFactory;
    protected $table = 'market_indexes';
    protected $fillable = ['code','name','country','market','premium_level','sort_order','active'];
    protected $casts = ['active' => 'boolean', 'sort_order' => 'integer'];

    public function companies(): BelongsToMany
    {
        return $this->belongsToMany(Company::class, 'company_market_index')->withTimestamps();
    }

    public function scopeActive(Builder $query): Builder { return $query->where('active', true); }
    public function scopeCode(Builder $query, string $code): Builder { return $query->where('code', strtoupper(trim($code))); }
    public function scopePremiumLevel(Builder $query, string $level): Builder { return $query->where('premium_level', $level); }
}
