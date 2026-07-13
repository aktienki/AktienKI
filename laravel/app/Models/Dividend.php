<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class Dividend extends Model{protected $fillable=['instrument_id','ex_date','amount','currency'];protected $casts=['ex_date'=>'date','amount'=>'decimal:8'];}