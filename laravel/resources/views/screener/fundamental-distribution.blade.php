<x-app-layout>
<div id="fundamental-dist-page" class="mx-auto max-w-7xl px-3 py-5 text-[var(--ak-text)] sm:px-5 lg:py-8">
    <header class="mb-4">
        <h1 class="text-3xl font-black tracking-tight">{{ __('Fundamental · Verteilung') }}</h1>
    </header>

    <div class="mb-3 flex items-start gap-1.5">
        <p class="text-xs font-semibold text-[var(--ak-muted)]">{{ __('Verteilung der gesamten Aktien-Universum über die 4 Kernkennzahlen, je Kennzahl als eigenes Balkendiagramm. Regler filtern ab welchem Dezil Balken hervorgehoben bleiben. Klick auf eine Aktie in der Liste öffnet ihre eigene Detailseite.') }}</p>
        <div class="fundamental-help" x-data="{ open: false }" @keydown.escape.window="open = false">
            <button type="button" class="fundamental-help-btn" @click="open = !open" :aria-expanded="open" aria-label="{{ __('Hilfe: Kennzahlen und Balkendiagramme erklärt') }}">
                <x-heroicon-o-question-mark-circle class="h-4 w-4" />
            </button>
            <div class="fundamental-help-backdrop" x-show="open" x-cloak x-transition.opacity @click="open = false">
                <div class="fundamental-help-panel" @click.stop>
                    <button type="button" class="fundamental-help-close" @click="open = false" aria-label="{{ __('Schließen') }}">&times;</button>
                    <h4>{{ __('Kennzahlen') }}</h4>
                    <dl>
                        <dt>{{ __('KGV (Kurs-Gewinn-Verhältnis)') }}</dt>
                        <dd>{{ __('Aktienkurs geteilt durch Gewinn je Aktie (trailing, letzte 12 Monate). Niedriger = die Aktie kostet weniger pro Euro/Dollar Gewinn – gilt grob als "günstiger".') }}</dd>
                        <dt>{{ __('Dividendenrendite') }}</dt>
                        <dd>{{ __('Jährliche Dividende geteilt durch den Aktienkurs, in Prozent. Höher = mehr Ausschüttung pro investiertem Euro/Dollar.') }}</dd>
                        <dt>{{ __('ROE (Eigenkapitalrendite)') }}</dt>
                        <dd>{{ __('Jahresgewinn geteilt durch das Eigenkapital, in Prozent. Höher ist besser, aber auch stark schuldenfinanzierte Firmen können hier künstlich hoch stehen.') }}</dd>
                        <dt>{{ __('Gewinnwachstum') }}</dt>
                        <dd>{{ __('Wachstum des Quartalsgewinns gegenüber demselben Quartal im Vorjahr, in Prozent.') }}</dd>
                    </dl>
                    <h4>{{ __('Balkendiagramme benutzen') }}</h4>
                    <p>{{ __('Jedes Diagramm zeigt eine Kennzahl in 10 Dezile eingeteilt (gleich viele Aktien pro Dezil) - die Balkenhöhe ist die Anzahl Aktien in diesem Dezil.') }}</p>
                    <p>{{ __('Die gestrichelte Linie ist ein Regler: anfassen und ziehen, beim Loslassen wird gefiltert. Bei KGV wirkt der Regler als Obergrenze ("bis X"), bei den anderen drei Kennzahlen als Untergrenze ("ab X").') }}</p>
                    <p>{{ __('Da alle 4 Diagramme dieselbe Aktienauswahl teilen, wirkt ein Regler auf allen 4 gleichzeitig - auch auf Diagramme, die die gezogene Kennzahl gar nicht selbst zeigen. Balken, die dadurch Aktien verloren haben, werden abgedunkelt statt ausgeblendet.') }}</p>
                </div>
            </div>
        </div>
    </div>

    @php
        $allParams = ['cap' => $capGroup, 'sector' => $sector, 'country' => $country, 'region' => $region, 'q' => $tableSearch ?: null, 'sort' => $table['sort'] ?? null, 'dir' => $table['dir'] ?? null];
        foreach ($metricRangeParams as $param => $range) {
            $allParams["{$param}_min"] = $range['min'];
            $allParams["{$param}_max"] = $range['max'];
        }
        $allParams = array_filter($allParams, fn ($v) => $v !== null && $v !== '');
        $link = fn (array $overrides) => route('fundamental.distribution', array_filter(array_merge($allParams, $overrides), fn ($v) => $v !== null && $v !== ''));
    @endphp
    <div class="mb-4 flex flex-wrap items-center gap-3">
        <div class="fundamental-cap-filters flex flex-wrap gap-2">
            <a href="{{ $link(['cap' => null]) }}" class="fundamental-cap-pill {{ $capGroup === null ? 'is-active' : '' }}">{{ __('Alle Größen') }}</a>
            <a href="{{ $link(['cap' => 'small']) }}" class="fundamental-cap-pill {{ $capGroup === 'small' ? 'is-active' : '' }}">{{ __('Small Cap · unter 2 Mrd.') }}</a>
            <a href="{{ $link(['cap' => 'mid']) }}" class="fundamental-cap-pill {{ $capGroup === 'mid' ? 'is-active' : '' }}">{{ __('Mid Cap · 2 bis unter 10 Mrd.') }}</a>
            <a href="{{ $link(['cap' => 'large']) }}" class="fundamental-cap-pill {{ $capGroup === 'large' ? 'is-active' : '' }}">{{ __('Large Cap · ab 10 Mrd.') }}</a>
        </div>

        <form method="GET" class="flex flex-wrap gap-2">
            @foreach($allParams as $key => $value)
                @if(!in_array($key, ['sector', 'country', 'region'], true))<input type="hidden" name="{{ $key }}" value="{{ $value }}">@endif
            @endforeach
            <select name="sector" onchange="this.form.requestSubmit()" class="fundamental-select">
                <option value="">{{ __('Alle Sektoren') }}</option>
                @foreach($filterOptions['sectors'] as $s)
                    <option value="{{ $s }}" @selected($sector === $s)>{{ __($s) }}</option>
                @endforeach
            </select>
            <select name="region" onchange="this.form.requestSubmit()" class="fundamental-select">
                <option value="">{{ __('Alle Regionen') }}</option>
                @foreach(\App\Services\FundamentalHeatmapService::REGIONS as $key => $r)
                    <option value="{{ $key }}" @selected($region === $key)>{{ __($r['label']) }}</option>
                @endforeach
            </select>
            <select name="country" onchange="this.form.requestSubmit()" class="fundamental-select">
                <option value="">{{ __('Alle Länder') }}</option>
                @foreach($filterOptions['countries'] as $c)
                    <option value="{{ $c }}" @selected($country === $c)>{{ \App\Services\FundamentalHeatmapService::countryFlag($c) }} {{ \App\Services\FundamentalHeatmapService::countryName($c) }}</option>
                @endforeach
            </select>
        </form>

        @if($allParams !== [])
            <a href="{{ route('fundamental.distribution') }}" class="fundamental-reset-pill" title="{{ __('Alle Filter zurücksetzen') }}">
                <x-heroicon-o-x-mark class="h-3.5 w-3.5" />{{ __('Zurücksetzen') }}
            </a>
        @endif
    </div>

    @php
        $boundariesByMetric = collect($histograms)->mapWithKeys(fn ($m) => [$m['key'] => $m['boundaries_raw']]);
        $metricUrlParam = ['trailing_pe' => 'pe', 'dividend_yield' => 'dy', 'return_on_equity' => 'roe', 'earnings_growth' => 'eg'];
        $invertedMetrics = ['trailing_pe'];

        $initialThresholds = [];
        foreach ($metricUrlParam as $key => $param) {
            $bounds = $boundariesByMetric[$key] ?? [];
            $inverted = in_array($key, $invertedMetrics, true);
            $active = $inverted ? ($metricRangeParams[$param]['max'] ?? null) : ($metricRangeParams[$param]['min'] ?? null);
            $default = $inverted ? 9 : 0;

            if ($active === null || $bounds === []) {
                $initialThresholds[$key] = $default;

                continue;
            }

            $activeValue = (float) $active;
            $closestIndex = null;
            $closestDiff = null;
            foreach ($bounds as $index => $boundary) {
                $diff = abs($boundary - $activeValue);
                if ($closestDiff === null || $diff < $closestDiff) {
                    $closestDiff = $diff;
                    $closestIndex = $index;
                }
            }

            $initialThresholds[$key] = $inverted ? $closestIndex : $closestIndex + 1;
        }
    @endphp
    <section class="fundamental-dist-grid"
         x-data="{
            thresholds: {{ json_encode($initialThresholds) }},
            boundaries: {{ json_encode($boundariesByMetric) }},
            urlParam: {{ json_encode($metricUrlParam) }},
            inverted: {{ json_encode($invertedMetrics) }},
            dragging: null, changed: false,
            pctFromEvent(e, panel) {
                const r = this.$refs['plot' + panel].getBoundingClientRect();
                const p = (e.clientX - r.left) / r.width;
                return Math.min(9, Math.max(0, Math.round(Math.min(1, Math.max(0, p)) * 10)));
            },
            onMove(e) {
                if (!this.dragging) return;
                let v = this.pctFromEvent(e, this.dragging.panel);
                if (this.inverted.includes(this.dragging.metric)) v = Math.max(0, v - 1);
                if (this.thresholds[this.dragging.metric] !== v) this.changed = true;
                this.thresholds[this.dragging.metric] = v;
            },
            onRelease() {
                if (this.dragging && this.changed) {
                    const form = this.$refs.filterForm;
                    for (const metric in this.thresholds) {
                        const t = this.thresholds[metric], b = this.boundaries[metric], p = this.urlParam[metric];
                        const isInverted = this.inverted.includes(metric);
                        const minField = form.elements[p + '_min'], maxField = form.elements[p + '_max'];
                        minField.value = ''; maxField.value = '';
                        if (!b || b.length === 0) continue;
                        if (isInverted) {
                            if (t < 9) maxField.value = b[t];
                        } else {
                            if (t > 0) minField.value = b[t - 1];
                        }
                    }
                    form.elements['page'].value = '';
                    form.requestSubmit();
                }
                this.dragging = null;
            },
         }"
         @pointermove.window="onMove($event)" @pointerup.window="onRelease()">
        <form method="GET" x-ref="filterForm" class="hidden">
            @foreach($allParams as $key => $value)
                @unless(str_ends_with($key, '_min') || str_ends_with($key, '_max'))
                    <input type="hidden" name="{{ $key }}" value="{{ $value }}">
                @endunless
            @endforeach
            <input type="hidden" name="page" value="">
            @foreach($metricUrlParam as $param)
                <input type="hidden" name="{{ $param }}_min" value="{{ $metricRangeParams[$param]['min'] ?? '' }}">
                <input type="hidden" name="{{ $param }}_max" value="{{ $metricRangeParams[$param]['max'] ?? '' }}">
            @endforeach
        </form>
        @foreach($histograms as $i => $m)
            @php
                $inverted = in_array($m['key'], $invertedMetrics, true);
                $pos = $inverted ? "(thresholds.{$m['key']} + 1)" : "thresholds.{$m['key']}";
                $dragStyle = 'left: calc((100% - 27px) * ${'.$pos.'} / 10 + ${'.$pos.' * 3}px - ${'.$pos.' === 0 ? 0 : 1.5}px)';
                $thresholdExpr = $inverted
                    ? "thresholds.{$m['key']} === 9 ? '".__('Alle')."' : '".__('bis')." ' + ".json_encode($m['ticks'])."[thresholds.{$m['key']} + 1]"
                    : "thresholds.{$m['key']} === 0 ? '".__('Alle')."' : '".__('ab')." ' + ".json_encode($m['ticks'])."[thresholds.{$m['key']}]";
            @endphp
            <div class="fundamental-dist-card">
                <header class="mb-2">
                    <h3 class="text-xs font-black">{{ $m['label'] }} <span class="font-semibold text-[var(--ak-muted)]">({{ $m['unit'] }})</span></h3>
                    <p class="text-[10px] font-semibold text-[var(--ak-muted)]">{{ $m['instruments_used'] }} {{ __('Aktien') }}</p>
                </header>
                <div x-ref="plot{{ $i }}" class="fundamental-dist-plot">
                    @for($b = 0; $b <= 9; $b++)
                        @php
                            $count = $m['counts'][$b];
                            $intensity = $m['max'] > 0 ? $count / $m['max'] : 0;
                            $dim = $inverted ? "{$b} > thresholds.{$m['key']}" : "{$b} < thresholds.{$m['key']}";
                        @endphp
                        <div class="fundamental-dist-bar-wrap">
                            <div
                                class="fundamental-dist-bar {{ $m['reduced'][$b] ? 'is-dimmed' : '' }}"
                                :class="({{ $dim }}) && 'is-dimmed'"
                                style="height: {{ max(2, round($intensity * 100)) }}%"
                                title="{{ $m['ticks'][$b] }} {{ $m['unit'] }}: {{ $count }} {{ __('Aktien') }}"
                            ><span class="fundamental-dist-bar-value {{ $count === 0 ? 'opacity-30' : '' }}">{{ $count }}</span></div>
                        </div>
                    @endfor
                    <span class="fundamental-heatmap-drag fundamental-heatmap-drag--x" :style="`{{ $dragStyle }}`" @pointerdown="dragging = { metric: '{{ $m['key'] }}', panel: {{ $i }} }">
                        <b></b><i></i>
                    </span>
                </div>
                <div class="fundamental-dist-ticks">
                    @foreach($m['ticks'] as $tick)
                        <span>{{ $tick }}</span>
                    @endforeach
                </div>
                <p class="mt-1.5 text-[9px] font-bold text-[var(--ak-muted)]">
                    {{ __('Filter:') }} <b class="text-[var(--ak-text)]" x-text="{{ $thresholdExpr }}"></b>
                </p>
            </div>
        @endforeach
    </section>

    <div class="ak-master-card fundamental-table-card mt-5">
        <div class="ak-master-card-header fundamental-table-head">
            <h3 class="text-sm font-black">{{ __('Aktien') }}</h3>
            <form method="GET" class="fundamental-table-search">
                @foreach($allParams as $key => $value)
                    @if(!in_array($key, ['q'], true))<input type="hidden" name="{{ $key }}" value="{{ $value }}">@endif
                @endforeach
                <input type="text" name="q" value="{{ $tableSearch }}" placeholder="{{ __('Symbol oder Name suchen') }}" class="ak-input h-8 text-xs" oninput="clearTimeout(this._t);this._t=setTimeout(()=>this.form.requestSubmit(),400)">
            </form>
        </div>

        @php
            $sortLink = function (string $col, string $label) use ($table, $link) {
                $nextDir = ($table['sort'] === $col && $table['dir'] === 'desc') ? 'asc' : 'desc';
                $icon = $table['sort'] === $col ? ($table['dir'] === 'desc' ? '↓' : '↑') : '';

                return '<a href="'.$link(['sort' => $col, 'dir' => $nextDir]).'" class="fundamental-table-sort">'.$label.' '.$icon.'</a>';
            };
        @endphp

        <div class="fundamental-table-scroll">
            <table class="fundamental-table">
                <thead>
                    <tr>
                        <th>{!! $sortLink('symbol', __('Symbol')) !!}</th>
                        <th>{!! $sortLink('name', __('Name')) !!}</th>
                        <th>{{ __('Land') }}</th>
                        <th>{{ __('Sektor') }}</th>
                        <th class="text-right">{!! $sortLink('trailing_pe', __('KGV')) !!}</th>
                        <th class="text-right">{!! $sortLink('dividend_yield', __('Div.-Rendite')) !!}</th>
                        <th class="text-right">{!! $sortLink('return_on_equity', __('ROE')) !!}</th>
                        <th class="text-right">{!! $sortLink('earnings_growth', __('Gewinnwachstum')) !!}</th>
                        <th class="text-right">{!! $sortLink('market_cap', __('Marktkap.')) !!}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($table['rows'] as $row)
                        <tr onclick="window.location='{{ route('fundamental.index', ['symbol' => $row->symbol]) }}'">
                            <td class="font-black">{{ $row->symbol }}</td>
                            <td class="fundamental-table-name">{{ $row->name }}</td>
                            <td>{{ \App\Services\FundamentalHeatmapService::countryFlag($row->country) }} {{ $row->country }}</td>
                            <td>{{ __($row->sector ?: '—') }}</td>
                            <td class="text-right tabular-nums">{{ $row->trailing_pe !== null ? number_format($row->trailing_pe, 1, ',', '.') : '–' }}</td>
                            <td class="text-right tabular-nums">
                                @if($row->dividend_yield !== null)
                                    @if($row->dividend_yield > 20)
                                        <span title="{{ __('Unplausibel hoch – möglicherweise fehlerhafter oder veralteter Rohwert der Datenquelle.') }}">⚠️ {{ number_format($row->dividend_yield, 2, ',', '.') }} %</span>
                                    @else
                                        {{ number_format($row->dividend_yield, 2, ',', '.') }} %
                                    @endif
                                @else
                                    –
                                @endif
                            </td>
                            <td class="text-right tabular-nums">{{ $row->return_on_equity !== null ? number_format($row->return_on_equity, 1, ',', '.').' %' : '–' }}</td>
                            <td class="text-right tabular-nums">{{ $row->earnings_growth !== null ? number_format($row->earnings_growth, 1, ',', '.').' %' : '–' }}</td>
                            <td class="text-right tabular-nums">{{ $row->market_cap !== null ? number_format($row->market_cap / 1_000_000_000, 1, ',', '.').' Mrd.' : '–' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="9" class="py-6 text-center text-[var(--ak-muted)]">{{ __('Keine Treffer.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @php $lastPage = max(1, (int) ceil($table['total'] / $table['per_page'])); @endphp
        <div class="fundamental-table-pagination">
            <span>{{ __(':total Aktien · Seite :page von :last', ['total' => $table['total'], 'page' => $table['page'], 'last' => $lastPage]) }}</span>
            <div class="flex gap-2">
                @if($table['page'] > 1)
                    <a href="{{ $link(['page' => $table['page'] - 1]) }}" class="fundamental-cap-pill">{{ __('Zurück') }}</a>
                @endif
                @if($table['page'] < $lastPage)
                    <a href="{{ $link(['page' => $table['page'] + 1]) }}" class="fundamental-cap-pill">{{ __('Weiter') }}</a>
                @endif
            </div>
        </div>
    </div>
</div>

<style>
    #fundamental-dist-page .fundamental-dist-grid { display: grid; grid-template-columns: 1fr; gap: .9rem; }
    @media (min-width: 700px) { #fundamental-dist-page .fundamental-dist-grid { grid-template-columns: repeat(2, 1fr); } }
    @media (min-width: 1300px) { #fundamental-dist-page .fundamental-dist-grid { grid-template-columns: repeat(4, 1fr); } }
    #fundamental-dist-page .fundamental-dist-card { border: 1px solid var(--ak-border); background: var(--ak-card); border-radius: 1rem; padding: .9rem; box-shadow: var(--ak-shadow); }
    #fundamental-dist-page .fundamental-dist-plot { position: relative; display: flex; align-items: flex-end; gap: 3px; height: 140px; }
    #fundamental-dist-page .fundamental-dist-bar-wrap { flex: 1 1 0; height: 100%; display: flex; align-items: flex-end; }
    #fundamental-dist-page .fundamental-dist-bar {
        position: relative; width: 100%; min-height: 4px; border-radius: 3px 3px 0 0;
        background: linear-gradient(180deg, #22d3ee, color-mix(in srgb, #22d3ee 45%, transparent));
        transition: opacity .15s ease;
    }
    #fundamental-dist-page .fundamental-dist-bar.is-dimmed { opacity: .18; }
    #fundamental-dist-page .fundamental-dist-bar-value { position: absolute; top: -14px; left: 50%; transform: translateX(-50%); font-size: .58rem; font-weight: 900; color: var(--ak-text); white-space: nowrap; }
    #fundamental-dist-page .fundamental-dist-ticks { display: flex; gap: 3px; margin-top: .35rem; }
    #fundamental-dist-page .fundamental-dist-ticks span { flex: 1 1 0; text-align: center; font-size: .56rem; font-weight: 700; color: var(--ak-muted); }

    #fundamental-dist-page .fundamental-cap-pill { display: inline-flex; align-items: center; padding: .5rem .9rem; border-radius: .7rem; border: 1px solid var(--ak-border); font-size: .72rem; font-weight: 800; color: var(--ak-muted); text-decoration: none; transition: border-color .15s ease, background .15s ease, color .15s ease; }
    #fundamental-dist-page .fundamental-cap-pill:hover { border-color: color-mix(in srgb, #22d3ee 45%, transparent); color: var(--ak-text); }
    #fundamental-dist-page .fundamental-cap-pill.is-active { border-color: #22d3ee; background: color-mix(in srgb, #22d3ee 14%, transparent); color: #22d3ee; }
    #fundamental-dist-page .fundamental-select { padding: .5rem .7rem; border-radius: .7rem; border: 1px solid var(--ak-border); background: var(--ak-card); font-size: .72rem; font-weight: 700; color: var(--ak-text); }
    #fundamental-dist-page .fundamental-reset-pill { display: inline-flex; align-items: center; gap: .3rem; padding: .5rem .8rem; border-radius: .7rem; border: 1px solid rgba(251,113,133,.35); background: rgba(251,113,133,.06); font-size: .72rem; font-weight: 800; color: #fb7185; text-decoration: none; transition: background .15s ease; }
    #fundamental-dist-page .fundamental-reset-pill:hover { background: rgba(251,113,133,.14); }
    #fundamental-dist-page .fundamental-help { position: relative; flex: none; }
    #fundamental-dist-page .fundamental-help-btn {
        display: flex; align-items: center; justify-content: center; width: 1.15rem; height: 1.15rem;
        border-radius: 999px; border: 1px solid var(--ak-border); background: var(--ak-card);
        color: var(--ak-muted); cursor: pointer; transition: border-color .15s ease, color .15s ease;
    }
    #fundamental-dist-page .fundamental-help-btn:hover,
    #fundamental-dist-page .fundamental-help-btn[aria-expanded="true"] { border-color: #22d3ee; color: #22d3ee; }
    #fundamental-dist-page .fundamental-help-backdrop { position: fixed; inset: 0; z-index: 200; display: flex; align-items: center; justify-content: center; padding: 1rem; background: rgba(2,8,20,.66); }
    #fundamental-dist-page .fundamental-help-panel { position: relative; width: min(30rem, 92vw); max-height: 80vh; overflow-y: auto; padding: 1.1rem 1.25rem; border-radius: 1rem; border: 1px solid rgba(83,226,240,.25); background: #071725; box-shadow: 0 30px 90px rgba(0,0,0,.6); }
    #fundamental-dist-page .fundamental-help-close { position: absolute; top: .6rem; right: .6rem; display: flex; align-items: center; justify-content: center; width: 1.6rem; height: 1.6rem; border-radius: .5rem; border: 1px solid rgba(83,226,240,.25); background: transparent; color: #9bb4ba; font-size: 1.1rem; line-height: 1; cursor: pointer; transition: border-color .15s ease, color .15s ease; }
    #fundamental-dist-page .fundamental-help-close:hover { color: #56e7f1; border-color: #56e7f1; }
    #fundamental-dist-page .fundamental-help-panel h4 { font-size: .78rem; font-weight: 900; color: #edf9fa; margin: 0 .8rem .4rem 0; }
    #fundamental-dist-page .fundamental-help-panel h4:not(:first-child) { margin-top: .8rem; }
    #fundamental-dist-page .fundamental-help-panel dt { font-size: .7rem; font-weight: 800; color: #56e7f1; margin-top: .5rem; }
    #fundamental-dist-page .fundamental-help-panel dt:first-child { margin-top: 0; }
    #fundamental-dist-page .fundamental-help-panel dd { font-size: .7rem; font-weight: 500; line-height: 1.5; color: #9bb4ba; margin: .15rem 0 0; }
    #fundamental-dist-page .fundamental-help-panel p { font-size: .7rem; font-weight: 500; line-height: 1.5; color: #9bb4ba; margin: .5rem 0 0; }

    #fundamental-dist-page .fundamental-heatmap-drag { position: absolute; z-index: 25; display: block; touch-action: none; top: 0; bottom: 0; width: 16px; margin-left: -8px; cursor: ew-resize; }
    #fundamental-dist-page .fundamental-heatmap-drag b { position: absolute; inset: 0 auto 0 50%; width: 2px; transform: translateX(-50%); background: repeating-linear-gradient(to bottom, rgba(34,211,238,.88) 0 4px, transparent 4px 7px); filter: drop-shadow(0 0 2px rgba(34,211,238,.45)); }
    #fundamental-dist-page .fundamental-heatmap-drag i { position: absolute; top: 50%; left: 50%; width: 7px; height: 7px; transform: translate(-50%, -50%) rotate(45deg); border: 1px solid rgba(103,232,249,.85); border-radius: 2px; background: #0b2131; box-shadow: 0 0 3px rgba(34,211,238,.45); }

    #fundamental-dist-page .fundamental-table-head { display: flex; align-items: center; justify-content: space-between; gap: .75rem; padding: 1rem 1.1rem; flex-wrap: wrap; }
    #fundamental-dist-page .fundamental-table-search input { width: 220px; }
    #fundamental-dist-page .fundamental-table-scroll { overflow-x: auto; }
    #fundamental-dist-page .fundamental-table { width: 100%; border-collapse: collapse; font-size: .74rem; }
    #fundamental-dist-page .fundamental-table th { padding: .55rem .9rem; text-align: left; font-size: .62rem; font-weight: 800; color: var(--ak-muted); text-transform: uppercase; letter-spacing: .02em; border-bottom: 1px solid var(--ak-border); white-space: nowrap; }
    #fundamental-dist-page .fundamental-table td { padding: .55rem .9rem; border-bottom: 1px solid var(--ak-border); white-space: nowrap; }
    #fundamental-dist-page .fundamental-table tbody tr { cursor: pointer; }
    #fundamental-dist-page .fundamental-table tbody tr:hover { background: var(--ak-surface-muted); }
    #fundamental-dist-page .fundamental-table-name { max-width: 240px; overflow: hidden; text-overflow: ellipsis; }
    #fundamental-dist-page .fundamental-table-sort { color: inherit; text-decoration: none; }
    #fundamental-dist-page .fundamental-table-sort:hover { color: #22d3ee; }
    #fundamental-dist-page .fundamental-table-pagination { display: flex; align-items: center; justify-content: space-between; gap: .75rem; padding: .85rem 1.1rem; font-size: .68rem; color: var(--ak-muted); flex-wrap: wrap; }
</style>
</x-app-layout>
