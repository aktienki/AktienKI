<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SystemRun extends Model
{
    use HasFactory;

    protected $fillable = ['run_type','status','started_at','finished_at','processed_count','error_count','message','meta'];
    protected $casts = ['started_at' => 'datetime', 'finished_at' => 'datetime', 'meta' => 'array'];
}
