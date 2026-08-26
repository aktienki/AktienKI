@props(['stats' => []])

@php
    $signals = ['BUY', 'WATCH', 'HOLD', 'SELL'];
    $distribution = $stats['distribution'] ?? [];
    $total = max(1, (int) ($stats['distribution_total'] ?? 0));
    $maxCount = max(1, (int) ($stats['distribution_max'] ?? 0));
    $colors = [
        'SELL' => '244,63,94',
        'HOLD' => '100,116,139',
        'WATCH' => '245,158,11',
        'BUY' => '16,185,129',
    ];
@endphp

<x-dashboard.card class="ak-signal-distribution-card ak-card-static ak-dashboard-card flex h-full min-h-[280px] w-full flex-col overflow-hidden p-4 lg:min-h-0">
    <div class="flex items-start gap-2.5">
        <span class="ak-transition-icon grid h-9 w-9 shrink-0 place-items-center rounded-xl border">
            <x-heroicon-o-squares-2x2 class="h-4.5 w-4.5" />
        </span>
        <div class="min-w-0">
            <p class="text-[10px] font-black uppercase tracking-[.18em] text-orange-400">{{ __('Signal Heatmap') }}</p>
            <h3 class="mt-0.5 text-sm font-black text-[var(--ak-text)]">{{ __('Aktuelle Verteilung') }}</h3>
            <p class="mt-0.5 text-[9px] text-[var(--ak-muted)]">{{ number_format((int) ($stats['distribution_total'] ?? 0), 0, ',', '.') }} {{ __('Aktien') }}</p>
        </div>
    </div>

    <div class="my-auto grid grid-cols-2 gap-2 pt-4">
        @foreach ($signals as $signal)
            @php
                $count = (int) ($distribution[$signal] ?? 0);
                $share = ($count / $total) * 100;
                $intensity = $count > 0 ? .12 + (.58 * ($count / $maxCount)) : .035;
            @endphp
            <div class="ak-signal-distribution-cell" data-signal="{{ strtolower($signal) }}" style="background-color:rgba({{ $colors[$signal] }},{{ number_format($intensity, 2, '.', '') }});">
                <span>{{ $signal }}</span>
                <strong>{{ number_format($count, 0, ',', '.') }}</strong>
                <small>{{ number_format($share, 0, ',', '.') }} %</small>
            </div>
        @endforeach
    </div>
</x-dashboard.card>
