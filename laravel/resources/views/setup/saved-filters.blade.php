<x-app-layout>
    @php
        $labels = [
            'q' => __('Aktie'), 'country' => __('Land'), 'exchange' => __('Börse'), 'sector' => __('Sektor'),
            'ai_type' => __('KI-Typ'), 'model' => __('Modell'), 'quality_tier' => __('Modellstufe mindestens'), 'signal' => __('Signal'),
            'score_min' => __('KI-Score'), 'confidence_min' => __('Konfidenz'), 'drawdown_max' => __('Drawdown'),
            'profit_factor_min' => __('Profitfaktor'), 'volatility_max' => __('Volatilität'), 'pe_max' => __('KGV'),
            'dividend_yield_min' => __('Dividendenrendite'), 'market_cap_min' => __('Marktkapitalisierung'),
            'revenue_growth_min' => __('Umsatzwachstum'), 'hit_rate_min' => __('Hitrate'),
            'sector_score_rotation' => __('Sektorrotation'), 'index_score_rotation' => __('Indexrotation'),
            'max_positions' => __('Maximale Positionen'),
            'position_factor' => __('Positionsanteil'),
            'exit_strategy' => __('Exitstrategie'),
        ];
        $suffixes = ['score_min' => ' / 10', 'confidence_min' => ' %', 'drawdown_max' => ' %', 'volatility_max' => ' %', 'dividend_yield_min' => ' %', 'revenue_growth_min' => ' %', 'hit_rate_min' => ' %'];
        $defaults = \App\Http\Controllers\SavedPredictionFilterController::FILTER_DEFAULTS;
    @endphp
    <div class="flex h-[calc(100dvh-89px)] min-h-0 flex-col py-4 text-[var(--ak-text)]">
        <header class="mb-4 flex shrink-0 items-center justify-between gap-4">
            <div class="flex min-w-0 items-center gap-3">
                <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl border border-amber-300/25 bg-amber-300/[.08] text-amber-300"><x-heroicon-o-bookmark-square class="h-6 w-6" /></div>
                <div><p class="text-[10px] font-black uppercase tracking-[.16em] text-teal-400">{{ __('Strategie') }}</p><h1 class="text-2xl font-black">{{ __('Strategie Manager') }}</h1><p class="mt-1 text-xs text-[var(--ak-muted)]">{{ __('Gespeicherte Strategien öffnen, bearbeiten oder löschen.') }}</p></div>
            </div>
            <a href="{{ route('setup.filter', $returnFilters) }}" class="inline-flex h-10 shrink-0 items-center gap-2 rounded-lg border border-[var(--ak-border)] bg-[var(--ak-card)] px-4 text-xs font-black text-[var(--ak-muted)] hover:border-teal-500/35 hover:text-teal-400"><x-heroicon-o-arrow-left class="h-4 w-4" />{{ __('Zurück zur Strategie') }}</a>
        </header>

        <section class="mb-3 grid shrink-0 grid-cols-3 gap-3">
            <div class="rounded-xl border border-[var(--ak-border)] bg-[var(--ak-card)] px-4 py-3"><span class="text-[9px] font-black uppercase tracking-wide text-[var(--ak-muted)]">{{ __('Eigene Strategien') }}</span><strong class="mt-1 block text-xl font-black text-teal-400">{{ $ownedSavedFilterCount }}</strong></div>
            <div class="rounded-xl border border-[var(--ak-border)] bg-[var(--ak-card)] px-4 py-3"><span class="text-[9px] font-black uppercase tracking-wide text-[var(--ak-muted)]">{{ __('Tariflimit') }}</span><strong class="mt-1 block text-xl font-black text-amber-300">{{ $savedFilterLimit }}</strong></div>
            <div class="rounded-xl border border-[var(--ak-border)] bg-[var(--ak-card)] px-4 py-3"><span class="text-[9px] font-black uppercase tracking-wide text-[var(--ak-muted)]">{{ __('Noch verfügbar') }}</span><strong class="mt-1 block text-xl font-black">{{ max(0, $savedFilterLimit - $ownedSavedFilterCount) }}</strong></div>
        </section>

        <section id="saved-filter-management" class="min-h-0 flex-1 overflow-y-auto rounded-2xl border border-[var(--ak-border)] bg-[var(--ak-card)] p-3">
            <div class="grid gap-2 xl:grid-cols-2">
                @forelse ($savedFilters as $savedFilter)
                    <article x-data="{ rename: false }" @if ((int) request('highlight') === (int) $savedFilter->id) id="saved-filter-highlight" @endif class="rounded-xl border {{ (int) request('highlight') === (int) $savedFilter->id ? 'border-teal-300/45 bg-teal-400/[.09] shadow-[0_0_28px_rgba(45,212,191,.10)]' : 'border-white/[.08] bg-white/[.035]' }} p-4">
                        @php
                            $metrics = $filterMetrics->get($savedFilter->id);
                            $assignedPortfolios = $savedFilter->portfolios;
                            $isAssignedToPortfolio = $assignedPortfolios->isNotEmpty();
                            $isOwner = (int) $savedFilter->user_id === (int) auth()->id();
                            $isPublicTemplate = $savedFilter->visibility === 'pro_public';
                        @endphp
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0"><div class="flex items-center gap-2"><h2 class="truncate text-base font-black text-white">{{ $savedFilter->name }}</h2>@if($isPublicTemplate)<span class="shrink-0 rounded-md border border-amber-300/25 bg-amber-300/[.09] px-2 py-1 text-[8px] font-black uppercase text-amber-300">{{ __('Pro Public') }}</span>@else<span class="shrink-0 rounded-md border border-slate-400/15 px-2 py-1 text-[8px] font-black uppercase text-slate-500">{{ __('Privat') }}</span>@endif @unless($isOwner)<span class="shrink-0 text-[8px] font-black uppercase text-orange-400">{{ __('Vorlage') }}</span>@endunless</div><p class="mt-1 text-[9px] text-[var(--ak-muted)]">{{ __('Aktualisiert') }} {{ $savedFilter->updated_at->timezone('Europe/Berlin')->format('d.m.Y H:i') }}</p></div>
                            <div class="flex shrink-0 items-center gap-1">
                                @if($isOwner)
                                <form method="POST" action="{{ route('setup.filter.saved.visibility', $savedFilter) }}">@csrf @method('PATCH')<input type="hidden" name="visibility" value="{{ $isPublicTemplate ? 'private' : 'pro_public' }}"><button type="submit" class="inline-flex h-8 items-center gap-1.5 rounded-md border px-2.5 text-[10px] font-black {{ $isPublicTemplate ? 'border-slate-300/15 text-slate-400' : 'border-amber-300/25 bg-amber-300/[.08] text-amber-200' }}" title="{{ $isPublicTemplate ? __('Auf privat stellen') : __('Für Pro freigeben') }}">@if($isPublicTemplate)<x-heroicon-o-lock-closed class="h-3.5 w-3.5" />@else<x-heroicon-o-globe-alt class="h-3.5 w-3.5" />@endif<span class="hidden 2xl:inline">{{ $isPublicTemplate ? __('Privat') : __('Freigeben') }}</span></button></form>
                                @if($isPublicTemplate)<form method="POST" action="{{ route('setup.filter.saved.import', $savedFilter) }}">@csrf<button type="submit" class="inline-flex h-8 items-center gap-1.5 rounded-md border border-amber-300/25 bg-amber-300/[.08] px-2.5 text-[10px] font-black text-amber-200" title="{{ __('Private Kopie dieser öffentlichen Strategie erstellen') }}"><x-heroicon-o-document-duplicate class="h-3.5 w-3.5" />{{ __('Kopie') }}</button></form>@endif
                                <a href="{{ route('setup.filter', array_merge($savedFilter->filters ?? [], ['saved_filter' => $savedFilter->id])) }}" class="inline-flex h-8 items-center gap-1.5 rounded-md border border-teal-300/25 bg-teal-400/[.08] px-2.5 text-[10px] font-black text-teal-300"><x-heroicon-o-pencil-square class="h-3.5 w-3.5" />{{ __('Filter bearbeiten') }}</a>
                                <button type="button" @click="rename = !rename" class="flex h-8 w-8 items-center justify-center rounded-md border border-white/10 text-slate-400 hover:text-white" title="{{ __('Umbenennen') }}"><x-heroicon-o-pencil-square class="h-4 w-4" /></button>
                                @if($isAssignedToPortfolio)
                                    <button type="button" disabled class="flex h-8 w-8 cursor-not-allowed items-center justify-center rounded-md border border-slate-500/15 text-slate-600" title="{{ __('Löschen nicht möglich: Strategie ist einem Depot zugeordnet.') }}"><x-heroicon-o-trash class="h-4 w-4" /></button>
                                @else
                                    <form method="POST" action="{{ route('setup.filter.saved.destroy', $savedFilter) }}">@csrf @method('DELETE')<button type="submit" class="flex h-8 w-8 items-center justify-center rounded-md border border-rose-300/15 text-rose-300 hover:bg-rose-400/10" title="{{ __('Löschen') }}"><x-heroicon-o-trash class="h-4 w-4" /></button></form>
                                @endif
                                @else
                                    <form method="POST" action="{{ route('setup.filter.saved.import', $savedFilter) }}">@csrf<button type="submit" class="inline-flex h-8 items-center gap-1.5 rounded-md border border-amber-300/30 bg-amber-300/[.10] px-3 text-[10px] font-black text-amber-200"><x-heroicon-o-arrow-down-tray class="h-3.5 w-3.5" />{{ __('Importieren') }}</button></form>
                                @endif
                            </div>
                        </div>
                        @if($savedFilter->description)<p class="mt-3 rounded-lg border border-white/[.07] bg-slate-950/20 px-3 py-2 text-[10px] leading-4 text-slate-300">{{ $savedFilter->description }}</p>@endif
                        @if($isAssignedToPortfolio)
                            <div class="mt-3 flex items-center gap-2 rounded-lg border border-orange-400/18 bg-orange-400/[.06] px-3 py-2">
                                <x-heroicon-o-briefcase class="h-4 w-4 shrink-0 text-orange-400" />
                                <span class="text-[9px] font-black uppercase tracking-wide text-slate-500">{{ __('Depotzuordnung') }}</span>
                                <span class="min-w-0 truncate text-[10px] font-black text-orange-400">{{ $assignedPortfolios->pluck('name')->join(' · ') }}</span>
                                <span class="ml-auto text-[9px] text-slate-500">{{ __('Änderung im Musterdepot') }}</span>
                            </div>
                        @endif
                        <div class="mt-3 flex min-h-12 flex-wrap content-start gap-1.5">
                            @foreach (collect(array_replace($defaults, $savedFilter->filters ?? []))->filter(function ($value, $key) use ($defaults) {
                                if ($key === 'exit_strategy') return true;
                                if (is_array($value)) return $value !== [];
                                return (string) $value !== (string) ($defaults[$key] ?? '') && $value !== '' && $value !== null;
                            }) as $key => $value)
                                @php
                                    $displayValue = $key === 'model' && is_array($value)
                                        ? collect($value)->map(fn ($id) => $modelAliases[(int) $id] ?? '#'.$id)->implode(', ')
                                        : ($key === 'exit_strategy'
                                            ? (['fixed_20d' => __('20 Tage'), 'winner_runner' => __('Winner Runner'), 'prediction_target' => __('Prognoseziel'), 'buy_and_hold' => __('Buy and Hold')][$value] ?? $value)
                                        : (in_array($key, ['sector_score_rotation', 'index_score_rotation'], true)
                                            ? ((int) $value === 1 ? __('Aktiv') : __('Inaktiv'))
                                            : (is_array($value) ? implode(', ', $value) : $value)));
                                @endphp
                                <span class="rounded-md border border-teal-300/15 bg-teal-400/[.055] px-2 py-1 text-[9px] text-slate-200"><b class="text-teal-300/70">{{ $labels[$key] ?? $key }}:</b> {{ $displayValue }}{{ $suffixes[$key] ?? '' }}</span>
                            @endforeach
                        </div>
                        @if ($isOwner && $metrics)
                            <div class="mt-3 grid grid-cols-4 gap-2 border-t border-white/[.07] pt-3">
                                @foreach ([
                                    [__('Performance/Jahr'), $metrics['performance_year'], '%', $metrics['performance_year'] >= 0 ? 'text-emerald-300' : 'text-rose-300'],
                                    [__('Profitfaktor'), $metrics['profit_factor'], '', ($metrics['profit_factor'] ?? 0) >= 1 ? 'text-emerald-300' : 'text-amber-300'],
                                    [__('Trades/Monat'), $metrics['trades_month'], '', 'text-slate-100'],
                                    [__('Drawdown'), $metrics['drawdown'], '%', 'text-rose-300'],
                                ] as [$metricLabel, $metricValue, $metricSuffix, $metricColor])
                                    <div class="rounded-lg border border-white/[.07] bg-slate-950/20 px-2.5 py-2">
                                        <span class="block truncate text-[8px] font-black uppercase tracking-wide text-slate-500">{{ $metricLabel }}</span>
                                        <strong class="mt-1 block text-sm font-black tabular-nums {{ $metricColor }}">{{ $metricValue === null ? '—' : number_format((float) $metricValue, 2, ',', '.').$metricSuffix }}</strong>
                                    </div>
                                @endforeach
                            </div>
                        @elseif($isOwner)
                            <div class="mt-3 rounded-lg border border-amber-300/15 bg-amber-300/[.04] px-3 py-2 text-[9px] text-amber-200/75">{{ __('Für diese Filtereinstellung liegt noch kein abgeschlossener Backtest vor.') }}</div>
                        @endif
                        @if($isOwner)<form method="POST" action="{{ route('setup.filter.saved.link', $savedFilter) }}" class="mt-3 flex flex-wrap items-center gap-2 border-t border-white/[.07] pt-3">@csrf @method('PATCH')
                            <span class="mr-auto text-[9px] font-black uppercase tracking-wide text-slate-500">{{ __('Benachrichtigung') }}</span>
                            <input type="hidden" name="email_notification_enabled" value="0">
                            <label class="flex h-9 shrink-0 cursor-pointer items-center gap-2 rounded-md border border-white/[.08] bg-white/[.025] px-2.5 text-[9px] font-bold text-slate-300">
                                <input type="checkbox" name="email_notification_enabled" value="1" @checked($savedFilter->email_notification_enabled) @disabled(! $emailServiceEnabled) class="h-3.5 w-3.5 rounded border-slate-500 bg-slate-900 text-teal-500 focus:ring-teal-500/30 disabled:opacity-40">
                                <x-heroicon-o-envelope class="h-3.5 w-3.5 text-teal-400" />{{ __('E-Mail bei neuen Treffern') }}
                            </label>
                            <button type="submit" class="inline-flex h-9 shrink-0 items-center gap-1.5 rounded-md border border-teal-300/30 bg-teal-400/[.12] px-3 text-[9px] font-black uppercase tracking-wide text-teal-200 transition hover:bg-teal-400/[.20]">
                                <x-heroicon-o-check class="h-3.5 w-3.5" />{{ __('Speichern') }}
                            </button>
                        </form>@endif
                        @error('email_notification')<p class="mt-2 text-[10px] font-bold text-rose-300">{{ $message }}</p>@enderror
                        @error('portfolio_automation')<p class="mt-2 text-[10px] font-bold text-rose-300">{{ $message }}</p>@enderror
                        @error('automation_initial_capital')<p class="mt-2 text-[10px] font-bold text-rose-300">{{ $message }}</p>@enderror
                        @if($isOwner)<form x-show="rename" x-cloak method="POST" action="{{ route('setup.filter.saved.update', $savedFilter) }}" class="mt-3 flex gap-2 border-t border-white/[.07] pt-3">@csrf @method('PATCH')<input name="name" value="{{ $savedFilter->name }}" maxlength="80" required class="ak-input h-9 min-w-0 flex-1 rounded-md px-3 text-xs text-white"><button class="h-9 rounded-md bg-teal-400/15 px-3 text-[10px] font-black text-teal-200">{{ __('Speichern') }}</button></form>@endif
                        @error('name_'.$savedFilter->id)<p class="mt-2 text-[10px] font-bold text-rose-300">{{ $message }}</p>@enderror
                    </article>
                @empty
                    <div class="col-span-full flex min-h-52 flex-col items-center justify-center text-center"><x-heroicon-o-bookmark class="h-10 w-10 text-slate-600" /><h2 class="mt-3 text-base font-black">{{ __('Noch kein Filter gespeichert.') }}</h2><a href="{{ route('setup.filter', $returnFilters) }}" class="mt-4 rounded-lg bg-teal-400/15 px-4 py-2 text-xs font-black text-teal-300">{{ __('Ersten Filter erstellen') }}</a></div>
                @endforelse
            </div>
        </section>
    </div>
    <style>
        #saved-filter-highlight { scroll-margin-top: 12px; }
        #saved-filter-management select.ak-input,
        #saved-filter-management input.ak-input {
            padding-top: 0 !important;
            padding-bottom: 0 !important;
            line-height: 34px !important;
        }
    </style>
    @if (request()->integer('highlight') > 0)
        <script>document.addEventListener('DOMContentLoaded', () => document.querySelector('#saved-filter-highlight')?.scrollIntoView({ block: 'nearest' }));</script>
    @endif
</x-app-layout>
