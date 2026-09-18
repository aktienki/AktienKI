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
        <div class="ak-master-card p-8 text-center text-sm text-[var(--ak-muted)]">
            {{ __('Keine Aktie ausgewählt. Diese Seite wird über einen Link von einer Aktie aus geöffnet (z.B. aus dem Screener), mit ?symbol=SYMBOL in der URL.') }}
        </div>
    @else
        <div class="fundamental-layout">
            <nav class="fundamental-symbol-grid" aria-label="{{ __('Ansicht') }}">
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
