<?php
namespace App\Models; use Illuminate\Database\Eloquent\Model; use Illuminate\Database\Eloquent\Relations\BelongsTo;
class SectorSnapshot extends Model { protected $fillable=['market_snapshot_id','sector','average_return','average_score','buy_ratio','sell_ratio','trend','rank','companies_count','metadata']; protected $casts=['average_return'=>'float','average_score'=>'float','buy_ratio'=>'float','sell_ratio'=>'float','metadata'=>'array']; public function snapshot():BelongsTo{return $this->belongsTo(MarketSnapshot::class,'market_snapshot_id');} }
