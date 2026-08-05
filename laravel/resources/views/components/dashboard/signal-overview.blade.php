@props(['stats' => []])

@php
    $average = (float) ($stats['average'] ?? 0);
    $positive = (int) ($stats['positive_count'] ?? 0);
    $negative = (int) ($stats['negative_count'] ?? 0);
    $directionTotal = max(1, $positive + $negative);
    $positiveShare = ($positive / $directionTotal) * 100;
    $averageTone = $average > 0
        ? 'border-emerald-400/25 bg-emerald-400/[.08] text-emerald-300'
        : ($average < 0
            ? 'border-rose-400/25 bg-rose-400/[.08] text-rose-300'
            : 'border-amber-400/20 bg-amber-400/[.06] text-amber-200');
    $signals = ['SELL', 'HOLD', 'WATCH', 'BUY'];
    $distribution = $stats['distribution'] ?? [];
    $distributionTotal = max(1, (int) ($stats['distribution_total'] ?? 0));
    $distributionMax = max(1, (int) ($stats['distribution_max'] ?? 0));
    $colors = [
        'SELL' => '244,63,94', 'HOLD' => '100,116,139',
        'WATCH' => '245,158,11', 'BUY' => '16,185,129',
    ];
@endphp

<x-dashboard.card class="ak-signal-overview-card ak-card-static ak-dashboard-card flex min-h-[240px] w-full flex-col px-4 py-2.5 lg:min-h-[255px]">
    <div class="flex items-start gap-2.5">
        <span class="ak-transition-icon grid h-9 w-9 shrink-0 place-items-center rounded-xl border">
            <x-heroicon-o-scale class="h-4.5 w-4.5" />
        </span>
        <div>
            <p class="text-[10px] font-black uppercase tracking-[.18em] text-orange-400">{{ __('Signale') }}</p>
            <h3 class="mt-0.5 text-sm font-black text-[var(--ak-text)]">{{ __('Signal Bias & aktuelle Verteilung') }}</h3>
            <p class="mt-0.5 text-[9px] text-[var(--ak-muted)]">{{ __('Richtungswechsel der letzten 5 Tage und neuestes Signal je Aktie') }}</p>
        </div>
    </div>

    <div class="my-auto min-h-0 pt-1.5">
        <section class="grid grid-cols-4 gap-1.5">
            @foreach ($signals as $signal)
                @php
                    $count = (int) ($distribution[$signal] ?? 0);
                    $share = ($count / $distributionTotal) * 100;
                    $intensity = $count > 0 ? .12 + (.58 * ($count / $distributionMax)) : .035;
                @endphp
                <div class="ak-signal-distribution-cell" data-signal="{{ strtolower($signal) }}" style="background-color:rgba({{ $colors[$signal] }},{{ number_format($intensity, 2, '.', '') }});">
                    <span>{{ $signal }}</span>
                    <strong>{{ number_format($count, 0, ',', '.') }}</strong>
                    <small>{{ number_format($share, 0, ',', '.') }} %</small>
                </div>
            @endforeach
        </section>

        <section class="ak-signal-overview-bias mt-2 grid grid-cols-[76px_minmax(0,1fr)] items-center gap-3 pt-2">
            <div class="inline-flex flex-col rounded-lg border px-3 py-1.5 text-center {{ $averageTone }}">
                <span class="text-[7px] font-black uppercase tracking-[.12em] opacity-70">{{ __('Market Bias') }}</span>
                <strong class="text-lg font-black tabular-nums">{{ $average > 0 ? '+' : '' }}{{ number_format($average, 2, ',', '.') }}</strong>
            </div>
            <div>
                <div class="mb-1.5 flex justify-between text-[7px] font-black uppercase tracking-wide">
                    <span class="text-rose-400">{{ __('Abwärts') }} {{ $negative }}</span>
                    <span class="text-[var(--ak-muted)]">{{ number_format($positiveShare, 0, ',', '.') }} % {{ __('positiv') }}</span>
                    <span class="text-emerald-500">{{ __('Aufwärts') }} {{ $positive }}</span>
                </div>
                <div class="flex h-2.5 overflow-hidden rounded-full bg-rose-400/55 shadow-inner">
                    <span class="h-full bg-emerald-400/80" style="width:{{ number_format($positiveShare, 2, '.', '') }}%"></span>
                </div>
            </div>
        </section>
    </div>
</x-dashboard.card>
