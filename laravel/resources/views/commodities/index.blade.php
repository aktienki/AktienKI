<x-app-layout>
<div class="commodity-screener screener-page mx-auto max-w-[96rem] px-3 py-5 text-[var(--ak-text)] sm:px-5 lg:py-8">
    <header class="mb-3 flex items-center justify-between gap-3"><div><span class="text-[10px] font-black uppercase tracking-[.18em] text-cyan-400">{{ __('Service-Datenbank') }}</span><h1 class="text-3xl font-black tracking-tight">{{ __('Rohstoffscreener') }}</h1></div><span class="rounded-[10px] border border-cyan-400/30 bg-cyan-400/[.08] px-3 py-2 text-xs font-bold text-[var(--ak-muted)]">{{ $commodities->count() }} {{ __('Rohstoffe') }}</span></header>
    <form method="GET" class="mb-4 flex gap-2 rounded-xl border border-cyan-400/20 bg-[var(--ak-card)] p-2"><input name="q" value="{{ request('q') }}" placeholder="{{ __('Rohstoff oder Symbol') }}" class="ak-input h-10 flex-1 text-base"><button class="rounded-lg border border-cyan-400/35 bg-cyan-400/10 px-5 text-xs font-black text-cyan-300">{{ __('Suchen') }}</button></form>
    <section class="commodity-table ak-card overflow-hidden">
        <div class="commodity-table-head hidden lg:grid" aria-hidden="true">
            <span class="commodity-watchlist-head"><x-heroicon-o-star class="h-4 w-4" /></span><span>{{ __('Rohstoff') }}</span><span>{{ __('Kurs') }}</span><span>{{ __('Panel') }}</span><span>{{ __('Signal') }}</span><span>{{ __('Assessment') }}</span><span>{{ __('Risiko') }}</span><span>10T</span><span>20T</span><span>40T</span><span></span>
        </div>
    @forelse($commodities as $position=>$commodity)
        @php
            $returns=collect([10=>$commodity->return_10d,20=>$commodity->return_20d,40=>$commodity->return_40d]);
            $riskPercent=\App\Support\RiskScore::toPercent($commodity->risk);
            $riskLabel=\App\Support\QualityGrade::risk($riskPercent);
            $riskLevel=\App\Support\QualityGrade::riskLevel($riskPercent);
            $scoreColor=$commodity->score_percent>=75?'#34d399':($commodity->score_percent>=50?'#fbbf24':'#fb7185');
            $riskColor=$riskPercent===null?'#94a3b8':($riskPercent<=30?'#34d399':($riskPercent<=60?'#fbbf24':'#fb7185'));
            $icons=['XAU_EUR'=>'◆','XAUUSD'=>'◆','SILVER'=>'◇','WTI'=>'◉','BRENT'=>'◉','NATURAL_GAS'=>'♨','COPPER'=>'⬡','CORN'=>'❋','WHEAT'=>'❋','COFFEE'=>'●','COCOA'=>'●'];
            $chartPoints=collect($commodity->chart_points??[])->filter(fn($point)=>is_numeric($point->close??null))->values();
            $chartCloses=$chartPoints->pluck('close')->map(fn($value)=>(float)$value);
            $lastPrice=$chartCloses->last();
            $previousPrice=$chartCloses->count()>1?$chartCloses->get($chartCloses->count()-2):null;
            $priceChange=$lastPrice!==null&&$previousPrice!==null&&$previousPrice!=0?(($lastPrice/$previousPrice)-1)*100:null;
            $sparkMin=$chartCloses->isNotEmpty()?(float)$chartCloses->min():0;
            $sparkMax=$chartCloses->isNotEmpty()?(float)$chartCloses->max():1;
            $sparkRange=max(.000001,$sparkMax-$sparkMin);
            $miniChartPolyline=$chartCloses->count()>1?$chartCloses->map(fn($value,$index)=>sprintf('%.1f,%.1f',$index*88/max(1,$chartCloses->count()-1),24-(((float)$value-$sparkMin)/$sparkRange)*22))->implode(' '):'';
            $panelPercent=max(0,min(100,(int)round((float)$commodity->score_percent)));
            $panelDecile=max(1,min(10,(int)ceil($panelPercent/10)));
            $commodityWatchlistIds=$commodity->local_instrument_id?$watchlistMemberships->get($commodity->local_instrument_id,collect()):collect();
            $isOnWatchlist=$commodityWatchlistIds->isNotEmpty();
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
        <article data-ranking="{{ $position+1 }}" class="commodity-table-row screener-stock-card index-screener-card overflow-hidden p-3" x-data="{expanded:false}">
            <div class="commodity-desktop-row hidden lg:grid">
            @if($commodity->local_instrument_id)
                <x-screener.watchlist-picker :watchlists="$userWatchlists" :membership-ids="$commodityWatchlistIds" :instrument-id="$commodity->local_instrument_id" :active="$isOnWatchlist" class="screener-desktop-watchlist-column" />
            @else
                <span class="screener-desktop-watchlist-column text-[var(--ak-muted)]"><x-heroicon-o-star class="h-4 w-4" /></span>
            @endif
            <button type="button" class="screener-desktop-summary w-full items-center text-left lg:grid" @click="expanded=!expanded" :aria-expanded="expanded.toString()">
                <span class="screener-desktop-stock"><b>#{{ $position+1 }}</b><i>{{ $icons[$commodity->symbol]??'◆' }}</i><span><strong>{{ $commodity->name }}</strong><small>{{ $commodity->symbol }} · {{ $commodity->currency }}@if($lastPrice!==null) · {{ number_format($lastPrice,2,',','.') }}@endif</small></span></span>
                <span class="screener-desktop-price"><span><strong>{{ $lastPrice!==null?number_format($lastPrice,2,',','.'):'—' }} <em>{{ $commodity->currency }}</em></strong>@if($priceChange!==null)<b class="{{ $priceChange>=0?'text-emerald-400':'text-rose-400' }}">{{ ($priceChange>0?'+':'').number_format($priceChange,2,',','.').' %' }}</b>@endif</span><svg class="screener-price-sparkline {{ $miniChartPolyline===''?'invisible':'' }}" viewBox="0 0 88 26" preserveAspectRatio="none" aria-hidden="true"><polyline points="{{ $miniChartPolyline }}" fill="none" stroke="{{ ($priceChange??0)>=0?'#34d399':'#fb7185' }}" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" vector-effect="non-scaling-stroke" /></svg></span>
                <span class="screener-desktop-panel"><small>{{ __('Panel') }}</small><strong class="{{ $panelPercent>=80?'text-emerald-400':($panelPercent>=60?'text-amber-400':'text-rose-400') }}">{{ $panelPercent }}</strong><i>D{{ $panelDecile }}</i></span>
                <span class="screener-desktop-signal" data-signal="{{ strtolower($commodity->signal) }}"><strong>{{ $commodity->signal }}</strong><small>{{ __('Signal') }}</small></span>
                <span class="screener-desktop-grade screener-desktop-scale-grade">
                    <strong>{{ __('Bewertung') }} · {{ $commodity->score_label }}</strong>
                    <i class="screener-desktop-scale signal" style="--position:{{ number_format((float)$commodity->score_percent,2,'.','') }}%;--marker:{{ $scoreColor }}" role="meter" aria-label="{{ __('Signalqualität') }} {{ $commodity->score_label }}" aria-valuemin="0" aria-valuemax="100" aria-valuenow="{{ number_format((float)$commodity->score_percent,1,'.','') }}"><em></em></i>
                </span>
                <span class="screener-desktop-grade screener-desktop-scale-grade">
                    <strong>{{ __('Risiko') }} · {{ $riskLabel ?? '—' }}</strong>
                    <i class="screener-desktop-scale risk" style="--position:{{ number_format(100-(float)($riskPercent??0),2,'.','') }}%;--marker:{{ $riskColor }}" role="meter" aria-label="{{ __('Risiko') }} {{ $riskLabel ?? '—' }}" aria-valuemin="0" aria-valuemax="100" @if($riskPercent!==null) aria-valuenow="{{ number_format(100-$riskPercent,1,'.','') }}" @endif><em @if($riskPercent===null) hidden @endif></em></i>
                </span>
                <span class="screener-desktop-forecasts">@foreach($returns as $days=>$value)<i><small>{{ $days }}T</small><strong class="{{ $value===null?'text-slate-400':($value>=0?'text-emerald-400':'text-rose-400') }}">{{ $value===null?'—':(($value>0?'+':'').number_format($value,1,',','.').' %') }}</strong></i>@endforeach</span>
                <x-heroicon-o-chevron-down class="h-5 w-5 text-cyan-300 transition" x-bind:class="expanded&&'rotate-180'" />
            </button>
            </div>
            <button type="button" class="screener-mobile-summary screener-mobile-summary-v2 lg:hidden" @click="expanded=!expanded" :aria-expanded="expanded.toString()">
                <span class="sms-v2-head"><b>#{{ $position+1 }}</b><i>{{ $icons[$commodity->symbol]??'◆' }}</i><span><strong>{{ $commodity->name }}</strong><small>{{ $commodity->symbol }} · {{ $commodity->currency }}</small></span><x-heroicon-o-chevron-down class="h-4 w-4 text-cyan-300 transition" x-bind:class="expanded&&'rotate-180'" /></span>
                <span class="sms-v2-forecast"><strong data-signal="{{ strtolower($commodity->signal) }}">{{ $commodity->signal }}</strong>@foreach($returns as $days=>$value)<i><small>{{ $days }}T</small><b class="{{ $value===null?'text-slate-400':($value>=0?'text-emerald-400':'text-rose-400') }}">{{ $value===null?'—':(($value>0?'+':'').number_format($value,1,',','.').' %') }}</b></i>@endforeach</span>
                <span class="sms-v2-scales"><i><small>{{ __('Signalqualität') }} · {{ $commodity->score_label }}</small><span class="sms-v2-scale signal" style="--position:{{ $commodity->score_percent }}%;--marker:{{ $scoreColor }}"><em></em></span></i><i><small>{{ __('Risiko') }} · {{ $riskLevel??'—' }}</small><span class="sms-v2-scale risk" style="--position:{{ 100-($riskPercent??0) }}%;--marker:{{ $riskColor }}"><em @if($riskPercent===null) hidden @endif></em></span></i></span>
            </button>
            <div x-cloak x-show="expanded" x-collapse class="commodity-details">
                <section class="commodity-chart">
                    <header><span><b>Chart+</b><small>{{ $commodity->symbol }} · {{ __('6 Monate') }}</small></span><em>{{ $commodity->as_of ? __('Prognose vom :date',['date'=>\Illuminate\Support\Carbon::parse($commodity->as_of)->format('d.m.Y')]) : '' }}</em></header>
                    @if($chartLine)
                        <svg viewBox="0 0 600 130" preserveAspectRatio="none" role="img" aria-label="{{ __('Kursverlauf mit Prognose') }}">
                            <defs><linearGradient id="commodity-line-{{ $position }}"><stop stop-color="var(--ak-accent)"/><stop offset="1" stop-color="var(--ak-muted)"/></linearGradient>@foreach($forecastCoordinates as $forecastIndex=>$forecast)<pattern id="commodity-forecast-{{ $position }}-{{ $forecastIndex }}" width="7" height="7" patternUnits="userSpaceOnUse" patternTransform="rotate(35)"><line x1="0" y1="0" x2="0" y2="7" stroke="{{ $forecast['price'] >= $lastPrice ? '#22c55e' : '#ef4444' }}" stroke-width="2" stroke-opacity=".4"/></pattern>@endforeach</defs>
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
    @empty<div class="p-8 text-center text-sm text-[var(--ak-muted)]">{{ __('Keine freigegebenen Rohstoff-Predictions verfügbar.') }}</div>@endforelse
    </section>
</div>
<style>
:root[data-theme="light"] body:not(.welcome-background) .commodity-screener .commodity-table-head{grid-template-columns:42px minmax(360px,1fr) 190px 54px 78px 112px 112px repeat(3,62px) 30px!important;gap:.65rem!important;align-items:center!important}
:root[data-theme="light"] body:not(.welcome-background) .commodity-screener .commodity-table-row .screener-desktop-summary{grid-template-columns:minmax(360px,1fr) 190px 54px 78px 112px 112px repeat(3,62px) 30px!important;gap:.65rem!important;align-items:center!important}
:root[data-theme="light"] body:not(.welcome-background) .commodity-screener .commodity-table-head>span:not(:first-child){border-left:1px solid var(--ak-border)!important;padding-left:.65rem!important;text-align:center!important}
:root[data-theme="light"] body:not(.welcome-background) .commodity-screener .commodity-table .commodity-table-row{margin:0!important;border:0!important;border-bottom:1px solid var(--ak-border)!important;border-radius:0!important;background:var(--ak-card)!important;background-image:none!important;box-shadow:none!important;transform:none!important}
:root[data-theme="light"] body:not(.welcome-background) .commodity-screener .commodity-table .commodity-table-row:last-child{border-bottom:0!important}
:root[data-theme="light"] body:not(.welcome-background) .commodity-screener .commodity-table .commodity-table-row:hover{background:var(--ak-card-hover)!important;box-shadow:none!important;transform:none!important}
:root[data-theme="light"] body:not(.welcome-background) .commodity-screener .commodity-table-row .screener-desktop-summary{margin:0!important;width:100%!important;padding:.35rem 0!important;border:0!important;background:transparent!important;box-shadow:none!important}
:root[data-theme="light"] body:not(.welcome-background) .commodity-screener .commodity-table-row .screener-desktop-summary::before{inset:.55rem auto .55rem 0!important;width:3px!important}
.commodity-table{border:1.5px solid var(--ak-border-hover)!important;border-radius:var(--ak-card-radius)!important;background:var(--ak-card)!important;box-shadow:var(--ak-shadow)!important}.commodity-table-head,.commodity-table-row .screener-desktop-summary{grid-template-columns:minmax(320px,1fr) 105px 130px 105px minmax(255px,.8fr) 30px!important;gap:.65rem!important;align-items:center}.commodity-table-head{min-height:3.5rem;padding:.75rem 1rem;border-bottom:2px solid var(--ak-border-strong);background:var(--ak-table-head-background);color:var(--ak-table-head-text);font-size:.62rem;font-weight:950;letter-spacing:.12em;text-transform:uppercase}.commodity-table-head>span:not(:first-child){border-left:1px solid var(--ak-border);padding-left:.65rem}.commodity-horizon-head{display:grid;grid-template-columns:repeat(3,1fr);gap:.4rem}.commodity-horizon-head i{text-align:center;font-style:normal}.commodity-table-row{height:auto!important;border:0!important;border-bottom:1px solid var(--ak-border)!important;border-radius:0!important;background:var(--ak-card)!important;box-shadow:none!important}.commodity-table-row:last-child{border-bottom:0!important}.commodity-table-row:hover{background:var(--ak-card-hover)!important}.commodity-table-row .screener-desktop-summary{margin:0!important;width:100%!important;padding:.15rem 0!important;border:0!important;background:transparent!important}.commodity-table-row .screener-desktop-forecasts{display:grid;grid-template-columns:repeat(3,1fr);gap:.4rem}.commodity-table-row .screener-desktop-forecasts i{min-width:0;border:1px solid var(--ak-border);border-radius:var(--ak-inner-radius);background:var(--ak-surface-muted);padding:.45rem .3rem;text-align:center}.commodity-details{margin-top:.8rem;padding-top:.8rem;border-top:1px solid var(--ak-border)}.commodity-chart{width:100%;border:1px solid var(--ak-border);border-radius:var(--ak-inner-radius);background:var(--ak-surface-muted);padding:.7rem}.commodity-chart header{display:flex;align-items:center;justify-content:space-between;margin-bottom:.35rem}.commodity-chart header span{display:flex;align-items:baseline;gap:.55rem}.commodity-chart header b{color:var(--ak-text);font-size:.82rem}.commodity-chart header small,.commodity-chart header em{color:var(--ak-muted);font-size:.55rem;font-style:normal;font-weight:800}.commodity-chart svg{display:block;width:100%;height:10.5rem}.commodity-chart-empty{display:grid;height:8rem;place-items:center;color:var(--ak-muted);font-size:.7rem}.commodity-screener .screener-desktop-stock>i{color:var(--ak-text-soft);font-size:1.25rem}@media(max-width:767px){.commodity-screener{padding-inline:.45rem}.commodity-table{border:0!important;background:transparent!important;box-shadow:none!important}.commodity-table-row{margin-bottom:.65rem;border:1.5px solid var(--ak-border-hover)!important;border-radius:var(--ak-card-radius)!important;box-shadow:var(--ak-shadow)!important;padding:.45rem!important}.commodity-chart svg{height:8.5rem}.commodity-chart header em{display:none}}
@media(max-width:1023px){:root[data-theme="light"] body:not(.welcome-background) .commodity-screener .commodity-table{border:0!important;background:transparent!important;box-shadow:none!important}:root[data-theme="light"] body:not(.welcome-background) .commodity-screener .commodity-table .commodity-table-row{margin-bottom:.65rem!important;border:1.5px solid var(--ak-border-hover)!important;border-radius:var(--ak-card-radius)!important;box-shadow:var(--ak-shadow)!important;padding:.45rem!important}}
@media(min-width:1024px){
    .commodity-screener .commodity-table-head{grid-template-columns:42px minmax(360px,1fr) 190px 54px 78px 112px 112px repeat(3,62px) 30px!important}
    .commodity-screener .commodity-desktop-row{display:grid;grid-template-columns:42px minmax(0,1fr);align-items:stretch}
    .commodity-screener .commodity-table-row .screener-desktop-summary{grid-template-columns:minmax(360px,1fr) 190px 54px 78px 112px 112px repeat(3,62px) 30px!important;gap:.65rem!important}
    .commodity-screener .screener-desktop-watchlist-column{position:relative;z-index:35;display:grid!important;width:42px;height:100%;min-height:100%;align-self:stretch;justify-self:center;place-items:center;margin:0;padding:0;border-right:1px solid color-mix(in srgb,var(--ak-muted) 18%,transparent)}
    .commodity-screener .screener-desktop-watchlist-column>summary{align-self:center;justify-self:center;margin:auto}
    .commodity-screener .screener-desktop-panel{display:flex;flex-direction:column;align-items:center;justify-content:center;gap:.12rem;width:54px;min-width:54px}
    .commodity-screener .screener-desktop-panel>small{color:var(--ak-muted);font-size:.5rem;font-weight:800;text-transform:uppercase}.commodity-screener .screener-desktop-panel>strong{font-size:.74rem;font-weight:900}.commodity-screener .screener-desktop-panel>i{color:var(--ak-muted);font-size:.5rem;font-weight:700;font-style:normal}
    .commodity-screener .screener-desktop-scale-grade{display:grid!important;width:112px!important;min-width:112px!important;align-content:center;align-items:stretch!important;justify-self:stretch;gap:.48rem;padding:0 .25rem}
    .commodity-screener .screener-desktop-scale-grade>strong{overflow:hidden;color:var(--ak-text);font-size:.68rem;font-weight:850;line-height:1;text-align:left;text-overflow:ellipsis;white-space:nowrap}
    .commodity-screener .screener-desktop-scale-grade>.screener-desktop-scale{position:relative;display:block!important;box-sizing:border-box;width:102px!important;min-width:102px!important;max-width:102px!important;height:.34rem;align-self:center;flex:0 0 102px!important;border-radius:.12rem;font-style:normal}
    .commodity-screener .screener-desktop-scale::before{position:absolute;inset:0;border-radius:inherit;background:linear-gradient(90deg,#fb7185,#fbbf24 48%,#34d399);content:"";-webkit-mask:repeating-linear-gradient(90deg,#000 0 calc(10% - 2px),transparent calc(10% - 2px) 10%);mask:repeating-linear-gradient(90deg,#000 0 calc(10% - 2px),transparent calc(10% - 2px) 10%);opacity:.32}
    .commodity-screener .screener-desktop-scale>em{position:absolute;top:50%;left:clamp(5%,var(--position),95%);z-index:2;width:calc(10% - 2px);min-width:5px;height:.72rem;border:1px solid color-mix(in srgb,var(--marker,#22d3ee) 72%,white);border-radius:.14rem;background:var(--marker,#22d3ee);box-shadow:0 0 .35rem color-mix(in srgb,var(--marker,#22d3ee) 70%,transparent);transform:translate(-50%,-50%)}
    .commodity-screener .screener-desktop-signal>strong{box-sizing:border-box;width:72px!important;min-width:72px!important;max-width:72px!important;text-align:center}
    .commodity-screener .screener-desktop-forecasts>i{box-sizing:border-box;width:62px!important;min-width:62px!important;max-width:62px!important}
}
</style>
</x-app-layout>
