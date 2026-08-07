<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BrokerOrder extends Model
{
    protected $guarded = [];
    protected function casts(): array { return ['request_payload' => 'array', 'response_payload' => 'array', 'submitted_at' => 'datetime']; }
    public function connection(): BelongsTo { return $this->belongsTo(BrokerConnection::class, 'broker_connection_id'); }
}
