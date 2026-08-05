@props(['dailyAiScores' => [], 'assessment' => []])

@php
    $dailyAiScores = collect($dailyAiScores)->take(-20)->values()->all();
    $latestScore = collect($dailyAiScores)->last()['y'] ?? null;
    $displayScore = $assessment['score'] ?? $latestScore;
@endphp

<x-dashboard.card class="ak-card-static ak-dashboard-card ak-dashboard-market-card flex min-h-[260px] flex-col border-orange-400/25 lg:min-h-0">
    <div class="flex items-start justify-between gap-4">
        <div>
            <p class="flex items-center gap-2 text-xs font-black uppercase tracking-[.18em] text-orange-400">
                <span class="inline-flex h-7 w-7 items-center justify-center rounded-lg border border-orange-400/25 bg-orange-400/10">
                    <x-heroicon-o-chart-bar-square class="h-4 w-4" />
                </span>
                {{ __('Market Momentum') }}
            </p>
            <p class="mt-1 text-xs text-slate-400">{{ __('Entwicklung des aggregierten KI-Scores · 20 Tage') }}</p>
        </div>
        <div class="text-right">
            <p class="text-2xl font-black text-white">{{ $displayScore !== null ? number_format($displayScore, 1, ',', '.') : '—' }}</p>
            <p class="text-[10px] font-semibold text-slate-500">/ 10</p>
        </div>
    </div>

    @if (count($dailyAiScores))
        <div class="ak-dashboard-score-chart mt-3 min-h-[150px] flex-1" wire:ignore x-data="dailyAiScoreChart(@js($dailyAiScores))">
            <div x-ref="chart" class="h-full min-h-[150px] w-full" aria-label="{{ __('Täglicher durchschnittlicher KI-Score') }}"></div>
        </div>
    @else
        <div class="my-auto py-5 text-center">
            <p class="text-sm font-bold text-slate-300">{{ __('Noch keine KI-Score-Historie vorhanden') }}</p>
        </div>
    @endif
</x-dashboard.card>
