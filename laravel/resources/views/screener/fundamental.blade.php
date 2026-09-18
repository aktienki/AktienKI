<x-app-layout>
<div id="fundamental-page" x-data="{ active: 'quarters' }" class="mx-auto max-w-7xl px-3 py-5 text-[var(--ak-text)] sm:px-5 lg:py-8">
    <header class="mb-4 flex items-center justify-between gap-3">
        <div>
            <h1 class="text-3xl font-black tracking-tight">{{ __('Fundamental') }}</h1>
            @if($selected)
                <p class="mt-1 text-xs font-semibold text-[var(--ak-muted)]">{{ $selected->symbol }} · {{ $selected->name }}</p>
            @endif
        </div>
    </header>

    @if(!$selected)
        <p class="mb-3 text-xs font-semibold text-[var(--ak-muted)]">{{ __('Verteilung der gesamten Aktien-Universum über die 4 Kernkennzahlen. Regler filtern je Achse ab welchem Dezil Zellen hervorgehoben bleiben. Klick auf eine Aktie im Screener öffnet ihre eigene Detailseite.') }}</p>

        @php
            $carry = ['q' => $tableSearch ?: null, 'sort' => $table['sort'] ?? null, 'dir' => $table['dir'] ?? null];
        @endphp
        <div class="mb-4 flex flex-wrap items-center gap-3">
            <div class="fundamental-cap-filters flex flex-wrap gap-2">
                <a href="{{ route('fundamental.index', array_filter(['sector' => $sector, 'country' => $country, 'region' => $region, ...$carry])) }}" class="fundamental-cap-pill {{ $capGroup === null ? 'is-active' : '' }}">{{ __('Alle Größen') }}</a>
                <a href="{{ route('fundamental.index', array_filter(['cap' => 'small', 'sector' => $sector, 'country' => $country, 'region' => $region, ...$carry])) }}" class="fundamental-cap-pill {{ $capGroup === 'small' ? 'is-active' : '' }}">{{ __('Small Cap · unter 2 Mrd.') }}</a>
                <a href="{{ route('fundamental.index', array_filter(['cap' => 'mid', 'sector' => $sector, 'country' => $country, 'region' => $region, ...$carry])) }}" class="fundamental-cap-pill {{ $capGroup === 'mid' ? 'is-active' : '' }}">{{ __('Mid Cap · 2 bis unter 10 Mrd.') }}</a>
                <a href="{{ route('fundamental.index', array_filter(['cap' => 'large', 'sector' => $sector, 'country' => $country, 'region' => $region, ...$carry])) }}" class="fundamental-cap-pill {{ $capGroup === 'large' ? 'is-active' : '' }}">{{ __('Large Cap · ab 10 Mrd.') }}</a>
            </div>

            <form method="GET" class="flex flex-wrap gap-2">
                @if($capGroup)<input type="hidden" name="cap" value="{{ $capGroup }}">@endif
                @foreach($carry as $key => $value)
                    @if($value !== null)<input type="hidden" name="{{ $key }}" value="{{ $value }}">@endif
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
        </div>

        <section class="ak-heatmap-metric-grid grid w-full grid-cols-1 items-start gap-3 md:grid-cols-2 xl:grid-cols-4"
             x-data="{
                thresholds: {
                    trailing_pe: { min: 0, max: 9 }, dividend_yield: { min: 0, max: 9 },
                    market_cap: { min: 0, max: 9 }, revenue_growth: { min: 0, max: 9 },
                },
                dragging: null,
                pctFromEvent(e, axis, panel) {
                    const r = this.$refs['plot' + panel].getBoundingClientRect();
                    const p = axis === 'x' ? (e.clientX - r.left) / r.width : (r.bottom - e.clientY) / r.height;
                    return Math.min(9, Math.max(0, Math.round(Math.min(1, Math.max(0, p)) * 10)));
                },
                onMove(e) {
                    if (!this.dragging) return;
                    const v = this.pctFromEvent(e, this.dragging.axis, this.dragging.panel);
                    const t = this.thresholds[this.dragging.metric];
                    if (this.dragging.bound === 'min') { t.min = Math.min(v, t.max); } else { t.max = Math.max(v, t.min); }
                },
             }"
             @pointermove.window="onMove($event)" @pointerup.window="dragging = null">
            @foreach($panels as $i => $panel)
                <div class="fundamental-heatmap-card flex h-auto min-w-0 flex-col rounded-2xl border border-[var(--ak-border)] bg-[var(--ak-card)] p-3 pb-4 shadow-[var(--ak-shadow)]">
                    <header class="mb-2">
                        <h3 class="text-xs font-black">{{ $panel['title'] }}</h3>
                        <p class="text-[10px] font-semibold text-[var(--ak-muted)]">{{ $panel['instruments_used'] }} {{ __('Aktien') }}</p>
                    </header>

                    <div class="flex gap-1">
                        <div class="flex flex-col justify-between py-[1px] text-right text-[6px] font-bold text-[var(--ak-muted)]">
                            @for($yb = 9; $yb >= 0; $yb--)
                                <span>{{ $panel['y_ticks'][$yb] }}</span>
                            @endfor
                        </div>
                        <div class="min-w-0 flex-1">
                            <div x-ref="plot{{ $i }}" class="fundamental-heatmap-plot relative grid aspect-square h-auto w-full grid-cols-10 grid-rows-10 gap-[3px]">
                                @for($yb = 9; $yb >= 0; $yb--)
                                    @for($xb = 0; $xb <= 9; $xb++)
                                        @php
                                            $count = $panel['grid'][$yb][$xb];
                                            $intensity = $panel['max'] > 0 ? $count / $panel['max'] : 0;
                                        @endphp
                                        <div
                                            class="fundamental-heatmap-cell relative flex aspect-square min-h-0 min-w-0 items-center justify-center rounded-[3px] border border-[rgba(34,211,238,.10)]"
                                            :class="({{ $xb }} < thresholds.{{ $panel['x_key'] }}.min || {{ $xb }} > thresholds.{{ $panel['x_key'] }}.max || {{ $yb }} < thresholds.{{ $panel['y_key'] }}.min || {{ $yb }} > thresholds.{{ $panel['y_key'] }}.max) && 'is-dimmed'"
                                            style="background-color: color-mix(in srgb, #22d3ee {{ round($intensity * 55, 1) }}%, transparent);"
                                            title="{{ __(':x_label :x_val :x_unit / :y_label :y_val :y_unit: :n Aktien', ['x_label' => $panel['x_label'], 'x_val' => $panel['x_ticks'][$xb], 'x_unit' => $panel['x_unit'], 'y_label' => $panel['y_label'], 'y_val' => $panel['y_ticks'][$yb], 'y_unit' => $panel['y_unit'], 'n' => $count]) }}"
                                        ><span class="text-[6px] font-black tabular-nums text-[var(--ak-text)] {{ $count === 0 ? 'opacity-20' : '' }}">{{ $count }}</span></div>
                                    @endfor
                                @endfor

                                <span class="fundamental-heatmap-drag fundamental-heatmap-drag--x" :style="`left: calc(${thresholds.{{ $panel['x_key'] }}.min * 10}% - ${thresholds.{{ $panel['x_key'] }}.min === 0 ? 0 : 1.5}px)`" @pointerdown="dragging = { metric: '{{ $panel['x_key'] }}', axis: 'x', bound: 'min', panel: {{ $i }} }">
                                    <b></b><i></i>
                                </span>
                                <span class="fundamental-heatmap-drag fundamental-heatmap-drag--x" :style="`left: calc(${(thresholds.{{ $panel['x_key'] }}.max + 1) * 10}% - ${thresholds.{{ $panel['x_key'] }}.max === 9 ? 3 : 1.5}px)`" @pointerdown="dragging = { metric: '{{ $panel['x_key'] }}', axis: 'x', bound: 'max', panel: {{ $i }} }">
                                    <b></b><i></i>
                                </span>
                                <span class="fundamental-heatmap-drag fundamental-heatmap-drag--y" :style="`bottom: calc(${thresholds.{{ $panel['y_key'] }}.min * 10}% - ${thresholds.{{ $panel['y_key'] }}.min === 0 ? 0 : 1.5}px)`" @pointerdown="dragging = { metric: '{{ $panel['y_key'] }}', axis: 'y', bound: 'min', panel: {{ $i }} }">
                                    <b></b><i></i>
                                </span>
                                <span class="fundamental-heatmap-drag fundamental-heatmap-drag--y" :style="`bottom: calc(${(thresholds.{{ $panel['y_key'] }}.max + 1) * 10}% - ${thresholds.{{ $panel['y_key'] }}.max === 9 ? 3 : 1.5}px)`" @pointerdown="dragging = { metric: '{{ $panel['y_key'] }}', axis: 'y', bound: 'max', panel: {{ $i }} }">
                                    <b></b><i></i>
                                </span>
                            </div>
                            <div class="mt-1 flex justify-between text-[6px] font-bold text-[var(--ak-muted)]">
                                @for($xb = 0; $xb <= 9; $xb++)
                                    <span>{{ $panel['x_ticks'][$xb] }}</span>
                                @endfor
                            </div>
                        </div>
                    </div>
                    <p class="mt-1 text-center text-[8px] font-black uppercase tracking-[.1em] text-[var(--ak-muted)]">{{ $panel['x_label'] }} <span class="normal-case tracking-normal text-[var(--ak-muted)]">({{ $panel['x_unit'] }})</span></p>
                    <p class="mt-1.5 flex items-center justify-between text-[9px] font-bold text-[var(--ak-muted)]">
                        @php
                            $rangeExpr = fn (string $metric, array $ticks) =>
                                "(thresholds.{$metric}.min === 0 && thresholds.{$metric}.max === 9) ? '".__('Alle')."' : "
                                .json_encode($ticks)."[thresholds.{$metric}.min] + ' – ' + (thresholds.{$metric}.max === 9 ? ".json_encode(end($ticks))." + '+' : ".json_encode($ticks)."[thresholds.{$metric}.max + 1])";
                        @endphp
                        <span>{{ $panel['y_label'] }} <span class="opacity-70">({{ $panel['y_unit'] }})</span>: <b class="text-[var(--ak-text)]" x-text="{{ $rangeExpr($panel['y_key'], $panel['y_ticks']) }}"></b></span>
                        <span>{{ $panel['x_label'] }} <span class="opacity-70">({{ $panel['x_unit'] }})</span>: <b class="text-[var(--ak-text)]" x-text="{{ $rangeExpr($panel['x_key'], $panel['x_ticks']) }}"></b></span>
                    </p>
                </div>
            @endforeach
        </section>

        <div class="ak-master-card fundamental-table-card mt-5">
            <div class="ak-master-card-header fundamental-table-head">
                <h3 class="text-sm font-black">{{ __('Aktien') }}</h3>
                <form method="GET" class="fundamental-table-search">
                    @if($capGroup)<input type="hidden" name="cap" value="{{ $capGroup }}">@endif
                    @if($sector)<input type="hidden" name="sector" value="{{ $sector }}">@endif
                    @if($country)<input type="hidden" name="country" value="{{ $country }}">@endif
                    @if($region)<input type="hidden" name="region" value="{{ $region }}">@endif
                    <input type="hidden" name="sort" value="{{ $table['sort'] }}">
                    <input type="hidden" name="dir" value="{{ $table['dir'] }}">
                    <input type="text" name="q" value="{{ $tableSearch }}" placeholder="{{ __('Symbol oder Name suchen') }}" class="ak-input h-8 text-xs" oninput="clearTimeout(this._t);this._t=setTimeout(()=>this.form.requestSubmit(),400)">
                </form>
            </div>

            @php
                $sortLink = function (string $col, string $label) use ($table, $capGroup, $sector, $country, $region, $tableSearch) {
                    $nextDir = ($table['sort'] === $col && $table['dir'] === 'desc') ? 'asc' : 'desc';
                    $params = array_filter(['cap' => $capGroup, 'sector' => $sector, 'country' => $country, 'region' => $region, 'q' => $tableSearch, 'sort' => $col, 'dir' => $nextDir]);
                    $icon = $table['sort'] === $col ? ($table['dir'] === 'desc' ? '↓' : '↑') : '';

                    return '<a href="'.route('fundamental.index', $params).'" class="fundamental-table-sort">'.$label.' '.$icon.'</a>';
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
                            <th class="text-right">{!! $sortLink('market_cap', __('Marktkap.')) !!}</th>
                            <th class="text-right">{!! $sortLink('revenue_growth', __('Umsatzwachstum')) !!}</th>
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
                                <td class="text-right tabular-nums">{{ $row->dividend_yield !== null ? number_format($row->dividend_yield, 2, ',', '.').' %' : '–' }}</td>
                                <td class="text-right tabular-nums">{{ $row->market_cap !== null ? number_format($row->market_cap / 1_000_000_000, 1, ',', '.').' Mrd.' : '–' }}</td>
                                <td class="text-right tabular-nums">{{ $row->revenue_growth !== null ? number_format($row->revenue_growth, 1, ',', '.').' %' : '–' }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="8" class="py-6 text-center text-[var(--ak-muted)]">{{ __('Keine Treffer.') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @php $lastPage = max(1, (int) ceil($table['total'] / $table['per_page'])); @endphp
            <div class="fundamental-table-pagination">
                <span>{{ __(':total Aktien · Seite :page von :last', ['total' => $table['total'], 'page' => $table['page'], 'last' => $lastPage]) }}</span>
                <div class="flex gap-2">
                    @if($table['page'] > 1)
                        <a href="{{ route('fundamental.index', array_filter(['cap' => $capGroup, 'sector' => $sector, 'country' => $country, 'region' => $region, 'q' => $tableSearch, 'sort' => $table['sort'], 'dir' => $table['dir'], 'page' => $table['page'] - 1])) }}" class="fundamental-cap-pill">{{ __('Zurück') }}</a>
                    @endif
                    @if($table['page'] < $lastPage)
                        <a href="{{ route('fundamental.index', array_filter(['cap' => $capGroup, 'sector' => $sector, 'country' => $country, 'region' => $region, 'q' => $tableSearch, 'sort' => $table['sort'], 'dir' => $table['dir'], 'page' => $table['page'] + 1])) }}" class="fundamental-cap-pill">{{ __('Weiter') }}</a>
                    @endif
                </div>
            </div>
        </div>
    @else
        <div class="fundamental-layout">
            <nav class="fundamental-symbol-grid" aria-label="{{ __('Ansicht') }}">
                <a href="{{ route('fundamental.index') }}" class="fundamental-symbol-tile fundamental-symbol-tile--current" title="{{ __('Zurück zur Übersicht') }}">
                    <strong>{{ $selected->symbol }}</strong>
                </a>
                <button type="button" class="fundamental-symbol-tile" :class="{ 'is-active': active === 'ratios' }" @click="active = 'ratios'">
                    <x-heroicon-o-calculator class="h-5 w-5" />
                    <small>{{ __('Kennzahlen') }}</small>
                </button>
                <button type="button" class="fundamental-symbol-tile" :class="{ 'is-active': active === 'quarters' }" @click="active = 'quarters'">
                    <x-heroicon-o-document-chart-bar class="h-5 w-5" />
                    <small>{{ __('Quartalszahlen') }}</small>
                </button>
            </nav>

            <section class="fundamental-main">
                <div class="ak-master-card fundamental-section" x-show="active === 'ratios'" x-cloak>
                    <div class="ak-master-card-header fundamental-head-inner"><h2 class="text-base font-black">{{ __('Kennzahlen') }}</h2></div>
                    <div class="fundamental-ratios-body">
                        @if($ratios)
                            <div class="fundamental-ratio-grid">
                                <div class="fundamental-ratio"><span>{{ __('KGV (trailing)') }}</span><b>{{ $ratios['trailing_pe'] !== null ? number_format($ratios['trailing_pe'], 1, ',', '.') : '–' }}</b></div>
                                <div class="fundamental-ratio"><span>{{ __('Dividendenrendite') }}</span><b>{{ $ratios['dividend_yield'] !== null ? number_format($ratios['dividend_yield'], 2, ',', '.').'%' : '–' }}</b></div>
                                <div class="fundamental-ratio"><span>{{ __('Marktkapitalisierung') }}</span><b>{{ $ratios['market_cap'] !== null ? number_format($ratios['market_cap'] / 1_000_000_000, 1, ',', '.').' Mrd.' : '–' }}</b></div>
                                <div class="fundamental-ratio"><span>{{ __('Umsatzwachstum') }}</span><b>{{ $ratios['revenue_growth'] !== null ? number_format($ratios['revenue_growth'], 1, ',', '.').'%' : '–' }}</b></div>
                            </div>
                            <p class="fundamental-footnote">{{ __('Stand:') }} {{ \Illuminate\Support\Carbon::parse($ratios['snapshot_date'])->format('d.m.Y') }}</p>
                        @else
                            <p class="text-xs text-[var(--ak-muted)]">{{ __('Keine Fundamentaldaten für diese Aktie vorhanden.') }}</p>
                        @endif
                    </div>
                </div>

                <div class="fundamental-section" x-show="active === 'quarters'" x-cloak>
                    @foreach($years as $yearBlock)
                        <div class="fy-block">
                            <div class="fy-head">
                                <span class="fy-label">{{ $yearBlock['year'] }}</span>
                                <span class="fy-count">{{ $yearBlock['count'] }} {{ __('von 4 Quartalen mit Daten') }}</span>
                            </div>
                            <div class="quarter-grid">
                                @foreach($yearBlock['quarters'] as $q)
                                    @if(!$q['has_data'])
                                        <div class="q-card is-empty">
                                            <div class="q-head"><span class="q-label">Q{{ $q['quarter'] }}</span></div>
                                            <div class="q-empty-note"><b>{{ __('Keine Daten:') }}</b> {{ __('Kein Quartalsbericht in diesem Zeitraum erfasst.') }}</div>
                                        </div>
                                    @elseif($q['unreliable'])
                                        <div class="q-card is-empty">
                                            <div class="q-head"><span class="q-label">Q{{ $q['quarter'] }}<span class="q-date">{{ $q['date']->format('d.m.Y') }}</span></span><span class="q-badge flag">{{ __('Unsicher') }}</span></div>
                                            <div class="q-empty-note"><b>{{ __('Kein verlässliches Ergebnis:') }}</b> {{ __('Die gemeldeten EPS-Werte weichen stark vom sonstigen Bereich dieser Aktie ab (vermutlicher Datenfehler) und wurden nicht angezeigt.') }}</div>
                                        </div>
                                    @else
                                        <div class="q-card" @if($q['candles']) data-chart='@json($q['candles'])' data-event="10" @endif>
                                            <div class="q-head">
                                                <span class="q-label">Q{{ $q['quarter'] }}<span class="q-date">{{ $q['date']->format('d.m.Y') }}</span></span>
                                                @if($q['surprise_percent'] !== null)
                                                    <span class="q-badge {{ $q['is_beat'] ? 'beat' : 'miss' }}">{{ $q['is_beat'] ? __('Beat') : __('Miss') }}</span>
                                                @endif
                                            </div>
                                            <div class="q-body">
                                                @if($q['candles'])
                                                    <div class="q-chart-wrap"><svg class="q-candles" viewBox="0 0 150 60" preserveAspectRatio="none"></svg></div>
                                                @endif
                                                @if($q['eps_actual'] !== null || $q['eps_estimate'] !== null)
                                                    <div class="q-row"><span class="lbl">{{ __('EPS Ist/Schätzung') }}</span><span class="val">{{ $q['eps_actual'] !== null ? number_format($q['eps_actual'], 2, ',', '.') : '–' }} / {{ $q['eps_estimate'] !== null ? number_format($q['eps_estimate'], 2, ',', '.') : '–' }}</span></div>
                                                @endif
                                                @if($q['surprise_percent'] !== null)
                                                    <div class="q-row"><span class="lbl">{{ __('Überraschung') }}</span><span class="val {{ $q['is_beat'] ? 'pos' : 'neg' }}">{{ $q['surprise_percent'] >= 0 ? '+' : '' }}{{ number_format($q['surprise_percent'], 1, ',', '.') }}&nbsp;%</span></div>
                                                @endif
                                                @if($q['return_pre_3d'] !== null)
                                                    <div class="q-row"><span class="lbl">{{ __('Drift −3T → Termin') }}</span><span class="val {{ $q['return_pre_3d'] >= 0 ? 'pos' : 'neg' }}">{{ $q['return_pre_3d'] >= 0 ? '+' : '' }}{{ number_format($q['return_pre_3d'], 1, ',', '.') }}&nbsp;%</span></div>
                                                @endif
                                                @if($q['return_post_3d'] !== null)
                                                    <div class="q-row"><span class="lbl">{{ __('Kurs +3T') }}</span><span class="val {{ $q['return_post_3d'] >= 0 ? 'pos' : 'neg' }}">{{ $q['return_post_3d'] >= 0 ? '+' : '' }}{{ number_format($q['return_post_3d'], 1, ',', '.') }}&nbsp;%</span></div>
                                                @endif
                                            </div>
                                        </div>
                                    @endif
                                @endforeach
                            </div>
                        </div>
                    @endforeach
                    <p class="fundamental-footnote">{{ __('Kerzenchart = ±10 Handelstage um den Termin, gestrichelte Linie markiert den Bericht. Quartale sind nach Kalenderquartal des Berichtsdatums gruppiert (Q1=Jan-Mär …).') }}</p>
                </div>
            </section>
        </div>
    @endif
</div>

<style>
    #fundamental-page .fundamental-layout { display: grid; gap: 1rem; grid-template-columns: 1fr; margin-top: .25rem; }
    @media (min-width: 900px) { #fundamental-page .fundamental-layout { grid-template-columns: 96px minmax(0, 1fr); align-items: start; } }

    /* Same tile pattern as the Concept Dashboard's left icon grid
       (#dashboard-concept-page .concept-icon-grid/.concept-icon-tile):
       a small, FIXED set of view sections for the one selected stock
       (which arrives via ?symbol= from elsewhere, e.g. the Screener list),
       swapped client-side with Alpine - not a picker for which stock. */
    #fundamental-page .fundamental-symbol-grid { display: grid; grid-template-columns: 1fr; grid-template-rows: repeat(10, 1fr); gap: .5rem; padding: .75rem; align-self: start; }
    #fundamental-page .fundamental-symbol-tile {
        display: flex; flex-direction: column; align-items: center; justify-content: center; gap: .3rem;
        aspect-ratio: 1 / 1; border-radius: .9rem; border: 1.5px solid var(--ak-border-strong);
        background: none; color: var(--ak-text); text-decoration: none; cursor: pointer; width: 100%;
        transition: border-color .15s ease, background .15s ease, color .15s ease;
    }
    #fundamental-page .fundamental-symbol-tile:hover { border-color: color-mix(in srgb, #22d3ee 45%, transparent); }
    #fundamental-page .fundamental-symbol-tile.is-active { border-color: #22d3ee; background: color-mix(in srgb, #22d3ee 14%, transparent); color: #22d3ee; }
    #fundamental-page .fundamental-symbol-tile--current { border-color: rgba(251,191,36,.5); background: rgba(251,191,36,.08); }
    #fundamental-page .fundamental-symbol-tile--current:hover { border-color: #fbbf24; }
    #fundamental-page .fundamental-symbol-tile--current strong { font-size: .58rem; font-weight: 900; text-align: center; line-height: 1.05; color: #fbbf24; word-break: break-word; }
    #fundamental-page .fundamental-symbol-tile svg { width: 1.15rem; height: 1.15rem; flex: none; }
    #fundamental-page .fundamental-symbol-tile small { font-size: .5rem; font-weight: 800; text-align: center; line-height: 1.05; }

    #fundamental-page .fundamental-section { padding: 0; }
    #fundamental-page .fundamental-head-inner { padding: 1rem 1.1rem; }
    #fundamental-page .fundamental-ratios-body { padding: 1.1rem; }
    #fundamental-page .fundamental-ratio-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: .75rem; }
    @media (min-width: 640px) { #fundamental-page .fundamental-ratio-grid { grid-template-columns: repeat(4, 1fr); } }
    #fundamental-page .fundamental-ratio { border: 1px solid var(--ak-border); border-radius: .8rem; padding: .7rem .8rem; }
    #fundamental-page .fundamental-ratio span { display: block; font-size: .6rem; font-weight: 800; color: var(--ak-muted); text-transform: uppercase; letter-spacing: .02em; }
    #fundamental-page .fundamental-ratio b { display: block; margin-top: .25rem; font-size: 1rem; font-weight: 900; }
    #fundamental-page .fundamental-footnote { margin-top: 1rem; font-size: .68rem; color: var(--ak-muted); line-height: 1.6; }

    #fundamental-page .fundamental-cap-pill { display: inline-flex; align-items: center; padding: .5rem .9rem; border-radius: .7rem; border: 1px solid var(--ak-border); font-size: .72rem; font-weight: 800; color: var(--ak-muted); text-decoration: none; transition: border-color .15s ease, background .15s ease, color .15s ease; }
    #fundamental-page .fundamental-cap-pill:hover { border-color: color-mix(in srgb, #22d3ee 45%, transparent); color: var(--ak-text); }
    #fundamental-page .fundamental-cap-pill.is-active { border-color: #22d3ee; background: color-mix(in srgb, #22d3ee 14%, transparent); color: #22d3ee; }
    #fundamental-page .fundamental-select { padding: .5rem .7rem; border-radius: .7rem; border: 1px solid var(--ak-border); background: var(--ak-card); font-size: .72rem; font-weight: 700; color: var(--ak-text); }

    #fundamental-page .fundamental-table-head { display: flex; align-items: center; justify-content: space-between; gap: .75rem; padding: 1rem 1.1rem; flex-wrap: wrap; }
    #fundamental-page .fundamental-table-search input { width: 220px; }
    #fundamental-page .fundamental-table-scroll { overflow-x: auto; }
    #fundamental-page .fundamental-table { width: 100%; border-collapse: collapse; font-size: .74rem; }
    #fundamental-page .fundamental-table th { padding: .55rem .9rem; text-align: left; font-size: .62rem; font-weight: 800; color: var(--ak-muted); text-transform: uppercase; letter-spacing: .02em; border-bottom: 1px solid var(--ak-border); white-space: nowrap; }
    #fundamental-page .fundamental-table td { padding: .55rem .9rem; border-bottom: 1px solid var(--ak-border); white-space: nowrap; }
    #fundamental-page .fundamental-table tbody tr { cursor: pointer; }
    #fundamental-page .fundamental-table tbody tr:hover { background: var(--ak-surface-muted); }
    #fundamental-page .fundamental-table-name { max-width: 240px; overflow: hidden; text-overflow: ellipsis; }
    #fundamental-page .fundamental-table-sort { color: inherit; text-decoration: none; }
    #fundamental-page .fundamental-table-sort:hover { color: #22d3ee; }
    #fundamental-page .fundamental-table-pagination { display: flex; align-items: center; justify-content: space-between; gap: .75rem; padding: .85rem 1.1rem; font-size: .68rem; color: var(--ak-muted); flex-wrap: wrap; }

    #fundamental-page .fundamental-heatmap-cell { transition: opacity .15s ease; }
    #fundamental-page .fundamental-heatmap-cell.is-dimmed { opacity: .18; }

    /* In-grid draggable filter lines, same visual language as the
       Strategie page's .ak-heatmap-filter-stroke/-handle. */
    #fundamental-page .fundamental-heatmap-drag { position: absolute; z-index: 25; display: block; touch-action: none; }
    #fundamental-page .fundamental-heatmap-drag--x { top: 0; bottom: 0; width: 16px; margin-left: -8px; cursor: ew-resize; }
    #fundamental-page .fundamental-heatmap-drag--y { left: 0; right: 0; height: 16px; margin-bottom: -8px; cursor: ns-resize; }
    #fundamental-page .fundamental-heatmap-drag b { position: absolute; display: block; filter: drop-shadow(0 0 2px rgba(34,211,238,.45)); }
    #fundamental-page .fundamental-heatmap-drag--x b { inset: 0 auto 0 50%; width: 2px; transform: translateX(-50%); background: repeating-linear-gradient(to bottom, rgba(34,211,238,.88) 0 4px, transparent 4px 7px); }
    #fundamental-page .fundamental-heatmap-drag--y b { inset: 50% 0 auto; height: 2px; transform: translateY(-50%); background: repeating-linear-gradient(to right, rgba(34,211,238,.88) 0 4px, transparent 4px 7px); }
    #fundamental-page .fundamental-heatmap-drag i { position: absolute; top: 50%; left: 50%; width: 7px; height: 7px; transform: translate(-50%, -50%) rotate(45deg); border: 1px solid rgba(103,232,249,.85); border-radius: 2px; background: #0b2131; box-shadow: 0 0 3px rgba(34,211,238,.45); }

    #fundamental-page .fy-block { margin-top: 1.4rem; }
    #fundamental-page .fy-block:first-child { margin-top: 0; }
    #fundamental-page .fy-head { display: flex; align-items: baseline; gap: .5rem; margin-bottom: .6rem; }
    #fundamental-page .fy-label { font-size: .92rem; font-weight: 900; }
    #fundamental-page .fy-count { font-size: .6rem; font-weight: 800; color: var(--ak-muted); text-transform: uppercase; letter-spacing: .03em; }
    #fundamental-page .quarter-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: .7rem; }
    @media (max-width: 900px) { #fundamental-page .quarter-grid { grid-template-columns: repeat(2, 1fr); } }
    @media (max-width: 560px) { #fundamental-page .quarter-grid { grid-template-columns: 1fr; } }
    #fundamental-page .q-card { border: 1px solid var(--ak-border); background: var(--ak-card); border-radius: 1rem; box-shadow: var(--ak-shadow); overflow: hidden; display: flex; flex-direction: column; }
    #fundamental-page .q-card.is-empty { border-color: rgba(251,191,36,.3); }
    #fundamental-page .q-head { display: flex; align-items: center; justify-content: space-between; gap: .4rem; padding: .65rem .75rem .55rem; border-bottom: 1px solid var(--ak-border); background: var(--ak-surface-muted); }
    #fundamental-page .q-label { font-size: .68rem; font-weight: 900; }
    #fundamental-page .q-date { display: block; font-size: .56rem; font-weight: 700; color: var(--ak-muted); margin-top: .1rem; }
    #fundamental-page .q-badge { font-size: .54rem; font-weight: 900; letter-spacing: .03em; text-transform: uppercase; padding: .22rem .45rem; border-radius: 999px; border: 1px solid; white-space: nowrap; }
    #fundamental-page .q-badge.beat { color: #34d399; border-color: rgba(52,211,153,.4); background: rgba(52,211,153,.08); }
    #fundamental-page .q-badge.miss { color: #fb7185; border-color: rgba(251,113,133,.4); background: rgba(251,113,133,.08); }
    #fundamental-page .q-badge.flag { color: #fbbf24; border-color: rgba(251,191,36,.45); background: rgba(251,191,36,.1); }
    #fundamental-page .q-body { padding: .7rem .75rem; font-size: .64rem; line-height: 1.55; color: var(--ak-muted); flex: 1; display: grid; gap: .5rem; }
    #fundamental-page .q-chart-wrap { border: 1px solid var(--ak-border); border-radius: .6rem; padding: .35rem .4rem .3rem; }
    #fundamental-page svg.q-candles { width: 100%; height: 54px; display: block; overflow: visible; }
    #fundamental-page .q-row { display: flex; justify-content: space-between; gap: .4rem; }
    #fundamental-page .q-row .lbl { color: var(--ak-muted); }
    #fundamental-page .q-row .val { font-weight: 900; color: var(--ak-text); font-variant-numeric: tabular-nums; }
    #fundamental-page .q-row .val.pos { color: #34d399; }
    #fundamental-page .q-row .val.neg { color: #fb7185; }
    #fundamental-page .q-empty-note { padding: .7rem .75rem; font-size: .6rem; line-height: 1.5; color: var(--ak-muted); }
    #fundamental-page .q-empty-note b { color: #fbbf24; }
</style>

<script>
(function () {
    function drawCandles(svg, data, eventIndex) {
        const vb = svg.viewBox.baseVal;
        const w = vb.width || 150, h = vb.height || 60, pad = 3;
        const n = data.length;
        const slot = (w - pad * 2) / n;
        const candleW = Math.min(6, slot * 0.62);
        const highs = data.map(d => d.h), lows = data.map(d => d.l);
        const max = Math.max(...highs), min = Math.min(...lows);
        const range = (max - min) || 1;
        const y = v => pad + (h - pad * 2) * (1 - (v - min) / range);

        let svgContent = '';
        const eventX = pad + slot * eventIndex + slot / 2;
        svgContent += `<line x1="${eventX}" y1="0" x2="${eventX}" y2="${h}" stroke="rgba(34,211,238,.35)" stroke-width="1" stroke-dasharray="2,2"/>`;

        data.forEach((d, i) => {
            const cx = pad + slot * i + slot / 2;
            const bullish = d.c >= d.o;
            const color = bullish ? '#34d399' : '#fb7185';
            const yHigh = y(d.h), yLow = y(d.l);
            const yOpen = y(d.o), yClose = y(d.c);
            const bodyTop = Math.min(yOpen, yClose);
            const bodyH = Math.max(1, Math.abs(yClose - yOpen));
            svgContent += `<line x1="${cx}" y1="${yHigh}" x2="${cx}" y2="${yLow}" stroke="${color}" stroke-width="1"/>`;
            svgContent += `<rect x="${cx - candleW / 2}" y="${bodyTop}" width="${candleW}" height="${bodyH}" fill="${color}"/>`;
        });
        svg.innerHTML = svgContent;
    }

    document.querySelectorAll('#fundamental-page [data-chart]').forEach(card => {
        try {
            const data = JSON.parse(card.getAttribute('data-chart'));
            const eventIndex = parseInt(card.getAttribute('data-event'), 10);
            const svg = card.querySelector('svg.q-candles');
            if (svg) drawCandles(svg, data, eventIndex);
        } catch (e) { console.error(e); }
    });
})();
</script>
</x-app-layout>
