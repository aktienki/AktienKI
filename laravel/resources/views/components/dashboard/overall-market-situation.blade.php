@props(['dailyAiScores' => [], 'assessment' => []])

@php
    $latestScore = collect($dailyAiScores)->last()['y'] ?? null;
    $displayScore = $assessment['score'] ?? $latestScore;
    $toneClasses = match ($assessment['tone'] ?? 'neutral') {
        'positive' => 'border-emerald-400/20 bg-emerald-400/[.07] text-emerald-300',
        'cautious' => 'border-rose-400/20 bg-rose-400/[.07] text-rose-300',
        default => 'border-amber-400/20 bg-amber-400/[.07] text-amber-300',
    };
@endphp

<section class="ak-card ak-dashboard-market-card ak-dashboard-overall flex h-full min-h-[330px] flex-col">
    <div class="flex items-start justify-between gap-4">
        <div>
            <p class="flex items-center gap-2 text-xs font-black uppercase tracking-[.18em] text-teal-600">
                <span class="inline-flex h-7 w-7 items-center justify-center rounded-lg border border-teal-500/25 bg-teal-500/10 text-[10px] tracking-normal">AKI</span>
                {{ __('Gesamtsituation') }}
            </p>
            <p class="mt-1 text-xs text-slate-400">{{ __('Durchschnittlicher KI-Score pro Tag') }}</p>
        </div>

        <div class="text-right">
            <p class="text-2xl font-black text-white">{{ $displayScore !== null ? number_format($displayScore, 1, ',', '.') : '—' }}</p>
            <p class="text-[10px] font-semibold text-slate-500">/ 10</p>
        </div>
    </div>

    <div
        class="ak-market-assessment mt-4 rounded-2xl border p-3.5 {{ $toneClasses }}"
        data-tone="{{ $assessment['tone'] ?? 'neutral' }}"
    >
        <div class="flex items-center justify-between gap-3">
            <p class="ak-market-assessment-title text-[10px] font-black uppercase tracking-[.16em]">{{ __('AKI Marktbewertung') }}</p>
            <span class="ak-market-assessment-status rounded-full border border-current/20 px-2.5 py-1 text-[10px] font-bold">{{ $assessment['status'] ?? __('Neutral') }}</span>
        </div>
        <ul class="ak-market-assessment-list mt-3 space-y-1.5 text-xs">
            <li class="flex items-center gap-2">
                <span class="ak-market-point h-2.5 w-2.5 shrink-0 rounded-full bg-teal-500"></span>
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
                <span class="ak-market-point h-2.5 w-2.5 shrink-0 rounded-full {{ ($assessment['averageVolatility'] ?? 0) >= 1 ? 'bg-amber-500' : 'bg-teal-500' }}"></span>
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

    @if (count($dailyAiScores))
        <div class="ak-dashboard-score-chart mt-3 min-h-[170px] flex-1" wire:ignore x-data="dailyAiScoreChart(@js($dailyAiScores))">
            <div x-ref="chart" class="h-full min-h-[170px] w-full" aria-label="{{ __('Täglicher durchschnittlicher KI-Score') }}"></div>
        </div>
    @else
        <div class="my-auto py-8 text-center">
            <span class="mx-auto flex h-14 w-14 items-center justify-center rounded-2xl border border-teal-500/20 bg-teal-500/10 text-teal-600">
                <x-heroicon-o-chart-bar-square class="h-6 w-6" />
            </span>
            <p class="mt-4 text-sm font-bold text-slate-300">{{ __('Noch keine KI-Score-Historie vorhanden') }}</p>
        </div>
    @endif

    <p class="mt-2 text-[9px] leading-4 text-slate-600">{{ __('Automatisierte KI-Auswertung deiner Daten · keine Anlageberatung.') }}</p>
</section>
