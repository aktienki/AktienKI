<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EntrySignalAlert extends Model
{
    protected $guarded = [];

    protected $casts = ['triggered_at' => 'datetime', 'notified_at' => 'datetime'];
}
