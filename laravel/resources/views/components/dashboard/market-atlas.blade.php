@props(['countryAiScores' => []])

<x-dashboard.card id="market-atlas" class="ak-standard-card ak-card-static ak-dashboard-card ak-dashboard-atlas flex min-h-[260px] flex-col scroll-mt-24 p-4 lg:min-h-0">
    <div class="ak-standard-card-head flex flex-wrap items-start justify-between gap-2">
        <div>
            <p class="text-xs font-black uppercase tracking-[.18em] text-cyan-300">{{ __('Global Market Map') }}</p>
            <p class="mt-1 text-xs text-slate-400">{{ __('Tagesentwicklung der verfügbaren Indizes nach Land') }}</p>
        </div>
        <span class="rounded-lg border border-cyan-300/25 bg-cyan-300/10 px-2.5 py-1 text-[10px] font-bold text-cyan-300">
            {{ count($countryAiScores) }} {{ __('Länder') }}
        </span>
    </div>

    <div
        class="relative min-h-0 flex-1 overflow-hidden"
        x-data="worldMarketMap(@js($countryAiScores), @js(route('indices.index')), @js(asset('assets/ne_50m_admin_0_countries.geojson')))">
        <svg
            x-ref="map"
            class="h-full min-h-[80px] w-full text-slate-500"
            viewBox="0 0 1000 500"
            preserveAspectRatio="xMidYMid meet"
            role="img"
            aria-label="{{ __('Weltkarte steigender und fallender Aktienkurse nach Ländern') }}">
        </svg>
        <p x-show="error" x-cloak class="absolute inset-0 grid place-items-center text-sm text-rose-300">{{ __('Kartendaten konnten nicht geladen werden.') }}</p>

        <div
            x-cloak
            x-show="selectedCountry"
            x-transition.origin.top.right
            @click.outside="selectedCountry = null"
            class="absolute right-2 top-2 w-60 rounded-xl border border-cyan-300/30 bg-[#0b1830]/95 p-4 shadow-2xl shadow-black/50 backdrop-blur-xl sm:right-4 sm:top-4">
            <button type="button" @click="selectedCountry = null" class="absolute right-2.5 top-2.5 text-slate-500 transition hover:text-white" aria-label="{{ __('Schließen') }}">
                <x-heroicon-o-x-mark class="h-4 w-4" />
            </button>
            <div class="flex items-center gap-2 pr-6">
                <span x-text="selectedCountry?.flag" class="text-xl"></span>
                <div class="min-w-0">
                    <p x-text="selectedCountry?.name" class="truncate text-sm font-black text-white"></p>
                    <p x-text="selectedCountry?.code" class="text-[9px] font-bold uppercase tracking-widest text-cyan-300"></p>
                </div>
            </div>
            <div class="mt-4 grid grid-cols-3 gap-2">
                <div class="rounded-xl border border-white/5 bg-white/[.04] p-2.5">
                    <p class="text-[9px] uppercase tracking-wide text-slate-500">{{ __('Tagestrend') }}</p>
                    <p class="mt-1 text-base font-black" :class="selectedCountry?.change === null ? 'text-slate-400' : (selectedCountry.change > 0 ? 'text-emerald-400' : (selectedCountry.change < -0.5 ? 'text-rose-400' : 'text-amber-400'))"><span x-text="selectedCountry?.change === null ? '—' : `${selectedCountry.change >= 0 ? '+' : ''}${selectedCountry.change.toFixed(2)} %`"></span></p>
                </div>
                <div class="rounded-xl border border-white/5 bg-white/[.04] p-2.5">
                    <p class="text-[9px] uppercase tracking-wide text-slate-500">{{ __('Indexstand') }}</p>
                    <p x-text="selectedCountry?.priceFormatted" class="mt-1 text-sm font-black text-white"></p>
                </div>
                <div class="rounded-xl border border-white/5 bg-white/[.04] p-2.5">
                    <p class="text-[9px] uppercase tracking-wide text-slate-500">{{ __('Indizes') }}</p>
                    <p x-text="selectedCountry?.indices" class="mt-1 text-lg font-black text-white"></p>
                </div>
            </div>
            <div class="mt-3 rounded-lg border border-white/5 bg-white/[.03] px-3 py-2 text-[10px] text-slate-400">
                <p class="font-bold text-slate-200" x-text="`${selectedCountry?.indexName} (${selectedCountry?.indexSymbol})`"></p>
                <p class="mt-0.5"><span>{{ __('Letzte Kurserhebung') }}:</span> <span x-text="selectedCountry?.latestAt ? `${selectedCountry.latestAt} Uhr` : '—'"></span></p>
            </div>
            <a :href="selectedCountry?.indexUrl" class="mt-3 inline-flex h-9 w-full items-center justify-center gap-2 rounded-lg border border-cyan-300/30 bg-cyan-300/15 px-3 text-xs font-bold text-cyan-300 transition hover:bg-cyan-300/25">
                {{ __('Index anzeigen') }}
                <x-heroicon-o-arrow-right class="h-3.5 w-3.5" />
            </a>
        </div>
    </div>

    <div class="ak-atlas-scale mt-2 flex w-full items-center justify-center gap-3 text-[7px] font-semibold text-slate-500" aria-label="{{ __('Farbskala fallend bis steigend') }}">
        <span class="inline-flex min-w-0 items-center gap-1 whitespace-nowrap"><i class="ak-atlas-scale-step h-1 w-6 shrink-0 bg-rose-500" data-tone="falling"></i>&lt; −0,5 %</span>
        <span class="inline-flex min-w-0 items-center gap-1 whitespace-nowrap"><i class="ak-atlas-scale-step h-1 w-6 shrink-0 bg-amber-400" data-tone="neutral"></i>−0,5–0 %</span>
        <span class="inline-flex min-w-0 items-center gap-1 whitespace-nowrap"><i class="ak-atlas-scale-step h-1 w-6 shrink-0 bg-emerald-500" data-tone="rising"></i>&gt; 0 %</span>
    </div>
</x-dashboard.card>
