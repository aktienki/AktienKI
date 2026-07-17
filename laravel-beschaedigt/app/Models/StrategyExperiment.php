<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StrategyExperiment extends Model
{
    protected $fillable = [
        'strategy_profile_id',
        'instrument_id',
        'owner_user_id',
        'public_id',
        'name',
        'status',
        'search_space',
        'algorithms',
        'selection_rules',
        'variants_total',
        'variants_completed',
        'variants_failed',
        'started_at',
        'finished_at',
        'error_message',
    ];

    protected $casts = [
        'search_space' => 'array',
        'algorithms' => 'array',
        'selection_rules' => 'array',
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
    ];

    public function strategyProfile(): BelongsTo
    {
        return $this->belongsTo(StrategyProfile::class);
    }

    public function instrument(): BelongsTo
    {
        return $this->belongsTo(Instrument::class);
    }

    public function variants(): HasMany
    {
        return $this->hasMany(StrategyExperimentVariant::class);
    }
}
