<x-app-layout>
    @php
        $labels = [
            'q' => __('Aktie'), 'country' => __('Land'), 'exchange' => __('Börse'), 'sector' => __('Sektor'),
            'ai_type' => __('KI-Typ'), 'model' => __('Modell'), 'quality_tier' => __('Modellstufe mindestens'), 'signal' => __('Signal'),
            'score_min' => __('KI-Score'), 'confidence_min' => __('Konfidenz'), 'drawdown_max' => __('Drawdown'),
            'profit_factor_min' => __('Profitfaktor'), 'volatility_max' => __('Volatilität'), 'pe_max' => __('KGV'),
            'dividend_yield_min' => __('Dividendenrendite'), 'market_cap_min' => __('Marktkapitalisierung'),
            'revenue_growth_min' => __('Umsatzwachstum'), 'hit_rate_min' => __('Hitrate'),
        ];
        $suffixes = ['score_min' => ' / 10', 'confidence_min' => ' %', 'drawdown_max' => ' %', 'volatility_max' => ' %', 'dividend_yield_min' => ' %', 'revenue_growth_min' => ' %', 'hit_rate_min' => ' %'];
    @endphp
    <div class="flex h-[calc(100dvh-89px)] min-h-0 flex-col py-4 text-[var(--ak-text)]">
        <header class="mb-4 flex shrink-0 items-center justify-between gap-4">
            <div class="flex min-w-0 items-center gap-3">
                <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl border border-amber-300/25 bg-amber-300/[.08] text-amber-300"><x-heroicon-o-bookmark-square class="h-6 w-6" /></div>
                <div><p class="text-[10px] font-black uppercase tracking-[.16em] text-teal-400">{{ __('Setup') }}</p><h1 class="text-2xl font-black">{{ __('Filter verwalten') }}</h1><p class="mt-1 text-xs text-[var(--ak-muted)]">{{ __('Gespeicherte Filter öffnen, umbenennen oder löschen.') }}</p></div>
            </div>
            <a href="{{ route('setup.filter', $returnFilters) }}" class="inline-flex h-10 shrink-0 items-center gap-2 rounded-lg border border-[var(--ak-border)] bg-[var(--ak-card)] px-4 text-xs font-black text-[var(--ak-muted)] hover:border-teal-500/35 hover:text-teal-400"><x-heroicon-o-arrow-left class="h-4 w-4" />{{ __('Zurück zum Setup') }}</a>
        </header>

        <section class="mb-3 grid shrink-0 grid-cols-3 gap-3">
            <div class="rounded-xl border border-[var(--ak-border)] bg-[var(--ak-card)] px-4 py-3"><span class="text-[9px] font-black uppercase tracking-wide text-[var(--ak-muted)]">{{ __('Gespeichert') }}</span><strong class="mt-1 block text-xl font-black text-teal-400">{{ $savedFilters->count() }}</strong></div>
            <div class="rounded-xl border border-[var(--ak-border)] bg-[var(--ak-card)] px-4 py-3"><span class="text-[9px] font-black uppercase tracking-wide text-[var(--ak-muted)]">{{ __('Tariflimit') }}</span><strong class="mt-1 block text-xl font-black text-amber-300">{{ $savedFilterLimit }}</strong></div>
            <div class="rounded-xl border border-[var(--ak-border)] bg-[var(--ak-card)] px-4 py-3"><span class="text-[9px] font-black uppercase tracking-wide text-[var(--ak-muted)]">{{ __('Noch verfügbar') }}</span><strong class="mt-1 block text-xl font-black">{{ max(0, $savedFilterLimit - $savedFilters->count()) }}</strong></div>
        </section>

        <section id="saved-filter-management" class="min-h-0 flex-1 overflow-y-auto rounded-2xl border border-[var(--ak-border)] bg-[var(--ak-card)] p-3">
            <div class="grid gap-2 xl:grid-cols-2">
                @forelse ($savedFilters as $savedFilter)
                    <article x-data="{ rename: false }" class="rounded-xl border border-white/[.08] bg-white/[.035] p-4">
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0"><h2 class="truncate text-base font-black text-white">{{ $savedFilter->name }}</h2><p class="mt-1 text-[9px] text-[var(--ak-muted)]">{{ __('Aktualisiert') }} {{ $savedFilter->updated_at->timezone('Europe/Berlin')->format('d.m.Y H:i') }}</p></div>
                            <div class="flex shrink-0 items-center gap-1">
                                <a href="{{ route('setup.filter', array_merge($savedFilter->filters ?? [], ['saved_filter' => $savedFilter->id])) }}" class="inline-flex h-8 items-center gap-1.5 rounded-md border border-teal-300/25 bg-teal-400/[.08] px-2.5 text-[10px] font-black text-teal-300"><x-heroicon-o-pencil-square class="h-3.5 w-3.5" />{{ __('Filter bearbeiten') }}</a>
                                <button type="button" @click="rename = !rename" class="flex h-8 w-8 items-center justify-center rounded-md border border-white/10 text-slate-400 hover:text-white" title="{{ __('Umbenennen') }}"><x-heroicon-o-pencil-square class="h-4 w-4" /></button>
                                <form method="POST" action="{{ route('setup.filter.saved.destroy', $savedFilter) }}">@csrf @method('DELETE')<button type="submit" class="flex h-8 w-8 items-center justify-center rounded-md border border-rose-300/15 text-rose-300 hover:bg-rose-400/10" title="{{ __('Löschen') }}"><x-heroicon-o-trash class="h-4 w-4" /></button></form>
                            </div>
                        </div>
                        <div class="mt-3 flex min-h-12 flex-wrap content-start gap-1.5">
                            @foreach (($savedFilter->filters ?? []) as $key => $value)
                                <span class="rounded-md border border-white/[.07] bg-slate-950/20 px-2 py-1 text-[9px] text-slate-300"><b class="text-slate-500">{{ $labels[$key] ?? $key }}:</b> {{ $value }}{{ $suffixes[$key] ?? '' }}</span>
                            @endforeach
                        </div>
                        <form method="POST" action="{{ route('setup.filter.saved.link', $savedFilter) }}" class="mt-3 flex items-center gap-2 border-t border-white/[.07] pt-3">@csrf @method('PATCH')
                            <label class="shrink-0 text-[9px] font-black uppercase tracking-wide text-slate-500">{{ __('Gekoppelt an') }}</label>
                            <select name="linked_target" onchange="this.form.requestSubmit()" class="ak-input h-9 min-w-0 flex-1 rounded-md px-2 text-[10px] text-white">
                                <option value="none" @selected(! $savedFilter->portfolio_id && ! $savedFilter->watchlist_id)>{{ __('Keine Kopplung') }}</option>
                                @if ($portfolios->isNotEmpty())<optgroup label="{{ __('Depots') }}">@foreach ($portfolios as $portfolio)<option value="portfolio:{{ $portfolio->id }}" @selected((int) $savedFilter->portfolio_id === (int) $portfolio->id)>◫ {{ $portfolio->name }}</option>@endforeach</optgroup>@endif
                                @if ($watchlists->isNotEmpty())<optgroup label="{{ __('Watchlists') }}">@foreach ($watchlists as $watchlist)<option value="watchlist:{{ $watchlist->id }}" @selected((int) $savedFilter->watchlist_id === (int) $watchlist->id)>★ {{ $watchlist->name }}</option>@endforeach</optgroup>@endif
                            </select>
                            @if ($savedFilter->portfolio || $savedFilter->watchlist)<span class="shrink-0 rounded-md border border-amber-300/20 bg-amber-300/[.07] px-2 py-1 text-[9px] font-bold text-amber-200">{{ $savedFilter->portfolio ? __('Depot') : __('Watchlist') }}</span>@endif
                            <input type="hidden" name="email_notification_enabled" value="0">
                            <label class="flex h-9 shrink-0 cursor-pointer items-center gap-2 rounded-md border border-white/[.08] bg-white/[.025] px-2.5 text-[9px] font-bold text-slate-300">
                                <input type="checkbox" name="email_notification_enabled" value="1" @checked($savedFilter->email_notification_enabled) @disabled(! $emailServiceEnabled) onchange="this.form.requestSubmit()" class="h-3.5 w-3.5 rounded border-slate-500 bg-slate-900 text-teal-500 focus:ring-teal-500/30 disabled:opacity-40">
                                <x-heroicon-o-envelope class="h-3.5 w-3.5 text-teal-400" />{{ __('E-Mail bei neuen Treffern') }}
                            </label>
                        </form>
                        @error('email_notification')<p class="mt-2 text-[10px] font-bold text-rose-300">{{ $message }}</p>@enderror
                        <form x-show="rename" x-cloak method="POST" action="{{ route('setup.filter.saved.update', $savedFilter) }}" class="mt-3 flex gap-2 border-t border-white/[.07] pt-3">@csrf @method('PATCH')<input name="name" value="{{ $savedFilter->name }}" maxlength="80" required class="ak-input h-9 min-w-0 flex-1 rounded-md px-3 text-xs text-white"><button class="h-9 rounded-md bg-teal-400/15 px-3 text-[10px] font-black text-teal-200">{{ __('Speichern') }}</button></form>
                        @error('name_'.$savedFilter->id)<p class="mt-2 text-[10px] font-bold text-rose-300">{{ $message }}</p>@enderror
                    </article>
                @empty
                    <div class="col-span-full flex min-h-52 flex-col items-center justify-center text-center"><x-heroicon-o-bookmark class="h-10 w-10 text-slate-600" /><h2 class="mt-3 text-base font-black">{{ __('Noch kein Filter gespeichert.') }}</h2><a href="{{ route('setup.filter', $returnFilters) }}" class="mt-4 rounded-lg bg-teal-400/15 px-4 py-2 text-xs font-black text-teal-300">{{ __('Ersten Filter erstellen') }}</a></div>
                @endforelse
            </div>
        </section>
    </div>
    <style>
        #saved-filter-management select.ak-input,
        #saved-filter-management input.ak-input {
            padding-top: 0 !important;
            padding-bottom: 0 !important;
            line-height: 34px !important;
        }
    </style>
</x-app-layout>
