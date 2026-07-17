<?php
namespace App\Services\Market; use App\Models\MarketSnapshot; use Illuminate\Support\Facades\Cache;
final class MarketSnapshotService { public function latest(): ?MarketSnapshot { return Cache::remember('market.snapshot.latest', now()->addSeconds(60), fn()=>MarketSnapshot::query()->with(['assets'=>fn($q)=>$q->orderBy('category')->orderBy('symbol'),'sectors'=>fn($q)=>$q->orderBy('rank'),'statistics'])->latest('snapshot_time')->first()); } public function forget():void{Cache::forget('market.snapshot.latest');} }
