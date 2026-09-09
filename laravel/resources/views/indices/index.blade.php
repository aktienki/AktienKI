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

        collect($indices)->each(function ($index) use ($outlookColumns) {
            $forecastValues = collect($outlookColumns)->mapWithKeys(fn ($field, $days) => [$days => is_numeric($index->{$field} ?? null) ? (float) $index->{$field} : null]);
            $scorePercent = is_numeric($index->rating_value ?? null) ? max(0, min(100, (float) $index->rating_value * 10)) : 0;
            $riskRaw = is_numeric($index->average_risk ?? null) ? (float) $index->average_risk : null;
            $riskPercent = $riskRaw === null ? null : max(0, min(100, $riskRaw <= 1 ? $riskRaw * 100 : ($riskRaw <= 10 ? $riskRaw * 10 : $riskRaw)));
            $mainOutlook = $forecastValues->get(20);
            $chartPoints = collect($index->chart_points ?? [])->filter(fn ($point) => is_numeric(data_get($point, 'close')))->values();
            $chartMin = $chartPoints->min(fn ($point) => (float) data_get($point, 'close'));
            $chartMax = $chartPoints->max(fn ($point) => (float) data_get($point, 'close'));
            $chartRange = max(.000001, (float) $chartMax - (float) $chartMin);

            $index->view_forecasts = $forecastValues;
            $index->view_score_percent = $scorePercent;
            $index->view_risk_percent = $riskPercent;
            $index->view_score_label = \App\Support\QualityGrade::fromPercent($scorePercent) ?? '—';
            $index->view_risk_label = \App\Support\QualityGrade::riskLevel($riskPercent) ?? '—';
            $index->view_score_color = $scorePercent >= 75 ? '#34d399' : ($scorePercent >= 50 ? '#fbbf24' : '#fb7185');
            $index->view_risk_color = $riskPercent === null ? '#64748b' : ($riskPercent <= 30 ? '#34d399' : ($riskPercent <= 60 ? '#fbbf24' : '#fb7185'));
            $index->view_signal = $mainOutlook === null ? 'WATCH' : ($mainOutlook >= 2 ? 'BUY' : ($mainOutlook < 0 ? 'SELL' : 'WATCH'));
            $index->view_polyline = $chartPoints->count() > 1 ? $chartPoints->map(fn ($point, $position) => sprintf('%.1f,%.1f', $position * 600 / ($chartPoints->count() - 1), 100 - (((float) data_get($point, 'close') - (float) $chartMin) / $chartRange) * 82))->implode(' ') : '';
            $index->view_market_info = app()->getLocale() === 'en' ? data_get($index, 'daily_market_info.market_info_en') : data_get($index, 'daily_market_info.market_info_de');
        });

        $indexColumnLabels = [
            'Europa' => __('Europa'),
            'Nordamerika' => __('Nordamerika'),
            'Andere' => __('Andere'),
        ];
        $indexColumnFlags = [
            'Europa' => '🇪🇺',
            'Nordamerika' => '🌎',
            'Andere' => '🌐',
        ];
        $indexColumnBucket = fn (?string $region): string => in_array((string) $region, ['Europa', 'Nordamerika'], true) ? (string) $region : 'Andere';
        $indicesByColumn = collect($indices)->groupBy(fn ($index) => $indexColumnBucket($index->region ?? null));
    @endphp
    @if($indices->isEmpty())
        <div class="ak-card p-8 text-center text-sm text-[var(--ak-muted)]">{{ __('Keine Indizes für diese Auswahl gefunden.') }}</div>
    @else
    {{-- Desktop: three region columns of index tiles --}}
    <section class="index-region-grid hidden gap-3 md:grid md:grid-cols-3" aria-label="{{ __('Indizes nach Region') }}">
        @foreach($indexColumnLabels as $columnKey => $columnLabel)
            <div class="index-region-column flex min-w-0 flex-col gap-2.5">
                <h2 class="index-region-head"><b class="inline-flex items-center gap-2"><i class="text-base not-italic" aria-hidden="true">{{ $indexColumnFlags[$columnKey] }}</i>{{ $columnLabel }}</b><span>{{ ($indicesByColumn[$columnKey] ?? collect())->count() }}</span></h2>
                @forelse($indicesByColumn[$columnKey] ?? [] as $index)
                    <article class="index-tile ak-card ak-dashboard-card overflow-hidden p-3" data-signal="{{ strtolower($index->view_signal) }}" x-data="{ expanded:false }">
                        <button type="button" class="index-tile-summary w-full" @click="expanded=!expanded" :aria-expanded="expanded.toString()">
                            <span class="index-tile-head">
                                <i class="index-tile-flag">{{ $indexFlags[$index->symbol] ?? '🌐' }}</i>
                                <span class="min-w-0"><strong class="index-tile-name">{{ $index->name }}</strong><small class="index-tile-sym">#{{ $index->global_rank }} · {{ $index->symbol }}</small></span>
                                <x-heroicon-o-chevron-down class="index-tile-chev h-4 w-4 shrink-0 text-cyan-300 transition" x-bind:class="expanded&&'rotate-180'" />
                            </span>
                            <span class="index-tile-metrics">
                                <i class="index-tile-signal" data-signal="{{ strtolower($index->view_signal) }}"><small>{{ __('Signal') }}</small><b>{{ $index->view_signal }}</b></i>
                                <i><small>{{ __('Bewertung') }}</small><b style="color:{{ $index->view_score_color }}">{{ $index->view_score_label }}</b></i>
                                <i><small>{{ __('Risiko') }}</small><b style="color:{{ $index->view_risk_color }}">{{ $index->view_risk_label }}</b></i>
                            </span>
                            <span class="index-tile-forecasts"><span class="index-tile-forecast-label">{{ __('Ausblick') }}</span>@foreach($index->view_forecasts as $days => $value)<i><small>{{ $days }}T</small><b class="{{ $value===null?'text-slate-400':($value>=0?'text-emerald-400':'text-rose-400') }}">{{ $value===null?'—':(($value>0?'+':'').number_format($value,1,',','.').'%') }}</b></i>@endforeach</span>
                        </button>
                        <div x-cloak x-show="expanded" x-collapse class="index-card-details index-tile-details">
                            <section class="index-card-chart">
                                <header><span>{{ __('Chart · 1 Jahr') }}</span><b>{{ $index->symbol }}</b></header>
                                @if($index->view_polyline)
                                    <svg viewBox="0 0 600 118" preserveAspectRatio="none" role="img" aria-label="{{ __('Kursverlauf des letzten Jahres') }}"><defs><linearGradient id="index-tile-line-{{ $index->id }}"><stop stop-color="var(--ak-accent)"/><stop offset="1" stop-color="var(--ak-muted)"/></linearGradient><linearGradient id="index-tile-area-{{ $index->id }}" x1="0" y1="0" x2="0" y2="1"><stop stop-color="var(--ak-accent)" stop-opacity=".18"/><stop offset="1" stop-color="var(--ak-muted)" stop-opacity="0"/></linearGradient></defs><polygon points="{{ $index->view_polyline }} 600,108 0,108" fill="url(#index-tile-area-{{ $index->id }})"/><polyline points="{{ $index->view_polyline }}" fill="none" stroke="url(#index-tile-line-{{ $index->id }})" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"/><path d="M0 108H600" stroke="var(--ak-border-strong)" stroke-opacity=".25"/></svg>
                                @else<p>{{ __('Der Kurschart ist momentan nicht verfügbar.') }}</p>@endif
                            </section>
                            <section class="index-card-copy"><h3>{{ __('Marktausblick') }}</h3><p>{{ $index->view_market_info ?: __('Für diesen Index liegt derzeit keine zusätzliche Marktanalyse vor.') }}</p><dl><div><dt>{{ __('Konfidenz') }}</dt><dd>{{ is_numeric($index->average_confidence??null)?number_format((float)$index->average_confidence,0,',','.').' %':'—' }}</dd></div><div><dt>{{ __('Hit-Rate') }}</dt><dd>{{ is_numeric($index->average_hit_rate??null)?number_format((float)$index->average_hit_rate,1,',','.').' %':'—' }}</dd></div><div><dt>{{ __('Aktien') }}</dt><dd>{{ (int)($index->members_count??0) }}</dd></div></dl></section>
                            <section class="index-card-members"><h3>{{ __('Führende Aktien') }}</h3>@forelse(collect($index->top_stocks??[])->take(3) as $member)<a href="{{ route('stocks.show',['symbol'=>$member->symbol]) }}"><span>{{ \App\Support\CountryFlag::emoji($member->country) }} {{ $member->name?:$member->symbol }}</span><b>{{ $member->personalized_signal??'—' }}</b></a>@empty<p>{{ __('Keine Aktien verfügbar.') }}</p>@endforelse<a class="index-card-all" href="{{ route('stocks.index',['index'=>$index->symbol]) }}">{{ __('Alle Indexmitglieder') }} →</a></section>
                        </div>
                    </article>
                @empty
                    <p class="index-region-empty">{{ __('Keine Indizes.') }}</p>
                @endforelse
            </div>
        @endforeach
    </section>

    {{-- Mobile: single stacked list --}}
    <section class="grid gap-3 md:hidden" aria-label="{{ __('Index-Ranking') }}">
        @foreach($indices as $index)
            <article class="screener-stock-card index-screener-card ak-card ak-dashboard-card overflow-hidden p-3" x-data="{ expanded:false }">
                <button type="button" class="screener-mobile-summary screener-mobile-summary-v2" @click="expanded=!expanded" :aria-expanded="expanded.toString()">
                    <span class="sms-v2-head"><b>#{{ $index->global_rank }}</b><i>{{ $indexFlags[$index->symbol] ?? '🌐' }}</i><span><strong>{{ $index->name }}</strong><small>{{ $index->symbol }} · {{ $index->region }}</small></span><em>{{ number_format((int)($index->members_count ?? 0),0,',','.') }}</em><x-heroicon-o-chevron-down class="h-4 w-4 text-cyan-300 transition" x-bind:class="expanded&&'rotate-180'" /></span>
                    <span class="sms-v2-forecast"><strong data-signal="{{ strtolower($index->view_signal) }}">{{ $index->view_signal }}</strong>@foreach($index->view_forecasts as $days=>$value)<i><small>{{ $days }}T</small><b class="{{ $value===null?'text-slate-400':($value>=0?'text-emerald-400':'text-rose-400') }}">{{ $value===null?'—':(($value>0?'+':'').number_format($value,1,',','.').' %') }}</b></i>@endforeach</span>
                    <span class="sms-v2-scales"><i><small>{{ __('Bewertung') }} · {{ $index->view_score_label }}</small><span class="sms-v2-scale signal" style="--position:{{ $index->view_score_percent }}%;--marker:{{ $index->view_score_color }}"><em></em></span></i><i><small>{{ __('Risiko') }} · {{ $index->view_risk_label }}</small><span class="sms-v2-scale risk" style="--position:{{ 100-($index->view_risk_percent??0) }}%;--marker:{{ $index->view_risk_color }}"><em></em></span></i></span>
                </button>
                <div x-cloak x-show="expanded" x-collapse class="index-card-details">
                    <section class="index-card-chart">
                        <header><span>{{ __('Chart · 1 Jahr') }}</span><b>{{ $index->symbol }}</b></header>
                        @if($index->view_polyline)
                            <svg viewBox="0 0 600 118" preserveAspectRatio="none" role="img" aria-label="{{ __('Kursverlauf des letzten Jahres') }}"><defs><linearGradient id="index-line-{{ $index->id }}"><stop stop-color="var(--ak-accent)"/><stop offset="1" stop-color="var(--ak-muted)"/></linearGradient><linearGradient id="index-area-{{ $index->id }}" x1="0" y1="0" x2="0" y2="1"><stop stop-color="var(--ak-accent)" stop-opacity=".18"/><stop offset="1" stop-color="var(--ak-muted)" stop-opacity="0"/></linearGradient></defs><polygon points="{{ $index->view_polyline }} 600,108 0,108" fill="url(#index-area-{{ $index->id }})"/><polyline points="{{ $index->view_polyline }}" fill="none" stroke="url(#index-line-{{ $index->id }})" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"/><path d="M0 108H600" stroke="var(--ak-border-strong)" stroke-opacity=".25"/></svg>
                        @else<p>{{ __('Der Kurschart ist momentan nicht verfügbar.') }}</p>@endif
                    </section>
                    <section class="index-card-copy"><h3>{{ __('Marktausblick') }}</h3><p>{{ $index->view_market_info ?: __('Für diesen Index liegt derzeit keine zusätzliche Marktanalyse vor.') }}</p><dl><div><dt>{{ __('Konfidenz') }}</dt><dd>{{ is_numeric($index->average_confidence??null)?number_format((float)$index->average_confidence,0,',','.').' %':'—' }}</dd></div><div><dt>{{ __('Hit-Rate') }}</dt><dd>{{ is_numeric($index->average_hit_rate??null)?number_format((float)$index->average_hit_rate,1,',','.').' %':'—' }}</dd></div><div><dt>{{ __('Aktien') }}</dt><dd>{{ (int)($index->members_count??0) }}</dd></div></dl></section>
                    <section class="index-card-members"><h3>{{ __('Führende Aktien') }}</h3>@forelse(collect($index->top_stocks??[])->take(3) as $member)<a href="{{ route('stocks.show',['symbol'=>$member->symbol]) }}"><span>{{ \App\Support\CountryFlag::emoji($member->country) }} {{ $member->name?:$member->symbol }}</span><b>{{ $member->personalized_signal??'—' }}</b></a>@empty<p>{{ __('Keine Aktien verfügbar.') }}</p>@endforelse<a class="index-card-all" href="{{ route('stocks.index',['index'=>$index->symbol]) }}">{{ __('Alle Indexmitglieder') }} →</a></section>
                </div>
            </article>
        @endforeach
    </section>
    @endif
</div>
<style>
#aggregate-screener{min-height:calc(100dvh - 73px)}.index-outlook-row{display:grid;grid-template-columns:minmax(220px,1.35fr) repeat(4,minmax(125px,1fr));align-items:stretch;border-top:1px solid color-mix(in srgb,var(--ak-muted) 14%,transparent)}.index-outlook-head{border-top:0;background:color-mix(in srgb,var(--ak-card) 82%,var(--ak-bg) 18%);font-size:8px;font-weight:900;letter-spacing:.12em;text-transform:uppercase;color:var(--ak-muted)}.index-outlook-row>span{display:flex;min-width:0;align-items:center;padding:.55rem .75rem;border-left:1px solid color-mix(in srgb,var(--ak-muted) 12%,transparent)}.index-outlook-row>span:first-child{border-left:0}.index-outlook-row:not(.index-outlook-head):hover{background:rgba(34,211,238,.035)}.index-outlook-name{flex-direction:column;align-items:flex-start!important;justify-content:center}.index-outlook-name b{overflow:hidden;max-width:100%;font-size:10px;text-overflow:ellipsis;white-space:nowrap}.index-outlook-name small{font-size:7px;color:var(--ak-muted)}.index-outlook-cell{position:relative;justify-content:center;overflow:hidden}.index-outlook-cell b{position:relative;z-index:3;padding:2px 5px;border-radius:5px;background:color-mix(in srgb,var(--ak-card) 78%,transparent);font-size:9px;font-weight:900}.index-outlook-axis{position:absolute;z-index:1;top:20%;bottom:20%;left:50%;width:1px;background:color-mix(in srgb,var(--ak-muted) 32%,transparent)}.index-outlook-bar{position:absolute;z-index:2;top:30%;bottom:30%;min-width:2px}.index-outlook-bar.is-positive{left:50%;border-radius:0 5px 5px 0;background:linear-gradient(90deg,rgba(16,185,129,.28),rgba(16,185,129,.72))}.index-outlook-bar.is-negative{right:50%;border-radius:5px 0 0 5px;background:linear-gradient(270deg,rgba(244,63,94,.28),rgba(244,63,94,.72))}
.index-outlook-row{grid-template-columns:minmax(220px,1.35fr) repeat(3,minmax(145px,1fr))}
.index-screener-card{height:auto!important}.index-card-details{display:grid;grid-template-columns:1.35fr 1fr .9fr;gap:.7rem;margin-top:.8rem;padding-top:.8rem;border-top:1px solid rgba(34,211,238,.14)}
.index-region-column{min-width:0}
.index-region-head{display:flex;align-items:center;justify-content:space-between;gap:.5rem;position:sticky;top:0;z-index:2;margin-bottom:.25rem;padding:.55rem .7rem;border-bottom:1px solid color-mix(in srgb,#22d3ee 28%,transparent);background:color-mix(in srgb,var(--ak-bg) 92%,transparent);font-size:.68rem;font-weight:950;letter-spacing:.14em;text-transform:uppercase;color:#22d3ee}
.index-region-head span{display:grid;min-width:1.7rem;height:1.4rem;place-items:center;border:1px solid color-mix(in srgb,#22d3ee 30%,transparent);border-radius:.45rem;background:color-mix(in srgb,#22d3ee 6%,transparent);font-size:.6rem;font-weight:950;color:var(--ak-muted)}
.index-region-empty{padding:1.1rem .5rem;text-align:center;font-size:.7rem;color:var(--ak-muted)}
.index-tile{--index-accent:#fbbf24;position:relative;height:auto!important;border-color:color-mix(in srgb,var(--index-accent) 23%,var(--ak-border))!important;border-radius:1rem!important;padding:.85rem .9rem .8rem!important;background:linear-gradient(145deg,color-mix(in srgb,var(--index-accent) 3%,var(--ak-card)) 0%,var(--ak-card) 58%)!important;box-shadow:0 10px 25px rgba(5,20,34,.08)!important;transition:border-color .18s ease,transform .18s ease,box-shadow .18s ease}
.index-tile::before{position:absolute;inset:.8rem auto .8rem 0;width:3px;border-radius:0 99px 99px 0;background:var(--index-accent);content:"";opacity:.82}
.index-tile[data-signal=buy]{--index-accent:#34d399}.index-tile[data-signal=sell]{--index-accent:#fb7185}
.index-tile:hover{border-color:color-mix(in srgb,var(--index-accent) 48%,var(--ak-border))!important;box-shadow:0 13px 30px color-mix(in srgb,var(--index-accent) 9%,rgba(5,20,34,.12))!important;transform:translateY(-1px)}
.index-tile-summary{display:flex;flex-direction:column;gap:.7rem;text-align:left}
.index-tile-head{display:flex;align-items:center;gap:.7rem;min-width:0;padding-left:.2rem}
.index-tile-flag{display:grid;width:2.25rem;height:2.25rem;flex:0 0 2.25rem;place-items:center;border:1px solid color-mix(in srgb,var(--index-accent) 24%,var(--ak-border));border-radius:.7rem;background:color-mix(in srgb,var(--index-accent) 7%,var(--ak-card));font-size:1.05rem;font-style:normal}
.index-tile-head>span{min-width:0;flex:1 1 auto}
.index-tile-name{display:block;overflow:hidden;color:var(--ak-text);font-size:.92rem;font-weight:950;letter-spacing:-.015em;text-overflow:ellipsis;white-space:nowrap}
.index-tile-sym{display:block;overflow:hidden;margin-top:.12rem;font-size:.58rem;font-weight:750;color:var(--ak-muted);text-overflow:ellipsis;white-space:nowrap}
.index-tile-chev{width:1.85rem!important;height:1.85rem!important;flex:0 0 1.85rem;margin-left:auto;border:1px solid color-mix(in srgb,var(--ak-muted) 18%,transparent);border-radius:.55rem;padding:.42rem;color:var(--ak-muted)!important}
.index-tile-metrics{display:grid;grid-template-columns:repeat(3,1fr);overflow:hidden;border:1px solid color-mix(in srgb,var(--ak-muted) 14%,transparent);border-radius:.72rem;background:color-mix(in srgb,var(--ak-bg) 35%,transparent)}
.index-tile-metrics>i{display:flex;min-height:2.9rem;flex-direction:row;align-items:center;justify-content:space-between;gap:.35rem;padding:.55rem .65rem;font-style:normal}
.index-tile-metrics>i+ i{border-left:1px solid color-mix(in srgb,var(--ak-muted) 14%,transparent)}
.index-tile-metrics>i small{font-size:.45rem;font-weight:950;letter-spacing:.08em;text-transform:uppercase;color:var(--ak-muted)}
.index-tile-metrics>i b{font-size:.78rem;font-weight:950}
.index-tile-signal[data-signal=buy]{background:color-mix(in srgb,#34d399 7%,transparent)}
.index-tile-signal[data-signal=buy] b{color:#34d399}
.index-tile-signal[data-signal=sell]{background:color-mix(in srgb,#fb7185 7%,transparent)}
.index-tile-signal[data-signal=sell] b{color:#fb7185}
.index-tile-signal[data-signal=watch] b{color:#fbbf24}
.index-tile-forecasts{display:grid;grid-template-columns:minmax(3.5rem,.7fr) repeat(3,1fr);align-items:center;padding:.05rem .15rem 0}
.index-tile-forecast-label{font-size:.46rem;font-weight:950;letter-spacing:.1em;text-transform:uppercase;color:var(--ak-muted)}
.index-tile-forecasts>i{display:flex;align-items:baseline;justify-content:center;gap:.35rem;padding:.15rem .25rem;font-style:normal}
.index-tile-forecasts>i+ i{border-left:1px solid color-mix(in srgb,var(--ak-muted) 12%,transparent)}
.index-tile-forecasts>i small{font-size:.46rem;font-weight:950;color:var(--ak-muted)}
.index-tile-forecasts>i b{font-size:.72rem;font-weight:950;font-variant-numeric:tabular-nums}
:root[data-theme="light"] .index-tile{box-shadow:0 10px 24px rgba(47,74,91,.08)!important}
:root[data-theme="light"] .index-tile-metrics{background:rgba(238,246,247,.72)}
:root[data-theme="light"] .index-tile-chev{background:rgba(255,255,255,.7)}
.index-tile-details{grid-template-columns:1fr!important;gap:.55rem!important}
.index-tile-details .index-card-chart svg{height:7.5rem}.index-card-details>section{min-width:0;border:1px solid rgba(34,211,238,.14);border-radius:.75rem;background:rgba(8,47,73,.08);padding:.8rem}.index-card-details h3,.index-card-chart header{display:flex;justify-content:space-between;color:#67e8f9;font-size:.66rem;font-weight:900;letter-spacing:.09em;text-transform:uppercase}.index-card-chart svg{width:100%;height:9rem;margin-top:.5rem}.index-card-chart>p,.index-card-copy>p,.index-card-members>p{margin-top:.6rem;color:var(--ak-muted);font-size:.72rem;line-height:1.55}.index-card-copy dl{display:grid;grid-template-columns:repeat(3,1fr);gap:.35rem;margin-top:.7rem}.index-card-copy dl div{border:1px solid rgba(148,163,184,.13);border-radius:.45rem;padding:.45rem}.index-card-copy dt{color:var(--ak-muted);font-size:.48rem;font-weight:900;text-transform:uppercase}.index-card-copy dd{margin-top:.15rem;font-size:.72rem;font-weight:900}.index-card-members>a:not(.index-card-all){display:flex;align-items:center;justify-content:space-between;gap:.5rem;margin-top:.45rem;border-bottom:1px solid rgba(148,163,184,.11);padding:.35rem 0;font-size:.65rem;font-weight:800}.index-card-members>a b{color:#34d399;font-size:.55rem}.index-card-all{display:inline-flex;margin-top:.65rem;color:#67e8f9;font-size:.62rem;font-weight:900}@media(max-width:767px){.index-screener{padding-inline:.45rem}.index-card-details{grid-template-columns:1fr}.index-card-chart svg{height:10rem}.index-card-copy>p{font-size:.78rem}.index-screener-card .sms-v2-head>em::after{content:' {{ __('Aktien') }}';color:var(--ak-muted);font-size:.45rem}.index-screener-card{padding:.45rem!important}}

/* --- Higher-contrast, dashboard-style skin --- */
#aggregate-screener{border-radius:1rem;padding-top:1rem;background-attachment:fixed}
:root:not([data-theme="light"]) #aggregate-screener{background-color:#07111f;background-image:linear-gradient(rgba(34,211,238,.03) 1px,transparent 1px),linear-gradient(90deg,rgba(34,211,238,.03) 1px,transparent 1px);background-size:34px 34px,34px 34px}
:root[data-theme="light"] #aggregate-screener{background-color:#e7edf3;background-image:linear-gradient(rgba(14,116,144,.035) 1px,transparent 1px),linear-gradient(90deg,rgba(14,116,144,.035) 1px,transparent 1px);background-size:32px 32px,32px 32px}
#aggregate-screener>header h1{letter-spacing:-.03em}

/* Column headers as solid chips */
.index-region-head{position:static;margin-bottom:.35rem;padding:.6rem .8rem;border:1px solid color-mix(in srgb,#22d3ee 34%,transparent);border-bottom:1px solid color-mix(in srgb,#22d3ee 34%,transparent);border-radius:.7rem;background:color-mix(in srgb,#22d3ee 12%,var(--ak-card));box-shadow:0 6px 16px rgba(5,20,34,.10);color:#0891b2}
:root:not([data-theme="light"]) .index-region-head{background:color-mix(in srgb,#22d3ee 10%,#0d1c2d);color:#5eead4}
.index-region-head span{border-color:color-mix(in srgb,#22d3ee 42%,transparent);background:color-mix(in srgb,#22d3ee 14%,transparent);color:var(--ak-text)}

/* Tiles: solid fill, firm border, real shadow, bold accent rail */
.index-tile{background:var(--ak-card)!important;border:1px solid color-mix(in srgb,var(--index-accent) 34%,var(--ak-border-strong))!important;box-shadow:0 12px 28px rgba(5,20,34,.20),0 1px 2px rgba(5,20,34,.10)!important}
:root[data-theme="light"] .index-tile{background:#ffffff!important;border-color:color-mix(in srgb,var(--index-accent) 40%,#b3c2d1)!important;box-shadow:0 10px 24px rgba(15,23,42,.12),0 1px 2px rgba(15,23,42,.06)!important}
.index-tile::before{inset:0 auto 0 0;width:4px;border-radius:0;opacity:1}
.index-tile:hover{transform:translateY(-2px)}
:root[data-theme="light"] .index-tile:hover{box-shadow:0 16px 34px rgba(15,23,42,.16),0 1px 2px rgba(15,23,42,.07)!important}

/* Inner blocks read as distinct panels */
.index-tile-flag{background:color-mix(in srgb,var(--index-accent) 16%,var(--ak-card));border-color:color-mix(in srgb,var(--index-accent) 42%,transparent)}
.index-tile-metrics{border-color:color-mix(in srgb,var(--ak-muted) 26%,transparent);background:color-mix(in srgb,var(--ak-bg) 55%,transparent)}
:root[data-theme="light"] .index-tile-metrics{background:#eef3f4}
.index-tile-metrics>i+ i,.index-tile-forecasts>i+ i{border-color:color-mix(in srgb,var(--ak-muted) 24%,transparent)}
.index-tile-metrics>i small,.index-tile-forecasts>i small,.index-tile-forecast-label{color:color-mix(in srgb,var(--ak-muted) 90%,var(--ak-text))}
.index-tile-name{font-weight:950}
.index-tile-sym{color:color-mix(in srgb,var(--ak-muted) 88%,var(--ak-text));font-weight:800}

/* === EXPERIMENTAL light theme: "Warm Ivory / Ink / Sage" (index screener only) === */
:root[data-theme="light"] #aggregate-screener{
    --isx-canvas:#f3efe6;--isx-card:#ffffff;--isx-border:#e4dbc9;
    --isx-ink:#26313f;--isx-muted:#7d7360;--isx-accent:#3f6f5e;
    --isx-buy:#2f7d55;--isx-watch:#b07515;--isx-sell:#bd5560;
    background-color:var(--isx-canvas)!important;
    background-image:linear-gradient(rgba(63,111,94,.05) 1px,transparent 1px),linear-gradient(90deg,rgba(63,111,94,.05) 1px,transparent 1px)!important;
    background-size:30px 30px,30px 30px!important;
    color:var(--isx-ink);
}
:root[data-theme="light"] #aggregate-screener > header h1{color:var(--isx-ink)!important}
:root[data-theme="light"] #aggregate-screener > header > span{border-color:var(--isx-border)!important;background:var(--isx-card)!important;color:var(--isx-muted)!important}

/* filter shell + fields */
:root[data-theme="light"] #aggregate-screener .screener-filter-shell > button{border-color:var(--isx-border)!important;background:var(--isx-card)!important;color:var(--isx-accent)!important;box-shadow:0 6px 16px rgba(38,49,63,.06)!important}
:root[data-theme="light"] #aggregate-screener .screener-filter-bar{border-color:var(--isx-border)!important;background:var(--isx-card)!important}
:root[data-theme="light"] #aggregate-screener .screener-filter-reset{border-color:color-mix(in srgb,var(--isx-watch) 45%,transparent)!important;background:color-mix(in srgb,var(--isx-watch) 10%,transparent)!important;color:var(--isx-watch)!important}

/* column heads: solid ink chip, ivory text */
:root[data-theme="light"] #aggregate-screener .index-region-head{
    border:1px solid var(--isx-ink)!important;border-radius:.7rem;background:var(--isx-ink)!important;
    box-shadow:0 8px 18px rgba(38,49,63,.16)!important;color:#f3efe6!important;letter-spacing:.16em}
:root[data-theme="light"] #aggregate-screener .index-region-head span{
    border-color:rgba(243,239,230,.35)!important;background:rgba(243,239,230,.14)!important;color:#f3efe6!important}

/* tiles: pure white on ivory, warm hairline, warm shadow, sage rail (signal-tinted) */
:root[data-theme="light"] #aggregate-screener .index-tile{
    --index-accent:var(--isx-accent);
    background:var(--isx-card)!important;border:1px solid var(--isx-border)!important;
    box-shadow:0 12px 26px rgba(38,49,63,.10),0 1px 2px rgba(38,49,63,.05)!important}
:root[data-theme="light"] #aggregate-screener .index-tile[data-signal=buy]{--index-accent:var(--isx-buy)}
:root[data-theme="light"] #aggregate-screener .index-tile[data-signal=sell]{--index-accent:var(--isx-sell)}
:root[data-theme="light"] #aggregate-screener .index-tile[data-signal=watch]{--index-accent:var(--isx-watch)}
:root[data-theme="light"] #aggregate-screener .index-tile:hover{border-color:color-mix(in srgb,var(--index-accent) 45%,var(--isx-border))!important;box-shadow:0 18px 34px rgba(38,49,63,.15),0 1px 2px rgba(38,49,63,.06)!important}
:root[data-theme="light"] #aggregate-screener .index-tile::before{background:var(--index-accent)!important}

/* inner panels + type */
:root[data-theme="light"] #aggregate-screener .index-tile-flag{border-color:color-mix(in srgb,var(--index-accent) 40%,var(--isx-border))!important;background:color-mix(in srgb,var(--index-accent) 12%,var(--isx-card))!important}
:root[data-theme="light"] #aggregate-screener .index-tile-name{color:var(--isx-ink)!important}
:root[data-theme="light"] #aggregate-screener .index-tile-sym,
:root[data-theme="light"] #aggregate-screener .index-tile-metrics>i small,
:root[data-theme="light"] #aggregate-screener .index-tile-forecasts>i small,
:root[data-theme="light"] #aggregate-screener .index-tile-forecast-label{color:var(--isx-muted)!important}
:root[data-theme="light"] #aggregate-screener .index-tile-metrics{border-color:var(--isx-border)!important;background:#faf7f0!important}
:root[data-theme="light"] #aggregate-screener .index-tile-metrics>i+ i,
:root[data-theme="light"] #aggregate-screener .index-tile-forecasts>i+ i{border-color:var(--isx-border)!important}
:root[data-theme="light"] #aggregate-screener .index-tile-chev{border-color:var(--isx-border)!important;background:#faf7f0!important;color:var(--isx-muted)!important}
:root[data-theme="light"] #aggregate-screener .index-tile-signal[data-signal=buy] b{color:var(--isx-buy)!important}
:root[data-theme="light"] #aggregate-screener .index-tile-signal[data-signal=sell] b{color:var(--isx-sell)!important}
:root[data-theme="light"] #aggregate-screener .index-tile-signal[data-signal=watch] b{color:var(--isx-watch)!important}
:root[data-theme="light"] #aggregate-screener .index-tile-signal[data-signal=buy]{background:color-mix(in srgb,var(--isx-buy) 8%,transparent)!important}
:root[data-theme="light"] #aggregate-screener .index-tile-signal[data-signal=sell]{background:color-mix(in srgb,var(--isx-sell) 8%,transparent)!important}
:root[data-theme="light"] #aggregate-screener .index-tile-forecasts>i b.text-emerald-400{color:var(--isx-buy)!important}
:root[data-theme="light"] #aggregate-screener .index-tile-forecasts>i b.text-rose-400{color:var(--isx-sell)!important}

/* Index Screener: slate-indigo stock terminal, matching Market Overview. */
:root[data-theme="light"] #aggregate-screener{
    --isx-canvas:#f1f5f9;--isx-card:#f8fafc;--isx-border:#c7d2fe;
    --isx-ink:#0f172a;--isx-muted:#64748b;--isx-accent:#4f46e5;
    --isx-buy:#059669;--isx-watch:#7c3aed;--isx-sell:#e11d48;
    background-color:var(--isx-canvas)!important;
    background-image:radial-gradient(circle at 88% 6%,rgba(99,102,241,.10),transparent 25%),radial-gradient(circle at 8% 86%,rgba(139,92,246,.06),transparent 28%),linear-gradient(rgba(79,70,229,.04) 1px,transparent 1px),linear-gradient(90deg,rgba(79,70,229,.04) 1px,transparent 1px)!important;
    background-size:auto,auto,32px 32px,32px 32px!important;
}
:root[data-theme="light"] #aggregate-screener > header h1{color:#0f172a!important}
:root[data-theme="light"] #aggregate-screener > header > span,
:root[data-theme="light"] #aggregate-screener .screener-filter-shell > button,
:root[data-theme="light"] #aggregate-screener .screener-filter-bar{
    border-color:#cbd5e1!important;background:linear-gradient(105deg,#f1f5f9,#f8fafc 72%,#eef2ff)!important;color:#475569!important;box-shadow:0 8px 20px rgba(51,65,85,.07)!important
}
:root[data-theme="light"] #aggregate-screener .screener-filter-shell > button{color:#4f46e5!important}
:root[data-theme="light"] #aggregate-screener .screener-filter-reset{border-color:#a5b4fc!important;background:#eef2ff!important;color:#4338ca!important}

:root[data-theme="light"] #aggregate-screener .index-region-head{
    border:1px solid #6366f1!important;border-bottom:2px solid #818cf8!important;
    background:linear-gradient(105deg,#0f172a 0%,#334155 58%,#3730a3 100%)!important;
    box-shadow:0 10px 24px rgba(49,46,129,.16)!important;color:#f8fafc!important
}
:root[data-theme="light"] #aggregate-screener .index-region-head span{border-color:#818cf8!important;background:rgba(99,102,241,.18)!important;color:#e0e7ff!important}

:root[data-theme="light"] #aggregate-screener .index-tile{
    border:1px solid #a5b4fc!important;background:linear-gradient(105deg,#f1f5f9,#f8fafc 70%,#eef2ff)!important;
    box-shadow:0 12px 28px rgba(49,46,129,.11),inset 3px 0 0 var(--index-accent)!important
}
:root[data-theme="light"] #aggregate-screener .index-tile::before{display:none!important}
:root[data-theme="light"] #aggregate-screener .index-tile:hover{border-color:#6366f1!important;background:linear-gradient(105deg,#eef2ff,#f8fafc)!important;box-shadow:0 17px 34px rgba(79,70,229,.16),inset 3px 0 0 var(--index-accent)!important}
:root[data-theme="light"] #aggregate-screener .index-tile-head{
    margin:-.85rem -.9rem 0!important;padding:.78rem .9rem!important;
    border-bottom:2px solid #818cf8!important;
    background:linear-gradient(105deg,#0f172a 0%,#334155 62%,#3730a3 100%)!important
}
:root[data-theme="light"] #aggregate-screener .index-tile-name{color:#f8fafc!important}
:root[data-theme="light"] #aggregate-screener .index-tile-sym{color:#cbd5e1!important}
:root[data-theme="light"] #aggregate-screener .index-tile-flag{border-color:#818cf8!important;background:rgba(238,242,255,.12)!important}
:root[data-theme="light"] #aggregate-screener .index-tile-chev{border-color:#818cf8!important;background:rgba(99,102,241,.16)!important;color:#e0e7ff!important}
:root[data-theme="light"] #aggregate-screener .index-tile-metrics{border-color:#cbd5e1!important;background:#f1f5f9!important}
:root[data-theme="light"] #aggregate-screener .index-tile-metrics>i+ i,
:root[data-theme="light"] #aggregate-screener .index-tile-forecasts>i+ i{border-color:#cbd5e1!important}
:root[data-theme="light"] #aggregate-screener .index-tile-metrics>i small,
:root[data-theme="light"] #aggregate-screener .index-tile-forecasts>i small,
:root[data-theme="light"] #aggregate-screener .index-tile-forecast-label{color:#64748b!important}
:root[data-theme="light"] #aggregate-screener .index-tile-chev,
:root[data-theme="light"] #aggregate-screener .index-tile-metrics,
:root[data-theme="light"] #aggregate-screener .index-tile-head{transition:border-color .18s ease,background .18s ease,box-shadow .18s ease}

:root[data-theme="light"] #aggregate-screener .index-card-details{border-top-color:#a5b4fc!important}
:root[data-theme="light"] #aggregate-screener .index-card-details>section{border-color:#c7d2fe!important;background:linear-gradient(105deg,#f1f5f9,#eef2ff)!important}
:root[data-theme="light"] #aggregate-screener .index-card-details h3,
:root[data-theme="light"] #aggregate-screener .index-card-chart header,
:root[data-theme="light"] #aggregate-screener .index-card-all{color:#4f46e5!important}

@media(max-width:767px){
    :root[data-theme="light"] #aggregate-screener .index-screener-card{border-color:#a5b4fc!important;background:linear-gradient(105deg,#f1f5f9,#f8fafc 70%,#eef2ff)!important;box-shadow:0 12px 28px rgba(49,46,129,.11),inset 3px 0 0 #6366f1!important}
    :root[data-theme="light"] #aggregate-screener .sms-v2-head{border-color:#818cf8!important;background:linear-gradient(105deg,#0f172a,#334155 62%,#3730a3)!important}
    :root[data-theme="light"] #aggregate-screener .sms-v2-head :is(strong,small,em,svg){color:#f8fafc!important}
}

/* Restrained card treatment: primary slate as frame and typography only. */
:root[data-theme="light"] #aggregate-screener .index-tile{
    border:2px solid #475569!important;
    background:linear-gradient(105deg,#f1f5f9,#f8fafc 72%,#eef2ff)!important;
    box-shadow:0 10px 24px rgba(51,65,85,.10)!important
}
:root[data-theme="light"] #aggregate-screener .index-tile:hover{
    border-color:#4f46e5!important;
    box-shadow:0 14px 30px rgba(79,70,229,.13)!important
}
:root[data-theme="light"] #aggregate-screener .index-tile-head{
    margin:0!important;
    padding:.05rem 0 .05rem .2rem!important;
    border:0!important;
    background:transparent!important;
    box-shadow:none!important
}
:root[data-theme="light"] #aggregate-screener .index-tile-name{color:#334155!important;font-weight:950!important}
:root[data-theme="light"] #aggregate-screener .index-tile-sym{color:#64748b!important}
:root[data-theme="light"] #aggregate-screener .index-tile-flag{border-color:#94a3b8!important;background:#f1f5f9!important}
:root[data-theme="light"] #aggregate-screener .index-tile-chev{border-color:#94a3b8!important;background:#f8fafc!important;color:#475569!important}

@media(max-width:767px){
    :root[data-theme="light"] #aggregate-screener .index-screener-card{border:2px solid #475569!important;background:linear-gradient(105deg,#f1f5f9,#f8fafc 72%,#eef2ff)!important;box-shadow:0 10px 24px rgba(51,65,85,.10)!important}
    :root[data-theme="light"] #aggregate-screener .sms-v2-head{border:0!important;background:transparent!important;box-shadow:none!important}
    :root[data-theme="light"] #aggregate-screener .sms-v2-head strong{color:#334155!important}
    :root[data-theme="light"] #aggregate-screener .sms-v2-head :is(small,em,svg){color:#64748b!important}
}
:root[data-theme="light"] #aggregate-screener .index-tile-flag{
    border:0!important;
    background:transparent!important;
    box-shadow:none!important
}
</style>
</x-app-layout>
