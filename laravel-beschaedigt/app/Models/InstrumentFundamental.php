<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class InstrumentFundamental extends Model{protected $fillable=['instrument_id','snapshot_date','data','source'];protected $casts=['snapshot_date'=>'date','data'=>'array'];}