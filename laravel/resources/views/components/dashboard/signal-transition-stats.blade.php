@props(['stats' => []])

<x-dashboard.card class="ak-card-static ak-dashboard-card flex h-full min-h-[260px] w-full flex-col overflow-hidden border-orange-400/25 px-4 py-3 lg:min-h-0">
    <div class="flex min-w-0 items-center gap-3">
            <span class="grid h-9 w-9 shrink-0 place-items-center rounded-lg border border-orange-400/25 bg-orange-400/10 text-orange-400">
                <x-heroicon-o-arrows-up-down class="h-4 w-4" />
            </span>
            <div class="min-w-0">
                <p class="text-xs font-black uppercase tracking-[.16em] text-orange-400">{{ __('Signal Flow') }}</p>
                <p class="mt-0.5 text-[10px] text-slate-400">{{ __('Wie viele Modelle ihre Einschätzung verändert haben · 5 Tage') }}</p>
            </div>
    </div>

    <div class="mt-auto grid grid-cols-3 gap-2 pb-4 pt-5">
        <div class="rounded-lg border border-white/[.07] bg-white/[.025] px-3 py-3">
            <p class="text-[9px] font-black uppercase tracking-wide text-slate-500">{{ __('Übergänge') }}</p>
            <p class="mt-1 text-lg font-black tabular-nums text-white">{{ number_format((int) ($stats['transition_count'] ?? 0), 0, ',', '.') }}</p>
        </div>
        <div class="rounded-lg border border-emerald-400/15 bg-emerald-400/[.04] px-3 py-3">
            <p class="text-[9px] font-black uppercase tracking-wide text-slate-500">{{ __('Aufwärts') }}</p>
            <p class="mt-1 text-lg font-black tabular-nums text-emerald-300">+{{ number_format((int) ($stats['positive_count'] ?? 0), 0, ',', '.') }}</p>
        </div>
        <div class="rounded-lg border border-rose-400/15 bg-rose-400/[.04] px-3 py-3">
            <p class="text-[9px] font-black uppercase tracking-wide text-slate-500">{{ __('Abwärts') }}</p>
            <p class="mt-1 text-lg font-black tabular-nums text-rose-300">−{{ number_format((int) ($stats['negative_count'] ?? 0), 0, ',', '.') }}</p>
        </div>
    </div>
</x-dashboard.card>
