<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ModelRun extends Model
{
    protected $fillable = ['run_uuid','type','status','started_at','finished_at','companies_total','companies_success','companies_failed','metrics','error'];
    protected $casts = ['started_at'=>'datetime','finished_at'=>'datetime','metrics'=>'array'];
}
