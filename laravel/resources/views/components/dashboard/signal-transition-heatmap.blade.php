@props(['stats' => []])

@php
    $signals = ['SELL', 'HOLD', 'WATCH', 'BUY'];
    $matrix = $stats['matrix'] ?? [];
    $maxCount = max(1, (int) ($stats['max_count'] ?? 0));
@endphp

<x-dashboard.card class="ak-standard-card ak-transition-matrix-card ak-card-static ak-dashboard-card flex min-h-[240px] w-full flex-col p-4 lg:min-h-[255px]">
    <div class="ak-standard-card-head flex items-start justify-between gap-3">
        <div class="flex min-w-0 items-center gap-2.5">
            <span class="ak-transition-icon grid h-9 w-9 shrink-0 place-items-center rounded-xl border">
                <x-heroicon-o-arrows-right-left class="h-4.5 w-4.5" />
            </span>
            <div class="min-w-0">
                <p class="text-[10px] font-black uppercase tracking-[.18em] text-orange-400">{{ __('Signalbewegung') }}</p>
                <h3 class="mt-0.5 truncate text-sm font-black text-[var(--ak-text)]">{{ __('Transition Matrix') }}</h3>
                <p class="mt-0.5 text-[9px] text-[var(--ak-muted)]">{{ __('Wechsel der letzten 5 Tage') }}</p>
            </div>
        </div>
        <div class="grid shrink-0 gap-1 text-[7px] font-black uppercase tracking-wide">
            <span class="ak-transition-legend is-up"><i></i>{{ __('Aufwärts') }}</span>
            <span class="ak-transition-legend is-down"><i></i>{{ __('Abwärts') }}</span>
        </div>
    </div>

    <div class="flex min-h-0 flex-1 items-center justify-center pt-1">
        <div class="grid w-full max-w-[165px] grid-cols-[36px_minmax(0,1fr)] grid-rows-[18px_minmax(0,1fr)] gap-x-1.5 gap-y-1 text-center">
            <div class="ak-transition-axis flex items-end justify-end pr-1 text-[7px] font-black uppercase tracking-wider">{{ __('Von') }}</div>
            <div class="grid grid-cols-4 gap-1">
                @foreach ($signals as $signal)
                    <div class="ak-transition-signal-label" data-signal="{{ strtolower($signal) }}">{{ $signal }}</div>
                @endforeach
            </div>

            <div class="grid grid-rows-4 gap-1 pr-1">
                @foreach ($signals as $signal)
                    <div class="ak-transition-row-label" data-signal="{{ strtolower($signal) }}">{{ $signal }}</div>
                @endforeach
            </div>

            <div class="grid aspect-square grid-cols-4 grid-rows-4 gap-1">
                @foreach ($signals as $fromRank => $fromSignal)
                    @foreach ($signals as $toRank => $toSignal)
                        @php
                            $count = (int) ($matrix[$fromRank.'-'.$toRank] ?? 0);
                            $intensity = $count > 0 ? 0.13 + (0.60 * ($count / $maxCount)) : 0;
                            $direction = $toRank > $fromRank ? 'up' : ($toRank < $fromRank ? 'down' : 'neutral');
                            $color = match ($direction) {
                                'up' => '16,185,129',
                                'down' => '244,63,94',
                                default => '100,116,139',
                            };
                        @endphp
                        <div
                            class="ak-transition-cell is-{{ $direction }} {{ $count > 0 ? 'has-value' : 'is-empty' }}"
                            @if ($count > 0) style="background-color:rgba({{ $color }},{{ number_format($intensity, 2, '.', '') }});" @endif
                            title="{{ $fromSignal }} → {{ $toSignal }}: {{ $count }}"
                        >
                            <strong>{{ $count }}</strong>
                            @if ($count > 0 && $direction !== 'neutral')
                                <small>{{ $direction === 'up' ? '↗' : '↘' }}</small>
                            @endif
                        </div>
                    @endforeach
                @endforeach
            </div>
        </div>
    </div>
</x-dashboard.card>
