<?php
namespace App\Services;
use App\Models\Instrument;
class SearchService{
public function search(string $term,int $limit=20){
return Instrument::query()
->where('symbol','like',"%{$term}%")
->orWhere('name','like',"%{$term}%")
->limit($limit)->get();
}
}
