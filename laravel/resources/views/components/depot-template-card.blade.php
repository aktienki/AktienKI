@props([
    'type' => 'strategy',
    'name',
    'description',
    'icon' => 'chart',
    'currency' => 'EUR',
    'stocks' => [],
    'instrumentIds' => [],
])

@php
    $simulatedValue = collect($stocks)->sum(fn ($stock) => (float) ($stock['value'] ?? 0));
    $simulatedInvested = collect($stocks)->sum(function ($stock) {
        $value = (float) ($stock['value'] ?? 0);
        $change = (float) ($stock['change'] ?? 0);

        return $change <= -99.9 ? $value : $value / (1 + ($change / 100));
    });
    $simulatedPerformance = $simulatedInvested > 0
        ? (($simulatedValue - $simulatedInvested) / $simulatedInvested) * 100
        : 0;
@endphp

<article class="flex h-full min-h-0 cursor-pointer flex-col rounded-2xl border border-dashed border-[var(--ak-border)] bg-[var(--ak-card)] p-4 shadow-[var(--ak-shadow)] transition hover:-translate-y-0.5 hover:border-teal-500/45">
    <div class="flex items-center gap-3">
        <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl border border-teal-500/25 bg-teal-500/10 text-teal-600">
            @if ($icon === 'sparkles')<x-heroicon-o-sparkles class="h-5 w-5" />
            @elseif ($icon === 'shield')<x-heroicon-o-shield-check class="h-5 w-5" />
            @elseif ($icon === 'scale')<x-heroicon-o-scale class="h-5 w-5" />
            @elseif ($icon === 'wave')<x-heroicon-o-chart-bar class="h-5 w-5" />
            @elseif ($icon === 'beaker')<x-heroicon-o-beaker class="h-5 w-5" />
            @elseif ($icon === 'chart')<x-heroicon-o-chart-bar-square class="h-5 w-5" />
            @else<x-heroicon-o-briefcase class="h-5 w-5" />@endif
        </span>
        <div class="min-w-0">
            @if ($type === 'paper')
                <p class="text-[9px] font-black uppercase tracking-[.14em] text-teal-700">{{ __('Musterdepot') }}</p>
            @endif
            <h2 class="{{ $type === 'paper' ? 'mt-0.5' : '' }} truncate text-lg font-black">{{ $name }}</h2>
        </div>
    </div>

    <p class="mt-2 min-h-10 text-xs leading-5 text-[var(--ak-muted)]">{{ $description }}</p>

    <div class="mt-2 grid grid-cols-2 gap-2">
        <div class="rounded-xl bg-[var(--ak-surface-muted)] px-3 py-2">
            <p class="text-[9px] font-black uppercase tracking-wide text-[var(--ak-muted)]">{{ __('Depotwert') }}</p>
            <p class="mt-0.5 text-base font-black tabular-nums">{{ number_format($simulatedValue, 2, ',', '.') }} {{ $currency }}</p>
        </div>
        <div class="rounded-xl bg-[var(--ak-surface-muted)] px-3 py-2">
            <p class="text-[9px] font-black uppercase tracking-wide text-[var(--ak-muted)]">{{ __('Performance') }}</p>
            <p class="mt-0.5 text-base font-black tabular-nums {{ $simulatedPerformance > 0 ? 'text-emerald-400' : ($simulatedPerformance < 0 ? 'text-rose-400' : 'text-[var(--ak-muted)]') }}">{{ $simulatedPerformance > 0 ? '+' : '' }}{{ number_format($simulatedPerformance, 2, ',', '.') }} %</p>
        </div>
    </div>

    @if (count($stocks) > 0)
        <div class="mt-2 flex items-center justify-between">
            <p class="text-[9px] font-black uppercase tracking-[.12em] text-[var(--ak-muted)]">{{ __('Simulierte Positionen') }}</p>
            <span class="rounded-md border border-amber-400/25 bg-amber-400/10 px-2 py-1 text-[8px] font-black uppercase text-amber-400">{{ __('Simulation') }}</span>
        </div>
        <div class="mt-1.5 grid auto-rows-fr grid-cols-1 gap-1.5">
            @foreach ($stocks as $stock)
                @php
                    $change = (float) ($stock['change'] ?? 0);
                    $instrumentId = $instrumentIds[$stock['symbol']] ?? null;
                @endphp
                <div class="min-w-0 rounded-lg border border-[var(--ak-border)] bg-[var(--ak-surface-muted)] px-2.5 py-2">
                    <div class="flex items-center justify-between gap-2">
                        <div class="flex min-w-0 items-center gap-2">
                            <span class="relative flex h-7 w-7 shrink-0 items-center justify-center overflow-hidden rounded-md border border-[var(--ak-border)] bg-transparent text-[7px] font-black text-teal-700">
                                {{ strtoupper(substr($stock['symbol'], 0, 2)) }}
                                @if ($instrumentId)
                                    <img src="{{ route('stocks.icon', $instrumentId) }}" alt="" class="absolute inset-1 h-5 w-5 object-contain" loading="eager" onerror="this.remove()">
                                @endif
                            </span>
                            <div class="min-w-0">
                                <p class="truncate text-sm font-black text-white">{{ $stock['name'] }}</p>
                                <p class="truncate text-[9px] font-black tracking-wide text-amber-500">{{ $stock['symbol'] }}</p>
                            </div>
                        </div>
                        <span class="shrink-0 text-[9px] font-black tabular-nums {{ $change > 0 ? 'text-emerald-400' : ($change < 0 ? 'text-rose-400' : 'text-[var(--ak-muted)]') }}">{{ $change > 0 ? '+' : '' }}{{ number_format($change, 1, ',', '.') }} %</span>
                    </div>
                    <dl class="mt-1.5 grid grid-cols-6 gap-1 border-t border-[var(--ak-border)] pt-1.5">
                        <div class="min-w-0"><dt class="truncate text-[7px] font-black uppercase text-[var(--ak-muted)]">{{ __('Kaufdatum') }}</dt><dd class="mt-0.5 truncate text-[8px] font-bold">{{ $stock['purchase_date'] }}</dd></div>
                        <div class="min-w-0"><dt class="truncate text-[7px] font-black uppercase text-[var(--ak-muted)]">{{ __('Anzahl') }}</dt><dd class="mt-0.5 truncate text-[8px] font-bold tabular-nums">{{ number_format((float) $stock['quantity'], 2, ',', '.') }}</dd></div>
                        <div class="min-w-0"><dt class="truncate text-[7px] font-black uppercase text-[var(--ak-muted)]">{{ __('Kaufpreis') }}</dt><dd class="mt-0.5 truncate text-[8px] font-bold tabular-nums">{{ number_format((float) $stock['buy_price'], 2, ',', '.') }}</dd></div>
                        <div class="min-w-0"><dt class="truncate text-[7px] font-black uppercase text-[var(--ak-muted)]">{{ __('Kurs') }}</dt><dd class="mt-0.5 truncate text-[8px] font-bold tabular-nums">{{ number_format((float) $stock['current_price'], 2, ',', '.') }}</dd></div>
                        <div class="min-w-0"><dt class="truncate text-[7px] font-black uppercase text-[var(--ak-muted)]">{{ __('Wert') }}</dt><dd class="mt-0.5 truncate text-[8px] font-bold tabular-nums">{{ number_format((float) $stock['value'], 0, ',', '.') }}</dd></div>
                        <div class="min-w-0"><dt class="truncate text-[7px] font-black uppercase text-[var(--ak-muted)]">{{ __('Perf.') }}</dt><dd class="mt-0.5 truncate text-[8px] font-black tabular-nums {{ $change > 0 ? 'text-emerald-400' : ($change < 0 ? 'text-rose-400' : 'text-[var(--ak-muted)]') }}">{{ $change > 0 ? '+' : '' }}{{ number_format($change, 1, ',', '.') }}%</dd></div>
                    </dl>
                </div>
            @endforeach
        </div>
    @endif

    <button type="button" class="mt-2 inline-flex h-8 w-full items-center justify-center gap-2 rounded-lg border border-[var(--ak-border)] bg-[var(--ak-surface-muted)] text-[10px] font-black text-[var(--ak-muted)] transition group-hover:border-teal-500/35 group-hover:text-teal-600">
        <x-heroicon-o-chart-bar-square class="h-4 w-4" />{{ __('Depot anzeigen') }}
    </button>
</article>
