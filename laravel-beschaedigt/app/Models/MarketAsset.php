<?php
namespace App\Models; use Illuminate\Database\Eloquent\Model; use Illuminate\Database\Eloquent\Relations\BelongsTo;
class MarketAsset extends Model { protected $fillable=['market_snapshot_id','symbol','name','category','price','change_percent','volume','signal','trend','score','observed_at','metadata']; protected $casts=['price'=>'float','change_percent'=>'float','volume'=>'float','score'=>'float','observed_at'=>'immutable_datetime','metadata'=>'array']; public function snapshot():BelongsTo{return $this->belongsTo(MarketSnapshot::class,'market_snapshot_id');} }
