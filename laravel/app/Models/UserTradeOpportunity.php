<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class UserTradeOpportunity extends Model
{
    protected $fillable = [
        'user_id', 'instrument_id', 'prediction_id', 'status', 'notify_on_buy',
        'detected_at', 'expires_at', 'viewed_at', 'completed_at', 'snapshot',
    ];

    protected function casts(): array
    {
        return [
            'notify_on_buy' => 'boolean',
            'detected_at' => 'immutable_datetime',
            'expires_at' => 'immutable_datetime',
            'viewed_at' => 'immutable_datetime',
            'completed_at' => 'immutable_datetime',
            'snapshot' => 'array',
        ];
    }

    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function instrument(): BelongsTo { return $this->belongsTo(Instrument::class); }
    public function prediction(): BelongsTo { return $this->belongsTo(Prediction::class); }
}
