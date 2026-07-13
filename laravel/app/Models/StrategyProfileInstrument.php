<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StrategyProfileInstrument extends Model
{
    protected $fillable = [
        'strategy_profile_id','instrument_id','role','alias','parameters','is_enabled',
    ];

    protected $casts = [
        'parameters' => 'array',
        'is_enabled' => 'boolean',
    ];

    public function strategyProfile(): BelongsTo
    {
        return $this->belongsTo(StrategyProfile::class);
    }

    public function instrument(): BelongsTo
    {
        return $this->belongsTo(Instrument::class);
    }
}
