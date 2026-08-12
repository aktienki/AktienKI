@props([
    'rank', 'eyebrow', 'name', 'symbol' => null, 'meta' => null, 'secondaryMeta' => null,
    'members' => 0, 'analyzed' => 0, 'score' => null, 'confidence' => null, 'risk' => null,
    'expectedReturn' => null, 'description', 'assessment', 'target', 'icon' => null,
    'metricOneLabel' => 'Mitglieder', 'metricOneValue' => null,
    'chartPoints' => [], 'chartLabel' => null,
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
        <div class="relative h-full min-h-0 rounded-xl border border-amber-400/25 bg-amber-400/[.05] p-3 pt-5 xl:col-span-2">
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
                    @foreach([__('Hit-Rate'),__('Ø/Trade'),__('Stabilität')] as $emptyMetric)<div class="screener-metric-donut" style="--donut-value:0%;--donut-color:#64748b"><span>—</span><small>{{ $emptyMetric }}</small></div>@endforeach
                    <div class="screener-metric-donut screener-risk-donut" style="--donut-value:{{ $riskPercent }}%;--donut-color:{{ $riskColor }}"><span>{{ $risk !== null ? number_format($risk,0,',','.').'%' : '—' }}</span><small>{{ __('Risiko') }}</small></div>
                </div><div class="mt-16"></div>
            </div>
            <div class="grid grid-cols-3 gap-2 rounded-xl border border-amber-400/25 bg-amber-400/[.05] px-3 py-2 sm:col-span-2">
                <div><p class="text-[8px] font-black uppercase text-[var(--ak-muted)]">{{ __('Mitglieder') }}</p><p class="mt-0.5 text-xs font-black text-amber-200">{{ $members }}</p></div>
                <div class="border-l border-amber-400/15 pl-2"><p class="text-[8px] font-black uppercase text-[var(--ak-muted)]">{{ __('Prognose 20T') }}</p><p class="mt-0.5 text-xs font-black {{ $returnClass }}">{{ $expectedReturn !== null ? (($expectedReturn > 0 ? '+' : '').number_format($expectedReturn,1,',','.').' %') : '—' }}</p></div>
                <div class="border-l border-amber-400/15 pl-2"><p class="text-[8px] font-black uppercase text-[var(--ak-muted)]">{{ __('Ranking') }}</p><p class="mt-0.5 text-xs font-black text-amber-200">#{{ $rank }}</p></div>
            </div>
            <details class="company-description-card screener-company-card relative z-20 flex h-full min-h-0 flex-col rounded-xl border border-amber-400/25 bg-amber-400/[.05] p-3 sm:col-span-2">
                <summary class="flex cursor-pointer list-none items-center justify-between gap-2"><div class="min-w-0 flex-1"><p class="text-[9px] font-black uppercase tracking-[.12em] text-cyan-300">{{ __('Beschreibung') }}</p><p class="company-preview mt-2 text-xs leading-5 text-[var(--ak-muted)]">{{ $description }}</p></div><span class="ml-2 inline-flex h-6 w-6 shrink-0 items-center justify-center rounded-full border border-cyan-300/50 bg-cyan-400/10 text-xs font-black text-cyan-200">i</span></summary>
                <p class="company-description mt-2 flex-1 text-xs leading-5 text-[var(--ak-muted)]">{{ $description }}</p>
            </details>
        </div>

        <div class="grid h-full min-h-0 gap-3 md:col-span-2 xl:col-span-2">
            <details class="simple-assessment-card relative z-20 h-full min-h-0 rounded-xl border border-amber-400/25 bg-amber-400/[.05] p-3">
                <summary class="flex min-h-0 cursor-pointer list-none flex-col"><div class="flex items-start justify-between gap-2"><p class="text-[9px] font-black uppercase tracking-[.12em] text-violet-300">{{ __('Bewertung · Chancen und Risiken') }}</p><span class="text-xs font-black text-violet-300">{{ __('Mehr') }} ↓</span></div><p class="assessment-preview mt-2 min-h-0 flex-1 overflow-hidden text-xs leading-5 text-[var(--ak-muted)]">{{ $assessment }}</p></summary>
                <div class="simple-assessment-full mt-3"><p class="text-xs leading-5 text-[var(--ak-muted)]">{{ $assessment }}</p><div class="mt-3 grid gap-3 sm:grid-cols-2"><div><p class="text-[9px] font-black uppercase text-emerald-300">{{ __('Chancen') }}</p><p class="mt-2 text-[10px] leading-4 text-[var(--ak-muted)]">{{ __('Wird mit den nächsten Analysedaten ergänzt.') }}</p></div><div><p class="text-[9px] font-black uppercase text-rose-300">{{ __('Risiken') }}</p><p class="mt-2 text-[10px] leading-4 text-[var(--ak-muted)]">{{ __('Wird mit den nächsten Analysedaten ergänzt.') }}</p></div></div></div>
            </details>
        </div>
    </div>
</article>
