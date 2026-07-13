<?php
namespace App\Repositories;
use App\Models\Exchange;
class ExchangeRepository{
public function all(){
return Exchange::orderBy('name')->get();
}
}
