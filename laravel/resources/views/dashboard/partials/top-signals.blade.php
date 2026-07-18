<section class="rounded-3xl border border-violet-200/20 bg-white/[0.07] p-5 shadow-2xl shadow-black/20 backdrop-blur-xl sm:p-6">
    <div class="mb-5 flex items-end justify-between gap-4">
        <div>
            <h2 class="text-xl font-semibold">Top KI-Signale</h2>
            <p class="mt-1 text-sm text-zinc-500">Stärkste Prognosen mit erwartetem 5-Tage-Verlauf.</p>
        </div>

        <span class="text-xs text-zinc-600">
            Top {{ $topSignals->count() }}
        </span>
    </div>

    <div class="space-y-4">
        @forelse($topSignals as $signal)
            @php
                $signalName = strtoupper((string) $signal->signal);
                $isBuy = in_array($signalName, ['BUY', 'LONG'], true);
                $isSell = in_array($signalName, ['SELL', 'SHORT'], true);

                $return = $signal->strategy_return_5d ?? $signal->market_return_5d;
                $returnPercent = is_null($return) ? null : ((float) $return * 100);

                $currentPrice = (float) (
                    $signal->current_price
                    ?? $signal->price_at_prediction
                    ?? $signal->entry_price
                    ?? 0
                );

                $targetPrice = (float) ($signal->predicted_price_5d ?? 0);

                if ($currentPrice <= 0 && $targetPrice > 0 && !is_null($returnPercent)) {
                    $currentPrice = $targetPrice / max(0.01, 1 + ($returnPercent / 100));
                }

                if ($targetPrice <= 0 && $currentPrice > 0 && !is_null($returnPercent)) {
                    $targetPrice = $currentPrice * (1 + ($returnPercent / 100));
                }

                $hasChart = $currentPrice > 0 && $targetPrice > 0;

                $points = [];

                if ($hasChart) {
                    $difference = $targetPrice - $currentPrice;
                    $trendStrength = max(0.0, min(100.0, (float) ($signal->trend_strength ?? 50))) / 100;
                    $confidence = max(0.0, min(100.0, (float) ($signal->confidence ?? 50))) / 100;

                    $curveFactors = [0, 0.13, 0.31, 0.54, 0.76, 1];

                    foreach ($curveFactors as $index => $factor) {
                        $wave = sin($factor * M_PI) * $difference * (0.035 + (0.035 * $trendStrength));
                        $direction = $difference >= 0 ? 1 : -1;
                        $confidenceAdjustment = $direction * abs($difference) * (1 - $confidence) * 0.05;

                        $value = $currentPrice
                            + ($difference * $factor)
                            + $wave
                            - ($confidenceAdjustment * sin($factor * M_PI));

                        $points[] = $value;
                    }

                    $minValue = min($points);
                    $maxValue = max($points);
                    $range = max($maxValue - $minValue, max(abs($currentPrice) * 0.01, 0.01));

                    $chartCoordinates = [];

                    foreach ($points as $index => $value) {
                        $x = 10 + ($index * 36);
                        $y = 62 - ((($value - $minValue) / $range) * 44);
                        $chartCoordinates[] = [$x, $y];
                    }

                    $linePath = collect($chartCoordinates)
                        ->map(fn ($point, $index) => ($index === 0 ? 'M' : 'L').number_format($point[0], 2, '.', '').' '.number_format($point[1], 2, '.', ''))
                        ->implode(' ');

                    $areaPath = $linePath.' L190 68 L10 68 Z';
                    $gradientId = 'signal-area-gradient-'.$signal->id;
                }
            @endphp

            <article class="grid gap-5 rounded-3xl border border-violet-200/20 bg-gradient-to-br from-white/[0.075] via-black/10 to-violet-400/[0.035] p-5 transition duration-300 hover:border-violet-300/35 hover:bg-white/[0.09] xl:grid-cols-[minmax(0,1.2fr)_minmax(230px,.85fr)_repeat(4,minmax(90px,.48fr))] xl:items-center">
                <div class="min-w-0">
                    <div class="flex items-center gap-3">
                        <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl border border-violet-300/15 bg-violet-500/15 text-sm font-bold text-violet-200">
                            {{ substr($signal->instrument?->symbol ?? 'AI', 0, 3) }}
                        </div>

                        <div class="min-w-0">
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="font-semibold text-white">
                                    {{ $signal->instrument?->symbol ?? 'Unbekannt' }}
                                </span>

                                <span class="rounded-lg px-2.5 py-1 text-[11px] font-bold {{ $isBuy ? 'bg-emerald-400/10 text-emerald-300' : ($isSell ? 'bg-rose-400/10 text-rose-300' : 'bg-amber-400/10 text-amber-200') }}">
                                    {{ $signalName ?: 'HOLD' }}
                                </span>
                            </div>

                            <div class="mt-1 truncate text-sm text-zinc-500">
                                {{ $signal->instrument?->name ?? 'Instrument' }}
                            </div>
                        </div>
                    </div>
                </div>

                <div class="rounded-2xl border border-violet-200/10 bg-black/10 px-3 py-2">
                    <div class="mb-1 flex items-center justify-between gap-3">
                        <span class="text-[10px] font-semibold uppercase tracking-[0.14em] text-zinc-500">
                            Erwarteter Verlauf
                        </span>

                        <span class="text-[10px] text-zinc-600">
                            5 Tage
                        </span>
                    </div>

                    @if($hasChart)
                        <svg
                            viewBox="0 0 200 72"
                            role="img"
                            aria-label="Erwarteter Kursverlauf für {{ $signal->instrument?->symbol ?? 'das Instrument' }}"
                            class="h-[76px] w-full overflow-visible"
                            preserveAspectRatio="none"
                        >
                            <defs>
                                <linearGradient id="{{ $gradientId }}" x1="0" y1="0" x2="0" y2="1">
                                    <stop offset="0%" stop-color="{{ $difference >= 0 ? '#34d399' : '#fb7185' }}" stop-opacity=".42"/>
                                    <stop offset="100%" stop-color="{{ $difference >= 0 ? '#34d399' : '#fb7185' }}" stop-opacity="0"/>
                                </linearGradient>
                            </defs>

                            <line x1="10" y1="68" x2="190" y2="68" stroke="rgba(255,255,255,.08)" stroke-width="1"/>

                            <path
                                d="{{ $areaPath }}"
                                fill="url(#{{ $gradientId }})"
                            />

                            <path
                                d="{{ $linePath }}"
                                fill="none"
                                stroke="{{ $difference >= 0 ? '#6ee7b7' : '#fda4af' }}"
                                stroke-width="2.5"
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                vector-effect="non-scaling-stroke"
                            />

                            @foreach($chartCoordinates as $index => $coordinate)
                                @if($index === 0 || $index === count($chartCoordinates) - 1)
                                    <circle
                                        cx="{{ $coordinate[0] }}"
                                        cy="{{ $coordinate[1] }}"
                                        r="2.8"
                                        fill="{{ $difference >= 0 ? '#a7f3d0' : '#fecdd3' }}"
                                    />
                                @endif
                            @endforeach
                        </svg>

                        <div class="mt-1 flex items-center justify-between text-[10px] text-zinc-600">
                            <span>Heute</span>
                            <span>Ziel</span>
                        </div>
                    @else
                        <div class="flex h-[94px] items-center justify-center text-xs text-zinc-600">
                            Noch keine Kursprojektion
                        </div>
                    @endif
                </div>

                <div>
                    <div class="text-[11px] uppercase tracking-wider text-zinc-600">AI Score</div>
                    <div class="mt-1 text-lg font-semibold text-violet-200">
                        {{ number_format((float) $signal->ai_score, 1, ',', '.') }}
                    </div>
                </div>

                <div>
                    <div class="text-[11px] uppercase tracking-wider text-zinc-600">Confidence</div>
                    <div class="mt-1 text-lg font-semibold">
                        {{ number_format((float) $signal->confidence, 1, ',', '.') }} %
                    </div>
                </div>

                <div>
                    <div class="text-[11px] uppercase tracking-wider text-zinc-600">5 Tage</div>
                    <div class="mt-1 text-lg font-semibold {{ ($returnPercent ?? 0) >= 0 ? 'text-emerald-300' : 'text-rose-300' }}">
                        {{ is_null($returnPercent) ? '—' : (($returnPercent >= 0 ? '+' : '').number_format($returnPercent, 2, ',', '.').' %') }}
                    </div>
                </div>

                <div>
                    <div class="text-[11px] uppercase tracking-wider text-zinc-600">Zielkurs 5T</div>
                    <div class="mt-1 text-lg font-semibold">
                        {{ is_null($signal->predicted_price_5d) ? '—' : number_format((float) $signal->predicted_price_5d, 2, ',', '.') }}
                    </div>
                </div>
            </article>
        @empty
            <div class="rounded-2xl border border-dashed border-violet-200/20 px-6 py-12 text-center">
                <div class="text-sm font-medium text-zinc-300">
                    Noch keine Prognosen vorhanden
                </div>

                <div class="mt-2 text-sm text-zinc-600">
                    Nach dem nächsten Prediction-Lauf erscheinen hier die Top-Signale.
                </div>
            </div>
        @endforelse
    </div>
</section>
