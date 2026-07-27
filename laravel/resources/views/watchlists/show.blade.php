@extends('layouts.aktienki')

@section('content')
    <div class="mx-auto w-full max-w-screen-2xl space-y-5 py-5">
        <header class="sticky top-[73px] z-40 -mx-2 flex flex-col justify-between gap-4 border-b border-[var(--ak-border)] bg-[var(--ak-bg-1)] px-2 py-3 shadow-[0_12px_24px_rgba(0,0,0,.12)] sm:flex-row sm:items-end">
            <div>
                <div class="flex flex-wrap items-center gap-2">
                    <p class="text-xs font-black uppercase tracking-[.2em] text-violet-300">{{ __('Watchlist') }}</p>
                    @if ($watchlist->is_default)
                        <span class="rounded-lg border border-violet-400/20 bg-violet-500/10 px-2 py-1 text-[9px] font-black uppercase tracking-wide text-violet-300">{{ __('Standard') }}</span>
                    @endif
                </div>
                <h1 class="mt-2 text-3xl font-black text-[var(--ak-text)]">{{ $watchlist->name }}</h1>
                @if ($watchlist->description)
                    <p class="mt-2 text-sm text-[var(--ak-muted)]">{{ $watchlist->description }}</p>
                @endif
            </div>
            <div class="flex flex-wrap gap-2">
                <a href="{{ route('stocks.index') }}" class="inline-flex h-10 items-center justify-center gap-2 rounded-xl border border-[var(--ak-border)] bg-[var(--ak-surface-muted)] px-4 text-xs font-bold text-[var(--ak-muted)] transition hover:border-violet-400/30 hover:text-[var(--ak-text)]">
                    <x-heroicon-o-plus class="h-4 w-4" />{{ __('Aktien hinzufügen') }}
                </a>
                <a href="{{ route('watchlists.index') }}" class="inline-flex h-10 items-center justify-center gap-2 rounded-xl border border-[var(--ak-border)] bg-[var(--ak-surface-muted)] px-4 text-xs font-bold text-[var(--ak-muted)] transition hover:border-violet-400/30 hover:text-[var(--ak-text)]">
                    <x-heroicon-o-cog-6-tooth class="h-4 w-4" />{{ __('Watchlists verwalten') }}
                </a>
            </div>
        </header>

        <section class="grid gap-3 sm:grid-cols-3">
            <article class="rounded-2xl border border-[var(--ak-border)] bg-[var(--ak-card)] p-4 shadow-[var(--ak-shadow)]">
                <p class="text-[10px] font-black uppercase tracking-[.15em] text-[var(--ak-muted)]">{{ __('Aktien') }}</p>
                <p class="mt-2 text-2xl font-black text-[var(--ak-text)]">{{ $watchlist->items->count() }}</p>
            </article>
            <article class="rounded-2xl border border-[var(--ak-border)] bg-[var(--ak-card)] p-4 shadow-[var(--ak-shadow)]">
                <p class="text-[10px] font-black uppercase tracking-[.15em] text-[var(--ak-muted)]">{{ __('Durchschnittlicher Profit') }}</p>
                <p class="mt-2 text-2xl font-black {{ ($averageProfit ?? 0) >= 0 ? 'text-emerald-400' : 'text-rose-400' }}">
                    {{ $averageProfit !== null ? (($averageProfit > 0 ? '+' : '').number_format($averageProfit, 2, ',', '.').' %') : '—' }}
                </p>
            </article>
            <article class="rounded-2xl border border-[var(--ak-border)] bg-[var(--ak-card)] p-4 shadow-[var(--ak-shadow)]">
                <p class="text-[10px] font-black uppercase tracking-[.15em] text-[var(--ak-muted)]">{{ __('Berechnungsbasis') }}</p>
                <p class="mt-2 text-sm font-black text-[var(--ak-text)]">{{ __('Seit Aufnahme in die Watchlist') }}</p>
            </article>
        </section>

        <section class="overflow-hidden rounded-[1.5rem] border border-[var(--ak-border)] bg-[var(--ak-card)] shadow-[var(--ak-shadow)]">
            @if ($watchlist->items->isEmpty())
                <div class="px-6 py-20 text-center">
                    <x-heroicon-o-star class="mx-auto h-10 w-10 text-violet-300/50" />
                    <h2 class="mt-4 font-black text-[var(--ak-text)]">{{ __('Noch keine Aktien enthalten') }}</h2>
                    <p class="mt-2 text-sm text-[var(--ak-muted)]">{{ __('Öffne die Aktienliste und klicke bei einer Aktie auf den Stern.') }}</p>
                </div>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[850px] border-collapse text-left">
                        <thead>
                            <tr class="border-b border-[var(--ak-border)] bg-[var(--ak-surface-muted)] text-[10px] font-black uppercase tracking-[.12em] text-[var(--ak-muted)]">
                                <th class="px-5 py-4">{{ __('Aktie') }}</th>
                                <th class="px-4 py-4 text-right">{{ __('Einstiegskurs') }}</th>
                                <th class="px-4 py-4 text-right">{{ __('Aktueller Kurs') }}</th>
                                <th class="px-4 py-4 text-center">{{ __('KI-Score') }}</th>
                                <th class="px-4 py-4 text-right">{{ __('Profit je Aktie') }}</th>
                                <th class="px-4 py-4 text-right">{{ __('Profit') }}</th>
                                <th class="w-16 px-4 py-4"><span class="sr-only">{{ __('Aktionen') }}</span></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-[var(--ak-border)]">
                            @foreach ($watchlist->items as $item)
                                @if ($item->instrument)
                                    @php
                                        $prediction = $latestPredictions->get($item->instrument_id);
                                        $entryPrice = is_numeric($item->entry_price) ? (float) $item->entry_price : null;
                                        $currentPrice = is_numeric($prediction?->current_price) ? (float) $prediction->current_price : null;
                                        $profitAbsolute = $entryPrice !== null && $currentPrice !== null ? $currentPrice - $entryPrice : null;
                                        $profitPercent = $entryPrice !== null && $entryPrice > 0 && $currentPrice !== null
                                            ? ($profitAbsolute / $entryPrice) * 100
                                            : null;
                                        $score = \App\Support\AiScore::toTen($prediction?->prediction_score);
                                        $scorePercent = \App\Support\AiScore::toPercent($prediction?->prediction_score);
                                        $currency = $item->entry_currency ?: $item->instrument->currency ?: 'USD';
                                        $stockIconUrl = route('stocks.icon', $item->instrument->id);
                                        $countryCode = strtoupper((string) $item->instrument->country);
                                        $countryFlag = strlen($countryCode) === 2 && function_exists('mb_chr')
                                            ? mb_chr(127397 + ord($countryCode[0])).mb_chr(127397 + ord($countryCode[1]))
                                            : '🌐';
                                        $itemIndices = $instrumentIndices->get($item->instrument_id, collect());
                                    @endphp
                                    <tr
                                        data-href="{{ route('stocks.show', ['symbol' => $item->instrument->symbol, 'return_to' => request()->getRequestUri()]) }}"
                                        role="link"
                                        tabindex="0"
                                        onclick="if (!event.target.closest('a,button,input,select,label,form')) window.location.assign(this.dataset.href)"
                                        onkeydown="if (event.target === this && (event.key === 'Enter' || event.key === ' ')) { event.preventDefault(); window.location.assign(this.dataset.href); }"
                                        class="cursor-pointer transition hover:bg-violet-500/[.075] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-violet-400/70"
                                    >
                                        <td class="px-5 py-4">
                                            <a href="{{ route('stocks.show', ['symbol' => $item->instrument->symbol, 'return_to' => request()->getRequestUri()]) }}" class="group flex min-w-0 items-center gap-3">
                                                <span class="relative flex h-10 w-10 shrink-0 items-center justify-center overflow-hidden rounded-xl border border-violet-400/20 bg-white/[.06]">
                                                    <span class="flex h-full w-full items-center justify-center bg-violet-500/10 text-xs font-black leading-none text-violet-300">
                                                        {{ strtoupper(substr($item->instrument->symbol, 0, 2)) }}
                                                    </span>
                                                    <span class="absolute inset-0 z-10 flex items-center justify-center p-1.5" aria-hidden="true">
                                                        <img
                                                            src="{{ $stockIconUrl }}"
                                                            alt=""
                                                            class="h-full w-full object-contain opacity-0"
                                                            loading="eager"
                                                            onload="this.classList.remove('opacity-0'); this.parentElement.classList.add('bg-slate-50')"
                                                            onerror="this.parentElement.classList.add('hidden')"
                                                        >
                                                    </span>
                                                </span>
                                                <span class="min-w-0">
                                                    <strong class="block text-sm text-[var(--ak-text)] transition group-hover:text-violet-300">{{ $item->instrument->symbol }}</strong>
                                                    <span class="block max-w-64 truncate text-xs text-[var(--ak-muted)]">{{ $item->instrument->name }}</span>
                                                    <span class="mt-1.5 flex min-w-0 flex-wrap items-center gap-1">
                                                        <span class="inline-flex items-center gap-1 rounded-md bg-white/[.04] px-1.5 py-0.5 text-[9px] font-bold text-[var(--ak-muted)]" title="{{ __('Land') }}">
                                                            <span>{{ $countryFlag }}</span>{{ $countryCode ?: '—' }}
                                                        </span>
                                                        <span class="inline-flex max-w-28 items-center gap-1 rounded-md bg-violet-500/[.08] px-1.5 py-0.5 text-[9px] font-bold text-violet-300" title="{{ __('Index') }}">
                                                            <x-heroicon-o-chart-bar class="h-3 w-3 shrink-0" />
                                                            <span class="truncate">{{ $itemIndices->isNotEmpty() ? $itemIndices->pluck('symbol')->join(', ') : '—' }}</span>
                                                        </span>
                                                        <span class="inline-flex max-w-32 items-center gap-1 rounded-md bg-amber-300/[.07] px-1.5 py-0.5 text-[9px] font-bold text-amber-200/80" title="{{ __('Sektor') }}">
                                                            <x-heroicon-o-squares-2x2 class="h-3 w-3 shrink-0" />
                                                            <span class="truncate">{{ __($item->instrument->sector ?: '—') }}</span>
                                                        </span>
                                                    </span>
                                                </span>
                                            </a>
                                        </td>
                                        <td class="px-4 py-4 text-right text-sm font-bold text-[var(--ak-text)]">
                                            {{ $entryPrice !== null ? number_format($entryPrice, 2, ',', '.').' '.$currency : '—' }}
                                        </td>
                                        <td class="px-4 py-4 text-right text-sm font-black text-[var(--ak-text)]">
                                            {{ $currentPrice !== null ? number_format($currentPrice, 2, ',', '.').' '.$currency : '—' }}
                                        </td>
                                        <td class="px-4 py-4">
                                            @if ($score !== null)
                                                <div class="mx-auto w-28">
                                                    <div class="mb-1 flex items-baseline justify-between">
                                                        <span class="text-sm font-black text-violet-300">{{ number_format($score, 1, ',', '.') }}</span>
                                                        <span class="text-[9px] text-[var(--ak-muted)]">/ 10</span>
                                                    </div>
                                                    <div class="h-1.5 overflow-hidden rounded-full bg-slate-500/15">
                                                        <div class="h-full rounded-full bg-gradient-to-r from-rose-400 via-amber-300 to-emerald-400" style="width: {{ $scorePercent }}%"></div>
                                                    </div>
                                                </div>
                                            @else
                                                <span class="block text-center text-sm text-[var(--ak-muted)]">—</span>
                                            @endif
                                        </td>
                                        <td class="px-4 py-4 text-right text-sm font-bold {{ ($profitAbsolute ?? 0) >= 0 ? 'text-emerald-400' : 'text-rose-400' }}">
                                            {{ $profitAbsolute !== null ? (($profitAbsolute > 0 ? '+' : '').number_format($profitAbsolute, 2, ',', '.').' '.$currency) : '—' }}
                                        </td>
                                        <td class="px-4 py-4 text-right">
                                            <span class="inline-flex min-w-24 justify-center rounded-lg border px-3 py-2 text-sm font-black {{ $profitPercent === null ? 'border-[var(--ak-border)] bg-[var(--ak-surface-muted)] text-[var(--ak-muted)]' : ($profitPercent >= 0 ? 'border-emerald-400/25 bg-emerald-400/10 text-emerald-400' : 'border-rose-400/25 bg-rose-400/10 text-rose-400') }}">
                                                {{ $profitPercent !== null ? (($profitPercent > 0 ? '+' : '').number_format($profitPercent, 2, ',', '.').' %') : '—' }}
                                            </span>
                                        </td>
                                        <td class="px-4 py-4">
                                            <form method="POST" action="{{ route('watchlists.items.destroy', [$watchlist->id, $item->instrument->id]) }}">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" title="{{ __('Aus Watchlist entfernen') }}" aria-label="{{ __('Aus Watchlist entfernen') }}: {{ $item->instrument->symbol }}" class="inline-flex h-9 w-9 items-center justify-center rounded-lg text-slate-500 transition hover:bg-rose-400/10 hover:text-rose-400">
                                                    <x-heroicon-o-trash class="h-4 w-4" />
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                @endif
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </section>

        <p class="text-center text-[10px] text-[var(--ak-muted)]">{{ __('Der Profit basiert auf dem beim Hinzufügen gespeicherten Aufnahmekurs und berücksichtigt keine Stückzahl, Gebühren oder Steuern.') }}</p>
    </div>
@endsection
