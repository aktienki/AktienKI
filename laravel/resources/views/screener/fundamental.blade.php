<x-app-layout>
<div id="fundamental-page" class="mx-auto max-w-7xl px-3 py-5 text-[var(--ak-text)] sm:px-5 lg:py-8">
    <header class="mb-4 flex items-center justify-between gap-3">
        <h1 class="text-3xl font-black tracking-tight">{{ __('Fundamental') }}</h1>
        <span class="rounded-[10px] border border-[var(--ak-border)] bg-[var(--ak-card)] px-3 py-2 text-xs font-bold text-[var(--ak-muted)]">{{ $instruments->count() }} {{ __('Aktien') }}</span>
    </header>

    <div class="fundamental-layout">
        <nav class="ak-master-card fundamental-symbol-panel" aria-label="{{ __('Aktienauswahl') }}">
            <form method="GET" class="fundamental-search">
                <input type="text" name="q" value="{{ $searchTerm }}" placeholder="{{ __('Symbol oder Name suchen') }}" class="ak-input h-9 w-full text-xs" oninput="clearTimeout(this._t);this._t=setTimeout(()=>this.form.requestSubmit(),400)">
            </form>
            <div class="fundamental-symbol-list">
                @forelse($instruments as $instrument)
                    <a href="{{ route('fundamental.index', array_filter(['symbol' => $instrument->symbol, 'q' => $searchTerm])) }}"
                       class="fundamental-symbol-item {{ $selected && $selected->id === $instrument->id ? 'is-active' : '' }}">
                        <span class="sym">{{ $instrument->symbol }}</span>
                        <small>{{ $instrument->name }}</small>
                    </a>
                @empty
                    <p class="px-3 py-4 text-xs text-[var(--ak-muted)]">{{ __('Keine Treffer.') }}</p>
                @endforelse
            </div>
        </nav>

        <section class="fundamental-main">
            @if($selected)
                <div class="ak-master-card fundamental-head-card">
                    <div class="ak-master-card-header fundamental-head-inner">
                        <div>
                            <h2 class="text-lg font-black">{{ $selected->symbol }}</h2>
                            <p class="text-xs font-semibold text-[var(--ak-muted)]">{{ $selected->name }}</p>
                        </div>
                    </div>
                </div>

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

                <p class="fundamental-footnote">{{ __('Kerzenchart = ±10 Handelstage um den Termin, gestrichelte Linie markiert den Bericht. Quartale sind nach Kalenderquartal des Berichtsdatums gruppiert (Q1=Jan-Mär …), nicht nach dem individuellen Geschäftsjahr der Aktie.') }}</p>
            @else
                <div class="ak-master-card p-8 text-center text-sm text-[var(--ak-muted)]">{{ __('Bitte eine Aktie auswählen.') }}</div>
            @endif
        </section>
    </div>
</div>

<style>
    #fundamental-page .fundamental-layout { display: grid; gap: 1rem; grid-template-columns: 1fr; margin-top: .25rem; }
    @media (min-width: 900px) { #fundamental-page .fundamental-layout { grid-template-columns: 260px minmax(0, 1fr); align-items: start; } }

    #fundamental-page .fundamental-symbol-panel { padding: .75rem; display: flex; flex-direction: column; gap: .6rem; max-height: calc(100dvh - 160px); }
    #fundamental-page .fundamental-symbol-list { overflow-y: auto; display: flex; flex-direction: column; gap: .15rem; }
    #fundamental-page .fundamental-symbol-item { display: flex; flex-direction: column; gap: .05rem; padding: .45rem .6rem; border-radius: .6rem; text-decoration: none; color: var(--ak-text); }
    #fundamental-page .fundamental-symbol-item:hover { background: var(--ak-surface-muted); }
    #fundamental-page .fundamental-symbol-item.is-active { background: color-mix(in srgb, #22d3ee 14%, transparent); }
    #fundamental-page .fundamental-symbol-item .sym { font-size: .74rem; font-weight: 900; }
    #fundamental-page .fundamental-symbol-item small { font-size: .62rem; font-weight: 600; color: var(--ak-muted); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }

    #fundamental-page .fundamental-head-card { margin-bottom: 1rem; }
    #fundamental-page .fundamental-head-inner { padding: 1rem 1.1rem; }
    #fundamental-page .fundamental-footnote { margin-top: 1.4rem; font-size: .68rem; color: var(--ak-muted); line-height: 1.6; }

    #fundamental-page .fy-block { margin-top: 1.4rem; }
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
