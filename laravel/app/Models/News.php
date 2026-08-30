<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class News extends Model
{
    protected $table = 'news';

    protected $guarded = [];

    protected $casts = [
        'published_at' => 'datetime',
        'ai_analyzed_at' => 'datetime',
        'sentiment_score' => 'float',
        'relevance_score' => 'integer',
        'raw_data' => 'array',
    ];

    public function instrument(): BelongsTo
    {
        return $this->belongsTo(Instrument::class);
    }
}
