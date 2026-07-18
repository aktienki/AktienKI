<section>
    <div class="mb-3 flex items-end justify-between gap-4">
        <div>
            <h2 class="text-lg font-semibold text-white">Market Dashboard</h2>
            <p class="mt-1 text-sm text-zinc-400">
                Leitmärkte und die jeweils stärkste KI-Aktie.
            </p>
        </div>

        <span class="hidden items-center gap-2 rounded-full border border-emerald-300/25 bg-emerald-400/10 px-3 py-1 text-xs font-medium text-emerald-200 sm:inline-flex">
            <span class="h-1.5 w-1.5 rounded-full bg-emerald-300 shadow-[0_0_9px_rgba(110,231,183,.9)]"></span>
            Live Snapshot
        </span>
    </div>

    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-5">
        @foreach(($marketCards ?? collect()) as $card)
            @php
                $asset = $card['asset'] ?? null;
                $topStock = $card['top_stock'] ?? null;

                $change = (float) ($asset?->change_percent ?? 0);
                $marketPositive = $change >= 0;

                $signal = strtoupper((string) ($topStock?->signal ?? ''));
                $isBuy = in_array(
                    $signal,
                    ['BUY', 'LONG', 'STRONG_BUY'],
                    true
                );
                $isSell = in_array(
                    $signal,
                    ['SELL', 'SHORT', 'STRONG_SELL'],
                    true
                );

                $strategyReturn = $topStock?->strategy_return_5d
                    ?? $topStock?->market_return_5d;

                $returnPercent = is_null($strategyReturn)
                    ? null
                    : ((float) $strategyReturn * 100);

                $currentPrice = (float) ($topStock?->current_price ?? 0);
                $targetPrice = (float) ($topStock?->predicted_price_5d ?? 0);
                $hasOutlook = $currentPrice > 0 && $targetPrice > 0;

                if ($hasOutlook) {
                    $difference = $targetPrice - $currentPrice;
                    $factors = [0, .14, .32, .55, .78, 1];
                    $values = [];

                    foreach ($factors as $factor) {
                        $values[] = $currentPrice + ($difference * $factor);
                    }

                    $minValue = min($values);
                    $maxValue = max($values);
                    $range = max(
                        $maxValue - $minValue,
                        max(abs($currentPrice) * .01, .01)
                    );

                    $coordinates = [];

                    foreach ($values as $index => $value) {
                        $coordinates[] = [
                            4 + ($index * 27.2),
                            42 - ((($value - $minValue) / $range) * 28),
                        ];
                    }

                    $linePath = collect($coordinates)
                        ->map(
                            fn ($point, $index) =>
                            ($index === 0 ? 'M' : 'L')
                            .number_format($point[0], 2, '.', '')
                            .' '
                            .number_format($point[1], 2, '.', '')
                        )
                        ->implode(' ');

                    $areaPath = $linePath.' L140 48 L4 48 Z';
                    $gradientId = 'market-card-gradient-'.$card['key'];
                }
            @endphp

            <article class="group relative flex min-h-[318px] flex-col overflow-hidden rounded-3xl border border-violet-200/25 bg-gradient-to-br from-white/[0.17] via-white/[0.115] to-violet-300/[0.075] p-5 shadow-2xl shadow-black/10 backdrop-blur-2xl transition duration-300 hover:-translate-y-1 hover:border-violet-200/45 hover:bg-white/[0.19]">
                <div class="absolute inset-x-0 top-0 h-1 {{ $marketPositive ? 'bg-gradient-to-r from-emerald-400/80 via-emerald-300/55 to-transparent' : 'bg-gradient-to-r from-rose-400/80 via-rose-300/55 to-transparent' }}"></div>

                <div class="flex items-start justify-between gap-3">
                    <div class="flex min-w-0 items-center gap-2">
                        <span class="text-xl leading-none">
                            {{ $card['icon'] ?? '•' }}
                        </span>
                        <div class="truncate text-base font-semibold text-white">
                            {{ $card['name'] ?? 'Markt' }}
                        </div>
                    </div>

                    <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-2xl border {{ $marketPositive ? 'border-emerald-300/25 bg-emerald-400/10 text-emerald-300' : 'border-rose-300/25 bg-rose-400/10 text-rose-300' }}">
                        <svg viewBox="0 0 24 24" fill="none" class="h-4 w-4" stroke="currentColor" stroke-width="2">
                            @if($marketPositive)
                                <path d="M4 16l5-5 4 4 7-8" stroke-linecap="round" stroke-linejoin="round"/>
                            @else
                                <path d="M4 8l5 5 4-4 7 8" stroke-linecap="round" stroke-linejoin="round"/>
                            @endif
                        </svg>
                    </div>
                </div>

                <div class="mt-5 text-3xl font-semibold tracking-tight text-white">
                    {{ is_null($asset?->price)
                        ? '—'
                        : number_format((float) $asset->price, 2, ',', '.') }}
                </div>

                <div class="mt-2">
                    @if($asset)
                        <span class="inline-flex items-center rounded-full px-2.5 py-1 text-sm font-semibold {{ $marketPositive ? 'bg-emerald-400/12 text-emerald-300' : 'bg-rose-400/12 text-rose-300' }}">
                            {{ $marketPositive ? '▲' : '▼' }}
                            {{ $marketPositive ? '+' : '' }}{{ number_format($change, 2, ',', '.') }} %
                        </span>
                    @else
                        <span class="text-xs text-zinc-500">
                            Noch keine Indexdaten
                        </span>
                    @endif
                </div>

                <div class="my-4 h-px bg-gradient-to-r from-transparent via-violet-200/20 to-transparent"></div>

                @if($topStock)
                    <div class="mt-auto">
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <div class="text-[10px] font-semibold uppercase tracking-[0.15em] text-zinc-500">
                                    Top KI-Aktie
                                </div>

                                <div class="mt-1 flex items-center gap-2">
                                    <span class="truncate text-sm font-semibold text-white">
                                        {{ $topStock->instrument?->symbol ?? '—' }}
                                    </span>

                                    <span class="rounded-md px-1.5 py-0.5 text-[9px] font-bold {{ $isBuy ? 'bg-emerald-400/12 text-emerald-300' : ($isSell ? 'bg-rose-400/12 text-rose-300' : 'bg-amber-400/12 text-amber-200') }}">
                                        {{ $signal ?: 'HOLD' }}
                                    </span>
                                </div>

                                <div class="mt-0.5 truncate text-[11px] text-zinc-500">
                                    {{ $topStock->instrument?->name ?? 'Instrument' }}
                                </div>
                            </div>

                            <div class="shrink-0 text-right">
                                <div class="text-[9px] uppercase tracking-wider text-zinc-600">
                                    AI Score
                                </div>
                                <div class="mt-0.5 text-lg font-semibold text-violet-200">
                                    {{ number_format((float) $topStock->ai_score, 0, ',', '.') }}
                                </div>
                            </div>
                        </div>

                        <div class="mt-3 grid grid-cols-[1fr_auto] items-end gap-3">
                            <div>
                                @if($hasOutlook)
                                    <svg
                                        viewBox="0 0 144 50"
                                        class="h-12 w-full overflow-visible"
                                        preserveAspectRatio="none"
                                        role="img"
                                        aria-label="KI-Ausblick für {{ $topStock->instrument?->symbol }}"
                                    >
                                        <defs>
                                            <linearGradient id="{{ $gradientId }}" x1="0" y1="0" x2="0" y2="1">
                                                <stop
                                                    offset="0%"
                                                    stop-color="{{ $difference >= 0 ? '#34d399' : '#fb7185' }}"
                                                    stop-opacity=".38"
                                                />
                                                <stop
                                                    offset="100%"
                                                    stop-color="{{ $difference >= 0 ? '#34d399' : '#fb7185' }}"
                                                    stop-opacity="0"
                                                />
                                            </linearGradient>
                                        </defs>

                                        <path
                                            d="{{ $areaPath }}"
                                            fill="url(#{{ $gradientId }})"
                                        />

                                        <path
                                            d="{{ $linePath }}"
                                            fill="none"
                                            stroke="{{ $difference >= 0 ? '#6ee7b7' : '#fda4af' }}"
                                            stroke-width="2"
                                            stroke-linecap="round"
                                            stroke-linejoin="round"
                                            vector-effect="non-scaling-stroke"
                                        />
                                    </svg>
                                @else
                                    <div class="flex h-12 items-center text-[10px] text-zinc-600">
                                        Noch kein Kursausblick
                                    </div>
                                @endif
                            </div>

                            <div class="pb-1 text-right">
                                <div class="text-[9px] uppercase tracking-wider text-zinc-600">
                                    5 Tage
                                </div>

                                <div class="mt-0.5 text-sm font-semibold {{ ($returnPercent ?? 0) >= 0 ? 'text-emerald-300' : 'text-rose-300' }}">
                                    {{ is_null($returnPercent)
                                        ? '—'
                                        : (($returnPercent >= 0 ? '+' : '')
                                            .number_format(
                                                $returnPercent,
                                                1,
                                                ',',
                                                '.'
                                            ).' %') }}
                                </div>
                            </div>
                        </div>
                    </div>
                @else
                    <div class="mt-auto rounded-2xl border border-dashed border-violet-200/15 px-3 py-4 text-center text-xs text-zinc-600">
                        Noch keine regionale KI-Prognose
                    </div>
                @endif
            </article>
        @endforeach
    </div>
</section>
