@props(['stats' => []])

@php
    $average = (float) ($stats['average'] ?? 0);
    $positive = (int) ($stats['positive_count'] ?? 0);
    $negative = (int) ($stats['negative_count'] ?? 0);
    $total = max(1, $positive + $negative);
    $positiveShare = ($positive / $total) * 100;
    $averageTone = $average > 0
        ? 'border-emerald-400/25 bg-emerald-400/[.08] text-emerald-300'
        : ($average < 0
            ? 'border-rose-400/25 bg-rose-400/[.08] text-rose-300'
            : 'border-amber-400/20 bg-amber-400/[.06] text-amber-200');
@endphp

<x-dashboard.card class="ak-card-static ak-dashboard-card flex h-full min-h-[260px] w-full flex-col overflow-hidden border-orange-400/25 px-4 py-3 lg:min-h-0">
    <div class="flex items-center gap-3">
        <span class="grid h-9 w-9 shrink-0 place-items-center rounded-lg border border-orange-400/25 bg-orange-400/10 text-orange-400">
            <x-heroicon-o-scale class="h-4 w-4" />
        </span>
        <div>
            <p class="text-xs font-black uppercase tracking-[.16em] text-orange-400">{{ __('Signal Bias') }}</p>
            <p class="mt-0.5 text-[10px] text-slate-400">{{ __('Überwiegen Auf- oder Abstufungen? · 5 Tage') }}</p>
        </div>
    </div>

    <div class="my-auto grid items-center gap-5 py-4 sm:grid-cols-[minmax(150px,.65fr)_minmax(220px,1.35fr)]">
        <div class="inline-flex min-w-28 flex-col rounded-xl border px-5 py-3 text-center {{ $averageTone }}">
            <span class="text-[9px] font-black uppercase tracking-[.14em] opacity-70">{{ __('Durchschnitt') }}</span>
            <strong class="mt-1 text-3xl font-black tabular-nums">{{ $average > 0 ? '+' : '' }}{{ number_format($average, 2, ',', '.') }}</strong>
        </div>
        <div>
            <div class="mb-2 flex items-center justify-between text-[9px] font-black uppercase tracking-wide">
                <span class="text-rose-400">{{ __('Abwärts') }} {{ $negative }}</span>
                <span class="text-emerald-500">{{ __('Aufwärts') }} {{ $positive }}</span>
            </div>
            <div class="flex h-3 w-full overflow-hidden rounded-full bg-rose-400/55 shadow-inner">
                <span class="h-full bg-emerald-400/80" style="width:{{ number_format($positiveShare, 2, '.', '') }}%"></span>
            </div>
            <div class="mt-2 flex justify-between text-[9px] font-bold text-[var(--ak-muted)]">
                <span>−1</span><span>{{ number_format($positiveShare, 0, ',', '.') }} % {{ __('positiv') }}</span><span>+1</span>
            </div>
        </div>
    </div>
</x-dashboard.card>
