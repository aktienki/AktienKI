<x-app-layout>
<div class="commodity-screener screener-page mx-auto max-w-7xl px-3 py-5 text-[var(--ak-text)] sm:px-5 lg:py-8">
    <header class="mb-3 flex items-center justify-between gap-3"><div><span class="text-[10px] font-black uppercase tracking-[.18em] text-cyan-400">{{ __('Service-Datenbank') }}</span><h1 class="text-3xl font-black tracking-tight">{{ __('Rohstoffscreener') }}</h1></div><span class="rounded-[10px] border border-cyan-400/30 bg-cyan-400/[.08] px-3 py-2 text-xs font-bold text-[var(--ak-muted)]">{{ $commodities->count() }} {{ __('Rohstoffe') }}</span></header>
    <form method="GET" class="mb-4 flex gap-2 rounded-xl border border-cyan-400/20 bg-[var(--ak-card)] p-2"><input name="q" value="{{ request('q') }}" placeholder="{{ __('Rohstoff oder Symbol') }}" class="ak-input h-10 flex-1 text-base"><button class="rounded-lg border border-cyan-400/35 bg-cyan-400/10 px-5 text-xs font-black text-cyan-300">{{ __('Suchen') }}</button></form>
    <section class="grid gap-3">
    @forelse($commodities as $position=>$commodity)
        @php
            $returns=collect([10=>$commodity->return_10d,20=>$commodity->return_20d,40=>$commodity->return_40d]);
            $risk=is_numeric($commodity->risk)?max(1,min(5,(int)round($commodity->risk))):null;
            $riskPercent=$risk!==null?(($risk-1)/4)*100:0;
            $scoreColor=$commodity->score_percent>=75?'#34d399':($commodity->score_percent>=50?'#fbbf24':'#fb7185');
            $riskColor=$riskPercent<=30?'#34d399':($riskPercent<=60?'#fbbf24':'#fb7185');
            $icons=['XAU_EUR'=>'◆','XAUUSD'=>'◆','SILVER'=>'◇','WTI'=>'◉','BRENT'=>'◉','NATURAL_GAS'=>'♨','COPPER'=>'⬡','CORN'=>'❋','WHEAT'=>'❋','COFFEE'=>'●','COCOA'=>'●'];
            $chartPoints=collect($commodity->chart_points??[])->filter(fn($point)=>is_numeric($point->close??null))->values();
            $chartCloses=$chartPoints->pluck('close')->map(fn($value)=>(float)$value);
            $lastPrice=$chartCloses->last();
            $forecastPrices=$lastPrice!==null?$returns->filter(fn($value)=>is_numeric($value))->map(fn($value)=>$lastPrice*(1+((float)$value/100))):collect();
            $chartScale=$chartCloses->concat($forecastPrices);
            $chartMin=$chartScale->isNotEmpty()?(float)$chartScale->min():0;
            $chartMax=$chartScale->isNotEmpty()?(float)$chartScale->max():1;
            $chartRange=max(.000001,$chartMax-$chartMin);
            $chartY=fn(float $value):float=>112-(($value-$chartMin)/$chartRange)*94;
            $historyEnd=470;
            $chartLine=$chartCloses->count()>1?$chartCloses->map(fn($value,$index)=>sprintf('%.1f,%.1f',$index*$historyEnd/max(1,$chartCloses->count()-1),$chartY($value)))->implode(' '):'';
            $forecastCoordinates=$forecastPrices->map(fn($value,$days)=>['days'=>$days,'x'=>$historyEnd+((int)$days/40)*130,'y'=>$chartY((float)$value),'price'=>(float)$value])->values();
        @endphp
        <article class="screener-stock-card index-screener-card ak-card ak-dashboard-card overflow-hidden p-3" x-data="{expanded:false}">
            <button type="button" class="screener-desktop-summary hidden w-full items-center gap-4 text-left md:grid" @click="expanded=!expanded" :aria-expanded="expanded.toString()">
                <span class="screener-desktop-stock"><b>#{{ $position+1 }}</b><i>{{ $icons[$commodity->symbol]??'◆' }}</i><span><strong>{{ $commodity->name }}</strong><small>{{ $commodity->symbol }} · {{ $commodity->currency }}</small></span></span>
                <span class="screener-desktop-signal" data-signal="{{ strtolower($commodity->signal) }}"><strong>{{ $commodity->signal }}</strong><small>{{ __('Signal') }}</small></span>
                <span class="screener-desktop-grade"><strong>{{ $commodity->score_label }}</strong><small>{{ __('Signalqualität') }}</small></span>
                <span class="screener-desktop-grade"><strong>{{ $risk??'—' }}</strong><small>{{ __('Risiko') }}</small></span>
                <span class="screener-desktop-forecasts">@foreach($returns as $days=>$value)<i><small>{{ $days }}T</small><strong class="{{ $value===null?'text-slate-400':($value>=0?'text-emerald-400':'text-rose-400') }}">{{ $value===null?'—':(($value>0?'+':'').number_format($value,1,',','.').' %') }}</strong></i>@endforeach</span>
                <x-heroicon-o-chevron-down class="h-5 w-5 text-cyan-300 transition" x-bind:class="expanded&&'rotate-180'" />
            </button>
            <button type="button" class="screener-mobile-summary screener-mobile-summary-v2 md:hidden" @click="expanded=!expanded" :aria-expanded="expanded.toString()">
                <span class="sms-v2-head"><b>#{{ $position+1 }}</b><i>{{ $icons[$commodity->symbol]??'◆' }}</i><span><strong>{{ $commodity->name }}</strong><small>{{ $commodity->symbol }} · {{ $commodity->currency }}</small></span><x-heroicon-o-chevron-down class="h-4 w-4 text-cyan-300 transition" x-bind:class="expanded&&'rotate-180'" /></span>
                <span class="sms-v2-forecast"><strong data-signal="{{ strtolower($commodity->signal) }}">{{ $commodity->signal }}</strong>@foreach($returns as $days=>$value)<i><small>{{ $days }}T</small><b class="{{ $value===null?'text-slate-400':($value>=0?'text-emerald-400':'text-rose-400') }}">{{ $value===null?'—':(($value>0?'+':'').number_format($value,1,',','.').' %') }}</b></i>@endforeach</span>
                <span class="sms-v2-scales"><i><small>{{ __('Signalqualität') }} · {{ $commodity->score_label }}</small><span class="sms-v2-scale signal" style="--position:{{ $commodity->score_percent }}%;--marker:{{ $scoreColor }}"><em></em></span></i><i><small>{{ __('Risiko') }} · {{ $risk??'—' }}</small><span class="sms-v2-scale risk" style="--position:{{ 100-$riskPercent }}%;--marker:{{ $riskColor }}"><em></em></span></i></span>
            </button>
            <div x-cloak x-show="expanded" x-collapse class="commodity-details">
                <section class="commodity-chart">
                    <header><span><b>Chart+</b><small>{{ $commodity->symbol }} · {{ __('6 Monate') }}</small></span><em>{{ $commodity->as_of ? __('Prognose vom :date',['date'=>\Illuminate\Support\Carbon::parse($commodity->as_of)->format('d.m.Y')]) : '' }}</em></header>
                    @if($chartLine)
                        <svg viewBox="0 0 600 130" preserveAspectRatio="none" role="img" aria-label="{{ __('Kursverlauf mit Prognose') }}">
                            <defs><linearGradient id="commodity-line-{{ $position }}"><stop stop-color="#22d3ee"/><stop offset="1" stop-color="#14b8a6"/></linearGradient>@foreach($forecastCoordinates as $forecastIndex=>$forecast)<pattern id="commodity-forecast-{{ $position }}-{{ $forecastIndex }}" width="7" height="7" patternUnits="userSpaceOnUse" patternTransform="rotate(35)"><line x1="0" y1="0" x2="0" y2="7" stroke="{{ $forecast['price'] >= $lastPrice ? '#22c55e' : '#ef4444' }}" stroke-width="2" stroke-opacity=".4"/></pattern>@endforeach</defs>
                            @foreach([18,49,80,112] as $gridY)<line x1="0" y1="{{ $gridY }}" x2="600" y2="{{ $gridY }}" stroke="currentColor" stroke-opacity=".09"/>@endforeach
                            <polyline points="{{ $chartLine }}" fill="none" stroke="url(#commodity-line-{{ $position }})" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" vector-effect="non-scaling-stroke"/>
                            @php $prior=['x'=>$historyEnd,'y'=>$chartY((float)$lastPrice),'price'=>(float)$lastPrice]; @endphp
                            @foreach($forecastCoordinates as $forecastIndex=>$forecast)
                                <polygon points="{{ $prior['x'] }},{{ $prior['y'] }} {{ $forecast['x'] }},{{ $forecast['y'] }} {{ $forecast['x'] }},{{ max($prior['y'],$forecast['y']) }}" fill="url(#commodity-forecast-{{ $position }}-{{ $forecastIndex }})" stroke="{{ $forecast['price'] >= $prior['price'] ? '#22c55e' : '#ef4444' }}" stroke-width="1.5" stroke-linejoin="round" vector-effect="non-scaling-stroke"/>
                                <text x="{{ $forecast['x']-3 }}" y="{{ max(9,$forecast['y']-5) }}" text-anchor="end" fill="#fbbf24" font-size="7" font-weight="900">{{ $forecast['days'] }}T</text>
                                @php $prior=$forecast; @endphp
                            @endforeach
                        </svg>
                    @else<div class="commodity-chart-empty">{{ __('Der Kurschart ist momentan nicht verfügbar.') }}</div>@endif
                </section>
            </div>
        </article>
    @empty<div class="ak-card p-8 text-center text-sm text-[var(--ak-muted)]">{{ __('Keine freigegebenen Rohstoff-Predictions verfügbar.') }}</div>@endforelse
    </section>
</div>
<style>
.index-screener-card{height:auto!important}.commodity-details{margin-top:.8rem;padding-top:.8rem;border-top:1px solid rgba(34,211,238,.14)}.commodity-chart{width:100%;border:1px solid rgba(34,211,238,.14);border-radius:.7rem;padding:.7rem}.commodity-chart header{display:flex;align-items:center;justify-content:space-between;margin-bottom:.35rem}.commodity-chart header span{display:flex;align-items:baseline;gap:.55rem}.commodity-chart header b{color:#67e8f9;font-size:.82rem}.commodity-chart header small,.commodity-chart header em{color:var(--ak-muted);font-size:.55rem;font-style:normal;font-weight:800}.commodity-chart svg{display:block;width:100%;height:10.5rem}.commodity-chart-empty{display:grid;height:8rem;place-items:center;color:var(--ak-muted);font-size:.7rem}.commodity-screener .screener-desktop-stock>i{color:#67e8f9;font-size:1.25rem}@media(max-width:767px){.commodity-screener{padding-inline:.45rem}.index-screener-card{padding:.45rem!important}.commodity-chart svg{height:8.5rem}.commodity-chart header em{display:none}}
</style>
</x-app-layout>
