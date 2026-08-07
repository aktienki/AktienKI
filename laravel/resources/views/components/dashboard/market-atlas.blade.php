@props(['countryAiScores' => []])

<x-dashboard.card id="market-atlas" class="ak-standard-card ak-card-static ak-dashboard-card ak-dashboard-atlas flex min-h-[260px] flex-col scroll-mt-24 p-4 lg:min-h-0">
    <div class="ak-standard-card-head flex flex-wrap items-start justify-between gap-2">
        <div>
            <p class="text-xs font-black uppercase tracking-[.18em] text-orange-400">{{ __('Global Market Map') }}</p>
            <p class="mt-1 text-xs text-slate-400">{{ __('Wo der Markt heute Stärke zeigt – aggregiert nach Herkunftsland') }}</p>
        </div>
        <span class="rounded-lg border border-orange-400/25 bg-orange-400/10 px-2.5 py-1 text-[10px] font-bold text-orange-400">
            {{ count($countryAiScores) }} {{ __('Länder') }}
        </span>
    </div>

    <div
        class="relative min-h-0 flex-1 overflow-hidden"
        x-data="worldMarketMap(@js($countryAiScores), @js(route('stocks.index')))">
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
            class="absolute right-2 top-2 w-60 rounded-xl border border-orange-400/30 bg-[#0b1830]/95 p-4 shadow-2xl shadow-black/50 backdrop-blur-xl sm:right-4 sm:top-4">
            <button type="button" @click="selectedCountry = null" class="absolute right-2.5 top-2.5 text-slate-500 transition hover:text-white" aria-label="{{ __('Schließen') }}">
                <x-heroicon-o-x-mark class="h-4 w-4" />
            </button>
            <div class="flex items-center gap-2 pr-6">
                <span x-text="selectedCountry?.flag" class="text-xl"></span>
                <div class="min-w-0">
                    <p x-text="selectedCountry?.name" class="truncate text-sm font-black text-white"></p>
                    <p x-text="selectedCountry?.code" class="text-[9px] font-bold uppercase tracking-widest text-orange-400"></p>
                </div>
            </div>
            <div class="mt-4 grid grid-cols-3 gap-2">
                <div class="rounded-xl border border-white/5 bg-white/[.04] p-2.5">
                    <p class="text-[9px] uppercase tracking-wide text-slate-500">{{ __('Tagestrend') }}</p>
                    <p class="mt-1 text-base font-black" :class="selectedCountry?.change > 0 ? 'text-emerald-400' : (selectedCountry?.change < 0 ? 'text-rose-400' : 'text-slate-400')"><span x-text="selectedCountry?.change === null ? '—' : `${selectedCountry.change >= 0 ? '+' : ''}${selectedCountry.change.toFixed(2)} %`"></span></p>
                </div>
                <div class="rounded-xl border border-white/5 bg-white/[.04] p-2.5">
                    <p class="text-[9px] uppercase tracking-wide text-slate-500">{{ __('KI-Score') }}</p>
                    <p class="mt-1 text-lg font-black text-white"><span x-text="selectedCountry?.scoreTen.toFixed(1)"></span><small class="ml-0.5 text-[9px] text-slate-500">/10</small></p>
                </div>
                <div class="rounded-xl border border-white/5 bg-white/[.04] p-2.5">
                    <p class="text-[9px] uppercase tracking-wide text-slate-500">{{ __('Aktien') }}</p>
                    <p x-text="selectedCountry?.stocks" class="mt-1 text-lg font-black text-white"></p>
                </div>
            </div>
            <a :href="selectedCountry?.stocksUrl" class="mt-3 inline-flex h-9 w-full items-center justify-center gap-2 rounded-lg border border-orange-400/30 bg-orange-400/15 px-3 text-xs font-bold text-orange-400 transition hover:bg-orange-400/25">
                {{ __('Aktien anzeigen') }}
                <x-heroicon-o-arrow-right class="h-3.5 w-3.5" />
            </a>
        </div>
    </div>

    <div class="mt-2 w-full">
        <div class="ak-atlas-scale grid w-full grid-cols-3 gap-px opacity-60" aria-label="{{ __('Farbskala fallend bis steigend') }}">
            <i class="ak-atlas-scale-step h-1 bg-rose-500"></i>
            <i class="ak-atlas-scale-step h-1 bg-slate-500"></i>
            <i class="ak-atlas-scale-step h-1 bg-emerald-500"></i>
        </div>
        <div class="mt-1 flex justify-between text-[8px] font-medium text-slate-600">
            <span>{{ __('Fallend') }}</span>
            <span>{{ __('Unverändert') }}</span>
            <span>{{ __('Steigend') }}</span>
        </div>
    </div>
</x-dashboard.card>
