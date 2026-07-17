<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class Split extends Model{protected $fillable=['instrument_id','split_date','ratio_from','ratio_to'];}