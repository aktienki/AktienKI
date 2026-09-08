<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class EntrySignalLifecycle extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'activated_at' => 'immutable_datetime',
            'expiry_market_date' => 'immutable_date',
            'expires_at' => 'immutable_datetime',
            'consumed_at' => 'immutable_datetime',
            'consumer_reference' => 'array',
            'closed_at' => 'immutable_datetime',
            'closed_effective_as_of' => 'immutable_datetime',
            'exit_as_of' => 'immutable_datetime',
            'exit_snapshot' => 'array',
        ];
    }

    public function decision(): BelongsTo
    {
        return $this->belongsTo(EntrySignalDecision::class, 'entry_signal_decision_id');
    }

    public function instrument(): BelongsTo
    {
        return $this->belongsTo(Instrument::class);
    }

    public function exitProfile(): BelongsTo
    {
        return $this->belongsTo(InstrumentExitProfile::class, 'instrument_exit_profile_id');
    }
}
