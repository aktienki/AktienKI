<?php
namespace App\Repositories;
use App\Models\Instrument;
class InstrumentRepository{
public function findBySymbol(string $symbol):?Instrument{
return Instrument::where('symbol',$symbol)->first();
}
public function active(){
return Instrument::where('is_active',true);
}
}
