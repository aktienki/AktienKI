@props(['opportunities' => [], 'risks' => []])

<x-dashboard.card class="ak-standard-card ak-card-static ak-dashboard-card flex min-h-[240px] w-full flex-col p-4 lg:min-h-[255px]">
    <div class="ak-standard-card-head flex items-start gap-2.5">
        <span class="grid h-9 w-9 shrink-0 place-items-center rounded-xl bg-amber-500/10 text-amber-500">
            <x-heroicon-o-light-bulb class="h-4.5 w-4.5" />
        </span>
        <div>
            <p class="text-[10px] font-black uppercase tracking-[.18em] text-[var(--ak-muted)]">{{ __('Chancen & Risiken') }}</p>
            <h3 class="mt-0.5 text-sm font-black text-[var(--ak-text)]">{{ __('Aktuelle Markteinschätzung') }}</h3>
            <p class="mt-0.5 text-[9px] text-[var(--ak-muted)]">{{ __('Regelbasierte Auswertung der aktuellen Marktlage') }}</p>
        </div>
    </div>

    <div class="my-auto grid min-h-0 gap-3 pt-1.5 sm:grid-cols-2">
        @foreach ([
            [__('Chancen'), $opportunities, 'text-emerald-500'],
            [__('Risiken'), $risks, 'text-rose-500'],
        ] as [$listTitle, $listItems, $listTone])
            <div class="rounded-xl border border-[var(--ak-border)] px-3 py-2.5">
                <p class="text-[10px] font-black uppercase tracking-[.1em] {{ $listTone }}">{{ $listTitle }} ({{ count($listItems) }})</p>
                <ul class="mt-1.5 space-y-1.5">
                    @forelse($listItems as $listItem)
                        <li class="text-[11px] leading-[1.4] text-[var(--ak-muted)]">{{ $listItem }}</li>
                    @empty
                        <li class="text-[11px] text-[var(--ak-muted)]">{{ __('Keine Einträge.') }}</li>
                    @endforelse
                </ul>
            </div>
        @endforeach
    </div>
</x-dashboard.card>
