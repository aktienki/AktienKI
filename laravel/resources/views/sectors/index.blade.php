<x-app-layout>
<div id="aggregate-screener" class="sector-screener screener-page mx-auto max-w-7xl px-3 py-5 text-[var(--ak-text)] sm:px-5 lg:py-8">
    <header class="mb-3 flex items-center justify-between gap-3"><h1 class="text-3xl font-black tracking-tight">{{ __('Sektorenscreener') }}</h1><span class="rounded-[10px] border border-cyan-400/30 bg-cyan-400/[.08] px-3 py-2 text-xs font-bold text-[var(--ak-muted)]">{{ $sectors->count() }} {{ __('Sektoren') }}</span></header>
    @if($isFreeRegional)<div class="mb-4 flex items-center justify-between gap-3 rounded-xl border border-amber-400/25 bg-amber-400/[.06] px-4 py-3"><p class="text-xs text-[var(--ak-muted)]"><b class="mr-2 text-[9px] uppercase tracking-[.12em] text-amber-400">FREE</b>{{ __('Sektorwerte aus deinem regionalen Top-100-Universum (:country).', ['country'=>$regionalCountry]) }}</p><a href="{{ route('pricing') }}" class="shrink-0 text-[9px] font-black text-amber-300">{{ __('Global ab Plus') }} →</a></div>@endif
    <form method="GET" class="screener-filter-bar mb-5 flex flex-nowrap gap-2 overflow-x-auto rounded-lg border border-cyan-400/30 bg-[var(--ak-card)] p-3 shadow-[var(--ak-shadow)]"><input name="q" value="{{ request('q') }}" oninput="clearTimeout(this._filterTimer);this._filterTimer=setTimeout(()=>this.form.requestSubmit(),500)" placeholder="{{ __('Sektor suchen') }}" class="ak-input h-10 min-w-[220px] flex-1 text-sm"><a href="{{ route('sectors.index') }}" class="screener-filter-reset inline-flex h-10 shrink-0 items-center justify-center border border-amber-400/40 bg-amber-400/[.10] px-4 text-xs font-black text-amber-300">Reset</a></form>
    <div class="mb-3 flex justify-between text-xs text-[var(--ak-muted)]"><span>{{ $sectors->count() }} {{ __('Sektoren mit Mitgliedern') }}</span><span>{{ __('Höchster KI-Score zuerst') }}</span></div>
    <div class="aggregate-results"><section class="grid grid-cols-1 gap-4">
    @foreach($sectors as $position=>$sector)
        @php
            $score=is_numeric($sector->average_score)?\App\Support\AiScore::toTen($sector->average_score):(is_numeric($sector->stored_rating)?(float)$sector->stored_rating:null);
            $confidence=is_numeric($sector->average_confidence)?max(0,min(100,(float)$sector->average_confidence<=1?(float)$sector->average_confidence*100:(float)$sector->average_confidence)):null;
            $risk=is_numeric($sector->risk_p75)?max(0,min(100,(float)$sector->risk_p75<=1?(float)$sector->risk_p75*100:(float)$sector->risk_p75)):null;
            $return=is_numeric($sector->average_expected_return_20d)?(float)$sector->average_expected_return_20d:null;
            $comment=$sectorComments->first(fn($item)=>mb_strtolower(trim((string)($item['sector']??'')))===mb_strtolower(trim((string)$sector->sector)));
            $description=$sector->description?:__('Die Beschreibung wird noch ergänzt.');
            $assessment=$sector->assessment?:($comment['summary']??($score!==null?__('Die Bewertung basiert auf :count aktuell analysierten Aktien.',['count'=>$sector->analyzed_count]):__('Die Bewertung wird mit den nächsten Analysedaten ergänzt.')));
        @endphp
        <x-screener.aggregate-card :rank="$position+1" :eyebrow="__('Sektor-Ranking')" :name="__($sector->sector)" :meta="$sector->stocks_count.' '.__('Aktien')" :secondary-meta="$sector->analyzed_count.' '.__('analysiert')" :members="(int)$sector->stocks_count" :analyzed="(int)$sector->analyzed_count" :score="$score" :confidence="$confidence" :risk="$risk" :expected-return="$return" :description="$description" :assessment="$assessment" :chart-points="$sector->etf_chart_points" :chart-label="__('ETF-Kurs · 1 Jahr')" :top-stocks="$sector->top_stocks" :realtime-quotes="$realtimeQuotes" :target="route('stocks.index',['sector'=>$sector->sector])"><x-slot:icon><x-sector-icon :sector="$sector->sector" class="h-5 w-5" /></x-slot:icon></x-screener.aggregate-card>
    @endforeach
    </section></div>
</div>
<style>#aggregate-screener{height:calc(100dvh - 73px);display:flex;min-height:0;flex-direction:column;overflow:hidden}#aggregate-screener>header,#aggregate-screener>form,#aggregate-screener>div.mb-3{flex:0 0 auto}.aggregate-results{min-height:0;flex:1 1 auto;overflow:auto;overscroll-behavior:contain;padding-right:3px}@media(min-width:1280px){.sector-screener .screener-stock-card{height:23rem}}@media(max-width:1023px){#aggregate-screener{height:auto;overflow:visible}.aggregate-results{overflow:visible}}</style>
@if($realtimeQuotes)
<script>
document.addEventListener('DOMContentLoaded', () => {
    const endpoint = @js(route('recommendations.live-quotes'));
    const cards = [...document.querySelectorAll('[data-sector-live-symbol]')];
    const visible = new Set();
    const observer = new IntersectionObserver(entries => entries.forEach(entry => {
        entry.isIntersecting ? visible.add(entry.target) : visible.delete(entry.target);
    }), { root: document.querySelector('.aggregate-results'), threshold: 0.1 });
    cards.forEach(card => observer.observe(card));

    const update = async () => {
        if (document.visibilityState !== 'visible') return;
        const active = [...visible].slice(0, 3);
        const symbols = [...new Set(active.map(card => card.dataset.sectorLiveSymbol).filter(Boolean))];
        if (!symbols.length) return;
        try {
            const url = new URL(endpoint, window.location.origin);
            url.searchParams.set('symbols', symbols.join(','));
            const response = await fetch(url, { credentials: 'same-origin', headers: { Accept: 'application/json' }, cache: 'no-store' });
            if (!response.ok) return;
            const { quotes = {} } = await response.json();
            active.forEach(card => {
                const quote = quotes[card.dataset.sectorLiveSymbol];
                const price = Number(quote?.price);
                if (!Number.isFinite(price) || price <= 0) return;
                const currency = quote.currency || card.querySelector('[data-sector-live-price]')?.dataset.liveCurrency || '';
                const priceEl = card.querySelector('[data-sector-live-price]');
                const changeEl = card.querySelector('[data-sector-live-change]');
                const timeEl = card.querySelector('[data-sector-live-time]');
                if (priceEl) priceEl.textContent = `${price.toLocaleString(document.documentElement.lang, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}${currency ? ` ${currency}` : ''}`;
                const change = Number(quote.change_percent);
                if (changeEl && Number.isFinite(change)) {
                    changeEl.textContent = `${change > 0 ? '+' : ''}${change.toLocaleString(document.documentElement.lang, { minimumFractionDigits: 2, maximumFractionDigits: 2 })} %`;
                    changeEl.className = `block text-[8px] ${change > 0 ? 'text-emerald-300' : (change < 0 ? 'text-rose-300' : 'text-[var(--ak-muted)]')}`;
                }
                if (timeEl) {
                    const timestamp = Number(quote.timestamp);
                    const date = Number.isFinite(timestamp) ? new Date(timestamp * 1000) : new Date();
                    const clock = date.toLocaleTimeString(document.documentElement.lang, { hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: false, timeZone: 'Europe/Berlin' });
                    const ageSeconds = Number(quote.age_seconds);
                    const isRealtime = quote.realtime === true && Number.isFinite(ageSeconds) && ageSeconds < 120;
                    if (isRealtime) {
                        timeEl.textContent = `Live · ${clock}`;
                        timeEl.className = 'block text-[7px] text-emerald-300';
                    } else {
                        const ageMinutes = Number.isFinite(ageSeconds) ? Math.max(1, Math.floor(ageSeconds / 60)) : null;
                        const ageLabel = ageMinutes === null ? 'veraltet' : (ageMinutes < 1440 ? `vor ${ageMinutes} Min.` : `vor ${Math.floor(ageMinutes / 1440)} Tag(en)`);
                        timeEl.textContent = `${ageLabel} · ${clock}`;
                        timeEl.className = 'block text-[7px] text-amber-300';
                    }
                }
            });
        } catch (_) {
            // Der letzte verifizierte Kurs bleibt bei einem kurzzeitigen Providerfehler sichtbar.
        }
    };
    window.setTimeout(update, 100);
    const timer = window.setInterval(update, 15_000);
    document.querySelector('.aggregate-results')?.addEventListener('scroll', () => window.setTimeout(update, 150), { passive: true });
    window.addEventListener('pagehide', () => { window.clearInterval(timer); observer.disconnect(); }, { once: true });
});
</script>
@endif
</x-app-layout>
