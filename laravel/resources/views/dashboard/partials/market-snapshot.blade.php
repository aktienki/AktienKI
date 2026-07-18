<section class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_minmax(340px,.65fr)]">
    <div class="rounded-3xl border border-white/10 bg-white/[0.035] p-5 shadow-2xl shadow-black/20 backdrop-blur-xl sm:p-6">
        <div class="flex flex-col gap-5 sm:flex-row sm:items-start sm:justify-between">
            <div>
                <h2 class="text-xl font-semibold">Market Snapshot</h2>
                <p class="mt-1 text-sm text-zinc-500">Stimmung, Marktbreite und Risikomodus.</p>
            </div>
            <div class="flex items-center gap-3">
                <div class="text-right">
                    <div class="text-[11px] uppercase tracking-wider text-zinc-600">Market Score</div>
                    <div class="mt-1 text-3xl font-semibold text-violet-200">{{ is_null($snapshot?->market_score) ? '—' : number_format((float) $snapshot->market_score, 1, ',', '.') }}</div>
                </div>
                <div class="flex h-14 w-14 items-center justify-center rounded-2xl border border-violet-400/20 bg-violet-500/10 text-violet-200">
                    <svg viewBox="0 0 24 24" fill="none" class="h-6 w-6" stroke="currentColor" stroke-width="1.7"><path d="M4 18V9m5 9V5m5 13v-7m5 7V3" stroke-linecap="round"/></svg>
                </div>
            </div>
        </div>

        <div class="mt-6 grid gap-3 sm:grid-cols-3">
            <div class="rounded-2xl border border-white/10 bg-black/15 p-4"><div class="text-xs text-zinc-600">Trend</div><div class="mt-2 text-lg font-semibold">{{ $snapshot?->market_trend ?? '—' }}</div></div>
            <div class="rounded-2xl border border-white/10 bg-black/15 p-4"><div class="text-xs text-zinc-600">Risk Mode</div><div class="mt-2 text-lg font-semibold">{{ $snapshot?->risk_mode ?? '—' }}</div></div>
            <div class="rounded-2xl border border-white/10 bg-black/15 p-4"><div class="text-xs text-zinc-600">Breadth</div><div class="mt-2 text-lg font-semibold">{{ is_null($snapshot?->breadth_score) ? '—' : number_format((float) $snapshot->breadth_score, 1, ',', '.') }}</div></div>
        </div>

        <div class="mt-6 space-y-4">
            @foreach([
                ['label' => 'BUY', 'value' => $signalCounts['buy'], 'class' => 'bg-emerald-400'],
                ['label' => 'HOLD', 'value' => $signalCounts['hold'], 'class' => 'bg-amber-300'],
                ['label' => 'SELL', 'value' => $signalCounts['sell'], 'class' => 'bg-rose-400'],
            ] as $row)
                @php $percentage = ($row['value'] / $signalTotal) * 100; @endphp
                <div>
                    <div class="mb-2 flex items-center justify-between text-sm"><span class="font-medium text-zinc-300">{{ $row['label'] }}</span><span class="text-zinc-500">{{ number_format($row['value'], 0, ',', '.') }} · {{ number_format($percentage, 1, ',', '.') }} %</span></div>
                    <div class="h-2 overflow-hidden rounded-full bg-white/5"><div class="h-full rounded-full {{ $row['class'] }}" style="width: {{ min(100, $percentage) }}%"></div></div>
                </div>
            @endforeach
        </div>
    </div>

    <div class="rounded-3xl border border-white/10 bg-white/[0.035] p-5 shadow-2xl shadow-black/20 backdrop-blur-xl sm:p-6">
        <div>
            <h2 class="text-xl font-semibold">Sektor-Radar</h2>
            <p class="mt-1 text-sm text-zinc-500">Stärkste Sektoren im aktuellen Snapshot.</p>
        </div>

        <div class="mt-6 space-y-3">
            @forelse($sectorSnapshots as $sector)
                @php $score = max(0, min(100, (float) ($sector->average_score ?? 0))); @endphp
                <div class="rounded-2xl border border-white/10 bg-black/15 p-4">
                    <div class="flex items-center justify-between gap-4">
                        <div class="min-w-0"><div class="truncate font-medium">{{ $sector->sector }}</div><div class="mt-1 text-xs text-zinc-600">{{ number_format((int) $sector->companies_count, 0, ',', '.') }} Unternehmen</div></div>
                        <div class="text-right"><div class="font-semibold text-violet-200">{{ number_format($score, 1, ',', '.') }}</div><div class="text-[10px] uppercase tracking-wider text-zinc-600">Score</div></div>
                    </div>
                    <div class="mt-3 h-1.5 overflow-hidden rounded-full bg-white/5"><div class="h-full rounded-full bg-gradient-to-r from-violet-500 to-fuchsia-400" style="width: {{ $score }}%"></div></div>
                </div>
            @empty
                <div class="rounded-2xl border border-dashed border-white/10 px-5 py-10 text-center text-sm text-zinc-600">Noch keine Sektor-Snapshots vorhanden.</div>
            @endforelse
        </div>
    </div>
</section>
