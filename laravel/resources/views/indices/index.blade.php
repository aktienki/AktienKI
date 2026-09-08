<x-app-layout>
<div id="aggregate-screener" class="index-screener screener-page mx-auto max-w-7xl px-3 py-5 text-[var(--ak-text)] sm:px-5 lg:py-8">
    <header class="mb-3 flex items-center justify-between gap-3"><h1 class="text-3xl font-black tracking-tight">{{ __('Indexscreener') }}</h1><span class="rounded-[10px] border border-cyan-400/30 bg-cyan-400/[.08] px-3 py-2 text-xs font-bold text-[var(--ak-muted)]">{{ $indices->count() }} {{ __('Indizes') }}</span></header>
    @if($isFreeRegional)<div class="mb-4 flex items-center justify-between gap-3 rounded-xl border border-amber-400/25 bg-amber-400/[.06] px-4 py-3"><p class="text-xs text-[var(--ak-muted)]"><b class="mr-2 text-[9px] uppercase tracking-[.12em] text-amber-400">FREE</b>{{ __('Indexwerte aus deinem regionalen Top-100-Portfolio (:country).', ['country'=>$regionalCountry]) }}</p><a href="{{ route('pricing') }}" class="shrink-0 text-[9px] font-black text-amber-300">{{ __('Global ab Plus') }} →</a></div>@endif
    <section x-data="{ filtersOpen: false }" class="screener-filter-shell mb-5 shrink-0">
        <button type="button" @click="filtersOpen = ! filtersOpen" :aria-expanded="filtersOpen" class="flex h-10 w-full items-center justify-between rounded-xl border border-cyan-400/30 bg-[var(--ak-card)] px-4 text-xs font-black text-cyan-300 shadow-[var(--ak-shadow)]"><span class="inline-flex items-center gap-2"><x-heroicon-o-adjustments-horizontal class="h-4 w-4" />{{ __('Filter anzeigen') }}</span><x-heroicon-o-chevron-down class="h-4 w-4 transition" x-bind:class="filtersOpen && 'rotate-180'" /></button>
        <form x-cloak x-show="filtersOpen" method="GET" class="screener-filter-bar mt-2 flex flex-nowrap gap-2 overflow-x-auto rounded-lg border border-cyan-400/30 bg-[var(--ak-card)] p-3 shadow-[var(--ak-shadow)]"><input name="q" value="{{ request('q') }}" oninput="clearTimeout(this._filterTimer);this._filterTimer=setTimeout(()=>this.form.requestSubmit(),500)" placeholder="{{ __('Index oder Symbol') }}" class="ak-input h-10 min-w-[220px] flex-[2] text-sm"><select name="region" onchange="this.form.requestSubmit()" class="ak-input h-10 min-w-[180px] flex-1 text-sm"><option value="">{{ __('Alle Regionen') }}</option>@foreach($regions as $region)<option value="{{ $region }}" @selected(request('region')===$region)>{{ $region }}</option>@endforeach</select><a href="{{ route('indices.index') }}" class="screener-filter-reset inline-flex h-10 shrink-0 items-center justify-center border border-amber-400/40 bg-amber-400/[.10] px-4 text-xs font-black text-amber-300">{{ __('Reset') }}</a></form>
    </section>
    @php
        $outlookColumns = [
            10 => 'expected_return_10d',
            20 => 'expected_return_20d',
            40 => 'expected_return_40d',
        ];
        $indexFlags = [
            '^GSPC'=>'🇺🇸','^IXIC'=>'🇺🇸','^DJI'=>'🇺🇸','^RUT'=>'🇺🇸','^NYA'=>'🇺🇸',
            '^GSPTSE'=>'🇨🇦','^MXX'=>'🇲🇽','^BVSP'=>'🇧🇷','^MERV'=>'🇦🇷','^IPSA'=>'🇨🇱',
            '^FTSE'=>'🇬🇧','^GDAXI'=>'🇩🇪','^FCHI'=>'🇫🇷','^STOXX50E'=>'🇪🇺','^STOXX'=>'🇪🇺',
            '^AEX'=>'🇳🇱','^BFX'=>'🇧🇪','^ATX'=>'🇦🇹','^SSMI'=>'🇨🇭','^IBEX'=>'🇪🇸',
            'FTSEMIB.MI'=>'🇮🇹','PSI20.LS'=>'🇵🇹','^OMX'=>'🇸🇪','^OMXC25'=>'🇩🇰',
            '^OMXH25'=>'🇫🇮','^OSEAX'=>'🇳🇴','^WIG20'=>'🇵🇱','^BUX'=>'🇭🇺',
            '^N225'=>'🇯🇵','^TOPX'=>'🇯🇵','^HSI'=>'🇭🇰','000001.SS'=>'🇨🇳','399001.SZ'=>'🇨🇳',
            '^KS11'=>'🇰🇷','^NSEI'=>'🇮🇳','^BSESN'=>'🇮🇳','^TWII'=>'🇹🇼','^STI'=>'🇸🇬',
            '^JKSE'=>'🇮🇩','^KLSE'=>'🇲🇾','^SET.BK'=>'🇹🇭','PSEI.PS'=>'🇵🇭',
            '^AXJO'=>'🇦🇺','^AORD'=>'🇦🇺','^NZ50'=>'🇳🇿','^J203.JO'=>'🇿🇦','^JN0U.JO'=>'🇿🇦',
            '^TASI.SR'=>'🇸🇦','DFMGI.AE'=>'🇦🇪','^TA125.TA'=>'🇮🇱','^CASE30'=>'🇪🇬',
        ];
        $largestOutlook = max(1, (float) $indices->flatMap(fn ($index) => collect($outlookColumns)
            ->map(fn ($field) => is_numeric($index->{$field} ?? null) ? abs((float) $index->{$field}) : null)
            ->filter())->max());
    @endphp
    <section class="grid gap-3" aria-label="{{ __('Index-Ranking') }}">
        @forelse($indices as $index)
            @php
                $forecastValues = collect($outlookColumns)->mapWithKeys(fn ($field, $days) => [$days => is_numeric($index->{$field} ?? null) ? (float) $index->{$field} : null]);
                $scorePercent = is_numeric($index->rating_value ?? null) ? max(0, min(100, (float)$index->rating_value * 10)) : 0;
                $riskRaw = is_numeric($index->average_risk ?? null) ? (float)$index->average_risk : null;
                $riskPercent = $riskRaw === null ? null : max(0, min(100, $riskRaw <= 1 ? $riskRaw * 100 : ($riskRaw <= 10 ? $riskRaw * 10 : $riskRaw)));
                $scoreLabel = \App\Support\QualityGrade::fromPercent($scorePercent) ?? '—';
                $riskLabel = \App\Support\QualityGrade::riskLevel($riskPercent) ?? '—';
                $scoreColor = $scorePercent >= 75 ? '#34d399' : ($scorePercent >= 50 ? '#fbbf24' : '#fb7185');
                $riskColor = $riskPercent === null ? '#64748b' : ($riskPercent <= 30 ? '#34d399' : ($riskPercent <= 60 ? '#fbbf24' : '#fb7185'));
                $mainOutlook = $forecastValues->get(20);
                $signal = $mainOutlook === null ? 'WATCH' : ($mainOutlook >= 2 ? 'BUY' : ($mainOutlook < 0 ? 'SELL' : 'WATCH'));
                $chartPoints = collect($index->chart_points ?? [])->filter(fn ($point) => is_numeric(data_get($point, 'close')))->values();
                $chartMin = $chartPoints->min(fn ($point) => (float)data_get($point, 'close'));
                $chartMax = $chartPoints->max(fn ($point) => (float)data_get($point, 'close'));
                $chartRange = max(.000001, (float)$chartMax - (float)$chartMin);
                $polyline = $chartPoints->count() > 1 ? $chartPoints->map(fn ($point, $position) => sprintf('%.1f,%.1f', $position * 600 / ($chartPoints->count() - 1), 100 - (((float)data_get($point, 'close') - (float)$chartMin) / $chartRange) * 82))->implode(' ') : '';
                $marketInfo = app()->getLocale() === 'en' ? data_get($index, 'daily_market_info.market_info_en') : data_get($index, 'daily_market_info.market_info_de');
            @endphp
            <article class="screener-stock-card index-screener-card ak-card ak-dashboard-card overflow-hidden p-3" x-data="{ expanded:false }">
                <button type="button" class="screener-desktop-summary hidden w-full items-center gap-4 text-left md:grid" @click="expanded=!expanded" :aria-expanded="expanded.toString()">
                    <span class="screener-desktop-stock"><b>#{{ $index->global_rank }}</b><i>{{ $indexFlags[$index->symbol] ?? '🌐' }}</i><span><strong>{{ $index->name }}</strong><small>{{ $index->symbol }} · {{ $index->region }}</small></span></span>
                    <span class="screener-desktop-signal" data-signal="{{ strtolower($signal) }}"><strong>{{ $signal }}</strong><small>{{ __('Signal') }}</small></span>
                    <span class="screener-desktop-grade"><strong>{{ $scoreLabel }}</strong><small>{{ __('Bewertung') }}</small></span>
                    <span class="screener-desktop-grade"><strong>{{ $riskLabel }}</strong><small>{{ __('Risiko') }}</small></span>
                    <span class="screener-desktop-forecasts">@foreach($forecastValues as $days=>$value)<i><small>{{ $days }}T</small><strong class="{{ $value===null?'text-slate-400':($value>=0?'text-emerald-400':'text-rose-400') }}">{{ $value===null?'—':(($value>0?'+':'').number_format($value,1,',','.').' %') }}</strong></i>@endforeach</span>
                    <x-heroicon-o-chevron-down class="h-5 w-5 text-cyan-300 transition" x-bind:class="expanded&&'rotate-180'" />
                </button>
                <button type="button" class="screener-mobile-summary screener-mobile-summary-v2 md:hidden" @click="expanded=!expanded" :aria-expanded="expanded.toString()">
                    <span class="sms-v2-head"><b>#{{ $index->global_rank }}</b><i>{{ $indexFlags[$index->symbol] ?? '🌐' }}</i><span><strong>{{ $index->name }}</strong><small>{{ $index->symbol }} · {{ $index->region }}</small></span><em>{{ number_format((int)($index->members_count ?? 0),0,',','.') }}</em><x-heroicon-o-chevron-down class="h-4 w-4 text-cyan-300 transition" x-bind:class="expanded&&'rotate-180'" /></span>
                    <span class="sms-v2-forecast"><strong data-signal="{{ strtolower($signal) }}">{{ $signal }}</strong>@foreach($forecastValues as $days=>$value)<i><small>{{ $days }}T</small><b class="{{ $value===null?'text-slate-400':($value>=0?'text-emerald-400':'text-rose-400') }}">{{ $value===null?'—':(($value>0?'+':'').number_format($value,1,',','.').' %') }}</b></i>@endforeach</span>
                    <span class="sms-v2-scales"><i><small>{{ __('Bewertung') }} · {{ $scoreLabel }}</small><span class="sms-v2-scale signal" style="--position:{{ $scorePercent }}%;--marker:{{ $scoreColor }}"><em></em></span></i><i><small>{{ __('Risiko') }} · {{ $riskLabel }}</small><span class="sms-v2-scale risk" style="--position:{{ 100-($riskPercent??0) }}%;--marker:{{ $riskColor }}"><em></em></span></i></span>
                </button>
                <div x-cloak x-show="expanded" x-collapse class="index-card-details">
                    <section class="index-card-chart">
                        <header><span>{{ __('Chart · 1 Jahr') }}</span><b>{{ $index->symbol }}</b></header>
                        @if($polyline)
                            <svg viewBox="0 0 600 118" preserveAspectRatio="none" role="img" aria-label="{{ __('Kursverlauf des letzten Jahres') }}"><defs><linearGradient id="index-line-{{ $index->id }}"><stop stop-color="#2563eb"/><stop offset="1" stop-color="#14b8a6"/></linearGradient><linearGradient id="index-area-{{ $index->id }}" x1="0" y1="0" x2="0" y2="1"><stop stop-color="#0ea5e9" stop-opacity=".28"/><stop offset="1" stop-color="#0f766e" stop-opacity="0"/></linearGradient></defs><polygon points="{{ $polyline }} 600,108 0,108" fill="url(#index-area-{{ $index->id }})"/><polyline points="{{ $polyline }}" fill="none" stroke="url(#index-line-{{ $index->id }})" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"/><path d="M0 108H600" stroke="#0d9488" stroke-opacity=".25"/></svg>
                        @else<p>{{ __('Der Kurschart ist momentan nicht verfügbar.') }}</p>@endif
                    </section>
                    <section class="index-card-copy"><h3>{{ __('Marktausblick') }}</h3><p>{{ $marketInfo ?: __('Für diesen Index liegt derzeit keine zusätzliche Marktanalyse vor.') }}</p><dl><div><dt>{{ __('Konfidenz') }}</dt><dd>{{ is_numeric($index->average_confidence??null)?number_format((float)$index->average_confidence,0,',','.').' %':'—' }}</dd></div><div><dt>{{ __('Hit-Rate') }}</dt><dd>{{ is_numeric($index->average_hit_rate??null)?number_format((float)$index->average_hit_rate,1,',','.').' %':'—' }}</dd></div><div><dt>{{ __('Aktien') }}</dt><dd>{{ (int)($index->members_count??0) }}</dd></div></dl></section>
                    <section class="index-card-members"><h3>{{ __('Führende Aktien') }}</h3>@forelse(collect($index->top_stocks??[])->take(3) as $member)<a href="{{ route('stocks.show',['symbol'=>$member->symbol]) }}"><span>{{ \App\Support\CountryFlag::emoji($member->country) }} {{ $member->name?:$member->symbol }}</span><b>{{ $member->personalized_signal??'—' }}</b></a>@empty<p>{{ __('Keine Aktien verfügbar.') }}</p>@endforelse<a class="index-card-all" href="{{ route('stocks.index',['index'=>$index->symbol]) }}">{{ __('Alle Indexmitglieder') }} →</a></section>
                </div>
            </article>
        @empty
            <div class="ak-card p-8 text-center text-sm text-[var(--ak-muted)]">{{ __('Keine Indizes für diese Auswahl gefunden.') }}</div>
        @endforelse
    </section>
</div>
<style>
#aggregate-screener{min-height:calc(100dvh - 73px)}.index-outlook-row{display:grid;grid-template-columns:minmax(220px,1.35fr) repeat(4,minmax(125px,1fr));align-items:stretch;border-top:1px solid color-mix(in srgb,var(--ak-muted) 14%,transparent)}.index-outlook-head{border-top:0;background:color-mix(in srgb,var(--ak-card) 82%,var(--ak-bg) 18%);font-size:8px;font-weight:900;letter-spacing:.12em;text-transform:uppercase;color:var(--ak-muted)}.index-outlook-row>span{display:flex;min-width:0;align-items:center;padding:.55rem .75rem;border-left:1px solid color-mix(in srgb,var(--ak-muted) 12%,transparent)}.index-outlook-row>span:first-child{border-left:0}.index-outlook-row:not(.index-outlook-head):hover{background:rgba(34,211,238,.035)}.index-outlook-name{flex-direction:column;align-items:flex-start!important;justify-content:center}.index-outlook-name b{overflow:hidden;max-width:100%;font-size:10px;text-overflow:ellipsis;white-space:nowrap}.index-outlook-name small{font-size:7px;color:var(--ak-muted)}.index-outlook-cell{position:relative;justify-content:center;overflow:hidden}.index-outlook-cell b{position:relative;z-index:3;padding:2px 5px;border-radius:5px;background:color-mix(in srgb,var(--ak-card) 78%,transparent);font-size:9px;font-weight:900}.index-outlook-axis{position:absolute;z-index:1;top:20%;bottom:20%;left:50%;width:1px;background:color-mix(in srgb,var(--ak-muted) 32%,transparent)}.index-outlook-bar{position:absolute;z-index:2;top:30%;bottom:30%;min-width:2px}.index-outlook-bar.is-positive{left:50%;border-radius:0 5px 5px 0;background:linear-gradient(90deg,rgba(16,185,129,.28),rgba(16,185,129,.72))}.index-outlook-bar.is-negative{right:50%;border-radius:5px 0 0 5px;background:linear-gradient(270deg,rgba(244,63,94,.28),rgba(244,63,94,.72))}
.index-outlook-row{grid-template-columns:minmax(220px,1.35fr) repeat(3,minmax(145px,1fr))}
.index-screener-card{height:auto!important}.index-card-details{display:grid;grid-template-columns:1.35fr 1fr .9fr;gap:.7rem;margin-top:.8rem;padding-top:.8rem;border-top:1px solid rgba(34,211,238,.14)}.index-card-details>section{min-width:0;border:1px solid rgba(34,211,238,.14);border-radius:.75rem;background:rgba(8,47,73,.08);padding:.8rem}.index-card-details h3,.index-card-chart header{display:flex;justify-content:space-between;color:#67e8f9;font-size:.66rem;font-weight:900;letter-spacing:.09em;text-transform:uppercase}.index-card-chart svg{width:100%;height:9rem;margin-top:.5rem}.index-card-chart>p,.index-card-copy>p,.index-card-members>p{margin-top:.6rem;color:var(--ak-muted);font-size:.72rem;line-height:1.55}.index-card-copy dl{display:grid;grid-template-columns:repeat(3,1fr);gap:.35rem;margin-top:.7rem}.index-card-copy dl div{border:1px solid rgba(148,163,184,.13);border-radius:.45rem;padding:.45rem}.index-card-copy dt{color:var(--ak-muted);font-size:.48rem;font-weight:900;text-transform:uppercase}.index-card-copy dd{margin-top:.15rem;font-size:.72rem;font-weight:900}.index-card-members>a:not(.index-card-all){display:flex;align-items:center;justify-content:space-between;gap:.5rem;margin-top:.45rem;border-bottom:1px solid rgba(148,163,184,.11);padding:.35rem 0;font-size:.65rem;font-weight:800}.index-card-members>a b{color:#34d399;font-size:.55rem}.index-card-all{display:inline-flex;margin-top:.65rem;color:#67e8f9;font-size:.62rem;font-weight:900}@media(max-width:767px){.index-screener{padding-inline:.45rem}.index-card-details{grid-template-columns:1fr}.index-card-chart svg{height:10rem}.index-card-copy>p{font-size:.78rem}.index-screener-card .sms-v2-head>em::after{content:' {{ __('Aktien') }}';color:var(--ak-muted);font-size:.45rem}.index-screener-card{padding:.45rem!important}}
</style>
</x-app-layout>
