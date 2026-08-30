<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class PredictionPurchaseReminder extends Model { protected $guarded=[]; protected $casts=['purchased_at'=>'datetime','remind_on'=>'date','notified_at'=>'datetime','exit_rules'=>'array','active_stop_price'=>'float','score_exit_streak'=>'integer','score_exit_evaluated_at'=>'datetime','score_exit_triggered_at'=>'datetime','score_exit_details'=>'array']; }
