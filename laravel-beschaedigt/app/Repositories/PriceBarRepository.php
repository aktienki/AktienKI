<?php
namespace App\Repositories;
use App\Models\PriceBar;
class PriceBarRepository{
public function latestForInstrument(int $instrumentId,int $limit=500){
return PriceBar::where('instrument_id',$instrumentId)
->orderByDesc('bar_time')->limit($limit)->get();
}
}
