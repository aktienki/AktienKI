<x-app-layout>
    <div class="flex h-[calc(100dvh-89px)] min-h-0 flex-col py-4 text-[var(--ak-text)]">
        <div class="mb-4 flex shrink-0 flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <div class="flex items-center gap-3">
                    <div class="flex h-11 w-11 items-center justify-center rounded-2xl bg-violet-600/20 ring-1 ring-violet-400/30">
                        <x-heroicon-o-table-cells class="h-6 w-6 text-teal-600" />
                    </div>

                    <div>
                        <h1 class="text-2xl font-bold tracking-tight text-[var(--ak-text)]">
                            {{ __('Aktien-Screener') }}
                        </h1>
                        <p class="mt-1 text-sm text-slate-400">
                            {{ __('Aktien nach Kennzahlen, KI-Score, Risiko und Signal durchsuchen.') }}
                        </p>
                    </div>
                </div>
            </div>

            <a href="{{ route('dashboard') }}"
               class="inline-flex items-center justify-center gap-2 rounded-xl border border-white/10 bg-slate-900/80 px-4 py-2 text-sm font-semibold text-slate-300 hover:bg-white/10 hover:text-white">
                <x-heroicon-o-arrow-left class="h-4 w-4" />
                {{ __('Dashboard') }}
            </a>
        </div>

        <div class="min-h-0 flex-1 overflow-hidden rounded-2xl border border-[var(--ak-border)] bg-[var(--ak-card)] shadow-[var(--ak-shadow)]">
            <livewire:stocks.stock-predictions-table />
        </div>
    </div>
</x-app-layout>
