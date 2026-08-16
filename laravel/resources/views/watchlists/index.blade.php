@extends('layouts.aktienki')

@section('content')
    <x-detail-page-theme />
    @php $watchlistLimitReached = $watchlistLimit !== null && $watchlists->where('active', true)->count() >= $watchlistLimit; @endphp
    <div
        x-data="{ setupOpen: @js(! $watchlistLimitReached && $errors->any()) }"
        @keydown.escape.window="setupOpen = false"
        class="ak-detail-design mx-auto w-full max-w-screen-2xl space-y-5 py-5"
    >
        <header class="ak-detail-hero flex flex-col justify-between gap-4 rounded-2xl border border-cyan-400/25 bg-cyan-400/[.035] px-5 py-4 shadow-[0_18px_55px_rgba(6,182,212,.06)] sm:flex-row sm:items-center">
            <div>
                <p class="text-xs font-black uppercase tracking-[.2em] text-cyan-400">{{ __('Persönliche Auswahl') }}</p>
                <h1 class="mt-2 text-3xl font-black text-[var(--ak-text)]">{{ __('Watchlists') }}</h1>
                <p class="mt-2 text-sm text-[var(--ak-muted)]">{{ __('Erstelle zuerst eine Watchlist und füge anschließend Aktien über den Stern in der Aktientabelle hinzu.') }}</p>
            </div>
            <div class="flex flex-wrap items-center gap-2 self-start sm:self-auto">
                @if ($watchlistLimitReached)
                    <div class="inline-flex h-10 items-center gap-2 rounded-xl border border-amber-300/25 bg-amber-300/[.08] px-4 text-xs font-bold text-amber-200">
                        <x-heroicon-o-information-circle class="h-4 w-4" />
                        {{ __('Limit erreicht: :count Watchlists', ['count' => $watchlistLimit]) }}
                    </div>
                @else
                    <button type="button" @click="setupOpen = true" class="inline-flex h-10 items-center justify-center gap-2 rounded-xl border border-cyan-400/35 bg-cyan-400/10 px-4 text-xs font-black text-cyan-300 shadow-lg shadow-cyan-950/20 transition hover:border-cyan-300/55 hover:bg-cyan-400/20 hover:text-cyan-200">
                        <x-heroicon-o-cog-6-tooth class="h-4 w-4" />{{ __('Watchlist einrichten') }}
                    </button>
                @endif
                <a href="{{ route('stocks.index') }}" class="inline-flex h-10 items-center justify-center gap-2 rounded-xl border border-[var(--ak-border)] bg-[var(--ak-surface-muted)] px-4 text-xs font-bold text-[var(--ak-muted)] transition hover:border-violet-400/30 hover:text-[var(--ak-text)]">
                    <x-heroicon-o-table-cells class="h-4 w-4" />{{ __('Zur Aktienliste') }}
                </a>
            </div>
        </header>

        @if (session('status') === 'watchlist-created')
            <div class="rounded-xl border border-emerald-400/20 bg-emerald-400/10 px-4 py-3 text-sm font-bold text-emerald-400">{{ __('Watchlist erstellt. Du kannst jetzt Aktien über den Stern hinzufügen.') }}</div>
        @elseif (session('status') === 'watchlist-item-removed')
            <div class="rounded-xl border border-emerald-400/20 bg-emerald-400/10 px-4 py-3 text-sm font-bold text-emerald-400">{{ __('Aktie wurde aus der Watchlist entfernt.') }}</div>
        @elseif (session('status') === 'watchlist-deleted')
            <div class="rounded-xl border border-emerald-400/20 bg-emerald-400/10 px-4 py-3 text-sm font-bold text-emerald-400">{{ __('Watchlist wurde gelöscht.') }}</div>
        @elseif (session('status') === 'watchlist-item-moved')
            <div class="rounded-xl border border-emerald-400/20 bg-emerald-400/10 px-4 py-3 text-sm font-bold text-emerald-400">{{ __('Aktie wurde in die ausgewählte Watchlist verschoben.') }}</div>
        @endif

        @unless ($watchlistLimitReached)
        <div x-cloak x-show="setupOpen" x-transition.opacity class="fixed inset-0 z-[80] bg-slate-950/65 backdrop-blur-sm" @click="setupOpen = false"></div>
        <aside
            x-cloak
            x-show="setupOpen"
            x-transition:enter="transition ease-out duration-300"
            x-transition:enter-start="translate-x-full"
            x-transition:enter-end="translate-x-0"
            x-transition:leave="transition ease-in duration-200"
            x-transition:leave-start="translate-x-0"
            x-transition:leave-end="translate-x-full"
            class="fixed bottom-0 right-0 top-[73px] z-[90] w-full max-w-md overflow-y-auto border-l border-[var(--ak-border)] bg-[color-mix(in_srgb,var(--ak-card)_90%,transparent)] p-5 shadow-2xl shadow-black/50"
            role="dialog"
            aria-modal="true"
            aria-labelledby="watchlist-setup-title"
        >
            <div class="flex items-start justify-between gap-4 border-b border-[var(--ak-border)] pb-5">
                <div class="flex items-center gap-3">
                    <span class="flex h-10 w-10 items-center justify-center rounded-xl bg-violet-500/10 text-violet-300"><x-heroicon-o-plus class="h-5 w-5" /></span>
                    <div>
                        <h2 id="watchlist-setup-title" class="font-black text-[var(--ak-text)]">{{ __('Neue Watchlist') }}</h2>
                        <p class="text-xs text-[var(--ak-muted)]">{{ __('Lege einen Namen für deine Auswahl fest') }}</p>
                    </div>
                </div>
                <button type="button" @click="setupOpen = false" class="inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-xl border border-[var(--ak-border)] text-[var(--ak-muted)] transition hover:border-violet-400/30 hover:text-[var(--ak-text)]" aria-label="{{ __('Schließen') }}">
                    <x-heroicon-o-x-mark class="h-5 w-5" />
                </button>
            </div>

            <form method="POST" action="{{ route('watchlists.store') }}" class="mt-6 space-y-5">
                @csrf
                <div>
                    <label for="watchlist-name" class="ak-label">{{ __('Name') }}</label>
                    <input id="watchlist-name" name="name" value="{{ old('name') }}" maxlength="80" required class="ak-input mt-2" placeholder="{{ __('Zum Beispiel: Technologie') }}">
                    @error('name')<p class="mt-2 text-xs text-rose-400">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="watchlist-description" class="ak-label">{{ __('Beschreibung') }} <span class="normal-case text-[var(--ak-muted)]">({{ __('optional') }})</span></label>
                    <textarea id="watchlist-description" name="description" rows="4" maxlength="500" class="ak-input mt-2 resize-none" placeholder="{{ __('Notizen zum Zweck dieser Watchlist') }}">{{ old('description') }}</textarea>
                    @error('description')<p class="mt-2 text-xs text-rose-400">{{ $message }}</p>@enderror
                </div>
                <button type="submit" class="inline-flex h-11 w-full items-center justify-center gap-2 rounded-xl border border-cyan-400/35 bg-cyan-400/10 px-5 text-sm font-black text-cyan-300 shadow-lg shadow-cyan-950/20 transition hover:border-cyan-300/55 hover:bg-cyan-400/20 hover:text-cyan-200">
                    <x-heroicon-o-plus-circle class="h-5 w-5" />{{ __('Watchlist erstellen') }}
                </button>
            </form>
        </aside>
        @endunless

        <section>
            <div class="space-y-4">
                @forelse ($watchlists as $watchlist)
                    @php $performance = $watchlistPerformance->get($watchlist->id); @endphp
                    <article
                        id="watchlist-{{ $watchlist->id }}"
                        data-watchlist-dropzone
                        data-watchlist-id="{{ $watchlist->id }}"
                        class="ak-detail-panel scroll-mt-24 overflow-hidden rounded-[1.5rem] border border-[var(--ak-border)] bg-[var(--ak-card)] p-5 shadow-[var(--ak-shadow)] transition duration-200"
                    >
                        <div class="flex flex-wrap items-start justify-between gap-3">
                            <div>
                                <div class="flex items-center gap-2">
                                    <h2 class="text-lg font-black text-[var(--ak-text)]">{{ $watchlist->name }}</h2>
                                    @if ($watchlist->is_default)
                                        <span class="rounded-lg border border-violet-400/20 bg-violet-500/10 px-2 py-1 text-[9px] font-black uppercase tracking-wide text-violet-300">{{ __('Standard') }}</span>
                                    @endif
                                </div>
                                @if ($watchlist->description)<p class="mt-1 text-xs text-[var(--ak-muted)]">{{ $watchlist->description }}</p>@endif
                            </div>
                            <div class="flex items-center gap-2">
                                <a href="{{ route('watchlists.show', $watchlist->id) }}" class="relative z-10 inline-flex h-9 shrink-0 items-center justify-center gap-2 rounded-lg border border-cyan-400/35 bg-cyan-400/10 px-3 text-xs font-black text-cyan-300 transition hover:border-cyan-300/55 hover:bg-cyan-400/20">
                                    <x-heroicon-o-eye class="h-4 w-4" />{{ __('Öffnen') }}
                                </a>
                                <span
                                    class="inline-flex h-9 items-center rounded-lg border px-3 text-xs font-black {{ ($performance['percent'] ?? null) === null ? 'border-[var(--ak-border)] bg-[var(--ak-surface-muted)] text-[var(--ak-muted)]' : ($performance['percent'] >= 0 ? 'border-emerald-400/25 bg-emerald-400/10 text-emerald-400' : 'border-rose-400/25 bg-rose-400/10 text-rose-400') }}"
                                    title="{{ __('Gleichgewichtete Performance seit Aufnahme in die Watchlist') }} · {{ $performance['evaluated'] ?? 0 }}/{{ $performance['total'] ?? 0 }} {{ __('Aktien ausgewertet') }}"
                                >
                                    {{ __('Performance') }}:
                                    @if (($performance['percent'] ?? null) !== null)
                                        <span class="ml-1">{{ $performance['percent'] > 0 ? '+' : '' }}{{ number_format($performance['percent'], 2, ',', '.') }} %</span>
                                    @else
                                        <span class="ml-1">—</span>
                                    @endif
                                </span>
                                <span class="rounded-full border border-[var(--ak-border)] bg-[var(--ak-surface-muted)] px-3 py-1.5 text-xs font-bold text-[var(--ak-muted)]">{{ $watchlist->items->count() }} {{ __('Aktien') }}</span>
                                <form method="POST" action="{{ route('watchlists.destroy', $watchlist->id) }}" onsubmit="return confirm(@js(__('Watchlist wirklich löschen? Alle enthaltenen Zuordnungen werden entfernt.')))">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" title="{{ __('Watchlist löschen') }}" aria-label="{{ __('Watchlist löschen') }}: {{ $watchlist->name }}" class="inline-flex h-9 w-9 items-center justify-center rounded-lg border border-[var(--ak-border)] text-slate-500 transition hover:border-rose-400/30 hover:bg-rose-400/10 hover:text-rose-400">
                                        <x-heroicon-o-trash class="h-4 w-4" />
                                    </button>
                                </form>
                            </div>
                        </div>

                        @if ($watchlist->items->isEmpty())
                            <div class="mt-5 rounded-2xl border border-dashed border-[var(--ak-border)] px-5 py-10 text-center">
                                <x-heroicon-o-star class="mx-auto h-7 w-7 text-violet-300/55" />
                                <p class="mt-3 text-sm font-bold text-[var(--ak-text)]">{{ __('Noch keine Aktien enthalten') }}</p>
                                <p class="mt-1 text-xs text-[var(--ak-muted)]">{{ __('Öffne die Aktienliste und klicke bei einer Aktie auf den Stern.') }}</p>
                            </div>
                        @else
                            <div class="mt-5 grid gap-2 sm:grid-cols-2 xl:grid-cols-3">
                                @foreach ($watchlist->items as $item)
                                    @if ($item->instrument)
                                        @php
                                            $currentPriceRow = $currentPrices->get($item->instrument_id);
                                            $currentPrice = is_numeric($currentPriceRow?->current_price) ? (float) $currentPriceRow->current_price : null;
                                            $itemPerformance = is_numeric($item->entry_price) && (float) $item->entry_price > 0 && $currentPrice !== null
                                                ? (($currentPrice - (float) $item->entry_price) / (float) $item->entry_price) * 100
                                                : null;
                                            $itemCurrency = $item->entry_currency ?: $item->instrument->currency;
                                            $countryCode = strtoupper((string) $item->instrument->country);
                                            $countryFlag = strlen($countryCode) === 2 && function_exists('mb_chr')
                                                ? mb_chr(127397 + ord($countryCode[0])).mb_chr(127397 + ord($countryCode[1]))
                                                : '🌐';
                                            $itemIndices = $instrumentIndices->get($item->instrument_id, collect());
                                        @endphp
                                        <div
                                            draggable="{{ $watchlists->count() > 1 ? 'true' : 'false' }}"
                                            data-watchlist-item
                                            data-source-watchlist-id="{{ $watchlist->id }}"
                                            data-instrument-id="{{ $item->instrument->id }}"
                                            data-move-url="{{ route('watchlists.items.move', [$watchlist->id, $item->instrument->id]) }}"
                                            class="flex items-center gap-2 rounded-xl border border-[var(--ak-border)] bg-[var(--ak-surface-muted)] p-2 transition hover:border-violet-400/30 {{ $watchlists->count() > 1 ? 'cursor-grab active:cursor-grabbing' : '' }}"
                                        >
                                            <span class="relative flex h-10 w-10 shrink-0 items-center justify-center overflow-hidden rounded-xl border border-violet-400/20 bg-violet-500/10">
                                                <span class="flex h-full w-full items-center justify-center text-xs font-black leading-none text-violet-300">
                                                    {{ strtoupper(substr($item->instrument->symbol, 0, 2)) }}
                                                </span>
                                                <span class="absolute inset-0 z-10 flex items-center justify-center p-1.5" aria-hidden="true">
                                                    <img
                                                        src="{{ route('stocks.icon', $item->instrument->id) }}"
                                                        alt=""
                                                        class="h-full w-full object-contain opacity-0"
                                                        loading="eager"
                                                        onload="this.classList.remove('opacity-0'); this.parentElement.classList.add('bg-slate-50')"
                                                        onerror="this.parentElement.classList.add('hidden')"
                                                    >
                                                </span>
                                            </span>
                                            <a href="{{ route('stocks.show', ['symbol' => $item->instrument->symbol, 'prediction' => $latestPredictionIds->get($item->instrument_id), 'return_to' => request()->getRequestUri()]) }}" class="min-w-0 flex-1 px-1 py-0.5">
                                                <span class="flex items-center justify-between gap-2">
                                                    <strong class="block text-sm text-violet-300">{{ $item->instrument->symbol }}</strong>
                                                    <strong class="text-xs {{ ($itemPerformance ?? 0) >= 0 ? 'text-emerald-400' : 'text-rose-400' }}">
                                                        {{ $itemPerformance !== null ? (($itemPerformance > 0 ? '+' : '').number_format($itemPerformance, 2, ',', '.').' %') : '—' }}
                                                    </strong>
                                                </span>
                                                <span class="block truncate text-xs text-[var(--ak-muted)]">{{ $item->instrument->name }}</span>
                                                <span class="mt-1.5 flex min-w-0 flex-wrap items-center gap-1">
                                                    <span class="inline-flex items-center gap-1 rounded-md bg-white/[.04] px-1.5 py-0.5 text-[9px] font-bold text-[var(--ak-muted)]" title="{{ __('Land') }}">
                                                        <span>{{ $countryFlag }}</span>{{ $countryCode ?: '—' }}
                                                    </span>
                                                    <span class="inline-flex max-w-28 items-center gap-1 rounded-md bg-violet-500/[.08] px-1.5 py-0.5 text-[9px] font-bold text-violet-300" title="{{ __('Index') }}">
                                                        <x-heroicon-o-chart-bar class="h-3 w-3 shrink-0" />
                                                        <span class="truncate">{{ $itemIndices->isNotEmpty() ? $itemIndices->pluck('symbol')->join(', ') : '—' }}</span>
                                                    </span>
                                                    <span class="inline-flex max-w-32 items-center gap-1 rounded-md bg-amber-300/[.07] px-1.5 py-0.5 text-[9px] font-bold text-amber-200/80" title="{{ __('Sektor') }}">
                                                        <x-sector-icon :sector="$item->instrument->sector" class="h-3 w-3 shrink-0" />
                                                        <span class="truncate">{{ __($item->instrument->sector ?: '—') }}</span>
                                                    </span>
                                                </span>
                                                <span class="mt-2 flex flex-wrap gap-x-3 gap-y-1 text-[10px] text-[var(--ak-muted)]">
                                                    <span>{{ __('Aufnahme') }}: <b class="text-[var(--ak-text)]">{{ is_numeric($item->entry_price) ? number_format((float) $item->entry_price, 2, ',', '.').' '.$itemCurrency : '—' }}</b></span>
                                                    <span>{{ __('Aktuell') }}: <b class="text-[var(--ak-text)]">{{ $currentPrice !== null ? number_format($currentPrice, 2, ',', '.').' '.$itemCurrency : '—' }}</b></span>
                                                </span>
                                            </a>
                                            @if ($watchlists->count() > 1)
                                                <form method="POST" action="{{ route('watchlists.items.move', [$watchlist->id, $item->instrument->id]) }}" class="sm:hidden">
                                                    @csrf
                                                    @method('PATCH')
                                                    <label class="sr-only" for="move-{{ $watchlist->id }}-{{ $item->instrument->id }}">{{ __('In Watchlist verschieben') }}</label>
                                                    <select
                                                        id="move-{{ $watchlist->id }}-{{ $item->instrument->id }}"
                                                        name="target_watchlist_id"
                                                        onchange="if (this.value) this.form.submit()"
                                                        class="h-9 w-9 rounded-lg border border-[var(--ak-border)] bg-[var(--ak-card)] px-1 text-xs text-violet-300"
                                                        title="{{ __('In Watchlist verschieben') }}"
                                                    >
                                                        <option value="">↪</option>
                                                        @foreach ($watchlists as $targetWatchlist)
                                                            @continue($targetWatchlist->id === $watchlist->id)
                                                            <option value="{{ $targetWatchlist->id }}">{{ $targetWatchlist->name }}</option>
                                                        @endforeach
                                                    </select>
                                                </form>
                                            @endif
                                            <form method="POST" action="{{ route('watchlists.items.destroy', [$watchlist->id, $item->instrument->id]) }}">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" title="{{ __('Aus Watchlist entfernen') }}" aria-label="{{ __('Aus Watchlist entfernen') }}: {{ $item->instrument->symbol }}" class="inline-flex h-9 w-9 items-center justify-center rounded-lg text-slate-500 transition hover:bg-rose-400/10 hover:text-rose-400">
                                                    <x-heroicon-o-trash class="h-4.5 w-4.5" />
                                                </button>
                                            </form>
                                        </div>
                                    @endif
                                @endforeach
                            </div>
                        @endif
                    </article>
                @empty
                    <div class="ak-detail-panel overflow-hidden rounded-[1.5rem] border border-dashed border-[var(--ak-border)] bg-[var(--ak-card)] px-6 py-16 text-center">
                        <x-heroicon-o-star class="mx-auto h-10 w-10 text-violet-300/50" />
                        <h2 class="mt-4 font-black text-[var(--ak-text)]">{{ __('Noch keine Watchlist vorhanden') }}</h2>
                        <p class="mt-2 text-sm text-[var(--ak-muted)]">{{ __('Erstelle links deine erste Watchlist. Sie wird automatisch als Standard verwendet.') }}</p>
                    </div>
                @endforelse
            </div>
        </section>
    </div>

    @if ($watchlists->count() > 1)
        <script>
            document.addEventListener('DOMContentLoaded', () => {
                const items = document.querySelectorAll('[data-watchlist-item]');
                const zones = document.querySelectorAll('[data-watchlist-dropzone]');
                let draggedItem = null;

                const clearZones = () => zones.forEach(zone => {
                    zone.classList.remove('border-violet-400', 'bg-violet-500/10', 'ring-2', 'ring-violet-400/20');
                });

                items.forEach(item => {
                    item.addEventListener('dragstart', event => {
                        draggedItem = item;
                        event.dataTransfer.effectAllowed = 'move';
                        event.dataTransfer.setData('text/plain', item.dataset.instrumentId);
                        window.setTimeout(() => item.classList.add('opacity-40'), 0);
                    });
                    item.addEventListener('dragend', () => {
                        item.classList.remove('opacity-40');
                        draggedItem = null;
                        clearZones();
                    });
                });

                zones.forEach(zone => {
                    zone.addEventListener('dragover', event => {
                        if (!draggedItem || zone.dataset.watchlistId === draggedItem.dataset.sourceWatchlistId) return;
                        event.preventDefault();
                        event.dataTransfer.dropEffect = 'move';
                        clearZones();
                        zone.classList.add('border-violet-400', 'bg-violet-500/10', 'ring-2', 'ring-violet-400/20');
                    });
                    zone.addEventListener('dragleave', event => {
                        if (!zone.contains(event.relatedTarget)) clearZones();
                    });
                    zone.addEventListener('drop', async event => {
                        event.preventDefault();
                        if (!draggedItem || zone.dataset.watchlistId === draggedItem.dataset.sourceWatchlistId) return;

                        const item = draggedItem;
                        clearZones();
                        item.classList.add('pointer-events-none', 'opacity-50');

                        try {
                            const response = await fetch(item.dataset.moveUrl, {
                                method: 'PATCH',
                                credentials: 'same-origin',
                                headers: {
                                    'Accept': 'application/json',
                                    'Content-Type': 'application/json',
                                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || @js(csrf_token()),
                                },
                                body: JSON.stringify({ target_watchlist_id: Number(zone.dataset.watchlistId) }),
                            });

                            if (!response.ok) throw new Error('move-failed');
                            window.location.reload();
                        } catch (_) {
                            item.classList.remove('pointer-events-none', 'opacity-50');
                            window.alert(@js(__('Die Aktie konnte nicht verschoben werden.')));
                        }
                    });
                });
            });
        </script>
    @endif
@endsection
