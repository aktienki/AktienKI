@props(['portfolios', 'instrumentId', 'instrumentName', 'currency', 'price', 'score' => null, 'compact' => false, 'active' => false])
@php
    $allPortfolios = collect($portfolios);
    $instrumentCurrency = strtoupper(trim((string) $currency));
    $eligible = $allPortfolios->filter(fn($p) => strtoupper(trim((string) $p->currency)) === $instrumentCurrency || strtoupper(trim((string) $p->currency)) === 'EUR');
    $usesGermanListing = $instrumentCurrency !== 'EUR' && $eligible->contains(fn($p) => strtoupper(trim((string) $p->currency)) === 'EUR');
    $currencyMismatch = $eligible->isEmpty() && $allPortfolios->isNotEmpty();
@endphp
<div x-data="{open:false,qty:1,depot:@js((string) optional($eligible->first())->id),price:@js((float)($price ?? 0)), balances:@js($eligible->mapWithKeys(fn($p)=>[(string)$p->id=>(float)($p->available_capital ?? 0)])), fees:@js($eligible->mapWithKeys(function($p){$meta=is_string($p->meta??null)?(json_decode($p->meta,true)?:[]):(array)($p->meta??[]);return[(string)$p->id=>(float)data_get($meta,'automation.trade_cost',0)];}))}" class="relative" @click.stop>
    <button type="button" @click="open=true" title="{{ $active ? __('Im Musterdepot') : __('Ins Musterdepot kaufen') }}" class="inline-flex {{ $compact ? 'h-8 w-8' : 'h-10 w-10' }} items-center justify-center rounded-xl border transition {{ $active ? 'border-cyan-400/30 bg-cyan-400/[.08] text-cyan-300 hover:bg-cyan-400/15' : 'border-slate-500/15 bg-slate-500/[.04] text-slate-500/40 hover:text-cyan-300' }}"><x-heroicon-o-beaker class="{{ $compact ? 'h-4 w-4' : 'h-5 w-5' }}" /></button>
    <template x-teleport="body"><div x-cloak x-show="open" class="fixed inset-0 z-[150] grid place-items-center bg-slate-950/75 p-4 backdrop-blur-sm" @keydown.escape.window="open=false" @click.self="open=false">
      <div class="w-full max-w-md rounded-2xl border border-cyan-400/30 bg-[#0d1b2d] p-5 shadow-2xl">
        <div class="flex items-start justify-between"><div><p class="text-[10px] font-black uppercase tracking-[.16em] text-cyan-400">{{ __('Musterdepot') }}</p><h2 class="mt-1 text-xl font-black text-white">{{ __('Virtuellen Kauf simulieren') }}</h2><p class="mt-1 text-xs text-slate-400">{{ $instrumentName }}</p></div><button type="button" @click="open=false" class="text-slate-400"><x-heroicon-o-x-mark class="h-5 w-5"/></button></div>
        @if($eligible->isEmpty())
          <div class="mt-5 rounded-xl border border-amber-300/25 bg-amber-300/[.07] p-4 text-sm text-amber-100">
            @if($currencyMismatch)
              <p class="font-black">{{ __('Musterdepot vorhanden, aber die Währung passt nicht.') }}</p>
              <p class="mt-2 text-xs leading-5 text-slate-300">{{ __('Die Aktie wird in :currency gehandelt. Deine vorhandenen Depots verwenden :depots. Ohne Währungsumrechnung würde der Kaufwert falsch verbucht.', ['currency' => $instrumentCurrency ?: '—', 'depots' => $allPortfolios->pluck('currency')->filter()->unique()->implode(', ')]) }}</p>
              <div class="mt-3 space-y-1.5">
                @foreach($allPortfolios as $portfolio)
                  <div class="flex items-center justify-between rounded-lg border border-white/10 px-3 py-2 text-xs"><span>{{ $portfolio->name }}</span><span class="font-black text-amber-300">{{ $portfolio->currency }}</span></div>
                @endforeach
              </div>
            @else
              <p class="font-black">{{ __('Noch kein Musterdepot vorhanden.') }}</p>
            @endif
            <a href="{{ route('paper-depots.index', ['currency' => $instrumentCurrency]) }}" class="mt-3 inline-flex font-black text-cyan-300 underline">{{ __('Passendes Musterdepot anlegen') }}</a>
          </div>
        @else
        <form method="POST" :action="`{{ url('/musterdepots') }}/${depot}/instruments/{{ $instrumentId }}`" class="mt-5 space-y-4">@csrf
          @if($usesGermanListing)<div class="rounded-xl border border-cyan-400/20 bg-cyan-400/[.06] p-3 text-xs leading-5 text-cyan-100">{{ __('Bei einem EUR-Depot wird vor dem Kauf automatisch die deutsche EUR-Notierung über TwelveData gesucht und deren Livekurs verwendet.') }}</div>@endif
          <label class="grid gap-1.5 text-[10px] font-black uppercase text-slate-400">{{ __('Musterdepot') }}<select x-model="depot" class="ak-input h-11 text-sm normal-case">@foreach($eligible as $p)<option value="{{ $p->id }}">{{ $p->name }} · {{ number_format((float)($p->available_capital ?? 0),2,',','.') }} {{ $p->currency }}</option>@endforeach</select></label>
          <div class="grid grid-cols-2 gap-2"><div class="rounded-xl border border-cyan-400/20 bg-cyan-400/[.05] p-3"><small class="text-slate-400">{{ __('Verfügbar') }}</small><strong class="mt-1 block text-cyan-300" x-text="new Intl.NumberFormat('de-DE',{minimumFractionDigits:2}).format(balances[depot]||0)"></strong></div><div class="rounded-xl border border-amber-300/20 bg-amber-300/[.05] p-3"><small class="text-slate-400">{{ __('Aktueller Kurs') }}</small><strong class="mt-1 block text-amber-300">{{ is_numeric($price) ? number_format((float)$price,2,',','.') : '—' }}</strong></div></div>
          <label class="grid gap-1.5 text-[10px] font-black uppercase text-slate-400">{{ __('Stückzahl') }}<input name="quantity" x-model.number="qty" type="number" min="1" max="100000" required class="ak-input h-11 text-sm"></label>
          <div class="grid grid-cols-4 gap-2 text-center"><div><small class="text-slate-500">{{ __('Kaufwert') }}</small><b class="block text-white" x-text="new Intl.NumberFormat('de-DE',{minimumFractionDigits:2}).format(qty*price)"></b></div><div><small class="text-slate-500">{{ __('Kosten') }}</small><b class="block text-amber-300" x-text="new Intl.NumberFormat('de-DE',{minimumFractionDigits:2}).format(fees[depot]||0)"></b></div><div><small class="text-slate-500">{{ __('Danach') }}</small><b class="block" :class="balances[depot]-qty*price-(fees[depot]||0)>=0?'text-emerald-300':'text-rose-300'" x-text="new Intl.NumberFormat('de-DE',{minimumFractionDigits:2}).format((balances[depot]||0)-qty*price-(fees[depot]||0))"></b></div><div><small class="text-slate-500">{{ __('KI-Score') }}</small><b class="block text-cyan-300">{{ is_numeric($score) ? number_format((float)$score,0,',','.') : '—' }}</b></div></div>
          <button :disabled="qty<1{{ $usesGermanListing ? '' : ' || qty*price+(fees[depot]||0)>(balances[depot]||0)' }}" class="h-11 w-full rounded-xl bg-cyan-500/20 text-sm font-black text-cyan-200 disabled:cursor-not-allowed disabled:opacity-35">{{ __('Kauf bestätigen') }}</button>
        </form>@endif
      </div></div></template>
</div>
