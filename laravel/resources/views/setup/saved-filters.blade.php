<x-app-layout>
    @php
        $labels = [
            'q' => __('Aktie'), 'country' => __('Land'), 'exchange' => __('Börse'), 'sector' => __('Sektor'),
            'ai_type' => __('KI-Typ'), 'model' => __('Modell'), 'quality_tier' => __('Modellstufe mindestens'), 'signal' => __('Signal'),
            'score_min' => __('KI-Score'), 'confidence_min' => __('Konfidenz'), 'drawdown_max' => __('Drawdown'), 'risk_max' => __('Modellrisiko'),
            'profit_per_trade_min' => __('Ø Netto-Trade'), 'median_return_min' => __('Median Netto-Trade'),
            'profit_factor_min' => __('Profitfaktor'), 'volatility_max' => __('Volatilität'), 'pe_max' => __('KGV'),
            'dividend_yield_min' => __('Dividendenrendite'), 'market_cap_min' => __('Marktkapitalisierung'),
            'revenue_growth_min' => __('Umsatzwachstum'), 'hit_rate_min' => __('Hitrate'),
            'sector_score_rotation' => __('Sektorrotation'), 'index_score_rotation' => __('Indexrotation'),
            'forecast_score_rotation_5d_enabled' => __('Forecast-Score-Rotation (5T)'),
            'strategy_priority' => __('Strategiepriorität'),
            'max_positions' => __('Maximale Positionen'),
            'position_factor' => __('Positionsanteil'),
            'exit_strategy' => __('Exitstrategie'),
        ];
        $suffixes = ['score_min' => ' / 10', 'confidence_min' => ' %', 'drawdown_max' => ' %', 'risk_max' => ' %', 'profit_per_trade_min' => ' %', 'median_return_min' => ' %', 'volatility_max' => ' %', 'dividend_yield_min' => ' %', 'revenue_growth_min' => ' %', 'hit_rate_min' => ' %'];
        $defaults = \App\Http\Controllers\SavedPredictionFilterController::FILTER_DEFAULTS;
    @endphp
    <div id="personal-dashboard" class="ak-body min-h-[calc(100dvh-73px)] xl:h-[calc(100dvh-89px)] xl:min-h-0">
    <div class="ak-container ak-strategy-manager flex h-[calc(100dvh-89px)] min-h-0 flex-col py-4 text-[var(--ak-text)] lg:py-5">
        <header class="dashboard-main-header mb-4 flex shrink-0 flex-wrap items-center justify-between gap-3">
            <div class="flex min-w-0 items-center gap-3">
                <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl border border-amber-300/25 bg-amber-300/[.08] text-amber-300"><x-heroicon-o-bookmark-square class="h-6 w-6" /></div>
                <div><p class="text-[10px] font-black uppercase tracking-[.2em] text-orange-400">{{ __('Strategie') }}</p><h1 class="mt-1 text-2xl font-black text-[var(--ak-text)] sm:text-3xl">{{ __('Strategie Manager') }}</h1><p class="mt-1 text-xs text-[var(--ak-muted)]">{{ __('Gespeicherte Strategien öffnen, bearbeiten oder löschen.') }}</p></div>
            </div>
            <a href="{{ route('setup.filter', $returnFilters) }}" data-back-link class="inline-flex h-10 shrink-0 items-center gap-2 rounded-xl border border-cyan-400/25 bg-[var(--ak-card)] px-4 text-xs font-black text-[var(--ak-muted)] shadow-[var(--ak-shadow)] transition hover:border-cyan-400/45 hover:text-cyan-400"><x-heroicon-o-arrow-left class="h-4 w-4" />{{ __('Zurück') }}</a>
        </header>

        @if(session('status'))<div class="mb-3 shrink-0 rounded-xl border border-emerald-300/25 bg-emerald-400/[.08] px-4 py-2.5 text-xs font-bold text-emerald-300">{{ session('status') }}</div>@endif
        @if($errors->any())<div class="mb-3 shrink-0 rounded-xl border border-rose-300/25 bg-rose-400/[.08] px-4 py-2.5 text-xs font-bold text-rose-300">{{ $errors->first() }}</div>@endif

        <section class="mb-3 grid shrink-0 grid-cols-1 gap-3 sm:grid-cols-3">
            <div class="ak-card ak-dashboard-card ak-card-static min-h-0 p-4"><span class="text-[9px] font-black uppercase tracking-[.12em] text-[var(--ak-muted)]">{{ __('Eigene Strategien') }}</span><strong class="mt-1 block text-xl font-black text-cyan-400">{{ $ownedSavedFilterCount }}</strong></div>
            <div class="ak-card ak-dashboard-card ak-card-static min-h-0 p-4"><span class="text-[9px] font-black uppercase tracking-[.12em] text-[var(--ak-muted)]">{{ __('Tariflimit') }}</span><strong class="mt-1 block text-xl font-black text-amber-300">{{ $savedFilterLimit }}</strong></div>
            <div class="ak-card ak-dashboard-card ak-card-static min-h-0 p-4"><span class="text-[9px] font-black uppercase tracking-[.12em] text-[var(--ak-muted)]">{{ __('Noch verfügbar') }}</span><strong class="mt-1 block text-xl font-black text-[var(--ak-text)]">{{ max(0, $savedFilterLimit - $ownedSavedFilterCount) }}</strong></div>
        </section>

        <section id="saved-filter-management" class="min-h-0 flex-1 overflow-y-auto pr-1">
            <div class="hidden overflow-x-auto rounded-2xl border border-white/[.09] bg-[var(--ak-card)] lg:block">
                <table class="w-full min-w-[1120px] border-collapse text-left">
                    <thead class="sticky top-0 z-10 bg-[#17263d] text-[10px] font-black uppercase tracking-[.13em] text-slate-400">
                        <tr class="border-b border-cyan-300/15">
                            <th class="w-10 px-3 py-3"></th>
                            <th class="px-3 py-3">{{ __('Strategie') }}</th>
                            <th class="px-3 py-3 text-center">{{ __('Positionen') }}</th>
                            <th class="px-3 py-3 text-center">{{ __('Faktor') }}</th>
                            <th class="px-3 py-3 text-right">{{ __('Endkapital') }}</th>
                            <th class="px-3 py-3 text-right">{{ __('Performance') }}</th>
                            <th class="px-3 py-3 text-right">{{ __('Profitfaktor') }}</th>
                            <th class="px-3 py-3 text-right">{{ __('Drawdown') }}</th>
                            <th class="px-3 py-3 text-right">{{ __('Trades') }}</th>
                            <th class="px-3 py-3 text-right">{{ __('Aktionen') }}</th>
                        </tr>
                    </thead>
                    @forelse ($savedFilters as $savedFilter)
                        @php
                            $optimizerResult = (array) data_get($savedFilter->filters, 'optimizer_result', []);
                            $legacyMetrics = $filterMetrics->get($savedFilter->id);
                            $tableFinalCapital = data_get($optimizerResult, 'final_capital');
                            $tablePerformance = data_get($optimizerResult, 'performance_percent', data_get($legacyMetrics, 'performance_year'));
                            $tableProfitFactor = data_get($optimizerResult, 'profit_factor', data_get($legacyMetrics, 'profit_factor'));
                            $tableDrawdown = data_get($optimizerResult, 'max_drawdown_percent', data_get($legacyMetrics, 'drawdown'));
                            $tableTrades = data_get($optimizerResult, 'trades');
                            $tablePortfolios = $savedFilter->portfolios;
                            $tableIsAssigned = $tablePortfolios->isNotEmpty();
                            $tableIsOwner = (int) $savedFilter->user_id === (int) auth()->id();
                        @endphp
                        <tbody x-data="{ open: false, rename: false }" @if ((int) request('highlight') === (int) $savedFilter->id) id="saved-filter-highlight-desktop" @endif class="border-b border-white/[.07] last:border-0 {{ (int) request('highlight') === (int) $savedFilter->id ? 'ak-strategy-entry--highlight' : '' }}">
                            <tr class="transition hover:bg-cyan-400/[.035]">
                                <td class="px-3 py-3"><button type="button" @click="open = !open" :aria-expanded="open.toString()" class="grid h-8 w-8 place-items-center rounded-lg border border-cyan-300/15 text-cyan-400"><x-heroicon-o-chevron-down class="h-4 w-4 transition" x-bind:class="open && 'rotate-180'" /></button></td>
                                <td class="px-3 py-3"><strong class="block max-w-[260px] truncate text-sm font-black text-white">{{ $savedFilter->name }}</strong><span class="mt-1 block text-[9px] font-bold uppercase tracking-wide text-slate-500">{{ $savedFilter->visibility === 'pro_public' ? __('Pro Public') : __('Privat') }}@if($tableIsAssigned) · {{ $tablePortfolios->pluck('name')->join(' · ') }}@endif</span></td>
                                <td class="px-3 py-3 text-center text-sm font-black tabular-nums text-slate-200">{{ data_get($savedFilter->filters, 'max_positions', '—') }}</td>
                                <td class="px-3 py-3 text-center"><span class="inline-flex min-w-8 justify-center rounded-md border border-amber-300/20 bg-amber-300/[.07] px-2 py-1 text-xs font-black text-amber-300">{{ data_get($savedFilter->filters, 'position_factor', '—') }}</span></td>
                                <td class="px-3 py-3 text-right text-sm font-black tabular-nums text-white">{{ is_numeric($tableFinalCapital) ? number_format((float) $tableFinalCapital, 2, ',', '.').' €' : '—' }}</td>
                                <td class="px-3 py-3 text-right text-sm font-black tabular-nums {{ (float) $tablePerformance >= 0 ? 'text-emerald-300' : 'text-rose-300' }}">{{ is_numeric($tablePerformance) ? sprintf('%+.2f %%', (float) $tablePerformance) : '—' }}</td>
                                <td class="px-3 py-3 text-right text-sm font-bold tabular-nums text-slate-200">{{ is_numeric($tableProfitFactor) ? number_format((float) $tableProfitFactor, 3, ',', '.') : '—' }}</td>
                                <td class="px-3 py-3 text-right text-sm font-bold tabular-nums text-rose-300">{{ is_numeric($tableDrawdown) ? number_format((float) $tableDrawdown, 2, ',', '.').' %' : '—' }}</td>
                                <td class="px-3 py-3 text-right text-sm font-bold tabular-nums text-slate-200">{{ is_numeric($tableTrades) ? number_format((int) $tableTrades, 0, ',', '.') : '—' }}</td>
                                <td class="px-3 py-3"><div class="flex justify-end gap-1.5">@if($tableIsOwner)<a href="{{ route('setup.filter', array_merge($savedFilter->filters ?? [], ['saved_filter' => $savedFilter->id])) }}" class="grid h-8 w-8 place-items-center rounded-lg border border-teal-300/20 text-teal-300" title="{{ __('Filter bearbeiten') }}"><x-heroicon-o-pencil-square class="h-4 w-4" /></a><button type="button" @click="open = true; rename = !rename" class="grid h-8 w-8 place-items-center rounded-lg border border-white/10 text-slate-400" title="{{ __('Umbenennen') }}"><x-heroicon-o-pencil class="h-4 w-4" /></button>@if($tableIsAssigned)<button disabled class="grid h-8 w-8 cursor-not-allowed place-items-center rounded-lg border border-white/[.06] text-slate-600"><x-heroicon-o-trash class="h-4 w-4" /></button>@else<form method="POST" action="{{ route('setup.filter.saved.destroy', $savedFilter) }}">@csrf @method('DELETE')<button class="grid h-8 w-8 place-items-center rounded-lg border border-rose-300/15 text-rose-300" title="{{ __('Löschen') }}"><x-heroicon-o-trash class="h-4 w-4" /></button></form>@endif @else<form method="POST" action="{{ route('setup.filter.saved.import', $savedFilter) }}">@csrf<button class="h-8 rounded-lg border border-amber-300/25 px-3 text-[10px] font-black text-amber-300">{{ __('Importieren') }}</button></form>@endif</div></td>
                            </tr>
                            <tr x-show="open" x-cloak class="bg-slate-950/20"><td></td><td colspan="9" class="px-3 pb-4 pt-1"><div class="grid gap-3 xl:grid-cols-[minmax(0,1fr)_auto] xl:items-end"><div><span class="text-[9px] font-black uppercase tracking-[.14em] text-cyan-400">{{ __('Kommentar') }}</span><p class="mt-1 max-w-4xl text-xs leading-5 text-slate-300">{{ $savedFilter->description ?: __('Kein Kommentar hinterlegt.') }}</p></div><span class="text-[10px] text-slate-500">{{ __('Aktualisiert') }} {{ $savedFilter->updated_at->timezone('Europe/Berlin')->format('d.m.Y H:i') }}</span></div>@if($tableIsOwner)<form x-show="rename" x-cloak method="POST" action="{{ route('setup.filter.saved.update', $savedFilter) }}" class="mt-3 flex max-w-xl gap-2">@csrf @method('PATCH')<input name="name" value="{{ $savedFilter->name }}" maxlength="80" required class="ak-input h-9 min-w-0 flex-1 rounded-md px-3 text-xs text-white"><button class="h-9 rounded-md bg-teal-400/15 px-3 text-[10px] font-black text-teal-200">{{ __('Speichern') }}</button></form>@endif</td></tr>
                        </tbody>
                    @empty
                        <tbody><tr><td colspan="10" class="px-4 py-14 text-center text-sm text-slate-400">{{ __('Noch kein Filter gespeichert.') }}</td></tr></tbody>
                    @endforelse
                </table>
            </div>
            <div class="grid gap-3 lg:hidden xl:grid-cols-2">
                @forelse ($savedFilters as $savedFilter)
                    <article x-data="{ rename: false }" @if ((int) request('highlight') === (int) $savedFilter->id) id="saved-filter-highlight-mobile" @endif class="ak-strategy-entry ak-card ak-dashboard-card ak-card-static min-h-0 p-3 {{ (int) request('highlight') === (int) $savedFilter->id ? 'ak-strategy-entry--highlight' : '' }}">
                        @php
                            $metrics = $filterMetrics->get($savedFilter->id);
                            $assignedPortfolios = $savedFilter->portfolios;
                            $isAssignedToPortfolio = $assignedPortfolios->isNotEmpty();
                            $isOwner = (int) $savedFilter->user_id === (int) auth()->id();
                            $isPublicTemplate = $savedFilter->visibility === 'pro_public';
                            $servingConfigurations = collect(data_get($savedFilter->filters, 'serving_model_configurations', []))
                                ->filter(fn ($configuration): bool => is_array($configuration));
                        @endphp
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0"><div class="flex items-center gap-2"><h2 class="truncate text-lg font-black text-white">{{ $savedFilter->name }}</h2>@if($isPublicTemplate)<span class="shrink-0 rounded-md border border-amber-300/25 bg-amber-300/[.09] px-2 py-1 text-[9px] font-black uppercase text-amber-300">{{ __('Pro Public') }}</span>@else<span class="shrink-0 rounded-md border border-slate-400/15 px-2 py-1 text-[9px] font-black uppercase text-slate-500">{{ __('Privat') }}</span>@endif @unless($isOwner)<span class="shrink-0 text-[9px] font-black uppercase text-orange-400">{{ __('Vorlage') }}</span>@endunless</div><p class="mt-0.5 text-[11px] text-[var(--ak-muted)]">{{ __('Aktualisiert') }} {{ $savedFilter->updated_at->timezone('Europe/Berlin')->format('d.m.Y H:i') }}</p></div>
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
                        @if($savedFilter->description)<p class="mt-2 rounded-lg border border-white/[.07] bg-slate-950/20 px-3 py-2 text-xs leading-4 text-slate-300">{{ $savedFilter->description }}</p>@endif
                        @if($servingConfigurations->isNotEmpty())
                            <div class="mt-2 rounded-xl border border-violet-300/20 bg-violet-400/[.055] p-2.5">
                                <div class="flex items-center justify-between gap-3"><span class="text-[10px] font-black uppercase tracking-[.14em] text-violet-300">{{ __('Serving-Modellkonfiguration') }}</span><span class="rounded-md border border-violet-300/20 px-2 py-1 text-[9px] font-black text-violet-300">{{ $servingConfigurations->count() }} {{ $servingConfigurations->count() === 1 ? __('Konfiguration') : __('Konfigurationen') }}</span></div>
                                <div class="mt-1.5 space-y-1.5">
                                    @foreach($servingConfigurations as $configuration)
                                        @php $configurationMetrics = (array) ($configuration['metrics'] ?? []); @endphp
                                        <div class="grid gap-2 rounded-lg border border-white/[.07] bg-slate-950/20 px-2.5 py-2 sm:grid-cols-[minmax(0,1fr)_auto] sm:items-center">
                                            <div class="min-w-0"><b class="block truncate text-xs text-white">{{ $configuration['symbol'] ?? '—' }} · {{ $configuration['horizon_days'] ?? '—' }}T · {{ $configuration['variant_label'] ?? $configuration['variant'] ?? '—' }}</b><span class="mt-0.5 block truncate text-[10px] text-slate-400">{{ $configuration['model_name'] ?? '—' }} · Release {{ substr((string) ($configuration['release_id'] ?? ''), 0, 8) }}</span></div>
                                            <div class="flex flex-wrap gap-1.5 sm:justify-end">
                                                @if($configuration['champion'] ?? false)<span class="rounded border border-emerald-300/25 px-1.5 py-1 text-[9px] font-black text-emerald-300">CHAMPION</span>@else<span class="rounded border border-slate-300/15 px-1.5 py-1 text-[9px] font-black text-slate-400">CHALLENGER</span>@endif
                                                @if($configuration['quality_gate_passed'] ?? false)<span class="rounded border border-amber-300/25 px-1.5 py-1 text-[9px] font-black text-amber-300">QUALITY GATE</span>@endif
                                                @if(is_numeric($configurationMetrics['hit_rate'] ?? null))<span class="rounded border border-white/[.08] px-1.5 py-1 text-[9px] text-slate-300">HR {{ number_format($configurationMetrics['hit_rate'] * 100, 1, ',', '.') }} %</span>@endif
                                                @if(is_numeric($configurationMetrics['profit_factor'] ?? null))<span class="rounded border border-white/[.08] px-1.5 py-1 text-[9px] text-slate-300">PF {{ number_format($configurationMetrics['profit_factor'], 2, ',', '.') }}</span>@endif
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        @endif
                        @if($isAssignedToPortfolio)
                            <div class="mt-3 flex items-center gap-2 rounded-lg border border-orange-400/18 bg-orange-400/[.06] px-3 py-2">
                                <x-heroicon-o-briefcase class="h-4 w-4 shrink-0 text-orange-400" />
                                <span class="text-[9px] font-black uppercase tracking-wide text-slate-500">{{ __('Depotzuordnung') }}</span>
                                <span class="min-w-0 truncate text-[10px] font-black text-orange-400">{{ $assignedPortfolios->pluck('name')->join(' · ') }}</span>
                                <span class="ml-auto text-[9px] text-slate-500">{{ __('Änderung im Musterdepot') }}</span>
                            </div>
                        @endif
                        <div class="mt-2 flex flex-wrap content-start gap-1.5">
                            @foreach (collect(array_replace($defaults, $savedFilter->filters ?? []))->filter(function ($value, $key) use ($defaults, $servingConfigurations) {
                                if (in_array($key, ['serving_model_configurations', 'optimizer_result'], true)) return false;
                                if ($key === 'exit_strategy' && $servingConfigurations->isNotEmpty()) return false;
                                if ($key === 'exit_strategy') return true;
                                if (is_array($value)) return $value !== [];
                                return (! array_key_exists($key, $defaults) || $value != $defaults[$key]) && $value !== '' && $value !== null;
                            }) as $key => $value)
                                @php
                                    $displayValue = $key === 'model' && is_array($value) && collect($value)->every(fn ($id) => is_scalar($id))
                                        ? collect($value)->map(fn ($id) => $modelAliases[(int) $id] ?? '#'.$id)->implode(', ')
                                        : ($key === 'exit_strategy' && is_scalar($value)
                                            ? (['fixed_20d' => __('20 Tage'), 'signal_change' => __('Signal- oder Marktphasenwechsel'), 'forecast_below_price' => __('Prognose unter Kurs'), 'winner_runner' => __('Winner Runner'), 'prediction_target' => __('Prognoseziel'), 'buy_and_hold' => __('Buy and Hold')][$value] ?? $value)
                                        : (in_array($key, ['sector_score_rotation', 'index_score_rotation'], true) && is_scalar($value)
                                            ? ((int) $value === 1 ? __('Aktiv') : __('Inaktiv'))
                                            : (is_array($value)
                                                ? (collect($value)->every(fn ($item) => is_scalar($item) || $item === null)
                                                    ? collect($value)->map(fn ($item) => (string) $item)->implode(', ')
                                                    : json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES))
                                                : $value)));
                                @endphp
                                <span class="rounded-md border border-teal-300/15 bg-teal-400/[.055] px-2 py-1 text-[10px] text-slate-200"><b class="text-teal-300/70">{{ $labels[$key] ?? $key }}:</b> {{ $displayValue }}{{ $suffixes[$key] ?? '' }}</span>
                            @endforeach
                        </div>
                        @if ($isOwner && $metrics)
                            <div class="mt-3 grid grid-cols-4 gap-2 border-t border-white/[.07] pt-3">
                                @foreach ([
                                    [__('Performance/Jahr'), $metrics['performance_year'], '%', $metrics['performance_year'] >= 0 ? 'text-emerald-300' : 'text-rose-300'],
                                    [__('Profitfaktor'), \App\Support\ProfitFactor::cap($metrics['profit_factor'] ?? null) ?? '—', '', ($metrics['profit_factor'] ?? 0) >= 1 ? 'text-emerald-300' : 'text-amber-300'],
                                    [__('Trades/Monat'), $metrics['trades_month'], '', 'text-slate-100'],
                                    [__('Drawdown'), $metrics['drawdown'], '%', 'text-rose-300'],
                                ] as [$metricLabel, $metricValue, $metricSuffix, $metricColor])
                                    <div class="rounded-lg border border-white/[.07] bg-slate-950/20 px-2.5 py-2">
                                        <span class="block truncate text-[8px] font-black uppercase tracking-wide text-slate-500">{{ $metricLabel }}</span>
                                        <strong class="mt-1 block text-sm font-black tabular-nums {{ $metricColor }}">{{ $metricValue === null ? '—' : number_format((float) $metricValue, 2, ',', '.').$metricSuffix }}</strong>
                                    </div>
                                @endforeach
                            </div>
                        @elseif($isOwner && $servingConfigurations->isEmpty())
                            <div class="mt-3 rounded-lg border border-amber-300/15 bg-amber-300/[.04] px-3 py-2 text-[9px] text-amber-200/75">{{ __('Für diese Filtereinstellung liegt noch kein abgeschlossener Backtest vor.') }}</div>
                        @endif
                        @if($isOwner)<form method="POST" action="{{ route('setup.filter.saved.link', $savedFilter) }}" class="mt-2 flex flex-wrap items-center gap-2 border-t border-white/[.07] pt-2">@csrf @method('PATCH')
                            <span class="mr-auto text-[10px] font-black uppercase tracking-wide text-slate-500">{{ __('Benachrichtigung') }}</span>
                            <input type="hidden" name="email_notification_enabled" value="0">
                            <label class="flex h-9 shrink-0 cursor-pointer items-center gap-2 rounded-md border border-white/[.08] bg-white/[.025] px-2.5 text-[11px] font-bold text-slate-300">
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
    </div>
    <style>
        #saved-filter-highlight-desktop, #saved-filter-highlight-mobile { scroll-margin-top: 12px; }
        .ak-strategy-manager .ak-dashboard-card { border-radius: 14px; }
        .ak-strategy-manager .ak-strategy-entry--highlight {
            border-color: rgba(103, 232, 249, .48) !important;
            box-shadow: 0 0 28px rgba(34, 211, 238, .12), inset 3px 0 0 #22d3ee !important;
        }
        #saved-filter-management select.ak-input,
        #saved-filter-management input.ak-input {
            padding-top: 0 !important;
            padding-bottom: 0 !important;
            line-height: 34px !important;
        }
        :root[data-theme="light"] .ak-strategy-manager #saved-filter-management article {
            border-color: #d9e7e4 !important;
            background: rgba(255, 255, 255, .94) !important;
        }
        :root[data-theme="light"] .ak-strategy-manager #saved-filter-management article#saved-filter-highlight-mobile {
            border-color: #5eaeb8 !important;
            background: #eef9fa !important;
            box-shadow: 0 7px 22px rgba(8, 108, 120, .13);
        }
        :root[data-theme="light"] .ak-strategy-manager #saved-filter-management :is(.text-white, .text-slate-100) {
            color: #0f172a !important;
        }
        :root[data-theme="light"] .ak-strategy-manager #saved-filter-management :is(.text-slate-200, .text-slate-300) {
            color: #334155 !important;
        }
        :root[data-theme="light"] .ak-strategy-manager #saved-filter-management :is(.text-slate-400, .text-slate-500) {
            color: #52627a !important;
        }
        :root[data-theme="light"] .ak-strategy-manager #saved-filter-management :is(.text-teal-200, .text-teal-300, .text-teal-400) {
            color: #0e6f78 !important;
        }
        :root[data-theme="light"] .ak-strategy-manager #saved-filter-management :is(.text-amber-200, .text-amber-300) {
            color: #9a5b05 !important;
        }
        :root[data-theme="light"] .ak-strategy-manager #saved-filter-management .text-emerald-300 {
            color: #047857 !important;
        }
        :root[data-theme="light"] .ak-strategy-manager #saved-filter-management .text-rose-300 {
            color: #be123c !important;
        }
        :root[data-theme="light"] .ak-strategy-manager #saved-filter-management .text-orange-400 {
            color: #c2410c !important;
        }
        :root[data-theme="light"] .ak-strategy-manager #saved-filter-management article [class*="border-white"] {
            border-color: #cbd5e1 !important;
        }
        :root[data-theme="light"] .ak-strategy-manager #saved-filter-management article [class*="bg-slate-950"] {
            background: #f1f5f9 !important;
        }
        :root[data-theme="light"] .ak-strategy-manager #saved-filter-management article [class*="bg-white"] {
            background: #f8fafc !important;
        }
        :root[data-theme="light"] .ak-strategy-manager #saved-filter-management article [class*="bg-teal-400"] {
            border-color: #8fc8ce !important;
            background: #e6f5f6 !important;
        }
        :root[data-theme="light"] .ak-strategy-manager #saved-filter-management article [class*="bg-amber-300"] {
            border-color: #e4bd69 !important;
            background: #fff6dc !important;
        }
        :root[data-theme="light"] .ak-strategy-manager #saved-filter-management article [class*="bg-orange-400"] {
            border-color: #f0b08c !important;
            background: #fff1e8 !important;
        }
        :root[data-theme="light"] .ak-strategy-manager #saved-filter-management table,
        :root[data-theme="light"] .ak-strategy-manager #saved-filter-management table tbody {
            background: rgba(255, 255, 255, .96) !important;
        }
        :root[data-theme="light"] .ak-strategy-manager #saved-filter-management table thead {
            background: #e8f0f4 !important;
        }
        :root[data-theme="light"] .ak-strategy-manager #saved-filter-management table :is(.text-white, .text-slate-200) {
            color: #172033 !important;
        }
        :root[data-theme="light"] .ak-strategy-manager #saved-filter-management table .text-slate-300 {
            color: #40516a !important;
        }
    </style>
    @if (request()->integer('highlight') > 0)
        <script>document.addEventListener('DOMContentLoaded', () => document.querySelector(window.innerWidth >= 1024 ? '#saved-filter-highlight-desktop' : '#saved-filter-highlight-mobile')?.scrollIntoView({ block: 'nearest' }));</script>
    @endif
</x-app-layout>
