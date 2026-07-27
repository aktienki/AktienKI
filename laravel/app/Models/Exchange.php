<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Exchange extends Model
{
    protected $guarded = [];

    protected $casts = [
        'active' => 'boolean',
    ];
}
