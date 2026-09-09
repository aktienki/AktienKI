<x-app-layout>
<div id="aggregate-screener" class="index-screener sector-screener screener-page mx-auto max-w-7xl px-3 py-5 text-[var(--ak-text)] sm:px-5 lg:py-8">
    <header class="mb-3 flex items-center justify-between gap-3">
        <h1 class="text-3xl font-black tracking-tight">{{ __('Sektorenscreener') }}</h1>
        <span class="rounded-[10px] border border-[var(--ak-border)] bg-[var(--ak-card)] px-3 py-2 text-xs font-bold text-[var(--ak-muted)]">{{ $sectors->count() }} {{ __('Sektoren') }}</span>
    </header>
    @if($isFreeRegional)
        <div class="mb-4 rounded-xl border border-amber-400/25 bg-amber-400/[.06] px-4 py-3 text-xs text-[var(--ak-muted)]"><b class="mr-2 text-amber-500">FREE</b>{{ __('Sektorwerte aus deinem regionalen Top-100-Portfolio (:country).',['country'=>$regionalCountry]) }}</div>
    @endif
    <section x-data="{filtersOpen:false}" class="screener-filter-shell mb-5 overflow-hidden rounded-xl border border-[var(--ak-border)] bg-[var(--ak-card)] shadow-[var(--ak-shadow)]">
        <button type="button" @click="filtersOpen=!filtersOpen" :aria-expanded="filtersOpen" class="flex h-10 w-full items-center justify-between border-0 bg-transparent px-4 text-xs font-black text-[var(--ak-text-soft)] shadow-none"><span class="inline-flex items-center gap-2"><x-heroicon-o-adjustments-horizontal class="h-4 w-4"/>{{ __('Filter anzeigen') }}</span><x-heroicon-o-chevron-down class="h-4 w-4 transition" x-bind:class="filtersOpen&&'rotate-180'"/></button>
        <form x-cloak x-show="filtersOpen" method="GET" class="flex gap-2 border-0 border-t border-[var(--ak-border)] bg-[var(--ak-card)] p-3 shadow-none"><input name="q" value="{{ request('q') }}" oninput="clearTimeout(this._t);this._t=setTimeout(()=>this.form.requestSubmit(),500)" placeholder="{{ __('Sektor suchen') }}" class="ak-input h-10 flex-1 text-sm"><a href="{{ route('sectors.index') }}" class="inline-flex h-10 items-center rounded-lg border border-[var(--ak-border)] px-4 text-xs font-black text-[var(--ak-muted)]">{{ __('Reset') }}</a></form>
    </section>

    <section class="sector-index-grid grid gap-3 md:grid-cols-2 xl:grid-cols-3">
    @forelse($sectors as $position=>$sector)
        @php
            $forecasts=collect([10=>$sector->average_expected_return_10d,20=>$sector->average_expected_return_20d,40=>$sector->average_expected_return_40d])->map(fn($v)=>is_numeric($v)?(float)$v:null);
            $scoreTen=is_numeric($sector->average_score)?\App\Support\AiScore::toTen($sector->average_score):null;
            $scorePct=$scoreTen===null?0:max(0,min(100,$scoreTen*10));
            $riskRaw=is_numeric($sector->average_risk)?(float)$sector->average_risk:null;
            $riskPct=$riskRaw===null?null:max(0,min(100,$riskRaw<=1?$riskRaw*100:($riskRaw<=10?$riskRaw*10:$riskRaw)));
            $scoreLabel=\App\Support\QualityGrade::fromPercent($scorePct)??'—';
            $riskLabel=\App\Support\QualityGrade::riskLevel($riskPct)??'—';
            $scoreColor=$scorePct>=75?'#34d399':($scorePct>=50?'#fbbf24':'#fb7185');
            $riskColor=$riskPct===null?'#64748b':($riskPct<=30?'#34d399':($riskPct<=60?'#fbbf24':'#fb7185'));
            $signal=$forecasts[20]===null?'WATCH':($forecasts[20]>=2?'BUY':($forecasts[20]<0?'SELL':'WATCH'));
            $points=collect($sector->etf_chart_points??[])->filter(fn($p)=>is_numeric(data_get($p,'close')))->values();
            $min=$points->min(fn($p)=>(float)data_get($p,'close'));$max=$points->max(fn($p)=>(float)data_get($p,'close'));$range=max(.000001,(float)$max-(float)$min);
            $line=$points->count()>1?$points->map(fn($p,$i)=>sprintf('%.1f,%.1f',$i*600/($points->count()-1),100-(((float)data_get($p,'close')-(float)$min)/$range)*82))->implode(' '):'';
            $comment=$sectorComments->first(fn($item)=>mb_strtolower(trim((string)($item['sector']??'')))===mb_strtolower(trim((string)$sector->sector)));
            $info=$comment['summary']??$sector->assessment??$sector->description;
        @endphp
        <article class="index-tile ak-card ak-dashboard-card overflow-hidden p-3" data-signal="{{ strtolower($signal) }}" x-data="{expanded:false}">
            <button type="button" class="index-tile-summary w-full" @click="expanded=!expanded" :aria-expanded="expanded.toString()">
                <span class="index-tile-head">
                    <i class="index-tile-flag"><x-sector-icon :sector="$sector->sector" class="h-5 w-5"/></i>
                    <span><strong class="index-tile-name">{{ __($sector->sector) }}</strong><small class="index-tile-sym">#{{ $position+1 }} · {{ (int)$sector->stocks_count }} {{ __('Aktien') }}</small></span>
                    <x-heroicon-o-chevron-down class="index-tile-chev h-4 w-4 transition" x-bind:class="expanded&&'rotate-180'"/>
                </span>
                <span class="index-tile-metrics">
                    <i class="index-tile-signal" data-signal="{{ strtolower($signal) }}"><small>{{ __('Signal') }}</small><b>{{ $signal }}</b></i>
                    <i><small>{{ __('Bewertung') }}</small><b style="color:{{ $scoreColor }}">{{ $scoreLabel }}</b></i>
                    <i><small>{{ __('Risiko') }}</small><b style="color:{{ $riskColor }}">{{ $riskLabel }}</b></i>
                </span>
                <span class="index-tile-forecasts"><span class="index-tile-forecast-label">{{ __('Ausblick') }}</span>@foreach($forecasts as $days=>$value)<i><small>{{ $days }}T</small><b class="{{ $value===null?'text-slate-400':($value>=0?'text-emerald-400':'text-rose-400') }}">{{ $value===null?'—':sprintf('%+.1f%%',$value) }}</b></i>@endforeach</span>
            </button>
            <div x-cloak x-show="expanded" x-collapse class="index-card-details index-tile-details">
                <section class="index-card-chart"><header><span>{{ __('Chart · 1 Jahr') }}</span><b>{{ __($sector->sector) }}</b></header>@if($line)<svg viewBox="0 0 600 118" preserveAspectRatio="none"><polyline points="{{ $line }}" fill="none" stroke="var(--ak-accent)" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"/></svg>@else<p>{{ __('Der Kurschart ist momentan nicht verfügbar.') }}</p>@endif</section>
                <section class="index-card-copy"><h3>{{ __('Marktausblick') }}</h3><p>{{ $info?:__('Für diesen Sektor liegt derzeit keine zusätzliche Marktanalyse vor.') }}</p><dl><div><dt>{{ __('Ø Konfidenz') }}</dt><dd>{{ is_numeric($sector->average_confidence)?number_format((float)$sector->average_confidence,0,',','.').' %':'—' }}</dd></div><div><dt>{{ __('Ø Hit-Rate') }}</dt><dd>{{ is_numeric($sector->average_hit_rate)?number_format((float)$sector->average_hit_rate,1,',','.').' %':'—' }}</dd></div><div><dt>{{ __('Aktien') }}</dt><dd>{{ (int)$sector->stocks_count }}</dd></div></dl></section>
                <section class="index-card-members"><h3>{{ __('Führende Aktien') }}</h3>@forelse(collect($sector->top_stocks??[])->take(3) as $member)<a href="{{ route('stocks.show',['symbol'=>$member->symbol]) }}"><span>{{ \App\Support\CountryFlag::emoji($member->country) }} {{ $member->name?:$member->symbol }}</span><b>{{ $member->personalized_signal??'—' }}</b></a>@empty<p>{{ __('Keine Aktien verfügbar.') }}</p>@endforelse<a class="index-card-all" href="{{ route('stocks.index',['sector'=>$sector->sector]) }}">{{ __('Alle Sektoraktien') }} →</a></section>
            </div>
        </article>
    @empty
        <div class="ak-card p-8 text-center text-sm text-[var(--ak-muted)] md:col-span-2 xl:col-span-3">{{ __('Keine Sektoren gefunden.') }}</div>
    @endforelse
    </section>
</div>
<style>
#aggregate-screener{min-height:calc(100dvh - 73px)}
.sector-index-grid{align-items:start}.index-tile{--index-accent:#b45309;height:auto!important;border:1.5px solid var(--ak-border-hover)!important;border-radius:var(--ak-card-radius)!important;background:var(--ak-card)!important;box-shadow:var(--ak-shadow)!important;transition:.18s ease}.index-tile[data-signal=buy]{--index-accent:#047857}.index-tile[data-signal=sell]{--index-accent:#be123c}.index-tile:hover{border-color:var(--ak-border-strong)!important;box-shadow:var(--ak-shadow-hover)!important;transform:translateY(-1px)}.index-tile-summary{display:flex;flex-direction:column;gap:.7rem;text-align:left}.index-tile-head{display:flex;align-items:center;gap:.7rem;padding:.8rem .9rem;border-bottom:1px solid var(--ak-border-hover);background:var(--ak-index-card-head-background)}.index-tile-flag{display:grid;width:2.25rem;height:2.25rem;flex:0 0 2.25rem;place-items:center;color:var(--ak-text-soft)}.index-tile-head>span{min-width:0;flex:1}.index-tile-name{display:block;overflow:hidden;color:var(--ak-text);font-size:.92rem;font-weight:950;text-overflow:ellipsis;white-space:nowrap}.index-tile-sym{display:block;margin-top:.12rem;color:var(--ak-muted);font-size:.58rem;font-weight:750}.index-tile-chev{width:1.85rem!important;height:1.85rem!important;border:1px solid var(--ak-border);border-radius:.55rem;padding:.42rem;color:var(--ak-muted)}.index-tile-metrics{display:grid;grid-template-columns:repeat(3,1fr);margin:0 .9rem;overflow:hidden;border:1px solid var(--ak-border);border-radius:var(--ak-inner-radius);background:var(--ak-surface-muted)}.index-tile-metrics>i{display:flex;min-height:2.9rem;align-items:center;justify-content:space-between;gap:.35rem;padding:.55rem .65rem;font-style:normal}.index-tile-metrics>i+ i{border-left:1px solid var(--ak-border)}.index-tile-metrics small,.index-tile-forecast-label{color:var(--ak-muted);font-size:.45rem;font-weight:950;letter-spacing:.08em;text-transform:uppercase}.index-tile-metrics b{font-size:.78rem;font-weight:950}.index-tile-signal[data-signal=buy] b{color:#047857}.index-tile-signal[data-signal=sell] b{color:#be123c}.index-tile-signal[data-signal=watch] b{color:#b45309}.index-tile-forecasts{display:grid;grid-template-columns:minmax(3.5rem,.7fr) repeat(3,1fr);align-items:center;padding:0 1rem .8rem}.index-tile-forecasts>i{display:flex;align-items:baseline;justify-content:center;gap:.3rem;font-style:normal}.index-tile-forecasts>i+ i{border-left:1px solid var(--ak-border)}.index-tile-forecasts small{color:var(--ak-muted);font-size:.46rem;font-weight:950}.index-tile-forecasts b{font-size:.72rem;font-weight:950}.index-card-details{display:grid;grid-template-columns:1fr;gap:.55rem;margin:.2rem .9rem .9rem;padding-top:.7rem;border-top:1px solid var(--ak-border)}.index-card-details>section{min-width:0;border:1px solid var(--ak-border);border-radius:var(--ak-inner-radius);background:var(--ak-surface-muted);padding:.8rem}.index-card-details h3,.index-card-chart header{display:flex;justify-content:space-between;color:var(--ak-text);font-size:.66rem;font-weight:900;text-transform:uppercase}.index-card-chart svg{width:100%;height:7.5rem;margin-top:.5rem}.index-card-chart p,.index-card-copy p,.index-card-members p{margin-top:.6rem;color:var(--ak-muted);font-size:.72rem;line-height:1.55}.index-card-copy dl{display:grid;grid-template-columns:repeat(3,1fr);gap:.35rem;margin-top:.7rem}.index-card-copy dl div{border:1px solid var(--ak-border);border-radius:.45rem;padding:.45rem}.index-card-copy dt{font-size:.48rem;color:var(--ak-muted)}.index-card-copy dd{font-size:.72rem;font-weight:900}.index-card-members>a:not(.index-card-all){display:flex;justify-content:space-between;gap:.5rem;margin-top:.45rem;border-bottom:1px solid var(--ak-border);padding:.35rem 0;font-size:.65rem;font-weight:800}.index-card-members a b,.index-card-all{color:var(--ak-accent)}.index-card-all{display:inline-flex;margin-top:.65rem;font-size:.62rem;font-weight:900}
.sector-screener .index-tile-metrics{margin:0}
.sector-screener .index-tile-forecasts{padding:.05rem .15rem 0}
.sector-screener .index-card-details{margin:.8rem 0 0;padding-top:.8rem}
</style>
</x-app-layout>
