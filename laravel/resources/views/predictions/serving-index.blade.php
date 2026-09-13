<x-app-layout>
    <main class="ak-body min-h-[calc(100dvh-73px)] py-5 sm:py-8">
        <div class="ak-container">
            <header class="mb-5 flex flex-col gap-4 xl:flex-row xl:items-end xl:justify-between">
                <div>
                    <p class="text-[10px] font-black uppercase tracking-[.22em] text-cyan-500">{{ __('Service Datenbank') }}</p>
                    <h1 class="mt-1 text-2xl font-black text-[var(--ak-text)] sm:text-3xl">Aktien, Modelle und Predictions</h1>
                    <p class="mt-2 max-w-4xl text-sm leading-6 text-[var(--ak-muted)]">
                        {{ __('Angezeigt werden die Standard- und TCN-Backtests der aktiven Modelle. Der grüne Status kennzeichnet das aktuell aktive Modell.') }}
                        {{ __('Die Auswählbarkeit wird für jede Modellvariante separat aus ihrem einjährigen Walk Forward bestimmt.') }}
                    </p>
                    <div class="mt-3 flex flex-wrap items-center gap-2 text-[8px] font-black uppercase tracking-[.1em]">
                        <span class="inline-flex items-center gap-1.5 rounded-md border border-emerald-500/55 bg-emerald-500/15 px-2 py-1 text-emerald-600"><i class="h-1.5 w-1.5 rounded-full bg-emerald-500"></i>{{ __('Aktives Modell') }}</span>
                        <span class="inline-flex items-center gap-1.5 rounded-md border border-slate-400/30 bg-transparent px-2 py-1 text-[var(--ak-muted)]"><i class="h-1.5 w-1.5 rounded-full bg-slate-400"></i>{{ __('Weiteres verfügbares Modell') }}</span>
                        @if($strategySelectionToken)
                            <label id="serving-select-all-badge" class="serving-select-all-badge ml-auto inline-flex min-h-9 cursor-pointer items-center gap-2 rounded-lg border px-3 py-2 text-[9px] font-black normal-case tracking-normal">
                                <input
                                    id="serving-select-all"
                                    form="serving-model-selection-form"
                                    type="checkbox"
                                    name="select_all"
                                    value="1"
                                    data-total="{{ $bulkSelectableModelKeys->count() }}"
                                    data-initial-all="{{ $bulkSelectableModelKeys->isNotEmpty() && $initialSelectedModelKeys->sort()->values()->all() === $bulkSelectableModelKeys->sort()->values()->all() ? 'true' : 'false' }}"
                                    class="serving-model-checkbox h-5 w-5 shrink-0 cursor-pointer"
                                    @checked($bulkSelectableModelKeys->isNotEmpty() && $initialSelectedModelKeys->sort()->values()->all() === $bulkSelectableModelKeys->sort()->values()->all())
                                >
                                <span id="serving-select-all-label">{{ __('Alle auswählen') }}</span>
                            </label>
                            <button type="submit" form="serving-model-selection-form" class="inline-flex min-h-9 items-center gap-2 rounded-lg border border-slate-600 bg-slate-700 px-3 py-2 text-[9px] font-black normal-case tracking-normal text-white shadow-sm transition hover:bg-slate-800">
                                <x-heroicon-o-calculator class="h-4 w-4" />
                                <span>{{ __('Strategie berechnen') }}</span>
                                <small class="rounded bg-white/15 px-1.5 py-0.5 text-[8px] tabular-nums"><span id="serving-selected-model-count">{{ $initialSelectedModelKeys->count() }}</span> {{ __('Modelle') }}</small>
                            </button>
                        @else
                            <span class="ml-auto inline-flex min-h-9 cursor-not-allowed items-center gap-2 rounded-lg border border-[var(--ak-border)] bg-[var(--ak-surface-muted)] px-3 py-2 text-[9px] normal-case tracking-normal text-[var(--ak-muted)] opacity-60" title="{{ __('Die aktuelle Auswahl enthält keine Modelle mit Backtestdaten.') }}">
                                <x-heroicon-o-calculator class="h-4 w-4" />{{ __('Keine Strategie berechenbar') }}
                            </span>
                        @endif
                    </div>
                </div>
                <div class="grid w-full grid-cols-2 gap-1.5 xl:w-[31rem]">
                    @foreach ([
                        ['label' => 'Aktive Aktien', 'value' => $summary->active_stocks, 'icon' => 'heroicon-o-building-office-2', 'tone' => 'text-cyan-400/70'],
                        ['label' => 'Freigegebene Horizonte', 'value' => $summary->eligible_horizons, 'icon' => 'heroicon-o-check-badge', 'tone' => 'text-emerald-400/70'],
                        ['label' => 'Prediction-Zeilen', 'value' => $summary->published_predictions, 'icon' => 'heroicon-o-chart-bar-square', 'tone' => 'text-violet-400/70'],
                        ['label' => 'Aktien mit Prediction', 'value' => $summary->stocks_with_predictions, 'icon' => 'heroicon-o-sparkles', 'tone' => 'text-amber-400/70'],
                    ] as $metric)
                        <article class="flex min-w-0 items-center gap-2.5 rounded-xl border border-[var(--ak-border)] bg-[var(--ak-card)]/45 px-2.5 py-2">
                            <span class="grid h-8 w-8 shrink-0 place-items-center rounded-lg border border-[var(--ak-border)] bg-white/[.025] {{ $metric['tone'] }}"><x-dynamic-component :component="$metric['icon']" class="h-4 w-4" /></span>
                            <span class="min-w-0">
                                <b class="block text-lg font-black leading-none tabular-nums text-[var(--ak-text)]">{{ number_format($metric['value'], 0, ',', '.') }}</b>
                                <small class="mt-1 block truncate text-[7px] font-black uppercase tracking-[.1em] text-[var(--ak-muted)]">{{ $metric['label'] }}</small>
                            </span>
                        </article>
                    @endforeach
                </div>
            </header>

            @error('models')<p class="mb-3 rounded-lg border border-rose-400/30 bg-rose-400/10 px-3 py-2 text-xs font-bold text-rose-600">{{ $message }}</p>@enderror

            @php
                $activeFilterCount = collect(request()->except(['page', 'sort', 'direction']))
                    ->filter(fn ($value) => $value !== null && $value !== '')
                    ->count();
            @endphp
            <section class="ak-card mb-4 p-2" style="min-height:0" x-data="{ open: {{ $activeFilterCount > 0 ? 'true' : 'false' }} }">
                <button type="button" class="flex w-full items-center justify-between gap-3 text-left" @click="open = !open" :aria-expanded="open.toString()">
                    <span class="flex min-w-0 items-center gap-2">
                        <span class="grid h-7 w-7 shrink-0 place-items-center rounded-lg border border-cyan-400/25 bg-cyan-400/[.07] text-cyan-400"><x-heroicon-o-funnel class="h-3.5 w-3.5" /></span>
                        <b class="text-[10px] font-black uppercase tracking-[.12em] text-[var(--ak-text)]">{{ __('Filter') }}</b>
                        @if($activeFilterCount > 0)
                            <small class="rounded-md border border-cyan-400/25 bg-cyan-400/[.07] px-1.5 py-0.5 text-[7px] font-black text-cyan-400">{{ $activeFilterCount }}</small>
                        @endif
                    </span>
                    <span class="grid h-7 w-7 shrink-0 place-items-center rounded-lg border border-[var(--ak-border)] text-cyan-400 transition" :class="open ? 'rotate-180 bg-cyan-400/[.08]' : ''"><x-heroicon-o-chevron-down class="h-3.5 w-3.5" /></span>
                </button>
                <form method="GET" x-cloak x-show="open" x-transition x-data="{ submitting: false }" @submit="submitting = true" @change="if ($event.target.matches('select')) $el.requestSubmit()" class="mt-2 grid gap-2 border-t border-[var(--ak-border)] pt-2 md:grid-cols-3 xl:grid-cols-11">
                    <input name="q" value="{{ request('q') }}" @input.debounce.450ms="$el.form.requestSubmit()" placeholder="Name, Symbol oder ISIN" class="ak-input xl:col-span-2" />
                    <select name="country" class="ak-input"><option value="">Alle Länder</option>@foreach($countries as $item)<option value="{{ $item }}" @selected(request('country') === $item)>{{ $item }}</option>@endforeach</select>
                    <select name="exchange" class="ak-input"><option value="">Alle Börsen</option>@foreach($exchanges as $item)<option value="{{ $item }}" @selected(request('exchange') === $item)>{{ $item }}</option>@endforeach</select>
                    <select name="sector" class="ak-input"><option value="">Alle Sektoren</option>@foreach($sectors as $item)<option value="{{ $item }}" @selected(request('sector') === $item)>{{ __($item) }}</option>@endforeach</select>
                    <select name="quality" class="ak-input">
                        <option value="">Alle Qualitätsklassen</option>
                        @foreach(['quality' => 'Quality', 'solid' => 'Solid', 'basic' => 'Basic', 'underperform' => 'Nicht qualifiziert', 'open' => 'Offen'] as $key => $label)
                            <option value="{{ $key }}" @selected(request('quality') === $key)>{{ $label }} ({{ $summary->quality_counts[$key] ?? 0 }})</option>
                        @endforeach
                    </select>
                    <select name="status" class="ak-input">
                        <option value="">Alle Zustände</option>
                        <option value="eligible" @selected(request('status') === 'eligible')>Prediction freigegeben</option>
                        <option value="blocked" @selected(request('status') === 'blocked')>Prediction gesperrt</option>
                        <option value="published" @selected(request('status') === 'published')>Prediction vorhanden</option>
                        <option value="waiting" @selected(request('status') === 'waiting')>Noch keine Prediction</option>
                    </select>
                    <select name="variant" class="ak-input">
                        <option value="">{{ __('Standard und TCN') }}</option>
                        <option value="standard" @selected(request('variant') === 'standard')>{{ __('Nur Standard') }}</option>
                        <option value="pure_tcn" @selected(request('variant') === 'pure_tcn')>{{ __('Nur TCN') }}</option>
                    </select>
                    <select name="panel" class="ak-input">
                        <option value="">{{ __('Alle Panel-Dezile') }}</option>
                        @foreach(range(10, 1) as $decile)
                            <option value="{{ $decile }}" @selected(request('panel') == $decile)>Panel D{{ $decile }}</option>
                        @endforeach
                    </select>
                    <select name="horizon" class="ak-input">
                        <option value="">{{ __('Alle Horizonte') }}</option>
                        @foreach([10, 20, 40] as $days)<option value="{{ $days }}" @selected(request('horizon') == $days)>{{ $days }}T</option>@endforeach
                    </select>
                    <select name="signal" class="ak-input">
                        <option value="">{{ __('Alle Signale') }}</option>
                        @foreach(['BUY', 'WATCH', 'HOLD', 'SELL'] as $signal)
                            <option value="{{ $signal }}" @selected(strtoupper((string) request('signal')) === $signal)>{{ signal_label($signal) }}</option>
                        @endforeach
                    </select>
                    <div class="col-span-full grid grid-cols-2 gap-2 lg:grid-cols-4">
                    <label class="min-w-0 rounded-xl border border-[var(--ak-border)] bg-[var(--ak-surface-muted)]/45 px-3 py-2" x-data="{ value: {{ (float) request('profit_per_trade_min', $metricRanges->profit_per_trade->min) }} }">
                        <span class="block text-[8px] font-black uppercase tracking-[.08em] text-[var(--ak-muted)]">{{ __('Walk Forward · Median pro Trade ab') }}</span>
                        <div class="relative mt-2 h-2.5 rounded-full border border-slate-400/25 shadow-inner" style="background:linear-gradient(90deg,rgba(244,63,94,.30),rgba(251,191,36,.27) 50%,rgba(16,185,129,.32))">
                            <span class="pointer-events-none absolute top-1/2 h-4 w-4 -translate-x-1/2 -translate-y-1/2 rounded-full border-2 border-white bg-cyan-500 shadow-[0_1px_5px_rgba(15,23,42,.35)]" :style="`left:${Math.max(0,Math.min(100,((value-({{ $metricRanges->profit_per_trade->min }}))/(({{ $metricRanges->profit_per_trade->max }})-({{ $metricRanges->profit_per_trade->min }})))*100))}%`"></span>
                            <input type="range" name="profit_per_trade_min" value="{{ request('profit_per_trade_min', $metricRanges->profit_per_trade->min) }}" @input="value = Number($event.target.value)" @change="$el.form.requestSubmit()" min="{{ $metricRanges->profit_per_trade->min }}" max="{{ $metricRanges->profit_per_trade->max }}" step="{{ $metricRanges->profit_per_trade->step }}" class="absolute inset-0 z-10 h-full w-full cursor-pointer opacity-0" />
                        </div>
                        <span class="mt-1 flex justify-between text-[7px] tabular-nums text-[var(--ak-muted)]"><span>{{ number_format($metricRanges->profit_per_trade->min, 1, ',', '.') }} %</span><span>{{ number_format($metricRanges->profit_per_trade->max, 1, ',', '.') }} %</span></span>
                    </label>
                    <label class="min-w-0 rounded-xl border border-[var(--ak-border)] bg-[var(--ak-surface-muted)]/45 px-3 py-2" x-data="{ value: {{ (float) request('drawdown_max', $metricRanges->drawdown->max) }} }">
                        <span class="block text-[8px] font-black uppercase tracking-[.08em] text-[var(--ak-muted)]">{{ __('Walk Forward · Drawdown bis') }}</span>
                        <div class="relative mt-2 h-2.5 rounded-full border border-slate-400/25 shadow-inner" style="background:linear-gradient(90deg,rgba(16,185,129,.32),rgba(251,191,36,.27) 50%,rgba(244,63,94,.30))">
                            <span class="pointer-events-none absolute top-1/2 h-4 w-4 -translate-x-1/2 -translate-y-1/2 rounded-full border-2 border-white bg-amber-500 shadow-[0_1px_5px_rgba(15,23,42,.35)]" :style="`left:${Math.max(0,Math.min(100,((value-{{ $metricRanges->drawdown->min }})/({{ $metricRanges->drawdown->max }}-{{ $metricRanges->drawdown->min }}))*100))}%`"></span>
                            <input type="range" name="drawdown_max" value="{{ request('drawdown_max', $metricRanges->drawdown->max) }}" @input="value = Number($event.target.value)" @change="$el.form.requestSubmit()" min="{{ $metricRanges->drawdown->min }}" max="{{ $metricRanges->drawdown->max }}" step="{{ $metricRanges->drawdown->step }}" class="absolute inset-0 z-10 h-full w-full cursor-pointer opacity-0" />
                        </div>
                        <span class="mt-1 flex justify-between text-[7px] tabular-nums text-[var(--ak-muted)]"><span>{{ number_format($metricRanges->drawdown->min, 1, ',', '.') }} %</span><span>{{ number_format($metricRanges->drawdown->max, 1, ',', '.') }} %</span></span>
                    </label>
                    <label class="min-w-0 rounded-xl border border-[var(--ak-border)] bg-[var(--ak-surface-muted)]/45 px-3 py-2" x-data="{ value: {{ (float) request('hit_rate_min', $metricRanges->hit_rate->min) }} }">
                        <span class="block text-[8px] font-black uppercase tracking-[.08em] text-[var(--ak-muted)]">{{ __('Walk Forward · Hit-Rate ab') }}</span>
                        <div class="relative mt-2 h-2.5 rounded-full border border-slate-400/25 shadow-inner" style="background:linear-gradient(90deg,rgba(244,63,94,.30),rgba(251,191,36,.27) 50%,rgba(16,185,129,.32))">
                            <span class="pointer-events-none absolute top-1/2 h-4 w-4 -translate-x-1/2 -translate-y-1/2 rounded-full border-2 border-white bg-emerald-500 shadow-[0_1px_5px_rgba(15,23,42,.35)]" :style="`left:${Math.max(0,Math.min(100,((value-{{ $metricRanges->hit_rate->min }})/({{ $metricRanges->hit_rate->max }}-{{ $metricRanges->hit_rate->min }}))*100))}%`"></span>
                            <input type="range" name="hit_rate_min" value="{{ request('hit_rate_min', $metricRanges->hit_rate->min) }}" @input="value = Number($event.target.value)" @change="$el.form.requestSubmit()" min="{{ $metricRanges->hit_rate->min }}" max="{{ $metricRanges->hit_rate->max }}" step="{{ $metricRanges->hit_rate->step }}" class="absolute inset-0 z-10 h-full w-full cursor-pointer opacity-0" />
                        </div>
                        <span class="mt-1 flex justify-between text-[7px] tabular-nums text-[var(--ak-muted)]"><span>{{ number_format($metricRanges->hit_rate->min, 1, ',', '.') }} %</span><span>{{ number_format($metricRanges->hit_rate->max, 1, ',', '.') }} %</span></span>
                    </label>
                    <label class="min-w-0 rounded-xl border border-[var(--ak-border)] bg-[var(--ak-surface-muted)]/45 px-3 py-2" x-data="{ value: {{ (int) request('minimum_trades', $metricRanges->trades->min) }} }">
                        <span class="block text-[8px] font-black uppercase tracking-[.08em] text-[var(--ak-muted)]">{{ __('Walk Forward · Anzahl Trades ab') }}</span>
                        <div class="relative mt-2 h-2.5 rounded-full border border-slate-400/25 shadow-inner" style="background:linear-gradient(90deg,rgba(148,163,184,.28),rgba(6,182,212,.30),rgba(16,185,129,.34))">
                            <span class="pointer-events-none absolute top-1/2 h-4 w-4 -translate-x-1/2 -translate-y-1/2 rounded-full border-2 border-white bg-violet-500 shadow-[0_1px_5px_rgba(15,23,42,.35)]" :style="`left:${Math.max(0,Math.min(100,((value-{{ $metricRanges->trades->min }})/({{ $metricRanges->trades->max }}-{{ $metricRanges->trades->min }}))*100))}%`"></span>
                            <input type="range" name="minimum_trades" value="{{ request('minimum_trades', $metricRanges->trades->min) }}" @input="value = Number($event.target.value)" @change="$el.form.requestSubmit()" min="{{ (int) $metricRanges->trades->min }}" max="{{ (int) $metricRanges->trades->max }}" step="1" class="absolute inset-0 z-10 h-full w-full cursor-pointer opacity-0" />
                        </div>
                        <span class="mt-1 flex justify-between text-[7px] tabular-nums text-[var(--ak-muted)]"><span x-text="`${Math.round(value)} Trades`">{{ number_format((int) request('minimum_trades', $metricRanges->trades->min), 0, ',', '.') }} Trades</span><span>{{ number_format((int) $metricRanges->trades->max, 0, ',', '.') }}</span></span>
                    </label>
                    </div>
                    <div class="col-span-full flex flex-nowrap items-center justify-end gap-2">
                        <span x-cloak x-show="submitting" class="mr-auto inline-flex items-center gap-1.5 text-[8px] font-black uppercase tracking-wide text-cyan-400"><span class="h-2 w-2 animate-pulse rounded-full bg-cyan-400"></span>{{ __('Filter werden aktualisiert') }}</span>
                        <button type="submit" class="inline-flex h-10 shrink-0 items-center justify-center gap-2 rounded-xl border border-cyan-400/40 bg-cyan-400/12 px-5 text-[10px] font-black uppercase tracking-[.1em] text-cyan-300 transition hover:border-cyan-300/65 hover:bg-cyan-400/20">
                            <x-heroicon-o-magnifying-glass class="h-4 w-4" />
                            <span>Suchen</span>
                        </button>
                        <a href="{{ route($indexRoute) }}" class="inline-flex h-10 shrink-0 items-center justify-center gap-2 rounded-xl border border-[var(--ak-border)] bg-[var(--ak-surface-muted)] px-4 text-[10px] font-black uppercase tracking-[.08em] text-[var(--ak-muted)] transition hover:border-slate-400/45 hover:text-[var(--ak-text)]">
                            <x-heroicon-o-arrow-path class="h-4 w-4" />
                            <span>Zurücksetzen</span>
                        </a>
                    </div>
                </form>
            </section>

            @if($strategySelectionToken)
                <form id="serving-model-selection-form" method="POST" action="{{ route('setup.models.strategy') }}">
                    @csrf
                    <input type="hidden" name="selection_token" value="{{ $strategySelectionToken }}">
            @endif
            <section class="ak-card overflow-hidden border-cyan-400/25">
                <div class="serving-model-scroll overflow-auto" style="max-height:75vh">
                    <table class="serving-model-table w-full table-fixed border-collapse text-left" style="min-width:1076px">
                        <colgroup>
                            <col style="width:140px">
                            <col style="width:96px">
                            @foreach(range(1, 6) as $modelColumn)<col style="width:140px">@endforeach
                        </colgroup>
                        <thead class="bg-cyan-400/[.055] text-[9px] font-black uppercase tracking-[.12em] text-[var(--ak-muted)]">
                            @php
                                $sortUrl = fn(string $column) => route($indexRoute, array_merge(request()->except('page'), [
                                    'sort' => $column,
                                    'direction' => $sort === $column && $direction === 'asc' ? 'desc' : 'asc',
                                ]));
                            @endphp
                            <tr>
                                <th class="px-3 py-3"><a href="{{ $sortUrl('stock') }}">Aktie {{ $sort === 'stock' ? ($direction === 'asc' ? '↑' : '↓') : '↕' }}</a></th>
                                <th class="px-2 py-3"><a href="{{ $sortUrl('quality') }}">Qualität {{ $sort === 'quality' ? ($direction === 'asc' ? '↑' : '↓') : '↕' }}</a></th>
                                @foreach([10,20,40] as $horizon)
                                    <th class="px-2 py-3 leading-4">{{ $horizon }}T · Standard</th>
                                    <th class="px-2 py-3 leading-4">{{ $horizon }}T · TCN</th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-[var(--ak-border)]">
                        @forelse($stocks as $stock)
                            @php
                                $qualityTone = match($stock->quality_class) {
                                    'quality' => 'border-emerald-400/40 text-emerald-400',
                                    'solid' => 'border-cyan-400/40 text-cyan-400',
                                    'basic' => 'border-amber-400/40 text-amber-400',
                                    'underperform' => 'border-rose-400/40 text-rose-400',
                                    default => 'border-slate-400/30 text-slate-400',
                                };
                            @endphp
                            <tr class="align-top text-xs text-[var(--ak-text)] transition hover:bg-slate-400/[.035]">
                                <td class="px-3 py-3">
                                    <div class="flex max-w-full items-center gap-2 font-black">
                                        <span class="shrink-0 text-base leading-none" aria-label="{{ $stock->country_code ?: __('Land') }}" title="{{ $stock->country_code ?: __('Land') }}">{{ \App\Support\CountryFlag::emoji($stock->country_code) }}</span>
                                        <span class="min-w-0 truncate">{{ $stock->name ?: $stock->symbol }}</span>
                                    </div>
                                    <small class="mt-1 block text-[9px] text-[var(--ak-muted)]">{{ $stock->country_code }} · {{ $stock->exchange }} · {{ $stock->symbol }}</small>
                                    <small class="block text-[8px] text-[var(--ak-muted)]">{{ $stock->sector_code ?: '—' }}</small>
                                    <a href="{{ route('stocks.models', ['symbol' => $stock->symbol]) }}" class="mt-2 inline-flex items-center gap-1 rounded-md border border-slate-400/40 bg-slate-400/[.06] px-2 py-1 text-[8px] font-black uppercase tracking-wide text-slate-600 transition hover:border-slate-500 hover:bg-slate-400/[.12] hover:text-slate-800">
                                        {{ __('Details') }} <span aria-hidden="true">→</span>
                                    </a>
                                </td>
                                <td class="px-2 py-3">
                                    <span class="inline-flex rounded-md border px-2 py-1 text-[9px] font-black uppercase {{ $qualityTone }}">{{ $stock->quality_class === 'underperform' ? 'Nicht qual.' : $stock->quality_class }}</span>
                                    <small class="mt-2 block text-[8px] text-[var(--ak-muted)]">{{ $stock->eligible_horizon_count }}/{{ count($stock->completed_horizons) }} Horizonte freigegeben</small>
                                </td>
                                @foreach([10,20,40] as $horizon)
                                @foreach(['standard', 'pure_tcn'] as $variantKey)
                                    @php
                                        $scope = $stock->horizons->get($horizon);
                                        $model = $scope?->model_variants?->get($variantKey);
                                        $modelIsActive = (bool) ($model?->is_active ?? false);
                                        $matchesModelFilter = $model?->matches_selection_filter ?? true;
                                        $modelCellTone = $modelFilterActive && ! $matchesModelFilter
                                            ? 'is-filtered-out'
                                            : '';
                                        $modelSelectable = $model
                                            && $matchesModelFilter
                                            && ($model->training_selectable ?? false);
                                        $modelSelectionKey = $model
                                            ? strtoupper((string) $stock->symbol).'|'.$horizon.'|'.$model->variant
                                            : null;
                                        $modelSelectionFrame = $model && $model->strategy_selectable
                                            ? (($modelSelectable && $initialSelectedModelKeys->contains($modelSelectionKey)) ? 'is-model-selected' : 'is-model-unselected')
                                            : '';
                                    @endphp
                                    <td data-model-active="{{ $modelIsActive && ($model?->strategy_selectable ?? false) ? 'true' : 'false' }}" data-model-variant="{{ $model?->variant }}" class="serving-model-cell px-2 py-3 transition-all {{ $modelCellTone }} {{ $modelSelectionFrame }}">
                                        @if($model)
                                            <div class="flex items-center gap-2">
                                                @if($modelSelectable)
                                                    <label class="inline-flex shrink-0 cursor-pointer items-center">
                                                        <input
                                                            type="checkbox"
                                                            name="models[]"
                                                            value="{{ $modelSelectionKey }}"
                                                            data-serving-model-checkbox
                                                            data-serving-select-all-eligible="true"
                                                            class="serving-model-checkbox h-5 w-5 shrink-0 cursor-pointer"
                                                            @checked($initialSelectedModelKeys->contains($modelSelectionKey))
                                                            aria-label="{{ __('Modell auswählen') }}: {{ $stock->symbol }} · {{ $horizon }}T · {{ $model->variant_label }}"
                                                        >
                                                    </label>
                                                @else
                                                    <span class="inline-flex shrink-0 cursor-not-allowed items-center" title="{{ !($model->training_selectable ?? false) ? __('Dieses Modell hat im zweijährigen Trainingsfenster keine Trades.') : __('Dieses Modell entspricht nicht den aktuell gesetzten Trainingsfiltern.') }}">
                                                        <input type="checkbox" disabled class="serving-model-checkbox h-5 w-5 shrink-0 cursor-not-allowed opacity-60" aria-label="{{ __('Modell nicht für Strategieberechnung verfügbar') }}: {{ $stock->symbol }} · {{ $horizon }}T · {{ $model->variant_label }}">
                                                    </span>
                                                @endif
                                                <b class="text-[var(--ak-text)]">{{ $model->variant_label }}</b>
                                                @if($modelIsActive)
                                                    <a href="{{ route('stocks.models', ['symbol' => $stock->symbol, 'horizon' => $horizon, 'variant' => $model->variant]) }}" class="serving-model-state rounded border px-1.5 py-0.5 text-[8px] font-black uppercase {{ $model->variant === 'pure_tcn' ? 'is-active-tcn' : 'is-active-standard' }}" style="border-color:#64748b!important;background:#475569!important;color:#fff!important">{{ __('Details') }}</a>
                                                @else
                                                    <span class="serving-model-state rounded border border-slate-400/40 bg-slate-400/10 px-1.5 py-0.5 text-[8px] font-black uppercase text-slate-600">{{ __('Verfügbar') }}</span>
                                                @endif
                                            </div>
                                            @if($model->training_selectable ?? false)
                                                <small class="mt-1 block text-[9px] text-[var(--ak-muted)]" title="{{ data_get($model, 'statistics_from', '—') }}–{{ data_get($model, 'statistics_to', '—') }}"><b>{{ __('Statistik 2J') }}</b> · HR {{ is_numeric($model->metrics->hit_rate) ? number_format($model->metrics->hit_rate, 1, ',', '.').' %' : '—' }} · PF {{ is_numeric($model->metrics->profit_factor) ? number_format($model->metrics->profit_factor, 2, ',', '.') : '—' }}</small>
                                                <small class="block text-[8px] text-[var(--ak-muted)]">{{ $model->metrics->trades }} Trades · Ø {{ is_numeric($model->metrics->average_return) ? sprintf('%+.2f %%', $model->metrics->average_return) : '—' }}</small>
                                            @else
                                                <small class="mt-1 block text-[9px] font-bold text-amber-600 dark:text-amber-300">{{ __('Statistik 2J · keine Trainingstrades') }}</small>
                                            @endif
                                            @php
                                                $walkForward = $model->walk_forward_metrics ?? null;
                                                $walkForwardTrades = (int) ($walkForward?->trades ?? 0);
                                                $walkForwardTone = $walkForwardTrades === 0
                                                    ? 'text-[var(--ak-muted)]'
                                                    : (($model->walk_forward_passed ?? false) ? 'text-cyan-700 dark:text-cyan-300' : 'text-rose-600 dark:text-rose-300');
                                            @endphp
                                            <small class="mt-1 block border-t border-slate-400/20 pt-1 text-[8px] {{ $walkForwardTone }}" title="{{ data_get($model, 'walk_forward_from', '—') }}–{{ data_get($model, 'walk_forward_to', '—') }}">
                                                <b>{{ __('Walk Forward 1J') }}</b>
                                                @if($walkForwardTrades > 0)
                                                    · HR {{ is_numeric($walkForward?->hit_rate) ? number_format($walkForward->hit_rate, 1, ',', '.').' %' : '—' }} · PF {{ is_numeric($walkForward?->profit_factor) ? number_format($walkForward->profit_factor, 2, ',', '.') : '—' }}
                                                    <span class="block text-[8px]">{{ $walkForwardTrades }} Trades · Median {{ is_numeric($walkForward?->median_return) ? sprintf('%+.2f %%', $walkForward->median_return) : '—' }} · Ø {{ is_numeric($walkForward?->average_return) ? sprintf('%+.2f %%', $walkForward->average_return) : '—' }}</span>
                                                @else
                                                    · {{ __('Keine Walk-Forward-Daten') }}
                                                @endif
                                            </small>
                                        @else
                                            <span class="text-slate-500">Keine Daten</span>
                                        @endif
                                    </td>
                                @endforeach
                                @endforeach
                            </tr>
                        @empty
                            <tr><td colspan="8" class="px-6 py-14 text-center text-sm text-[var(--ak-muted)]">Keine aktiven Modelle mit diesen Filtern vorhanden.</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </section>
            @if($strategySelectionToken)
                </form>
            @endif
            @once
                <style>
                    .serving-model-scroll { -webkit-overflow-scrolling: touch; }
                    .serving-model-checkbox {
                        appearance: none !important;
                        -webkit-appearance: none !important;
                        display: inline-grid !important;
                        place-content: center;
                        border: 1px solid #94a3b8 !important;
                        border-radius: .3rem !important;
                        background: #fff !important;
                        color: #fff !important;
                        box-shadow: 0 1px 2px rgba(15, 23, 42, .08);
                    }
                    .serving-model-checkbox::before {
                        width: .68rem;
                        height: .38rem;
                        content: "";
                        border: solid currentColor;
                        border-width: 0 0 2px 2px;
                        opacity: 0;
                        transform: translateY(-.06rem) rotate(-45deg) scale(.65);
                        transition: opacity .1s ease, transform .1s ease;
                    }
                    .serving-model-checkbox:checked {
                        border-color: #0891b2 !important;
                        background: #0891b2 !important;
                        box-shadow: 0 0 0 2px rgba(6, 182, 212, .2);
                    }
                    .serving-model-checkbox:checked::before {
                        opacity: 1;
                        transform: translateY(-.06rem) rotate(-45deg) scale(1);
                    }
                    .serving-model-checkbox:indeterminate {
                        border-color: #0891b2 !important;
                        background: linear-gradient(#fff, #fff) center / .7rem 2px no-repeat #0891b2 !important;
                    }
                    .serving-model-checkbox:focus-visible {
                        outline: 2px solid rgba(6, 182, 212, .65) !important;
                        outline-offset: 2px;
                    }
                    .serving-model-checkbox:disabled {
                        border-color: #cbd5e1 !important;
                        background: #f1f5f9 !important;
                        box-shadow: none;
                    }
                    .serving-model-cell.is-model-selected {
                        outline: 2px solid rgba(16, 185, 129, .78);
                        outline-offset: -2px;
                        background: rgba(16, 185, 129, .08);
                    }
                    .serving-model-cell.is-model-unselected {
                        outline: 2px solid rgba(245, 158, 11, .68);
                        outline-offset: -2px;
                        background: rgba(245, 158, 11, .055);
                    }
                    @media (min-width: 1024px) { .serving-model-scroll { max-height: none; } }
                    /* Fixed first column + fixed header while the rest scrolls horizontally. */
                    .serving-model-table thead th,
                    .serving-model-table tbody td:first-child { background: var(--ak-surface); }
                    .serving-model-table thead th { position: sticky; top: 0; z-index: 20; box-shadow: inset 0 -1px 0 var(--ak-border); }
                    .serving-model-table :is(thead, tbody) :is(th, td):first-child { position: sticky; left: 0; box-shadow: inset -1px 0 0 var(--ak-border); }
                    .serving-model-table tbody td:first-child { z-index: 10; }
                    .serving-model-table thead th:first-child { z-index: 30; }
                </style>
            @endonce
            <div class="mt-5">{{ $stocks->links() }}</div>
            <p class="mt-3 text-[9px] text-[var(--ak-muted)]">Quelle: aktienki_serving_next · kein Fallback auf die alte Predictions-Tabelle.</p>
        </div>
    </main>
    @once
        <script>
            (() => {
                const initialiseModelSelection = () => {
                    const selectAll = document.getElementById('serving-select-all');
                    const selectAllBadge = document.getElementById('serving-select-all-badge');
                    const selectAllLabel = document.getElementById('serving-select-all-label');
                    const counter = document.getElementById('serving-selected-model-count');
                    const selectionForm = document.getElementById('serving-model-selection-form');
                    const modelCheckboxes = [...document.querySelectorAll('[data-serving-model-checkbox]')];
                    const bulkCheckboxes = modelCheckboxes.filter((checkbox) => checkbox.dataset.servingSelectAllEligible === 'true');

                    if (!selectAll || !counter || selectAll.dataset.bound === 'true') return;
                    selectAll.dataset.bound = 'true';

                    const total = Number(selectAll.dataset.total || modelCheckboxes.length);
                    const renderModelFrames = () => {
                        modelCheckboxes.forEach((checkbox) => {
                            const cell = checkbox.closest('.serving-model-cell');
                            cell?.classList.toggle('is-model-selected', checkbox.checked);
                            cell?.classList.toggle('is-model-unselected', !checkbox.checked);
                        });
                    };
                    const renderSelectAllState = () => {
                        const allSelected = selectAll.checked && !selectAll.indeterminate;
                        selectAllBadge?.classList.toggle('is-selected', allSelected);
                        if (selectAllLabel) {
                            selectAllLabel.textContent = allSelected
                                ? @js(__('Alle abwählen'))
                                : @js(__('Alle auswählen'));
                        }
                    };
                    const updateFromModels = () => {
                        const checked = modelCheckboxes.filter((checkbox) => checkbox.checked).length;
                        const checkedBulk = bulkCheckboxes.filter((checkbox) => checkbox.checked).length;
                        const allVisibleSelected = bulkCheckboxes.length > 0 && checkedBulk === bulkCheckboxes.length;
                        selectAll.checked = total > 0 && allVisibleSelected
                            && (selectAll.checked || bulkCheckboxes.length === total);
                        selectAll.indeterminate = checked > 0 && !selectAll.checked;
                        counter.textContent = String(selectAll.checked ? total : checked);
                        renderSelectAllState();
                        renderModelFrames();
                    };

                    selectAll.addEventListener('change', () => {
                        modelCheckboxes.forEach((checkbox) => {
                            checkbox.checked = selectAll.checked && checkbox.dataset.servingSelectAllEligible === 'true';
                        });
                        selectAll.indeterminate = false;
                        counter.textContent = String(selectAll.checked ? total : 0);
                        renderSelectAllState();
                        renderModelFrames();
                    });

                    modelCheckboxes.forEach((checkbox) => {
                        checkbox.addEventListener('change', updateFromModels);
                    });
                    updateFromModels();
                };

                initialiseModelSelection();
                document.addEventListener('DOMContentLoaded', initialiseModelSelection, { once: true });
                document.addEventListener('livewire:navigated', initialiseModelSelection);
            })();
        </script>
    @endonce
</x-app-layout>
