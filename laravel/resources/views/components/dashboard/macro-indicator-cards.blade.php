@props(['cards' => []])

<section class="mt-4 grid gap-3 md:grid-cols-3" aria-label="{{ __('Makroindikatoren') }}">
    @foreach ($cards as $macroCard)
        @php
            $allPoints = collect($macroCard['series'])->flatMap(fn (array $series) => $series['points'] ?? [])->filter(fn (array $point) => is_numeric($point['value'] ?? null))->values();
            $minValue = (float) ($allPoints->min('value') ?? 0);
            $maxValue = (float) ($allPoints->max('value') ?? 1);
            $range = max(0.0001, $maxValue - $minValue);
            $chartPoints = function (array $points, ?float $scaleMin = null, ?float $scaleRange = null) use ($minValue, $range): string {
                $scaleMin ??= $minValue;
                $scaleRange ??= $range;
                $count = max(1, count($points) - 1);
                return collect($points)->values()->map(function (array $point, int $index) use ($scaleMin, $scaleRange, $count): string {
                    $x = 8 + ($index / $count) * 304;
                    $y = 78 - (((float) $point['value'] - $scaleMin) / $scaleRange) * 64;
                    return number_format($x, 1, '.', '').','.number_format($y, 1, '.', '');
                })->implode(' ');
            };
            $latestPoint = $allPoints->last();
        @endphp
        <article class="ak-card-static ak-standard-card overflow-hidden rounded-2xl border border-teal-500/25 bg-[var(--ak-surface)] p-0 shadow-[0_12px_28px_rgba(15,23,42,.08)]">
            <header class="flex items-start justify-between gap-3 border-b border-teal-500/20 bg-gradient-to-r from-teal-500/[.12] via-transparent to-amber-400/[.08] px-4 py-3">
                <div class="min-w-0">
                    <p class="text-[9px] font-black uppercase tracking-[.18em] text-teal-500">{{ __('Makroindikator') }}</p>
                    <h2 class="mt-1 truncate text-base font-black text-[var(--ak-text)]">{{ $macroCard['title'] }}</h2>
                    <p class="mt-0.5 truncate text-[10px] text-[var(--ak-muted)]">{{ $macroCard['subtitle'] }}</p>
                </div>
                @if (in_array($macroCard['key'] ?? null, ['rates', 'vdax', 'ai-dax'], true))
                    <div class="flex shrink-0 gap-2 text-right">
                        @foreach ($macroCard['series'] as $series)
                            @php $latestRate = collect($series['points'] ?? [])->last(); @endphp
                            @if ($latestRate)
                                <div>
                                    <small class="block text-[8px] font-black uppercase text-[var(--ak-muted)]">{{ $series['name'] }}</small>
                                    <strong class="block text-sm font-black tabular-nums text-[var(--ak-text)]">{{ number_format((float) $latestRate['value'], 2, ',', '.') }}{{ in_array($macroCard['key'] ?? null, ['vdax', 'ai-dax'], true) && ($series['name'] ?? '') === __('DAX Kurs') ? ' Punkte' : (($macroCard['key'] ?? null) === 'ai-dax' ? ' /10' : '%') }}</strong>
                                </div>
                            @endif
                        @endforeach
                    </div>
                @elseif ($latestPoint)
                    <div class="shrink-0 text-right">
                        <strong class="block text-lg font-black tabular-nums text-[var(--ak-text)]">{{ number_format((float) $latestPoint['value'], 2, ',', '.') }}{{ $macroCard['unit'] }}</strong>
                        <small class="text-[9px] font-bold text-[var(--ak-muted)]">{{ $latestPoint['label'] }}</small>
                    </div>
                @endif
            </header>
            <div class="px-3 pb-3 pt-2">
                @if ($allPoints->isEmpty())
                    <div class="grid h-[104px] place-items-center rounded-xl border border-dashed border-[var(--ak-border)] text-[10px] font-bold text-[var(--ak-muted)]">{{ __('Noch keine historischen Reihen verfügbar.') }}</div>
                @else
                    <div class="mb-1 flex flex-wrap items-center gap-x-3 gap-y-1">
                        @foreach ($macroCard['series'] as $series)
                            <span class="inline-flex items-center gap-1 text-[9px] font-black text-[var(--ak-muted)]"><i class="h-2 w-2 rounded-full" style="background:{{ $series['color'] }}"></i>{{ $series['name'] }}</span>
                        @endforeach
                    </div>
                    <svg viewBox="0 0 320 92" class="h-[104px] w-full" role="img" aria-label="{{ $macroCard['title'] }} {{ __('Linienchart') }}">
                        @if (in_array($macroCard['key'] ?? null, ['vdax', 'ai-dax'], true))
                            @php
                                $daxAxis = collect($macroCard['series'])->firstWhere('name', __('DAX Kurs'))['points'] ?? [];
                                $compareAxis = collect($macroCard['series'])->firstWhere('name', ($macroCard['key'] ?? null) === 'vdax' ? __('VDAX') : __('Median KI-Score'))['points'] ?? [];
                                $daxValues = collect($daxAxis)->pluck('value')->filter(fn ($value) => is_numeric($value));
                                $compareValues = collect($compareAxis)->pluck('value')->filter(fn ($value) => is_numeric($value));
                            @endphp
                            <text x="8" y="10" fill="{{ ($macroCard['key'] ?? null) === 'vdax' ? '#fb7185' : '#22d3ee' }}" fill-opacity=".9" font-size="7">{{ number_format((float) ($compareValues->max() ?? 0), 1, ',', '.') }}{{ ($macroCard['key'] ?? null) === 'vdax' ? '%' : '' }}</text>
                            <text x="8" y="88" fill="{{ ($macroCard['key'] ?? null) === 'vdax' ? '#fb7185' : '#22d3ee' }}" fill-opacity=".9" font-size="7">{{ number_format((float) ($compareValues->min() ?? 0), 1, ',', '.') }}{{ ($macroCard['key'] ?? null) === 'vdax' ? '%' : '' }}</text>
                            <text x="312" y="10" text-anchor="end" fill="#fbbf24" fill-opacity=".9" font-size="7">{{ number_format((float) ($daxValues->max() ?? 0), 1, ',', '.') }}</text>
                            <text x="312" y="88" text-anchor="end" fill="#fbbf24" fill-opacity=".9" font-size="7">{{ number_format((float) ($daxValues->min() ?? 0), 1, ',', '.') }}</text>
                        @endif
                        <line x1="8" y1="14" x2="312" y2="14" stroke="currentColor" stroke-opacity=".10" stroke-dasharray="3 4" />
                        <line x1="8" y1="46" x2="312" y2="46" stroke="currentColor" stroke-opacity=".10" stroke-dasharray="3 4" />
                        <line x1="8" y1="78" x2="312" y2="78" stroke="currentColor" stroke-opacity=".10" stroke-dasharray="3 4" />
                        @foreach ($macroCard['series'] as $series)
                            @if (count($series['points'] ?? []) > 0)
                                @php
                                    $seriesValues = collect($series['points'])->pluck('value')->filter(fn ($value) => is_numeric($value));
                                    $seriesMin = (float) ($seriesValues->min() ?? 0);
                                    $seriesRange = max(0.0001, (float) ($seriesValues->max() ?? 1) - $seriesMin);
                                    $useOwnScale = in_array($macroCard['key'] ?? null, ['vdax', 'ai-dax'], true);
                                @endphp
                                <polyline points="{{ $useOwnScale ? $chartPoints($series['points'], $seriesMin, $seriesRange) : $chartPoints($series['points']) }}" fill="none" stroke="{{ $series['color'] }}" stroke-width="3.5" stroke-linecap="round" stroke-linejoin="round" vector-effect="non-scaling-stroke" style="filter:drop-shadow(0 1px 2px rgba(15,23,42,.28))" />
                            @endif
                        @endforeach
                    </svg>
                    @if (($macroCard['key'] ?? null) === 'ai-dax')
                        <p class="mt-0.5 text-right text-[8px] font-bold text-[var(--ak-muted)]">{{ __('KI-Score links / DAX-Punkte rechts') }}</p>
                    @elseif (($macroCard['key'] ?? null) === 'vdax')
                        <p class="mt-0.5 text-right text-[8px] font-bold text-[var(--ak-muted)]">{{ __('VDAX links in Prozentpunkten · DAX rechts in Punkten') }}</p>
                    @elseif (($macroCard['key'] ?? null) === 'rates')
                        <p class="mt-0.5 text-right text-[8px] font-bold text-[var(--ak-muted)]">{{ __('Tageswert in Prozentpunkten') }}</p>
                    @elseif (($macroCard['key'] ?? null) === 'bonds')
                        <p class="mt-0.5 text-right text-[8px] font-bold text-[var(--ak-muted)]">{{ __('Tageswert in USD') }}</p>
                    @endif
                @endif
            </div>
        </article>
    @endforeach
</section>
