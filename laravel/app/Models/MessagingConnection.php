<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MessagingConnection extends Model
{
    protected $guarded = [];
    protected function casts(): array { return ['credentials' => 'encrypted:array', 'enabled' => 'boolean', 'last_sent_at' => 'datetime']; }
}
