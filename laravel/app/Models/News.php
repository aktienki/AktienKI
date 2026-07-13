<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class News extends Model{protected $fillable=['instrument_id','headline','summary','url','source','published_at'];protected $casts=['published_at'=>'datetime'];}