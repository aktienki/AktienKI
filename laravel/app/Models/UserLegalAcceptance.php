<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserLegalAcceptance extends Model
{
    protected $table = 'user_legal_acceptances';

    protected $guarded = [];

    protected $casts = [
        'accepted' => 'boolean',
        'accepted_at' => 'datetime',
        'meta' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function legalDocument(): BelongsTo
    {
        return $this->belongsTo(LegalDocument::class);
    }

    public function document(): BelongsTo
    {
        return $this->legalDocument();
    }
}
