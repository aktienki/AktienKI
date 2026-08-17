<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CorporateEventImport extends Model
{
    protected $guarded = [];

    protected $casts = [
        'requested_from' => 'date',
        'requested_until' => 'date',
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
        'raw_payload' => 'array',
    ];

    public function events(): HasMany { return $this->hasMany(CorporateEvent::class, 'import_id'); }
}
