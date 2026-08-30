@props(['stats' => []])

@php
    $average = (float) ($stats['average'] ?? 0);
    $positive = (int) ($stats['positive_count'] ?? 0);
    $negative = (int) ($stats['negative_count'] ?? 0);
    $directionTotal = max(1, $positive + $negative);
    $positiveShare = ($positive / $directionTotal) * 100;
    $biasPosition = max(0, min(100, (($average + 1) / 2) * 100));
    $biasColor = $average < -.05 ? '#e87989' : ($average > .05 ? '#4fbf91' : '#d6ad45');
    $signals = ['SELL', 'WAIT', 'HOLD', 'WATCH', 'BUY'];
    $distribution = $stats['distribution'] ?? [];
    $distributionChanges = app(\App\Services\SignalDistributionDeltaService::class)->changes();
    $distributionTotal = max(1, (int) ($stats['distribution_total'] ?? 0));
@endphp

<x-dashboard.card class="ak-standard-card ak-signal-overview-card ak-card-static ak-dashboard-card flex min-h-[240px] w-full flex-col p-4 lg:min-h-[255px]">
    <div class="ak-standard-card-head flex items-start gap-2.5">
        <span class="ak-transition-icon grid h-9 w-9 shrink-0 place-items-center rounded-xl border">
            <x-heroicon-o-scale class="h-4.5 w-4.5" />
        </span>
        <div>
            <p class="text-[10px] font-black uppercase tracking-[.18em] text-orange-400">{{ __('Signale') }}</p>
            <h3 class="mt-0.5 text-sm font-black text-[var(--ak-text)]">{{ __('Signal Bias & aktuelle Verteilung') }}</h3>
            <p class="mt-0.5 text-[9px] text-[var(--ak-muted)]">{{ __('Richtungswechsel der letzten 5 Tage und KI-Score-Verteilung des aktiven Portfolios') }}</p>
        </div>
    </div>

    <div class="my-auto min-h-0 pt-1.5">
        <section class="ak-signal-distribution-grid grid gap-1.5">
            @foreach ($signals as $signal)
                @php
                    $count = (int) ($distribution[$signal] ?? 0);
                    $share = ($count / $distributionTotal) * 100;
                    $change = (int) ($distributionChanges[$signal] ?? 0);
                @endphp
                <div class="ak-signal-distribution-cell" data-signal="{{ strtolower($signal) }}" style="--signal-share:{{ number_format($share, 2, '.', '') }};">
                    <span>{{ $signal }}</span>
                    <strong>{{ number_format($count, 0, ',', '.') }}</strong>
                    <em class="absolute right-2 top-1.5 text-[8px] font-black not-italic tabular-nums text-cyan-500" title="{{ __('Änderung zur vorherigen Auswertung') }}">{{ $change > 0 ? '+' : ($change < 0 ? '−' : '±') }}{{ abs($change) }}</em>
                    <small>{{ number_format($share, 0, ',', '.') }} %</small>
                </div>
            @endforeach
        </section>

        <section class="ak-signal-overview-bias mt-2 grid grid-cols-[76px_minmax(0,1fr)] items-center gap-3 pt-2">
            <div
                class="inline-flex flex-col rounded-lg border px-3 py-1.5 text-center"
                style="border-color:color-mix(in srgb,{{ $biasColor }} 42%,transparent);background-color:color-mix(in srgb,{{ $biasColor }} 10%,transparent);color:{{ $biasColor }}"
            >
                <span class="text-[7px] font-black uppercase tracking-[.12em] opacity-70">{{ __('Market Bias') }}</span>
                <strong class="text-lg font-black tabular-nums">{{ $average > 0 ? '+' : '' }}{{ number_format($average, 2, ',', '.') }}</strong>
            </div>
            <div>
                <div class="mb-1.5 flex justify-between text-[7px] font-black uppercase tracking-wide">
                    <span class="text-rose-400">{{ __('Abwärts') }} {{ $negative }}</span>
                    <span class="text-[var(--ak-muted)]">{{ number_format($positiveShare, 0, ',', '.') }} % {{ __('positiv') }}</span>
                    <span class="text-emerald-500">{{ __('Aufwärts') }} {{ $positive }}</span>
                </div>
                <div
                    class="relative h-2.5 rounded-full border border-[var(--ak-border)] shadow-inner"
                    style="background:linear-gradient(90deg,rgba(232,121,137,.58) 0%,rgba(240,178,113,.42) 34%,rgba(224,190,91,.50) 50%,rgba(139,201,153,.40) 66%,rgba(79,191,145,.56) 100%)"
                    role="meter"
                    aria-label="{{ __('Market Bias') }}"
                    aria-valuemin="-1"
                    aria-valuemax="1"
                    aria-valuenow="{{ number_format($average, 2, '.', '') }}"
                    title="{{ __('Market Bias: :value', ['value' => number_format($average, 2, ',', '.')]) }}"
                >
                    <span
                        class="absolute top-1/2 h-5 w-[3px] -translate-x-1/2 -translate-y-1/2 rounded-full border border-white/80 shadow-[0_1px_5px_rgba(15,23,42,.30)]"
                        style="left:{{ number_format($biasPosition, 2, '.', '') }}%;background-color:{{ $biasColor }}"
                        aria-hidden="true"
                    ></span>
                </div>
            </div>
        </section>
    </div>
</x-dashboard.card>
