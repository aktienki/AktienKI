<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AktienkiJob extends Model
{
    use HasFactory;

    protected $table = 'aktienki_jobs';
    protected $fillable = ['type','status','payload','error','started_at','finished_at'];
    protected $casts = ['payload' => 'array', 'started_at' => 'datetime', 'finished_at' => 'datetime'];
}
