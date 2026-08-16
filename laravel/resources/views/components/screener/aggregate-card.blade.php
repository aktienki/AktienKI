@props([
    'rank', 'eyebrow', 'name', 'symbol' => null, 'meta' => null, 'secondaryMeta' => null,
    'members' => 0, 'analyzed' => 0, 'score' => null, 'confidence' => null, 'hitRate' => null,
    'profitPerTrade' => null, 'stability' => null, 'risk' => null,
    'expectedReturn' => null, 'description', 'assessment', 'target', 'icon' => null,
    'metricOneLabel' => 'Mitglieder', 'metricOneValue' => null,
    'chartPoints' => [], 'chartLabel' => null,
    'topStocks' => [],
    'realtimeQuotes' => false,
    'marketInfo' => null, 'marketInfoDate' => null, 'marketInfoModel' => null,
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
    $confidenceColor = $confidence !== null ? $qualityDonutColor($confidencePercent) : '#64748b';
    $riskColor = $risk !== null ? $qualityDonutColor(100 - $riskPercent) : '#64748b';
    $returnClass = ($expectedReturn ?? 0) >= 0 ? 'text-emerald-300' : 'text-rose-300';
    $chartValues = collect($chartPoints)->pluck('close')->filter(fn($value) => is_numeric($value))->map(fn($value) => (float)$value)->values();
    $chartMin = $chartValues->isNotEmpty() ? (float)$chartValues->min() : 0;
    $chartRange = $chartValues->isNotEmpty() ? max(.000001, (float)$chartValues->max() - $chartMin) : 1;
    $chartPolyline = $chartValues->count() > 1
        ? $chartValues->map(fn($value, $index) => sprintf('%.1f,%.1f', $index * 600 / ($chartValues->count() - 1), 112 - (($value - $chartMin) / $chartRange) * 96))->implode(' ')
        : '';
@endphp
<article class="screener-stock-card ak-card ak-dashboard-card relative overflow-hidden p-3">
    <a href="{{ $target }}" class="absolute inset-0 z-10" aria-label="{{ $name }}"></a>
    <div class="grid h-full min-h-0 gap-2 md:grid-cols-2 xl:grid-cols-6">
        <div class="relative h-full min-h-0 rounded-xl border border-transparent bg-[linear-gradient(145deg,rgba(34,211,238,.14),rgba(8,47,73,.10)_48%,rgba(251,191,36,.055))] p-3 pt-5 shadow-[inset_3px_0_0_rgba(34,211,238,.62),0_0_30px_rgba(34,211,238,.18),0_12px_34px_rgba(2,8,23,.24)] xl:col-span-2">
            <div class="grid gap-3 md:grid-cols-[.85fr_1fr]">
                <div>
                    <p class="screener-border-title text-amber-300">{{ $eyebrow }} <strong>#{{ $rank }}</strong></p>
                    <div class="mt-1 flex items-center gap-2">@if($icon)<span class="text-cyan-300">{{ $icon }}</span>@endif<h2 class="text-base font-black">{{ $name }}</h2></div>
                    @if($symbol)<p class="text-xs font-black uppercase tracking-[.12em] text-cyan-300">{{ $symbol }}</p>@endif
                    <p class="mt-2 text-sm">{{ $meta ?: '—' }}</p>
                    <p class="mt-1 text-[10px] font-bold text-[var(--ak-muted)]">{{ $secondaryMeta ?: '—' }}</p>
                    <span class="mt-3 inline-flex w-28 justify-center rounded-lg border border-amber-400/40 bg-amber-400/[.08] px-2.5 py-1 text-[10px] font-black tracking-[.08em] text-amber-300">#{{ $rank }}</span>
                </div>
                <div>
                    <p class="text-[9px] font-black uppercase tracking-[.12em] text-cyan-300">{{ __($metricOneLabel) }}</p>
                    <p class="mt-2 text-2xl font-black">{{ $metricOneValue ?? $members }}</p>
                    <p class="mt-3 text-[9px] font-black uppercase text-[var(--ak-muted)]">{{ __('Analysiert') }}</p>
                    <p class="mt-1 text-lg font-black text-emerald-300">{{ $analyzed }}</p>
                </div>
                <div class="md:col-span-2">
                    <div class="mb-1 flex items-center justify-between text-[9px] font-black uppercase tracking-[.1em] text-[var(--ak-muted)]"><span>{{ $chartLabel ?: __('Chart') }}</span>@if($chartValues->isNotEmpty())<span class="text-amber-300">{{ number_format((float)$chartValues->last(),2,',','.') }}</span>@endif</div>
                    <div class="relative h-24 overflow-hidden">@if($chartPolyline)<svg viewBox="0 0 600 120" class="h-24 w-full" role="img" aria-label="{{ $chartLabel ?: __('Kursverlauf') }}" preserveAspectRatio="none"><path d="M0 118H600" stroke="currentColor" stroke-opacity=".16"/><polyline points="{{ $chartPolyline }}" fill="none" stroke="#22d3ee" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"/></svg>@else<div class="grid h-full place-items-center text-xs italic text-[var(--ak-muted)]">{{ __('Keine Daten') }}</div>@endif</div>
                </div>
            </div>
        </div>

        <div class="grid h-full min-h-0 gap-2 sm:grid-cols-2 xl:col-span-2 xl:grid-rows-[auto_auto_1fr]">
            <div class="relative rounded-xl border border-amber-400/25 bg-amber-400/[.05] p-3 sm:col-span-2">
                <div class="screener-ranking-donuts">
                    <div class="screener-metric-donut screener-metric-donut-score" style="--donut-value:{{ $scorePercent }}%;--donut-color:{{ $scoreColor }}"><span>{{ $score !== null ? number_format($score * 10, 0, ',', '.') : '—' }}</span><small>{{ __('KI-Score') }}</small></div>
                    <div class="screener-metric-donut" style="--donut-value:{{ $confidencePercent }}%;--donut-color:{{ $confidenceColor }}"><span>{{ $confidence !== null ? number_format($confidence,0,',','.').'%' : '—' }}</span><small>{{ __('Konf.') }}</small></div>
                    <div class="screener-metric-donut" style="--donut-value:{{ $hitRatePercent }}%;--donut-color:{{ $hitRate !== null ? $qualityDonutColor($hitRatePercent) : '#64748b' }}"><span>{{ $hitRate !== null ? number_format($hitRate,0,',','.').'%' : '—' }}</span><small>{{ __('Hit-Rate') }}</small></div>
                    <div class="screener-metric-donut" style="--donut-value:{{ $profitPercent }}%;--donut-color:{{ $profitPerTrade !== null ? $qualityDonutColor($profitPercent) : '#64748b' }}"><span>{{ $profitPerTrade !== null ? (($profitPerTrade > 0 ? '+' : '').number_format($profitPerTrade,2,',','.').'%') : '—' }}</span><small>{{ __('Ø/Trade') }}</small></div>
                    <div class="screener-metric-donut" style="--donut-value:{{ $stabilityPercent }}%;--donut-color:{{ $stability !== null ? $qualityDonutColor($stabilityPercent) : '#64748b' }}"><span>{{ $stability !== null ? number_format($stability,0,',','.').'%' : '—' }}</span><small>{{ __('Stabilität') }}</small></div>
                    <div class="screener-metric-donut screener-risk-donut" style="--donut-value:{{ $riskPercent }}%;--donut-color:{{ $riskColor }}"><span>{{ $risk !== null ? number_format($risk,0,',','.').'%' : '—' }}</span><small>{{ __('Risiko') }}</small></div>
                </div><div class="screener-donut-spacer" aria-hidden="true"></div>
            </div>
            <div class="grid grid-cols-3 gap-2 rounded-xl border border-amber-400/25 bg-amber-400/[.05] px-3 py-2 sm:col-span-2">
                <div><p class="text-[8px] font-black uppercase text-[var(--ak-muted)]">{{ __('Mitglieder') }}</p><p class="mt-0.5 text-xs font-black text-amber-200">{{ $members }}</p></div>
                <div class="border-l border-amber-400/15 pl-2"><p class="text-[8px] font-black uppercase text-[var(--ak-muted)]">{{ __('Prognose 20T') }}</p><p class="mt-0.5 text-xs font-black {{ $returnClass }}">{{ $expectedReturn !== null ? (($expectedReturn > 0 ? '+' : '').number_format($expectedReturn,1,',','.').' %') : '—' }}</p></div>
                <div class="border-l border-amber-400/15 pl-2"><p class="text-[8px] font-black uppercase text-[var(--ak-muted)]">{{ __('Ranking') }}</p><p class="mt-0.5 text-xs font-black text-amber-200">#{{ $rank }}</p></div>
            </div>
            @php
                $cardInfo = $marketInfo ?: $description;
            @endphp
            <details class="company-description-card screener-company-card relative z-20 flex h-full min-h-0 flex-col rounded-xl border border-amber-400/25 bg-amber-400/[.05] p-3 sm:col-span-2">
                <summary class="flex cursor-pointer list-none items-center justify-between gap-2"><div class="min-w-0 flex-1"><div class="flex items-center gap-2"><p class="text-[9px] font-black uppercase tracking-[.12em] text-cyan-300">{{ $marketInfo ? __('Tägliche Marktinfo') : __('Beschreibung') }}</p>@if($marketInfoDate)<span class="text-[8px] font-bold text-[var(--ak-muted)]">{{ \Illuminate\Support\Carbon::parse($marketInfoDate)->format('d.m.Y') }}</span>@endif</div><p class="company-preview mt-2 text-xs leading-5 text-[var(--ak-muted)]">{{ $cardInfo }}</p></div><span class="ml-2 inline-flex h-6 w-6 shrink-0 items-center justify-center rounded-full border border-cyan-300/50 bg-cyan-400/10 text-xs font-black text-cyan-200">i</span></summary>
                <p class="company-description mt-2 flex-1 text-xs leading-5 text-[var(--ak-muted)]">{{ $cardInfo }}</p>
                @if($marketInfoModel)<p class="mt-2 text-[8px] font-bold uppercase tracking-[.1em] text-cyan-300/60">{{ __('Erstellt mit') }} {{ $marketInfoModel }}</p>@endif
            </details>
        </div>

        <div class="grid h-full min-h-0 gap-3 md:col-span-2 xl:col-span-2">
            @if(collect($topStocks)->isNotEmpty())
                <div class="relative z-20 h-full min-h-0 rounded-xl border border-amber-400/25 bg-amber-400/[.05] p-3">
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
