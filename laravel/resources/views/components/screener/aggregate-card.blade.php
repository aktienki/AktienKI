@props([
    'rank', 'eyebrow', 'name', 'symbol' => null, 'meta' => null, 'secondaryMeta' => null,
    'members' => 0, 'analyzed' => 0, 'score' => null, 'confidence' => null, 'hitRate' => null,
    'profitPerTrade' => null, 'stability' => null, 'risk' => null,
    'expectedReturn' => null, 'description', 'assessment', 'target', 'icon' => null,
    'metricOneLabel' => 'Mitglieder', 'metricOneValue' => null,
    'chartPoints' => [], 'chartLabel' => null,
    'topStocks' => [],
    'scoreTrend' => [],
    'analysisCard' => null,
    'technicalAnalysis' => false,
    'realtimeQuotes' => false,
    'marketInfo' => null, 'marketInfoDate' => null, 'marketInfoModel' => null,
    'mobileCompact' => false,
])
@php
    $scorePercent = $score !== null ? max(0, min(100, (float)$score * 10)) : 0;
    $qualityDonutColor = static function (float $percent): string {
        $percent = max(0, min(100, $percent));
        $hue = $percent <= 50 ? ($percent / 50) * 48 : 48 + (($percent - 50) / 50) * 94;
        return sprintf('hsl(%.1f 78%% 52%%)', $hue);
    };
    $scoreColor = $score !== null ? $qualityDonutColor($scorePercent) : '#64748b';
    $confidencePercent = $confidence !== null ? max(0, min(100, (float)$confidence)) : 0;
    $hitRatePercent = $hitRate !== null ? max(0, min(100, (float)$hitRate)) : 0;
    $stabilityPercent = $stability !== null ? max(0, min(100, (float)$stability)) : 0;
    $profitPercent = $profitPerTrade !== null ? max(0, min(100, 50 + ((float)$profitPerTrade * 10))) : 0;
    $riskPercent = $risk !== null ? max(0, min(100, (float)$risk)) : 0;
    $analysisCoveragePercent = $members > 0 ? max(0, min(100, ((float) $analyzed / (float) $members) * 100)) : 0;
    $confidenceColor = $confidence !== null ? $qualityDonutColor($confidencePercent) : '#64748b';
    $riskColor = $risk !== null ? $qualityDonutColor(100 - $riskPercent) : '#64748b';
    $returnClass = ($expectedReturn ?? 0) >= 0 ? 'text-emerald-300' : 'text-rose-300';
    $chartValues = collect($chartPoints)->pluck('close')->filter(fn($value) => is_numeric($value))->map(fn($value) => (float)$value)->values();
    $emaValues = collect(); $upperBand = collect(); $lowerBand = collect(); $ema = null; $emaFactor = 2 / 21;
    foreach ($chartValues as $index => $value) {
        $ema = $ema === null ? $value : ($value * $emaFactor) + ($ema * (1 - $emaFactor));
        $emaValues->put($index, $ema);
        if ($index >= 19) {
            $window = $chartValues->slice($index - 19, 20); $mean = (float) $window->avg();
            $sd = sqrt((float) $window->map(fn ($item) => ($item - $mean) ** 2)->avg());
            $upperBand->put($index, $mean + (2 * $sd)); $lowerBand->put($index, $mean - (2 * $sd));
        }
    }
    $indicatorScale = $technicalAnalysis ? $chartValues->concat($upperBand)->concat($lowerBand) : $chartValues;
    $chartMin = $indicatorScale->isNotEmpty() ? (float)$indicatorScale->min() : 0;
    $chartRange = $indicatorScale->isNotEmpty() ? max(.000001, (float)$indicatorScale->max() - $chartMin) : 1;
    $chartY = fn (float $value): float => 112 - (($value - $chartMin) / $chartRange) * 96;
    $chartX = fn (int $index): float => $index * 600 / max(1, $chartValues->count() - 1);
    $chartPolyline = $chartValues->count() > 1
        ? $chartValues->map(fn($value, $index) => sprintf('%.1f,%.1f', $chartX($index), $chartY($value)))->implode(' ')
        : '';
    $emaPolyline = $emaValues->map(fn ($value, $index) => sprintf('%.1f,%.1f', $chartX($index), $chartY($value)))->implode(' ');
    $upperPolyline = $upperBand->map(fn ($value, $index) => sprintf('%.1f,%.1f', $chartX($index), $chartY($value)))->implode(' ');
    $lowerPolyline = $lowerBand->map(fn ($value, $index) => sprintf('%.1f,%.1f', $chartX($index), $chartY($value)))->implode(' ');
    $bollingerArea = $upperPolyline.' '.$lowerBand->reverse()->map(fn ($value, $index) => sprintf('%.1f,%.1f', $chartX($index), $chartY($value)))->implode(' ');
    $chartArea = $chartPolyline ? '0,116 '.$chartPolyline.' 600,116' : '';
    $chartLastY = $chartValues->isNotEmpty() ? $chartY((float) $chartValues->last()) : 112;
    $changes = $chartValues->zip($chartValues->slice(1))->map(fn ($pair) => isset($pair[1]) ? $pair[1] - $pair[0] : null)->filter(fn ($value) => $value !== null)->take(-14);
    $avgGain = (float) $changes->filter(fn ($value) => $value > 0)->sum() / 14; $avgLoss = abs((float) $changes->filter(fn ($value) => $value < 0)->sum()) / 14;
    $rsi = $changes->count() >= 14 ? ($avgLoss == 0.0 ? 100.0 : 100 - (100 / (1 + ($avgGain / $avgLoss)))) : null;
    $trendUp = $chartValues->isNotEmpty() && $emaValues->isNotEmpty() && (float) $chartValues->last() >= (float) $emaValues->last();
    $aggregateCountryCode = strtoupper(trim((string) Illuminate\Support\Str::before((string) $meta, '·')));
    $aggregateFlag = strlen($aggregateCountryCode) === 2
        ? mb_chr(127397 + ord($aggregateCountryCode[0])).mb_chr(127397 + ord($aggregateCountryCode[1]))
        : '🌐';
    $scoreTrendValues = collect($scoreTrend)->pluck('average_score')->filter(fn ($value) => is_numeric($value))->map(fn ($value) => (float) $value)->values();
    $scoreTrendMin = $scoreTrendValues->isNotEmpty() ? (float) $scoreTrendValues->min() : 0;
    $scoreTrendRange = $scoreTrendValues->isNotEmpty() ? max(.001, (float) $scoreTrendValues->max() - $scoreTrendMin) : 1;
    $scoreTrendPolyline = $scoreTrendValues->count() > 1
        ? $scoreTrendValues->map(fn ($value, $index) => sprintf('%.1f,%.1f', $index * 76 / ($scoreTrendValues->count() - 1) + 2, 22 - (($value - $scoreTrendMin) / $scoreTrendRange) * 18))->implode(' ')
        : '';
@endphp
<article data-ranking="{{ $rank }}" class="screener-stock-card ak-card ak-dashboard-card {{ $analysisCard ? 'screener-card-with-analysis' : '' }} relative overflow-hidden p-3" @if($mobileCompact) x-data="{ mobileExpanded: false }" @endif>
    <a href="{{ $target }}" class="absolute inset-0 z-10 {{ $mobileCompact ? 'hidden md:block' : '' }}" aria-label="{{ $name }}"></a>
    @if($mobileCompact)
        <button type="button" class="screener-mobile-summary aggregate-mobile-summary md:hidden" @click="mobileExpanded = ! mobileExpanded" :aria-expanded="mobileExpanded.toString()">
            <span class="screener-mobile-summary-top">
                <span class="screener-mobile-title-context">
                    <span class="screener-mobile-name-row">
                        <b class="screener-mobile-rank">#{{ $rank }}</b>
                        <span class="screener-mobile-flag" aria-label="{{ $aggregateCountryCode }}">{{ $aggregateFlag }}</span>
                        <span class="screener-mobile-name" title="{{ $name }}">{{ $name }}</span>
                    </span>
                    <span class="screener-mobile-header-meta">
                        @if($symbol)<span><x-heroicon-o-chart-bar-square class="h-3 w-3" /><small>{{ $symbol }}</small></span>@endif
                        <span><x-heroicon-o-globe-alt class="h-3 w-3" /><small>{{ $secondaryMeta ?: $meta }}</small></span>
                    </span>
                </span>
                <span class="aggregate-mobile-score-trend" title="{{ __('Verlauf des durchschnittlichen KI-Scores aller Indexmitglieder') }}">
                    @if($scoreTrendPolyline)
                        <svg viewBox="0 0 80 26" role="img" aria-label="{{ __('Ø KI-Score-Verlauf') }}"><path d="M2 23H78" stroke="currentColor" stroke-opacity=".16" /><polyline points="{{ $scoreTrendPolyline }}" fill="none" stroke="currentColor" stroke-width="1.1" stroke-linecap="round" stroke-linejoin="round" /></svg>
                    @else
                        <span class="aggregate-mobile-score-trend-empty">—</span>
                    @endif
                    <small>{{ __('Ø Score-Verlauf') }}</small>
                </span>
                <span class="screener-mobile-header-metric aggregate-mobile-score-metric"><b class="aggregate-mobile-header-value">{{ $score !== null ? number_format($score * 10, 0, ',', '.') : '—' }}</b><small>{{ __('Ø KI-Score') }}</small></span>
                <span class="screener-mobile-header-metric aggregate-mobile-return-metric"><b class="aggregate-mobile-header-value {{ $returnClass }}">{{ $expectedReturn !== null ? (($expectedReturn > 0 ? '+' : '').number_format($expectedReturn, 1, ',', '.').' %') : '—' }}</b><small>{{ __('Ø mögliche Rendite') }}</small></span>
                <x-heroicon-o-chevron-down class="screener-mobile-chevron h-4 w-4" x-bind:class="mobileExpanded && 'rotate-180'" />
            </span>
            <span class="aggregate-mobile-summary-metrics">
                <span><b>{{ $members }}</b><small>{{ __('Mitglieder') }}</small></span>
                <span><b>{{ $analyzed }}</b><small>{{ __('Analysiert') }}</small></span>
                <span><b>{{ $risk !== null ? number_format($risk, 0, ',', '.').' %' : '—' }}</b><small>{{ __('Ø Risiko') }}</small></span>
            </span>
        </button>
    @endif
    <div class="{{ $mobileCompact ? 'screener-mobile-details' : '' }} grid h-full min-h-0 gap-2 md:grid-cols-2 2xl:grid-cols-6" @if($mobileCompact) x-bind:class="mobileExpanded && 'is-mobile-open'" @endif>
        <div class="screener-index-primary {{ $mobileCompact ? 'screener-chart-panel' : '' }} {{ $analysisCard ? 'screener-chart-panel-with-analysis' : '' }} relative h-full min-h-0 rounded-xl border border-transparent bg-[linear-gradient(145deg,rgba(34,211,238,.14),rgba(8,47,73,.10)_48%,rgba(251,191,36,.055))] p-3 pt-5 shadow-[inset_3px_0_0_rgba(34,211,238,.62),0_0_30px_rgba(34,211,238,.18),0_12px_34px_rgba(2,8,23,.24)] 2xl:col-span-2">
            @if($mobileCompact)<div class="screener-card-actions relative z-30 mb-2 flex md:hidden"><a href="{{ $target }}" class="inline-flex h-8 w-8 items-center justify-center rounded-xl border border-violet-400/30 bg-violet-400/[.08] text-violet-300"><x-heroicon-o-arrow-top-right-on-square class="h-4 w-4" /></a></div>@endif
            <div class="grid gap-3 md:grid-cols-[.85fr_1fr]">
                <div class="{{ $mobileCompact ? 'screener-expanded-identity' : '' }}">
                    <p class="screener-border-title text-amber-300" style="display:flex!important;flex-direction:row!important;align-items:center!important;gap:.3rem!important;white-space:nowrap!important"><strong style="display:inline!important;margin:0!important">#{{ $rank }}</strong><span>{{ $eyebrow }}</span></p>
                    <div class="mt-1 flex items-center gap-2">@if($icon)<span class="text-cyan-300">{{ $icon }}</span>@endif<span class="text-lg leading-none" aria-label="{{ $aggregateCountryCode }}">{{ $aggregateFlag }}</span><h2 class="text-base font-black">{{ $name }}</h2></div>
                    @if($symbol)<p class="text-xs font-black uppercase tracking-[.12em] text-cyan-300">{{ $symbol }}</p>@endif
                    <p class="mt-2 text-sm">{{ $meta ?: '—' }}</p>
                    <p class="mt-1 text-[10px] font-bold text-[var(--ak-muted)]">{{ $secondaryMeta ?: '—' }}</p>
                    <span class="mt-3 inline-flex w-28 justify-center rounded-lg border border-amber-400/40 bg-amber-400/[.08] px-2.5 py-1 text-[10px] font-black tracking-[.08em] text-amber-300">#{{ $rank }}</span>
                </div>
                <div class="{{ $mobileCompact ? 'screener-expanded-price' : '' }}">
                    <p class="text-[9px] font-black uppercase tracking-[.12em] text-cyan-300">{{ __('Analysiert') }}</p>
                    <p class="mt-1 whitespace-nowrap text-xl font-black"><span class="text-emerald-300">{{ $analyzed }}</span><span class="text-[var(--ak-muted)]">/{{ $members }}</span></p>
                    <p class="mt-1 text-xs font-black text-emerald-300">{{ number_format($analysisCoveragePercent, 0, ',', '.') }} %</p>
                </div>
                <div class="md:col-span-2">
                    <div class="mb-1 flex items-center justify-between text-[9px] font-black uppercase tracking-[.1em] text-[var(--ak-muted)]"><span>{{ $chartLabel ?: __('Chart') }}</span>@if($chartValues->isNotEmpty())<span class="text-amber-300">{{ number_format((float)$chartValues->last(),2,',','.') }}</span>@endif</div>
                    <div class="relative h-24 overflow-hidden">@if($chartPolyline)<svg viewBox="0 0 600 120" class="h-24 w-full" role="img" aria-label="{{ $chartLabel ?: __('Kursverlauf') }}" preserveAspectRatio="none"><defs><linearGradient id="index-chart-fill-{{ $rank }}" x1="0" y1="0" x2="0" y2="1"><stop offset="0%" stop-color="#22d3ee" stop-opacity=".28"/><stop offset="100%" stop-color="#22d3ee" stop-opacity=".015"/></linearGradient><filter id="index-chart-glow-{{ $rank }}" x="-10%" y="-20%" width="120%" height="140%"><feGaussianBlur stdDeviation="2" result="blur"/><feMerge><feMergeNode in="blur"/><feMergeNode in="SourceGraphic"/></feMerge></filter></defs><path d="M0 24H600M0 56H600M0 88H600M0 116H600" stroke="currentColor" stroke-opacity=".09" stroke-dasharray="4 6" vector-effect="non-scaling-stroke"/>@if($technicalAnalysis)<polygon points="{{ $bollingerArea }}" fill="#a78bfa" fill-opacity=".11"/><polyline points="{{ $upperPolyline }}" fill="none" stroke="#a78bfa" stroke-opacity=".55" stroke-width="1.1" vector-effect="non-scaling-stroke"/><polyline points="{{ $lowerPolyline }}" fill="none" stroke="#a78bfa" stroke-opacity=".55" stroke-width="1.1" vector-effect="non-scaling-stroke"/><polyline points="{{ $emaPolyline }}" fill="none" stroke="#f59e0b" stroke-width="1.8" stroke-linecap="round" vector-effect="non-scaling-stroke"/>@endif<polygon points="{{ $chartArea }}" fill="url(#index-chart-fill-{{ $rank }})"/><polyline points="{{ $chartPolyline }}" fill="none" stroke="#22d3ee" stroke-width="3.2" stroke-linecap="round" stroke-linejoin="round" vector-effect="non-scaling-stroke" filter="url(#index-chart-glow-{{ $rank }})"/><circle cx="600" cy="{{ number_format($chartLastY, 1, '.', '') }}" r="4.2" fill="#f8fafc" stroke="#0891b2" stroke-width="2.5" vector-effect="non-scaling-stroke"/></svg>@else<div class="grid h-full place-items-center text-xs italic text-[var(--ak-muted)]">{{ __('Keine Daten') }}</div>@endif</div>
                    @if($technicalAnalysis && $chartValues->isNotEmpty())<div class="mt-1 flex flex-wrap items-center gap-1.5 text-[7px] font-black"><span class="rounded border border-amber-400/25 bg-amber-400/[.07] px-1.5 py-0.5 text-amber-500">EMA20 · {{ $trendUp ? __('Aufwärtstrend') : __('Abwärtstrend') }}</span><span class="rounded border border-violet-400/25 bg-violet-400/[.07] px-1.5 py-0.5 text-violet-500">RSI14 · {{ $rsi !== null ? number_format($rsi, 0, ',', '.') : '—' }} · {{ $rsi !== null && $rsi >= 70 ? __('überkauft') : ($rsi !== null && $rsi <= 30 ? __('überverkauft') : __('neutral')) }}</span><span class="text-[var(--ak-muted)]">{{ __('Violett: Bollinger-Band') }}</span></div>@endif
                </div>
            </div>
            @if($analysisCard)
                <div class="screener-index-analysis-donuts relative z-20 mt-3 min-w-0 border-t border-cyan-300/15 pt-3">
                    <div class="screener-ranking-donuts">
                        <div class="screener-metric-wrap"><div class="screener-metric-donut" style="--donut-value:{{ $confidencePercent }}%;--donut-color:{{ $confidenceColor }}"><span>{{ $confidence !== null ? number_format($confidence,0,',','.') : '—' }}</span></div><small>{{ __('Ø Konf.') }} (%)</small></div>
                        <div class="screener-metric-wrap"><div class="screener-metric-donut" style="--donut-value:{{ $hitRatePercent }}%;--donut-color:{{ $hitRate !== null ? $qualityDonutColor($hitRatePercent) : '#64748b' }}"><span>{{ $hitRate !== null ? number_format($hitRate,0,',','.') : '—' }}</span></div><small>{{ __('Ø Hit-Rate') }} (%)</small></div>
                        <div class="screener-metric-wrap"><div class="screener-metric-donut" style="--donut-value:{{ $profitPercent }}%;--donut-color:{{ $profitPerTrade !== null ? $qualityDonutColor($profitPercent) : '#64748b' }}"><span class="screener-metric-value-long">{{ $profitPerTrade !== null ? (($profitPerTrade > 0 ? '+' : '').number_format($profitPerTrade,2,',','.')) : '—' }}</span></div><small>{{ __('Ø/Trade') }} (%)</small></div>
                        <div class="screener-metric-wrap"><div class="screener-metric-donut" style="--donut-value:{{ $stabilityPercent }}%;--donut-color:{{ $stability !== null ? $qualityDonutColor($stabilityPercent) : '#64748b' }}"><span>{{ $stability !== null ? number_format($stability,0,',','.') : '—' }}</span></div><small>{{ __('Ø Stabilität') }} (%)</small></div>
                    </div>
                </div>
            @endif
        </div>

        @unless($analysisCard)
        <div class="screener-index-metrics grid h-full min-h-0 gap-2 sm:grid-cols-2 2xl:col-span-2">
            <div class="screener-index-donut-panel relative rounded-xl border border-amber-400/25 bg-amber-400/[.05] p-3 sm:col-span-2">
                <div class="screener-ranking-donuts">
                    <div class="screener-metric-wrap"><div class="screener-metric-donut" style="--donut-value:{{ $confidencePercent }}%;--donut-color:{{ $confidenceColor }}"><span>{{ $confidence !== null ? number_format($confidence,0,',','.') : '—' }}</span></div><small>{{ __('Ø Konf.') }} (%)</small></div>
                    <div class="screener-metric-wrap"><div class="screener-metric-donut" style="--donut-value:{{ $hitRatePercent }}%;--donut-color:{{ $hitRate !== null ? $qualityDonutColor($hitRatePercent) : '#64748b' }}"><span>{{ $hitRate !== null ? number_format($hitRate,0,',','.') : '—' }}</span></div><small>{{ __('Ø Hit-Rate') }} (%)</small></div>
                    <div class="screener-metric-wrap"><div class="screener-metric-donut" style="--donut-value:{{ $profitPercent }}%;--donut-color:{{ $profitPerTrade !== null ? $qualityDonutColor($profitPercent) : '#64748b' }}"><span class="screener-metric-value-long">{{ $profitPerTrade !== null ? (($profitPerTrade > 0 ? '+' : '').number_format($profitPerTrade,2,',','.')) : '—' }}</span></div><small>{{ __('Ø/Trade') }} (%)</small></div>
                    <div class="screener-metric-wrap"><div class="screener-metric-donut" style="--donut-value:{{ $stabilityPercent }}%;--donut-color:{{ $stability !== null ? $qualityDonutColor($stabilityPercent) : '#64748b' }}"><span>{{ $stability !== null ? number_format($stability,0,',','.') : '—' }}</span></div><small>{{ __('Ø Stabilität') }} (%)</small></div>
                </div><div class="screener-donut-spacer" aria-hidden="true"></div>
            </div>
        </div>
        @endunless
            @php
                $cardInfo = $marketInfo ?: $description;
            @endphp
            <details class="screener-index-market-info company-description-card screener-company-card relative z-20 flex h-full min-h-0 flex-col rounded-xl border border-amber-400/25 bg-amber-400/[.05] p-3 md:col-span-2 {{ $analysisCard ? '2xl:col-span-2' : '2xl:col-span-4' }}">
                <summary class="flex cursor-pointer list-none items-center justify-between gap-2"><div class="min-w-0 flex-1"><div class="flex items-center gap-2"><p class="text-[9px] font-black uppercase tracking-[.12em] text-cyan-300">{{ $marketInfo ? __('Tägliche Marktinfo') : __('Beschreibung') }}</p>@if($marketInfoDate)<span class="text-[8px] font-bold text-[var(--ak-muted)]">{{ \Illuminate\Support\Carbon::parse($marketInfoDate)->format('d.m.Y') }}</span>@endif</div><p class="company-preview mt-2 text-xs leading-5 text-[var(--ak-muted)]">{{ $cardInfo }}</p></div><span class="ml-2 inline-flex h-6 w-6 shrink-0 items-center justify-center rounded-full border border-cyan-300/50 bg-cyan-400/10 text-xs font-black text-cyan-200">i</span></summary>
                <p class="company-description mt-2 flex-1 text-xs leading-5 text-[var(--ak-muted)]">{{ $cardInfo }}</p>
                @if($marketInfoModel)<p class="mt-2 text-[8px] font-bold uppercase tracking-[.1em] text-cyan-300/60">{{ __('Erstellt mit') }} {{ $marketInfoModel }}</p>@endif
            </details>
        <div class="screener-index-top-stocks grid h-auto min-h-0 gap-3 md:col-span-2 2xl:col-span-2">
            @if(collect($topStocks)->isNotEmpty())
                <div class="screener-top-stocks-panel relative z-20 h-full min-h-0 rounded-xl border border-amber-400/25 bg-amber-400/[.05] p-3">
                    <div class="flex items-center justify-between gap-2 border-b border-cyan-300/15 pb-2">
                        <p class="text-[9px] font-black uppercase tracking-[.12em] text-cyan-300">{{ __('Top 3 Aktien') }}</p>
                        <span class="text-right text-[8px] font-bold {{ $realtimeQuotes ? 'text-emerald-300' : 'text-[var(--ak-muted)]' }}">{{ $realtimeQuotes ? __('TwelveData · Kursstand') : __('Tageskurse') }}</span>
                    </div>
                    <div class="screener-top-stocks-grid mt-2 grid gap-1.5">
                        @foreach(collect($topStocks)->take(3) as $topStock)
                            @php
                                $topScore = is_numeric($topStock->ai_score ?? null) ? \App\Support\AiScore::toTen($topStock->ai_score) : null;
                                $latestDailyClose = is_numeric($topStock->latest_daily_close ?? null) ? (float) $topStock->latest_daily_close : null;
                                $previousDailyClose = is_numeric($topStock->previous_daily_close ?? null) ? (float) $topStock->previous_daily_close : null;
                                $topPrice = $realtimeQuotes && is_numeric($topStock->live_price ?? null)
                                    ? (float) $topStock->live_price
                                    : ($latestDailyClose ?? (is_numeric($topStock->prediction_price ?? null) ? (float) $topStock->prediction_price : null));
                                $changeBase = $realtimeQuotes ? $latestDailyClose : $previousDailyClose;
                                $topChange = $topPrice !== null && $changeBase !== null && $changeBase != 0
                                    ? (($topPrice / $changeBase) - 1) * 100
                                    : null;
                                $topSignal = strtoupper((string) ($topStock->personalized_signal ?? 'HOLD'));
                                $signalClass = $topSignal === 'BUY' ? 'text-emerald-300' : ($topSignal === 'SELL' ? 'text-rose-300' : 'text-amber-300');
                                $changeClass = ($topChange ?? 0) > 0 ? 'text-emerald-300' : (($topChange ?? 0) < 0 ? 'text-rose-300' : 'text-[var(--ak-muted)]');
                                $quoteTime = $realtimeQuotes && !empty($topStock->quote_time)
                                    ? \Illuminate\Support\Carbon::parse($topStock->quote_time)
                                    : null;
                                $quoteClock = $quoteTime?->timezone('Europe/Berlin')->format('H:i:s');
                                $quoteAgeMinutes = $quoteTime ? max(0, (int) $quoteTime->diffInMinutes(now())) : null;
                                $quoteIsFresh = $quoteAgeMinutes !== null && $quoteAgeMinutes < 2;
                                $countryCode = strtoupper(trim((string) ($topStock->country ?? '')));
                                $countryFlag = strlen($countryCode) === 2
                                    ? mb_chr(127397 + ord($countryCode[0])).mb_chr(127397 + ord($countryCode[1]))
                                    : '🌐';
                            @endphp
                            <a href="{{ route('stocks.show', ['symbol' => $topStock->symbol, 'prediction' => $topStock->prediction_id, 'return_to' => request()->getRequestUri()]) }}" onclick="event.stopPropagation()" data-sector-live-symbol="{{ $topStock->symbol }}" class="relative z-30 grid grid-cols-[minmax(0,1fr)_auto] gap-2 rounded-lg border border-cyan-300/10 bg-cyan-400/[.035] px-2.5 py-1.5 transition hover:border-cyan-300/30 hover:bg-cyan-400/[.08]">
                                <span class="min-w-0 self-center"><b class="flex items-center gap-1.5 truncate text-xs text-[var(--ak-text)]"><span aria-hidden="true">{{ $countryFlag }}</span><span class="truncate">{{ $topStock->symbol }}</span></b><small class="block truncate text-[8px] text-[var(--ak-muted)]">{{ $topStock->name }}</small><small class="block text-[7px] {{ $signalClass }}">{{ $topSignal }} · Score {{ $topScore !== null ? number_format($topScore * 10, 0, ',', '.') : '—' }}</small></span>
                                <span class="text-right">
                                    <span class="flex items-center justify-end gap-1.5"><b data-sector-live-price data-live-currency="{{ $topStock->currency ?? '' }}" class="text-xs text-[var(--ak-text)]">{{ $topPrice !== null ? number_format($topPrice, 2, ',', '.').' '.($topStock->currency ?? '') : '—' }}</b><i class="grid h-5 min-w-5 place-items-center rounded-md bg-cyan-400/10 px-1 text-[8px] font-black not-italic text-cyan-300">#{{ (int) $topStock->sector_rank }}</i></span>
                                    <small data-sector-live-change class="block text-[8px] {{ $changeClass }}">{{ $topChange !== null ? (($topChange > 0 ? '+' : '').number_format($topChange, 2, ',', '.').' %') : '—' }}</small>
                                    <small data-sector-live-time class="block text-[7px] {{ $quoteIsFresh ? 'text-emerald-300' : 'text-amber-300' }}">@if($quoteClock){{ $quoteIsFresh ? __('Live') : __('vor :minutes Min.', ['minutes' => $quoteAgeMinutes]) }} · {{ $quoteClock }}@else{{ __('Livekurs wird geladen') }}@endif</small>
                                </span>
                            </a>
                        @endforeach
                    </div>
                </div>
            @else
                <details class="simple-assessment-card relative z-20 h-full min-h-0 rounded-xl border border-amber-400/25 bg-amber-400/[.05] p-3">
                    <summary class="flex min-h-0 cursor-pointer list-none flex-col"><div class="flex items-start justify-between gap-2"><p class="text-[9px] font-black uppercase tracking-[.12em] text-violet-300">{{ __('Bewertung · Chancen und Risiken') }}</p><span class="text-xs font-black text-violet-300">{{ __('Mehr') }} ↓</span></div><p class="assessment-preview mt-2 min-h-0 flex-1 overflow-hidden text-xs leading-5 text-[var(--ak-muted)]">{{ $assessment }}</p></summary>
                    <div class="simple-assessment-full mt-3"><p class="text-xs leading-5 text-[var(--ak-muted)]">{{ $assessment }}</p></div>
                </details>
            @endif
        </div>
    </div>
</article>
