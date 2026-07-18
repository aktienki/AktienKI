<aside class="rounded-3xl border border-white/10 bg-gradient-to-b from-violet-500/10 to-white/[0.025] p-5 shadow-2xl shadow-black/20 backdrop-blur-xl sm:p-6">
    <div class="flex items-start justify-between gap-4">
        <div>
            <h2 class="text-xl font-semibold">KI Engine</h2>
            <p class="mt-1 text-sm text-zinc-500">Training und Champion-Status.</p>
        </div>
        <span class="inline-flex items-center gap-2 rounded-full border border-emerald-400/20 bg-emerald-400/10 px-3 py-1 text-xs font-semibold text-emerald-300">
            <span class="h-1.5 w-1.5 rounded-full bg-emerald-300"></span> Aktiv
        </span>
    </div>

    <div class="mt-6 grid grid-cols-2 gap-3">
        <div class="rounded-2xl border border-white/10 bg-black/15 p-4">
            <div class="text-[11px] uppercase tracking-wider text-zinc-600">Aktive Instrumente</div>
            <div class="mt-2 text-2xl font-semibold">{{ number_format($instrumentCount, 0, ',', '.') }}</div>
        </div>
        <div class="rounded-2xl border border-white/10 bg-black/15 p-4">
            <div class="text-[11px] uppercase tracking-wider text-zinc-600">Champions</div>
            <div class="mt-2 text-2xl font-semibold">{{ $champions->count() }}</div>
        </div>
    </div>

    <div class="mt-4 rounded-2xl border border-white/10 bg-black/15 p-4">
        <div class="flex items-center justify-between gap-3">
            <span class="text-sm text-zinc-500">Letzter Trainingslauf</span>
            <span class="rounded-md px-2 py-1 text-xs font-semibold {{ $latestRun?->status === 'completed' ? 'bg-emerald-400/10 text-emerald-300' : 'bg-violet-400/10 text-violet-200' }}">{{ strtoupper($latestRun?->status ?? 'KEIN LAUF') }}</span>
        </div>
        <div class="mt-3 font-semibold">{{ $latestRun?->instrument?->symbol ?? 'Noch kein Instrument' }}</div>
        <div class="mt-1 text-sm text-zinc-500">{{ $latestRun?->trainedModel?->definition?->name ?? 'Modell noch nicht verfügbar' }}</div>
        <div class="mt-3 text-xs text-zinc-600">{{ $latestRun?->started_at?->format('d.m.Y H:i') ?? '—' }}</div>
    </div>

    <div class="mt-4 space-y-3">
        @forelse($champions->take(3) as $champion)
            <div class="flex items-center justify-between gap-4 rounded-xl border border-white/5 bg-white/[0.025] px-3 py-3">
                <div class="min-w-0">
                    <div class="truncate text-sm font-semibold">{{ $champion->instrument?->symbol ?? 'Global' }}</div>
                    <div class="truncate text-xs text-zinc-600">{{ $champion->algorithm ?? $champion->activeModel?->definition?->name ?? 'Champion' }}</div>
                </div>
                <div class="text-right">
                    <div class="text-sm font-semibold text-violet-200">{{ number_format((float) $champion->elo_rating, 0, ',', '.') }}</div>
                    <div class="text-[10px] uppercase tracking-wider text-zinc-600">ELO</div>
                </div>
            </div>
        @empty
            <div class="rounded-xl border border-dashed border-white/10 px-4 py-6 text-center text-sm text-zinc-600">Noch keine aktiven Champion-Modelle.</div>
        @endforelse
    </div>
</aside>
