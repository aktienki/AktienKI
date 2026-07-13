<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StrategyProfile extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'owner_user_id','code','name','description','scope','status',
        'target_type','target_horizon_days','interval','history_years',
        'retraining_interval_days','configuration','allowed_algorithms',
        'version','is_active',
    ];

    protected $casts = [
        'configuration' => 'array',
        'allowed_algorithms' => 'array',
        'is_active' => 'boolean',
    ];

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }

    public function instruments(): HasMany
    {
        return $this->hasMany(StrategyProfileInstrument::class);
    }
}
