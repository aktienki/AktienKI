<section class="rounded-3xl border border-white/10 bg-white/[0.035] p-5 shadow-2xl shadow-black/20 backdrop-blur-xl sm:p-6">
    <div class="mb-5 flex items-end justify-between gap-4">
        <div>
            <h2 class="text-xl font-semibold">Top KI-Signale</h2>
            <p class="mt-1 text-sm text-zinc-500">Die stärksten aktuellen Prognosen nach AI Score.</p>
        </div>
        <span class="text-xs text-zinc-600">Top {{ $topSignals->count() }}</span>
    </div>

    <div class="space-y-3">
        @forelse($topSignals as $signal)
            @php
                $signalName = strtoupper((string) $signal->signal);
                $isBuy = in_array($signalName, ['BUY', 'LONG'], true);
                $isSell = in_array($signalName, ['SELL', 'SHORT'], true);
                $return = $signal->strategy_return_5d ?? $signal->market_return_5d;
                $returnPercent = is_null($return) ? null : ((float) $return * 100);
            @endphp
            <article class="grid gap-4 rounded-2xl border border-white/10 bg-black/15 p-4 transition hover:border-violet-400/30 md:grid-cols-[minmax(0,1.4fr)_repeat(4,minmax(90px,.55fr))] md:items-center">
                <div class="min-w-0">
                    <div class="flex items-center gap-3">
                        <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-violet-500/15 text-sm font-bold text-violet-200">
                            {{ substr($signal->instrument?->symbol ?? 'AI', 0, 3) }}
                        </div>
                        <div class="min-w-0">
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="font-semibold">{{ $signal->instrument?->symbol ?? 'Unbekannt' }}</span>
                                <span class="rounded-md px-2 py-0.5 text-[11px] font-bold {{ $isBuy ? 'bg-emerald-400/10 text-emerald-300' : ($isSell ? 'bg-rose-400/10 text-rose-300' : 'bg-amber-400/10 text-amber-200') }}">{{ $signalName ?: 'HOLD' }}</span>
                            </div>
                            <div class="mt-0.5 truncate text-sm text-zinc-500">{{ $signal->instrument?->name ?? 'Instrument' }}</div>
                        </div>
                    </div>
                </div>

                <div>
                    <div class="text-[11px] uppercase tracking-wider text-zinc-600">AI Score</div>
                    <div class="mt-1 text-lg font-semibold text-violet-200">{{ number_format((float) $signal->ai_score, 1, ',', '.') }}</div>
                </div>
                <div>
                    <div class="text-[11px] uppercase tracking-wider text-zinc-600">Confidence</div>
                    <div class="mt-1 text-lg font-semibold">{{ number_format((float) $signal->confidence, 1, ',', '.') }} %</div>
                </div>
                <div>
                    <div class="text-[11px] uppercase tracking-wider text-zinc-600">5 Tage</div>
                    <div class="mt-1 text-lg font-semibold {{ ($returnPercent ?? 0) >= 0 ? 'text-emerald-300' : 'text-rose-300' }}">
                        {{ is_null($returnPercent) ? '—' : (($returnPercent >= 0 ? '+' : '').number_format($returnPercent, 2, ',', '.').' %') }}
                    </div>
                </div>
                <div>
                    <div class="text-[11px] uppercase tracking-wider text-zinc-600">Zielkurs 5T</div>
                    <div class="mt-1 text-lg font-semibold">{{ is_null($signal->predicted_price_5d) ? '—' : number_format((float) $signal->predicted_price_5d, 2, ',', '.') }}</div>
                </div>
            </article>
        @empty
            <div class="rounded-2xl border border-dashed border-white/10 px-6 py-12 text-center">
                <div class="text-sm font-medium text-zinc-300">Noch keine Prognosen vorhanden</div>
                <div class="mt-2 text-sm text-zinc-600">Nach dem nächsten Prediction-Lauf erscheinen hier die Top-Signale.</div>
            </div>
        @endforelse
    </div>
</section>
