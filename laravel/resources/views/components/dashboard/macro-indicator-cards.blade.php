@props(['cards' => [], 'collapsible' => false])

@php
    $sharedForecastBandValues = collect($cards)->flatMap(function (array $card) {
        $band = $card['forecast_band'] ?? [];
        return collect(['q25', 'median', 'q75'])->flatMap(fn (string $key) => collect($band[$key] ?? [])->pluck('value'));
    })->filter(fn ($value) => is_numeric($value));
    $sharedForecastBandMin = min(0, (float) ($sharedForecastBandValues->min() ?? 0));
    $sharedForecastBandMax = max(0, (float) ($sharedForecastBandValues->max() ?? 0));
    $sharedForecastBandRange = max(.0001, $sharedForecastBandMax - $sharedForecastBandMin);
@endphp

<section class="mt-4 grid gap-3 md:grid-cols-3" aria-label="{{ __('Makroindikatoren') }}">
    @foreach ($cards as $macroCard)
        @php
            $allPoints = collect($macroCard['series'])->flatMap(fn (array $series) => $series['points'] ?? [])->filter(fn (array $point) => is_numeric($point['value'] ?? null))->values();
            $pricePoints = collect($macroCard['series'])
                ->reject(fn (array $series): bool => ($series['axis'] ?? null) === 'score')
                ->flatMap(fn (array $series) => $series['points'] ?? [])
                ->filter(fn (array $point) => is_numeric($point['value'] ?? null))
                ->values();
            $scalePoints = $pricePoints->isNotEmpty() ? $pricePoints : $allPoints;
            $minValue = (float) ($scalePoints->min('value') ?? 0);
            $maxValue = (float) ($scalePoints->max('value') ?? 1);
            $range = max(0.0001, $maxValue - $minValue);
            $chartPointCount = max(1, (int) collect($macroCard['series'])->map(
                fn (array $series): int => count($series['points'] ?? []) + (int) ($series['offset'] ?? 0)
            )->max() - 1);
            $chartPoints = function (array $points, ?float $scaleMin = null, ?float $scaleRange = null, int $offset = 0) use ($minValue, $range, $chartPointCount): string {
                $scaleMin ??= $minValue;
                $scaleRange ??= $range;
                return collect($points)->values()->map(function (array $point, int $index) use ($scaleMin, $scaleRange, $chartPointCount, $offset): string {
                    $x = 8 + (($index + $offset) / $chartPointCount) * 304;
                    $y = 78 - (((float) $point['value'] - $scaleMin) / $scaleRange) * 64;
                    return number_format($x, 1, '.', '').','.number_format($y, 1, '.', '');
                })->implode(' ');
            };
            $latestPoint = $allPoints->last();
            $macroKey = $macroCard['key'] ?? null;
            $titleCountryCode = strtoupper(trim((string) \Illuminate\Support\Str::before((string) ($macroCard['title'] ?? ''), ' ·')));
            $titleCountryFlag = strlen($titleCountryCode) === 2
                ? mb_chr(127397 + ord($titleCountryCode[0])).mb_chr(127397 + ord($titleCountryCode[1]))
                : null;
            $macroFlag = $titleCountryFlag ?: match ($macroKey) {
                'dax-ai-score', 'ai-dax', 'dax-backtest', 'vdax' => '🇩🇪',
                'sp500-ai-score', 'sp500-backtest', 'nasdaq-backtest' => '🇺🇸',
                'global-ai-score' => '🌐',
                default => null,
            };
            $forecastMedianEnd = data_get(collect(data_get($macroCard, 'forecast_band.median', []))->last(), 'value');
            $forecastDirection = is_numeric($forecastMedianEnd)
                ? ((float) $forecastMedianEnd > .5 ? 'up' : ((float) $forecastMedianEnd < -.5 ? 'down' : 'neutral'))
                : 'neutral';
        @endphp
        <article @if($collapsible) x-data="{ expanded: false }" @endif class="ak-card-static ak-standard-card min-w-0 overflow-hidden rounded-2xl border border-teal-500/25 bg-[var(--ak-surface)] p-0 shadow-[0_12px_28px_rgba(15,23,42,.08)]">
            <header class="ak-macro-card-head relative flex items-start justify-between gap-3 border-b border-teal-500/20 bg-gradient-to-r from-teal-500/[.12] via-transparent to-amber-400/[.08] px-4 py-3 {{ $collapsible ? 'pr-24' : '' }}">
                <div class="ak-macro-card-copy min-w-0">
                    <p class="text-[9px] font-black uppercase tracking-[.18em] text-teal-500">{{ __('Makroindikator') }}</p>
                    <h2 class="ak-macro-card-title mt-1 flex min-w-0 items-center gap-1.5 text-base font-black text-[var(--ak-text)]">@if($macroFlag)<span class="shrink-0" aria-hidden="true">{{ $macroFlag }}</span>@endif<span class="truncate">{{ $macroCard['title'] }}</span></h2>
                    <p class="ak-macro-card-subtitle mt-0.5 truncate text-[10px] text-[var(--ak-muted)]">{{ $macroCard['subtitle'] }}</p>
                </div>
                <div class="flex min-w-0 shrink-0 items-start gap-2">
                @if (in_array($macroCard['key'] ?? null, ['rates', 'vdax', 'ai-dax', 'dax-backtest', 'sp500-backtest', 'nasdaq-backtest', 'dax-ai-score', 'sp500-ai-score', 'global-ai-score'], true))
                    <div class="ak-macro-card-values flex shrink-0 gap-2 text-right">
                        @foreach ($macroCard['series'] as $series)
                            @php $latestRate = collect($series['points'] ?? [])->last(); $displayValue = $series['display_value'] ?? ($latestRate['value'] ?? null); @endphp
                            @if ($latestRate)
                                @php
                                    $valueUnit = $series['display_unit'] ?? (($series['axis'] ?? null) === 'score' ? ' /7'
                                        : (($series['axis'] ?? null) === 'basis100' ? ' Basis 100'
                                        : (($series['axis'] ?? null) === 'return' ? ' %'
                                        : (in_array($macroCard['key'] ?? null, ['dax-ai-score', 'sp500-ai-score'], true) ? ' Punkte'
                                        : (($macroCard['key'] ?? null) === 'global-ai-score' ? ' Indexpunkte' : '%')))));
                                @endphp
                                <div class="ak-macro-card-value">
                                    <small class="block text-[8px] font-black uppercase text-[var(--ak-muted)]">{{ $series['name'] }}</small>
                                    <strong class="block text-sm font-black tabular-nums text-[var(--ak-text)]">{{ number_format((float) $displayValue, ($series['axis'] ?? null) === 'score' ? 1 : 2, ',', '.') }}{{ $valueUnit }}</strong>
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
                @if($collapsible)
                    <span class="absolute right-14 top-3 z-20 inline-flex h-9 w-9 items-center justify-center rounded-xl border {{ $forecastDirection === 'up' ? 'border-emerald-400/35 bg-emerald-400/[.08] text-emerald-400' : ($forecastDirection === 'down' ? 'border-rose-400/35 bg-rose-400/[.08] text-rose-400' : 'border-amber-400/35 bg-amber-400/[.08] text-amber-400') }}" title="{{ $forecastDirection === 'up' ? __('Steigend erwartet') : ($forecastDirection === 'down' ? __('Fallend erwartet') : __('Neutral erwartet')) }}">
                        @if($forecastDirection === 'up')<x-heroicon-o-arrow-trending-up class="h-4 w-4" />@elseif($forecastDirection === 'down')<x-heroicon-o-arrow-trending-down class="h-4 w-4" />@else<x-heroicon-o-arrow-right class="h-4 w-4" />@endif
                    </span>
                    <button type="button" @click="expanded = ! expanded" :aria-expanded="expanded.toString()" class="absolute right-3 top-3 z-20 inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-xl border border-cyan-400/30 bg-cyan-400/[.07] text-cyan-400 transition hover:bg-cyan-400/[.14]" aria-label="{{ __('Karte ein- oder ausklappen') }}">
                        <x-heroicon-o-chevron-down class="h-4 w-4 transition-transform duration-200" x-bind:class="expanded && 'rotate-180'" />
                    </button>
                @endif
                </div>
            </header>
            <div @if($collapsible) x-show="expanded" x-cloak @endif class="px-3 pb-3 pt-2">
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
                        @if (($macroCard['normalize'] ?? true) === false && $minValue <= 0 && $maxValue >= 0)
                            @php $zeroY = 78 - ((0 - $minValue) / $range) * 64; @endphp
                            <line x1="8" y1="{{ number_format($zeroY, 1, '.', '') }}" x2="312" y2="{{ number_format($zeroY, 1, '.', '') }}" stroke="currentColor" stroke-opacity=".34" stroke-dasharray="5 4" />
                        @endif
                        @if (in_array($macroCard['key'] ?? null, ['dax-backtest', 'sp500-backtest'], true) && collect($macroCard['series'])->contains(fn (array $series): bool => ($series['axis'] ?? null) === 'score'))
                            <text x="312" y="10" text-anchor="end" fill="#a78bfa" fill-opacity=".9" font-size="7">7</text>
                            <text x="312" y="48" text-anchor="end" fill="#a78bfa" fill-opacity=".75" font-size="7">5</text>
                            <text x="312" y="88" text-anchor="end" fill="#a78bfa" fill-opacity=".9" font-size="7">3</text>
                        @endif
                        @foreach ($macroCard['series'] as $series)
                            @if (count($series['points'] ?? []) > 0)
                                @php
                                    $seriesValues = collect($series['points'])->pluck('value')->filter(fn ($value) => is_numeric($value));
                                    $seriesMin = (float) ($seriesValues->min() ?? 0);
                                    $seriesRange = max(0.0001, (float) ($seriesValues->max() ?? 1) - $seriesMin);
                                    $isScoreAxis = ($series['axis'] ?? null) === 'score';
                                    $isNormalizedComparison = ($macroCard['normalize'] ?? true) && in_array($macroCard['key'] ?? null, ['dax-ai-score', 'sp500-ai-score', 'global-ai-score'], true);
                                    $comparisonValues = collect($macroCard['series'])->flatMap(fn (array $item) => collect($item['points'] ?? [])->pluck('value'))->filter(fn ($value) => is_numeric($value));
                                    $comparisonMin = (float) ($comparisonValues->min() ?? 0);
                                    $comparisonRange = max(0.0001, (float) ($comparisonValues->max() ?? 1) - $comparisonMin);
                                    $plotPoints = $isNormalizedComparison
                                        ? collect($series['points'])->map(fn (array $point): array => [
                                            ...$point,
                                            'value' => (((float) $point['value'] - $comparisonMin) / $comparisonRange) * 100,
                                        ])->all()
                                        : $series['points'];
                                    $useOwnScale = in_array($macroCard['key'] ?? null, ['vdax', 'ai-dax'], true) || $isScoreAxis;
                                @endphp
                                <polyline points="{{ $isNormalizedComparison ? $chartPoints($plotPoints, 0, 100, (int) ($series['offset'] ?? 0)) : ($isScoreAxis ? $chartPoints($plotPoints, (float) ($series['score_min'] ?? 0), max(.0001, (float) ($series['score_max'] ?? 10) - (float) ($series['score_min'] ?? 0)), (int) ($series['offset'] ?? 0)) : ($useOwnScale ? $chartPoints($plotPoints, $seriesMin, $seriesRange, (int) ($series['offset'] ?? 0)) : $chartPoints($plotPoints, null, null, (int) ($series['offset'] ?? 0)))) }}" fill="none" stroke="{{ $series['color'] }}" stroke-width="{{ $isScoreAxis ? '2.2' : '3.5' }}" @if($series['dashed'] ?? false) stroke-dasharray="5 4" @endif stroke-linecap="round" stroke-linejoin="round" vector-effect="non-scaling-stroke" style="filter:drop-shadow(0 1px 2px rgba(15,23,42,.28))" />
                            @endif
                        @endforeach
                    </svg>
                    @if (in_array($macroCard['key'] ?? null, ['dax-ai-score', 'sp500-ai-score', 'global-ai-score'], true))
                        <p class="mt-0.5 text-right text-[8px] font-bold text-[var(--ak-muted)]">{{ __('Gleicher Handelstag · Prognoserevision in Prozentpunkten · Indexbewegung in Prozent') }}</p>
                    @elseif (($macroCard['key'] ?? null) === 'ai-dax')
                        <p class="mt-0.5 text-right text-[8px] font-bold text-[var(--ak-muted)]">{{ __('KI-Score links / DAX-Punkte rechts') }}</p>
                    @elseif (($macroCard['key'] ?? null) === 'vdax')
                        <p class="mt-0.5 text-right text-[8px] font-bold text-[var(--ak-muted)]">{{ __('VDAX links in Prozentpunkten · DAX rechts in Punkten') }}</p>
                    @elseif (($macroCard['key'] ?? null) === 'rates')
                        <p class="mt-0.5 text-right text-[8px] font-bold text-[var(--ak-muted)]">{{ __('Tageswert in Prozentpunkten') }}</p>
                    @elseif (($macroCard['key'] ?? null) === 'bonds')
                        <p class="mt-0.5 text-right text-[8px] font-bold text-[var(--ak-muted)]">{{ __('Tageswert in USD') }}</p>
                    @elseif (in_array($macroCard['key'] ?? null, ['dax-backtest', 'sp500-backtest'], true))
                        <p class="mt-0.5 text-right text-[8px] font-bold text-[var(--ak-muted)]">{{ __('Gleiches 20T-Prognosefenster · Orange erwartete Rendite · Cyan realisierte Rendite · Violett Modellscore 3–7') }}</p>
                    @endif
                    @if (!empty($macroCard['forecast_band']))
                        @php
                            $forecastBand = $macroCard['forecast_band'];
                            $q25 = collect($forecastBand['q25'] ?? [])->values();
                            $medianPath = collect($forecastBand['median'] ?? [])->values();
                            $q75 = collect($forecastBand['q75'] ?? [])->values();
                            $bandValues = $q25->concat($medianPath)->concat($q75)->pluck('value')->filter(fn ($value) => is_numeric($value));
                            $bandMin = $sharedForecastBandMin;
                            $bandMax = $sharedForecastBandMax;
                            $bandRange = $sharedForecastBandRange;
                            $bandX = fn (int $index): float => 8 + ($index / max(1, $medianPath->count() - 1)) * 304;
                            $bandY = fn (float $value): float => 54 - (($value - $bandMin) / $bandRange) * 42;
                            $bandPoints = fn ($points): string => collect($points)->values()->map(
                                fn (array $point, int $index): string => number_format($bandX($index), 1, '.', '').','.number_format($bandY((float) $point['value']), 1, '.', '')
                            )->implode(' ');
                            $areaPoints = $bandPoints($q75).' '.$bandPoints($q25->reverse()->values());
                            $bandZeroY = $bandY(0);
                            $q25End = (float) data_get($q25->last(), 'value', 0);
                            $medianEnd = (float) data_get($medianPath->last(), 'value', 0);
                            $q75End = (float) data_get($q75->last(), 'value', 0);
                        @endphp
                        <div class="mt-2 border-t border-[var(--ak-border)] pt-2">
                            <div class="flex items-center justify-between gap-2 text-[8px] font-black uppercase tracking-[.08em] text-[var(--ak-muted)]">
                                <span>{{ __('Erwartungsband · nächste 20 Handelstage') }}</span>
                                <span class="tabular-nums">{{ number_format($q25End, 1, ',', '.') }} % · {{ number_format($medianEnd, 1, ',', '.') }} % · {{ number_format($q75End, 1, ',', '.') }} %</span>
                            </div>
                            <svg viewBox="0 0 320 66" class="mt-1 h-[76px] w-full" role="img" aria-label="{{ __('Q25-, Median- und Q75-Projektion für die nächsten 20 Handelstage') }}">
                                <line x1="8" y1="{{ number_format($bandZeroY, 1, '.', '') }}" x2="312" y2="{{ number_format($bandZeroY, 1, '.', '') }}" stroke="currentColor" stroke-opacity=".25" stroke-dasharray="3 4" />
                                <polygon points="{{ $areaPoints }}" fill="var(--ak-forecast-line)" fill-opacity=".16" />
                                <polyline points="{{ $bandPoints($q25) }}" fill="none" stroke="var(--ak-forecast-line)" stroke-opacity=".48" stroke-width="1.2" vector-effect="non-scaling-stroke" />
                                <polyline points="{{ $bandPoints($q75) }}" fill="none" stroke="var(--ak-forecast-line)" stroke-opacity=".48" stroke-width="1.2" vector-effect="non-scaling-stroke" />
                                <polyline points="{{ $bandPoints($medianPath) }}" fill="none" stroke="var(--ak-forecast-line)" stroke-width="2" stroke-dasharray="5 4" stroke-linecap="round" vector-effect="non-scaling-stroke" />
                                <text x="8" y="64" fill="currentColor" fill-opacity=".55" font-size="7">T</text>
                                <text x="312" y="64" text-anchor="end" fill="currentColor" fill-opacity=".55" font-size="7">T+20</text>
                            </svg>
                            <p class="flex items-center justify-between gap-2 text-[8px] font-bold text-[var(--ak-muted)]"><span>{{ __('Gemeinsame Y-Skala') }} {{ number_format($bandMin, 1, ',', '.') }} % {{ __('bis') }} {{ number_format($bandMax, 1, ',', '.') }} %</span><span>{{ __('Q25–Q75 · gestrichelt: Median · Stand') }} {{ $forecastBand['as_of'] ?? '—' }}</span></p>
                        </div>
                    @endif
                @endif
            </div>
        </article>
    @endforeach
</section>
