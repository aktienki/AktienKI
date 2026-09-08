<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class ExternalBuyReview extends Model
{
    protected $guarded = [];

    protected $casts = [
        'triggered_at' => 'datetime',
        'positive_factors' => 'array',
        'risk_factors' => 'array',
        'key_findings' => 'array',
        'research_limitations' => 'array',
        'sources' => 'array',
        'request_identity' => 'array',
        'signal_scope' => 'array',
        'usage' => 'array',
        'pricing_snapshot' => 'array',
        'raw_response' => 'array',
        'started_at' => 'datetime',
        'researched_at' => 'datetime',
        'failed_at' => 'datetime',
    ];

    public function instrument(): BelongsTo
    {
        return $this->belongsTo(Instrument::class);
    }

    public function prediction(): BelongsTo
    {
        return $this->belongsTo(Prediction::class);
    }

    public function previousPrediction(): BelongsTo
    {
        return $this->belongsTo(Prediction::class, 'previous_prediction_id');
    }
}
