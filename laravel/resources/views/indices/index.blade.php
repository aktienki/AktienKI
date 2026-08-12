<x-app-layout>
<div id="aggregate-screener" class="screener-page mx-auto max-w-7xl px-3 py-5 text-[var(--ak-text)] sm:px-5 lg:py-8">
    <header class="mb-5 flex items-end justify-between gap-3"><div><p class="text-[10px] font-black uppercase tracking-[.2em] text-cyan-400">aKI Index Intelligence</p><h1 class="mt-1 text-3xl font-black tracking-tight">{{ __('Indexscreener') }}</h1><p class="mt-1 text-sm text-[var(--ak-muted)]">{{ __('Globale Leitindizes und ihre KI-Bewertung auf einen Blick.') }}</p></div><span class="rounded-[10px] border border-cyan-400/30 bg-cyan-400/[.08] px-3 py-2 text-xs font-bold text-[var(--ak-muted)]">{{ $indices->count() }} {{ __('Indizes') }}</span></header>
    <form method="GET" class="screener-filter-bar mb-5 flex flex-nowrap gap-2 overflow-x-auto rounded-lg border border-cyan-400/30 bg-[var(--ak-card)] p-3 shadow-[var(--ak-shadow)]"><input name="q" value="{{ request('q') }}" oninput="clearTimeout(this._filterTimer);this._filterTimer=setTimeout(()=>this.form.requestSubmit(),500)" placeholder="{{ __('Index oder Symbol') }}" class="ak-input h-10 min-w-[220px] flex-[2] text-sm"><select name="region" onchange="this.form.requestSubmit()" class="ak-input h-10 min-w-[180px] flex-1 text-sm"><option value="">{{ __('Alle Regionen') }}</option>@foreach($regions as $region)<option value="{{ $region }}" @selected(request('region')===$region)>{{ $region }}</option>@endforeach</select><a href="{{ route('indices.index') }}" class="screener-filter-reset inline-flex h-10 shrink-0 items-center justify-center border border-amber-400/40 bg-amber-400/[.10] px-4 text-xs font-black text-amber-300">Reset</a></form>
    <div class="mb-3 flex justify-between text-xs text-[var(--ak-muted)]"><span>{{ $indices->count() }} {{ __('Indizes mit Mitgliedern') }}</span><span>{{ __('Globales Ranking') }}</span></div>
    <div class="aggregate-results"><section class="grid grid-cols-1 gap-4">
    @foreach($indices as $index)
        @php
            $confidence=is_numeric($index->average_confidence)?max(0,min(100,(float)$index->average_confidence<=1?(float)$index->average_confidence*100:(float)$index->average_confidence)):null;
            $return=is_numeric($index->expected_return)?(float)$index->expected_return:null;
            $assessment=$index->assessment?:($index->rating_value!==null?__('Die Bewertung basiert auf :count aktuell analysierten Indexmitgliedern.',['count'=>$index->analyzed_count]):__('Die Bewertung wird mit den nächsten Analysedaten ergänzt.'));
        @endphp
        <x-screener.aggregate-card :rank="$index->global_rank" :eyebrow="__('Globales Ranking')" :name="$index->name" :symbol="$index->symbol" :meta="$index->country.' · '.$index->currency" :secondary-meta="$index->region" :members="(int)$index->members_count" :analyzed="(int)$index->analyzed_count" :score="$index->rating_value" :confidence="$confidence" :expected-return="$return" :description="$index->description" :assessment="$assessment" :target="route('stocks.index',['index'=>$index->symbol])" />
    @endforeach
    </section></div>
</div>
<style>#aggregate-screener{height:calc(100dvh - 73px);display:flex;min-height:0;flex-direction:column;overflow:hidden}#aggregate-screener>header,#aggregate-screener>form,#aggregate-screener>div.mb-3{flex:0 0 auto}.aggregate-results{min-height:0;flex:1 1 auto;overflow:auto;overscroll-behavior:contain;padding-right:3px}@media(max-width:1023px){#aggregate-screener{height:auto;overflow:visible}.aggregate-results{overflow:visible}}</style>
</x-app-layout>
