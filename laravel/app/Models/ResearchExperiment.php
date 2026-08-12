<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class ResearchExperiment extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'symbols' => 'array',
            'configuration' => 'array',
            'result' => 'array',
            'progress' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
