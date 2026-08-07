@props(['assessment' => []])

@php
    $toneClasses = match ($assessment['tone'] ?? 'neutral') {
        'positive' => 'border-emerald-400/20 bg-emerald-400/[.07] text-emerald-300',
        'cautious' => 'border-rose-400/20 bg-rose-400/[.07] text-rose-300',
        default => 'border-amber-400/20 bg-amber-400/[.07] text-amber-300',
    };
@endphp

<x-dashboard.card class="ak-standard-card ak-card-static ak-dashboard-card ak-dashboard-market-card ak-dashboard-overall flex min-h-[260px] flex-col lg:min-h-0">
    <div class="ak-standard-card-head flex items-start justify-between gap-4">
        <div>
            <p class="flex items-center gap-2 text-xs font-black uppercase tracking-[.18em] text-orange-400">
                <span class="inline-flex h-7 w-7 items-center justify-center rounded-lg border border-orange-400/25 bg-orange-400/10 text-[10px] tracking-normal">AKI</span>
                {{ __('Market Regime') }}
            </p>
            <p class="mt-1 text-xs text-slate-400">{{ __('Breite, Bewegung und Risiko in einer Einordnung') }}</p>
        </div>
    </div>

    <div
        class="ak-market-assessment mt-4 rounded-2xl border p-3.5 {{ $toneClasses }}"
        data-tone="{{ $assessment['tone'] ?? 'neutral' }}"
    >
        <div class="flex items-center justify-between gap-3">
            <p class="ak-market-assessment-title text-[10px] font-black uppercase tracking-[.16em]">{{ __('Aktuelles Marktregime') }}</p>
            <span class="ak-market-assessment-status rounded-full border border-current/20 px-2.5 py-1 text-[10px] font-bold">{{ $assessment['status'] ?? __('Neutral') }}</span>
        </div>
        <ul class="ak-market-assessment-list mt-3 space-y-1.5 text-xs">
            <li class="flex items-center gap-2">
                <span class="ak-market-point h-2.5 w-2.5 shrink-0 rounded-full bg-orange-400"></span>
                <span>{{ __(':positive von :total Märkten im Plus', [
                    'positive' => $assessment['positiveMarkets'] ?? 0,
                    'total' => $assessment['marketCount'] ?? 0,
                ]) }}</span>
            </li>
            <li class="flex items-center gap-2">
                <span class="ak-market-point h-2.5 w-2.5 shrink-0 rounded-full {{ ($assessment['averageChange'] ?? 0) >= 0 ? 'bg-emerald-500' : 'bg-rose-500' }}"></span>
                <span>{{ __('Durchschnittliche Marktbewegung: :value %', [
                    'value' => number_format($assessment['averageChange'] ?? 0, 2, ',', '.'),
                ]) }}</span>
            </li>
            <li class="flex items-center gap-2">
                <span class="ak-market-point h-2.5 w-2.5 shrink-0 rounded-full {{ ($assessment['averageVolatility'] ?? 0) >= 1 ? 'bg-amber-500' : 'bg-orange-400' }}"></span>
                <span>{{ __('Stündliche Volatilität: :value %', [
                    'value' => number_format($assessment['averageVolatility'] ?? 0, 2, ',', '.'),
                ]) }}</span>
            </li>
            <li class="flex items-center gap-2">
                <span class="ak-market-point h-2.5 w-2.5 shrink-0 rounded-full bg-slate-400"></span>
                <span>{{ __('Berücksichtigtes Risikoprofil: :profile', [
                    'profile' => $assessment['riskName'] ?? __('ausgewogen'),
                ]) }}</span>
            </li>
        </ul>
    </div>

    <p class="mt-auto pt-3 text-[9px] leading-4 text-slate-600">{{ __('Automatisierte KI-Auswertung deiner Daten · keine Anlageberatung.') }}</p>
</x-dashboard.card>
