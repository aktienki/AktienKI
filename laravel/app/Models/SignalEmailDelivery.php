<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SignalEmailDelivery extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'queued_at' => 'datetime',
            'sent_at' => 'datetime',
            'failed_at' => 'datetime',
        ];
    }
}
