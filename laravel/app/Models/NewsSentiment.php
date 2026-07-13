<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class NewsSentiment extends Model{protected $fillable=['news_id','sentiment','confidence'];}