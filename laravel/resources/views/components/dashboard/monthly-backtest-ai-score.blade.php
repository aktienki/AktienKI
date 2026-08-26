@props(['series' => []])

<article class="ak-detail-panel ak-standard-card mt-4 p-4 sm:p-5">
    <header class="flex flex-wrap items-start justify-between gap-3 border-b border-cyan-400/15 pb-3">
        <div>
            <p class="ak-market-eyebrow">{{ __('Backtest-Historie') }}</p>
            <h2 class="mt-1 text-lg font-black tracking-[-.02em] text-[var(--ak-text)] sm:text-xl">
                {{ __('KI-Score-Verteilung pro Monat') }}
            </h2>
            <p class="mt-1 text-xs text-[var(--ak-muted)]">
                {{ __('Boxplot aus Backtests · Median, Q25–Q75 und Spannweite · letzte 3 Jahre · Skala 3–8') }}
            </p>
        </div>
        <span class="rounded-lg border border-cyan-400/20 bg-cyan-400/[.06] px-2.5 py-1 text-[9px] font-black uppercase tracking-[.1em] text-cyan-500">
            {{ __('Nur abgeschlossene Backtests') }}
        </span>
    </header>

    @php
        $chartWidth = 1200;
        $chartHeight = 280;
        $plotLeft = 46;
        $plotTop = 14;
        $plotWidth = 1138;
        $plotHeight = 220;
        $monthGroups = collect(range(1, 12))->mapWithKeys(function (int $month) use ($series): array {
            $points = collect($series)
                ->filter(fn (array $point): bool => (int) substr((string) ($point['month'] ?? ''), 5, 2) === $month)
                ->sortBy('month')
                ->take(-3)
                ->values();

            return [$month => $points];
        });
        $monthSlot = $plotWidth / 12;
        $monthGap = 14;
        $monthInnerWidth = $monthSlot - $monthGap;
        $yearSlot = $monthInnerWidth / 3;
        $barWidth = max(6, $yearSlot * .58);
        $years = collect($series)->pluck('month')->filter()->map(fn (string $month): int => (int) substr($month, 0, 4))->unique()->sort()->values();
        $yearPalette = ['#06b6d4', '#14b8a6', '#f59e0b', '#8b5cf6', '#e11d48'];
        $yearColors = $years->mapWithKeys(fn (int $year, int $index): array => [$year => $yearPalette[$index % count($yearPalette)]]);
    @endphp

    <div class="mt-3 flex flex-wrap items-center gap-x-4 gap-y-1 text-[9px] font-bold text-[var(--ak-muted)]" aria-label="{{ __('Farblegende nach Jahr') }}">
        @foreach ($yearColors as $year => $color)
            <span class="inline-flex items-center gap-1.5"><i class="h-2.5 w-2.5 rounded-sm" style="background-color: {{ $color }}"></i>{{ $year }}</span>
        @endforeach
    </div>

    <div class="mt-2 w-full overflow-x-auto pb-1">
        <svg
            class="h-[250px] min-w-[900px] w-full sm:h-[290px]"
            viewBox="0 0 {{ $chartWidth }} {{ $chartHeight }}"
            preserveAspectRatio="none"
            role="img"
            aria-label="{{ __('Boxplot der monatlichen KI-Score-Verteilung') }}">
            @foreach ([3, 4, 5, 6, 7, 8] as $tick)
                @php $y = $plotTop + $plotHeight - ((($tick - 3) / 5) * $plotHeight); @endphp
                <line x1="{{ $plotLeft }}" y1="{{ $y }}" x2="{{ $plotLeft + $plotWidth }}" y2="{{ $y }}" class="stroke-slate-300/45 [[data-theme=dark]_&]:stroke-slate-600/35" stroke-dasharray="4 5" />
                <text x="34" y="{{ $y + 4 }}" text-anchor="end" class="fill-slate-500 text-[10px] font-bold [[data-theme=dark]_&]:fill-slate-400">{{ $tick }}</text>
            @endforeach

            @php $neutralY = $plotTop + $plotHeight - ((2 / 5) * $plotHeight); @endphp
            <line x1="{{ $plotLeft }}" y1="{{ $neutralY }}" x2="{{ $plotLeft + $plotWidth }}" y2="{{ $neutralY }}" class="stroke-amber-500/70 [[data-theme=dark]_&]:stroke-yellow-400/70" stroke-width="1.5" stroke-dasharray="7 5" />
            <text x="{{ $plotLeft + 5 }}" y="{{ $neutralY - 6 }}" class="fill-amber-700 text-[9px] font-black [[data-theme=dark]_&]:fill-yellow-300">{{ __('Neutral 5,0') }}</text>

            @foreach ($monthGroups as $month => $points)
                @php
                    $monthBaseX = $plotLeft + (($month - 1) * $monthSlot);
                    $monthX = $monthBaseX + ($monthGap / 2);
                @endphp
                @if ($month > 1)
                    <line x1="{{ $monthBaseX }}" y1="{{ $plotTop }}" x2="{{ $monthBaseX }}" y2="{{ $plotTop + $plotHeight }}" class="stroke-slate-300/20 [[data-theme=dark]_&]:stroke-slate-600/20" />
                @endif

                @foreach (range(0, 2) as $yearIndex)
                    @php
                    $point = $points->get($yearIndex);
                    $year = $point !== null ? (int) substr((string) $point['month'], 0, 4) : null;
                    $yearColor = $year !== null ? ($yearColors->get($year) ?? '#06b6d4') : '#94a3b8';
                    $rawMedian = is_numeric($point['median'] ?? null) ? (float) $point['median'] : null;
                    $rawQ25 = is_numeric($point['q25'] ?? null) ? (float) $point['q25'] : null;
                    $rawQ75 = is_numeric($point['q75'] ?? null) ? (float) $point['q75'] : null;
                    $rawMinimum = is_numeric($point['minimum'] ?? null) ? (float) $point['minimum'] : null;
                    $rawMaximum = is_numeric($point['maximum'] ?? null) ? (float) $point['maximum'] : null;
                    $median = $rawMedian !== null ? max(3, min(8, $rawMedian)) : null;
                    $q25 = $rawQ25 !== null ? max(3, min(8, $rawQ25)) : null;
                    $q75 = $rawQ75 !== null ? max(3, min(8, $rawQ75)) : null;
                    $minimum = $rawMinimum !== null ? max(3, min(8, $rawMinimum)) : null;
                    $maximum = $rawMaximum !== null ? max(3, min(8, $rawMaximum)) : null;
                    $x = $monthX + ($yearIndex * $yearSlot) + (($yearSlot - $barWidth) / 2);
                    $centerX = $x + ($barWidth / 2);
                    $scoreY = fn (float $score): float => $plotTop + $plotHeight - ((($score - 3) / 5) * $plotHeight);
                    @endphp
                    @if ($median !== null && $q25 !== null && $q75 !== null && $minimum !== null && $maximum !== null)
                    @php
                        $minY = $scoreY($minimum);
                        $maxY = $scoreY($maximum);
                        $q25Y = $scoreY($q25);
                        $q75Y = $scoreY($q75);
                        $medianY = $scoreY($median);
                        $boxHeight = max(2, $q25Y - $q75Y);
                        $capWidth = max(4, $barWidth * .65);
                    @endphp
                    <g>
                        <title>{{ $point['label'] }} · Median {{ number_format($rawMedian, 2, ',', '.') }} · Q25–Q75 {{ number_format($rawQ25, 2, ',', '.') }}–{{ number_format($rawQ75, 2, ',', '.') }} · Min–Max {{ number_format($rawMinimum, 2, ',', '.') }}–{{ number_format($rawMaximum, 2, ',', '.') }} · {{ number_format((int) ($point['observations'] ?? 0), 0, ',', '.') }} {{ __('Beobachtungen') }}</title>
                        <line x1="{{ $centerX }}" y1="{{ $maxY }}" x2="{{ $centerX }}" y2="{{ $minY }}" style="stroke: {{ $yearColor }}" stroke-width="1.6" />
                        <line x1="{{ $centerX - ($capWidth / 2) }}" y1="{{ $maxY }}" x2="{{ $centerX + ($capWidth / 2) }}" y2="{{ $maxY }}" style="stroke: {{ $yearColor }}" stroke-width="1.6" />
                        <line x1="{{ $centerX - ($capWidth / 2) }}" y1="{{ $minY }}" x2="{{ $centerX + ($capWidth / 2) }}" y2="{{ $minY }}" style="stroke: {{ $yearColor }}" stroke-width="1.6" />
                        <rect x="{{ $x }}" y="{{ $q75Y }}" width="{{ $barWidth }}" height="{{ $boxHeight }}" rx="2" style="fill: {{ $yearColor }}; fill-opacity: .78; stroke: {{ $yearColor }}" stroke-width="1.9" />
                        <line x1="{{ $x + 1 }}" y1="{{ $medianY }}" x2="{{ $x + $barWidth - 1 }}" y2="{{ $medianY }}" class="stroke-white [[data-theme=light]_&]:stroke-slate-950" stroke-width="3" />
                    </g>
                    @endif
                    @if ($point !== null)
                        <text x="{{ $centerX }}" y="247" text-anchor="middle" class="fill-slate-400 text-[7px] font-semibold [[data-theme=dark]_&]:fill-slate-500">{{ substr((string) $point['month'], 2, 2) }}</text>
                    @endif
                @endforeach

                <text x="{{ $monthBaseX + ($monthSlot / 2) }}" y="266" text-anchor="middle" class="fill-slate-600 text-[10px] font-black [[data-theme=dark]_&]:fill-slate-300">{{ \Carbon\Carbon::create(2026, $month, 1)->locale(app()->getLocale())->translatedFormat('M') }}</text>
            @endforeach
        </svg>
    </div>
</article>
