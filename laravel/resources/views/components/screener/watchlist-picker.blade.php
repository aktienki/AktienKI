@props([
    'watchlists',
    'membershipIds',
    'paperPortfolios' => collect(),
    'paperPortfolioMembershipIds' => collect(),
    'instrumentId',
    'instrumentName' => '',
    'predictionId' => null,
    'active' => false,
])

@php
    $membershipIds = collect($membershipIds);
    $paperPortfolios = collect($paperPortfolios);
    $paperPortfolioMembershipIds = collect($paperPortfolioMembershipIds);
    $isStored = $active || $paperPortfolioMembershipIds->isNotEmpty();
@endphp

<div {{ $attributes->class(['screener-watchlist-picker relative grid place-items-center']) }} x-data="{ collectionOpen: false }" @click.stop>
    <button type="button" @click.prevent.stop="collectionOpen = true" title="{{ __('Zu Watchlist oder Musterdepot hinzufügen') }}" aria-label="{{ __('Zu Watchlist oder Musterdepot hinzufügen') }}" class="screener-collection-star grid h-8 w-8 shrink-0 cursor-pointer place-items-center rounded-xl border border-amber-400/30 bg-amber-400/[.08] p-0 text-amber-500 transition hover:border-amber-500/60 hover:bg-amber-400/[.16] {{ $isStored ? 'shadow-[0_0_12px_rgba(251,191,36,.24)]' : '' }}">
        @if($isStored)<x-heroicon-s-star class="block h-4 w-4" />@else<x-heroicon-o-star class="block h-4 w-4" />@endif
    </button>

    <template x-teleport="body">
        <div x-cloak x-show="collectionOpen" x-transition.opacity class="ak-collection-modal fixed inset-0 z-[170] grid place-items-center bg-slate-950/55 p-4 backdrop-blur-sm" @keydown.escape.window="collectionOpen = false" @click.self="collectionOpen = false" role="dialog" aria-modal="true" aria-label="{{ __('Watchlist oder Musterdepot auswählen') }}">
            <section class="ak-collection-dialog max-h-[90dvh] w-full max-w-xl overflow-y-auto rounded-2xl border border-[var(--ak-border-strong)] bg-[var(--ak-card)] shadow-2xl">
                <header class="ak-collection-dialog-head flex items-start justify-between gap-4 border-b border-[var(--ak-border)] px-5 py-4">
                    <div class="flex min-w-0 items-center gap-3"><span class="grid h-10 w-10 shrink-0 place-items-center rounded-xl border border-amber-500/30 bg-amber-400/10 text-amber-600"><x-heroicon-o-star class="h-5 w-5" /></span><div class="min-w-0"><h2 class="font-black text-[var(--ak-text)]">{{ __('Watchlist oder Musterdepot') }}</h2><p class="truncate text-xs text-[var(--ak-muted)]">{{ $instrumentName }}</p></div></div>
                    <button type="button" @click="collectionOpen = false" class="grid h-9 w-9 shrink-0 place-items-center rounded-xl border border-[var(--ak-border)] bg-[var(--ak-card)] text-[var(--ak-muted)] hover:text-[var(--ak-text)]" aria-label="{{ __('Schließen') }}"><x-heroicon-o-x-mark class="h-5 w-5" /></button>
                </header>

                <div class="grid gap-4 p-5 sm:grid-cols-2">
                    <div class="ak-collection-section rounded-xl border border-[var(--ak-border)] bg-[var(--ak-surface-muted)] p-3">
                        <div class="mb-3 flex items-center justify-between gap-2"><h3 class="text-xs font-black uppercase tracking-[.12em] text-[var(--ak-text)]">{{ __('Watchlists') }}</h3><a href="{{ route('watchlists.index', ['create' => 1, 'return_to' => request()->getRequestUri()]) }}" class="ak-collection-create ak-collection-create-small"><x-heroicon-o-plus class="h-3.5 w-3.5" />{{ __('Neu') }}</a></div>
                        <div class="space-y-2">
                            @forelse($watchlists as $watchlist)
                                <form method="POST" action="{{ route('watchlists.items.toggle', ['watchlist' => $watchlist->id, 'instrument' => $instrumentId]) }}">@csrf @if($predictionId !== null)<input type="hidden" name="prediction_id" value="{{ $predictionId }}">@endif
                                    <button type="submit" class="flex min-h-10 w-full items-center justify-between gap-3 rounded-lg border border-[var(--ak-border)] bg-[var(--ak-card)] px-3 py-2 text-left text-xs font-bold text-[var(--ak-text)] hover:border-amber-500/40"><span class="truncate">{{ $watchlist->name }}</span><span class="grid h-6 w-6 shrink-0 place-items-center rounded-md {{ $membershipIds->contains((int) $watchlist->id) ? 'bg-amber-500 text-white' : 'bg-[var(--ak-surface-muted)] text-[var(--ak-muted)]' }}">{{ $membershipIds->contains((int) $watchlist->id) ? '✓' : '+' }}</span></button>
                                </form>
                            @empty
                                <div class="rounded-lg border border-dashed border-[var(--ak-border-strong)] bg-[var(--ak-card)] p-4 text-center"><p class="text-xs text-[var(--ak-muted)]">{{ __('Noch keine Watchlist vorhanden.') }}</p><a href="{{ route('watchlists.index', ['create' => 1, 'return_to' => request()->getRequestUri()]) }}" class="ak-collection-create mt-4 w-full"><x-heroicon-o-plus-circle class="h-5 w-5" />{{ __('Watchlist erstellen') }}</a></div>
                            @endforelse
                        </div>
                    </div>

                    <div class="ak-collection-section rounded-xl border border-[var(--ak-border)] bg-[var(--ak-surface-muted)] p-3">
                        <div class="mb-3 flex items-center justify-between gap-2"><h3 class="text-xs font-black uppercase tracking-[.12em] text-[var(--ak-text)]">{{ __('Musterdepots') }}</h3><a href="{{ route('paper-depots.index', ['return_to' => request()->getRequestUri()]) }}" class="ak-collection-create ak-collection-create-small"><x-heroicon-o-plus class="h-3.5 w-3.5" />{{ __('Neu') }}</a></div>
                        <div class="space-y-2">
                            @forelse($paperPortfolios as $portfolio)
                                <form method="POST" action="{{ route('paper-depots.instruments.store', ['portfolio' => $portfolio->id, 'instrument' => $instrumentId]) }}" class="rounded-lg border border-[var(--ak-border)] bg-[var(--ak-card)] p-2.5">@csrf
                                    <div class="flex items-center justify-between gap-2"><span class="min-w-0 truncate text-xs font-bold text-[var(--ak-text)]">{{ $portfolio->name }}</span>@if($paperPortfolioMembershipIds->contains((int) $portfolio->id))<span class="rounded-md bg-slate-600 px-2 py-1 text-[9px] font-black text-white">{{ __('Enthalten') }}</span>@endif</div>
                                    @unless($paperPortfolioMembershipIds->contains((int) $portfolio->id))<div class="mt-2 flex gap-2"><input name="quantity" type="number" min="1" max="100000" value="1" required aria-label="{{ __('Stückzahl') }}" class="ak-input h-9 min-w-0 flex-1 px-2 text-xs"><button type="submit" class="h-9 rounded-lg bg-slate-600 px-3 text-[10px] font-black text-white hover:bg-slate-700">{{ __('Hinzufügen') }}</button></div>@endunless
                                </form>
                            @empty
                                <div class="rounded-lg border border-dashed border-[var(--ak-border-strong)] bg-[var(--ak-card)] p-4 text-center"><p class="text-xs text-[var(--ak-muted)]">{{ __('Noch kein Musterdepot vorhanden.') }}</p><a href="{{ route('paper-depots.index', ['return_to' => request()->getRequestUri()]) }}" class="ak-collection-create mt-4 w-full"><x-heroicon-o-plus-circle class="h-5 w-5" />{{ __('Musterdepot erstellen') }}</a></div>
                            @endforelse
                        </div>
                    </div>
                </div>
            </section>
        </div>
    </template>
</div>
