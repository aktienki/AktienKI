<section>
    <div class="mb-4 flex items-end justify-between gap-4">
        <div>
            <h2 class="text-lg font-semibold">Marktübersicht</h2>
            <p class="mt-1 text-sm text-zinc-500">Indizes, Rohstoffe, Währungen und Risikoindikatoren.</p>
        </div>
        <span class="rounded-full border border-emerald-400/20 bg-emerald-400/10 px-3 py-1 text-xs font-medium text-emerald-300">Snapshot</span>
    </div>

    <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4 xl:grid-cols-5">
        @forelse($marketAssets as $asset)
            @php
                $positive = (float) $asset->change_percent >= 0;
            @endphp
            <article class="group rounded-2xl border border-white/10 bg-gradient-to-br from-white/[0.07] to-white/[0.025] p-4 shadow-2xl shadow-black/10 backdrop-blur-xl transition duration-300 hover:-translate-y-0.5 hover:border-violet-400/30">
                <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0">
                        <div class="truncate text-xs font-medium uppercase tracking-wider text-zinc-500">{{ $asset->symbol }}</div>
                        <div class="mt-1 truncate font-semibold text-zinc-100">{{ $asset->name }}</div>
                    </div>
                    <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl {{ $positive ? 'bg-emerald-400/10 text-emerald-300' : 'bg-rose-400/10 text-rose-300' }}">
                        <svg viewBox="0 0 24 24" fill="none" class="h-4 w-4" stroke="currentColor" stroke-width="2"><path d="M4 16l5-5 4 4 7-8" stroke-linecap="round" stroke-linejoin="round"/></svg>
                    </div>
                </div>
                <div class="mt-5 text-2xl font-semibold tracking-tight">{{ is_null($asset->price) ? '—' : number_format((float) $asset->price, 2, ',', '.') }}</div>
                <div class="mt-2 flex items-center justify-between text-sm">
                    <span class="font-semibold {{ $positive ? 'text-emerald-300' : 'text-rose-300' }}">{{ $positive ? '+' : '' }}{{ number_format((float) $asset->change_percent, 2, ',', '.') }} %</span>
                    <span class="text-xs text-zinc-600">{{ $asset->category }}</span>
                </div>
            </article>
        @empty
            @foreach(['DAX','S&P 500','NASDAQ','Gold','VIX'] as $placeholder)
                <article class="rounded-2xl border border-dashed border-white/10 bg-white/[0.025] p-4">
                    <div class="text-sm font-semibold text-zinc-300">{{ $placeholder }}</div>
                    <div class="mt-5 text-2xl font-semibold text-zinc-600">—</div>
                    <div class="mt-2 text-xs text-zinc-600">Noch kein Market Snapshot</div>
                </article>
            @endforeach
        @endforelse
    </div>
</section>
