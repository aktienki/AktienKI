<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

final class EntrySignalDecision extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'filters_passed' => 'boolean',
            'reason_codes' => 'array',
            'source_snapshot' => 'array',
            'filter_snapshot' => 'array',
            'source_as_of' => 'immutable_datetime',
            'source_market_date' => 'immutable_date',
            'evaluated_at' => 'immutable_datetime',
            'forecast_horizon_sessions' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function savedPredictionFilter(): BelongsTo
    {
        return $this->belongsTo(SavedPredictionFilter::class);
    }

    public function instrument(): BelongsTo
    {
        return $this->belongsTo(Instrument::class);
    }

    public function lifecycle(): HasOne
    {
        return $this->hasOne(EntrySignalLifecycle::class);
    }
}
