<?php
namespace App\Services;
use App\Repositories\InstrumentRepository;
class MarketService{
public function __construct(private InstrumentRepository $instruments){}
public function findSymbol(string $symbol){
return $this->instruments->findBySymbol($symbol);
}
}
