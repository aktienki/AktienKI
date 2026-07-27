<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ApplicationLog extends Model
{
    protected $guarded = [];

    protected $casts = [
        'logged_at' => 'datetime',
        'meta' => 'array',
    ];
}
