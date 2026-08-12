<x-app-layout>
<div id="aggregate-screener" class="screener-page mx-auto max-w-7xl px-3 py-5 text-[var(--ak-text)] sm:px-5 lg:py-8">
    <header class="mb-5 flex items-end justify-between gap-3"><div><p class="text-[10px] font-black uppercase tracking-[.2em] text-cyan-400">aKI Sector Intelligence</p><h1 class="mt-1 text-3xl font-black tracking-tight">{{ __('Sektorenscreener') }}</h1><p class="mt-1 text-sm text-[var(--ak-muted)]">{{ __('Sektoren und ihre aggregierte KI-Bewertung auf einen Blick.') }}</p></div><span class="rounded-[10px] border border-cyan-400/30 bg-cyan-400/[.08] px-3 py-2 text-xs font-bold text-[var(--ak-muted)]">{{ $sectors->count() }} {{ __('Sektoren') }}</span></header>
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
        <x-screener.aggregate-card :rank="$position+1" :eyebrow="__('Sektor-Ranking')" :name="__($sector->sector)" :meta="$sector->stocks_count.' '.__('Aktien')" :secondary-meta="$sector->analyzed_count.' '.__('analysiert')" :members="(int)$sector->stocks_count" :analyzed="(int)$sector->analyzed_count" :score="$score" :confidence="$confidence" :risk="$risk" :expected-return="$return" :description="$description" :assessment="$assessment" :chart-points="$sector->etf_chart_points" :chart-label="__('ETF-Kurs · 1 Jahr')" :target="route('stocks.index',['sector'=>$sector->sector])"><x-slot:icon><x-sector-icon :sector="$sector->sector" class="h-5 w-5" /></x-slot:icon></x-screener.aggregate-card>
    @endforeach
    </section></div>
</div>
<style>#aggregate-screener{height:calc(100dvh - 73px);display:flex;min-height:0;flex-direction:column;overflow:hidden}#aggregate-screener>header,#aggregate-screener>form,#aggregate-screener>div.mb-3{flex:0 0 auto}.aggregate-results{min-height:0;flex:1 1 auto;overflow:auto;overscroll-behavior:contain;padding-right:3px}@media(max-width:1023px){#aggregate-screener{height:auto;overflow:visible}.aggregate-results{overflow:visible}}</style>
</x-app-layout>
