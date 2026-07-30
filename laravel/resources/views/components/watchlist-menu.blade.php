@auth
    @php
        $topbarWatchlists = \Illuminate\Support\Facades\DB::table('watchlists as watchlist')
            ->leftJoin('watchlist_items as item', 'item.watchlist_id', '=', 'watchlist.id')
            ->leftJoin('instruments as instrument', function ($join) {
                $join->on('instrument.id', '=', 'item.instrument_id')
                    ->where('instrument.is_active', true)
                    ->whereNull('instrument.deleted_at');
            })
            ->where('watchlist.user_id', auth()->id())
            ->where('watchlist.active', true)
            ->groupBy('watchlist.id', 'watchlist.name', 'watchlist.is_default')
            ->selectRaw('watchlist.id, watchlist.name, watchlist.is_default, COUNT(instrument.id) AS stocks_count')
            ->orderByDesc('watchlist.is_default')
            ->orderBy('watchlist.name')
            ->get();
    @endphp

    <div
        x-data="{
            open: false,
            loading: false,
            watchlists: @js($topbarWatchlists->map(fn ($watchlist) => [
                'id' => (int) $watchlist->id,
                'name' => $watchlist->name,
                'is_default' => (bool) $watchlist->is_default,
                'stocks_count' => (int) $watchlist->stocks_count,
                'url' => route('watchlists.show', $watchlist->id),
            ])->values()),
            async toggle() {
                this.open = !this.open;
                if (!this.open) return;
                this.loading = true;
                try {
                    const response = await fetch(@js(route('watchlists.menu')), {
                        headers: { 'Accept': 'application/json' },
                        credentials: 'same-origin',
                        cache: 'no-store'
                    });
                    if (response.ok) {
                        const payload = await response.json();
                        this.watchlists = payload.watchlists ?? [];
                    }
                } finally {
                    this.loading = false;
                }
            }
        }"
        @click.outside="open = false"
        class="relative hidden lg:block"
    >
        <button
            type="button"
            @click="toggle()"
            :aria-expanded="open"
            class="inline-flex h-10 items-center gap-2 rounded-xl border px-3 text-xs font-bold transition {{ request()->routeIs('watchlists.*') ? 'border-violet-400/30 bg-violet-500/15 text-violet-200' : 'border-white/10 bg-white/[.04] text-slate-300 hover:border-violet-400/25 hover:text-white' }}"
        >
            <x-heroicon-o-star class="h-4 w-4 text-amber-300" />
            <span>Watchlist</span>
            <x-heroicon-o-chevron-down class="h-3.5 w-3.5 text-slate-500 transition" x-bind:class="{ 'rotate-180': open }" />
        </button>

        <div
            x-cloak
            x-show="open"
            x-transition.origin.top.right
            class="absolute right-0 z-50 mt-3 w-72 overflow-hidden rounded-2xl border border-white/10 bg-[#171325] shadow-2xl shadow-black/60"
        >
            <div class="border-b border-white/10 px-4 py-3">
                <p class="text-[10px] font-black uppercase tracking-[.16em] text-violet-300">{{ __('Meine Watchlists') }}</p>
            </div>

            <div class="max-h-72 overflow-y-auto p-2">
                <div x-show="loading" class="px-4 py-3 text-center text-xs text-slate-500">{{ __('Wird aktualisiert …') }}</div>
                <template x-for="watchlist in watchlists" :key="watchlist.id">
                    <a :href="watchlist.url" class="flex items-center justify-between gap-3 rounded-xl px-3 py-2.5 text-sm transition hover:bg-violet-500/15">
                        <span class="flex min-w-0 items-center gap-2">
                            <x-heroicon-s-star class="h-4 w-4 shrink-0" x-bind:class="watchlist.is_default ? 'text-amber-300' : 'text-slate-500'" />
                            <span class="truncate font-bold text-slate-200" x-text="watchlist.name"></span>
                        </span>
                        <span class="shrink-0 rounded-lg bg-white/[.05] px-2 py-1 text-[10px] font-black text-slate-400" x-text="watchlist.stocks_count"></span>
                    </a>
                </template>
                <template x-if="!loading && watchlists.length === 0">
                    <div class="px-4 py-7 text-center">
                        <x-heroicon-o-star class="mx-auto h-6 w-6 text-violet-300/50" />
                        <p class="mt-2 text-xs text-slate-400">{{ __('Noch keine Watchlist vorhanden') }}</p>
                    </div>
                </template>
            </div>

            <div class="border-t border-white/10 p-2">
                <a href="{{ route('watchlists.index') }}" class="flex items-center justify-center gap-2 rounded-xl px-3 py-2.5 text-xs font-black text-violet-300 transition hover:bg-violet-500/15 hover:text-violet-200">
                    <x-heroicon-o-cog-6-tooth class="h-4 w-4" />{{ __('Verwalten') }}
                </a>
            </div>
        </div>
    </div>
@endauth
