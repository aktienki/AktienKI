<x-app-layout>
    <main class="ak-body min-h-[calc(100dvh-73px)] py-5 sm:py-8">
        <div class="ak-container">
            <header class="mb-5 flex flex-col gap-4 xl:flex-row xl:items-end xl:justify-between">
                <div>
                    <p class="text-[10px] font-black uppercase tracking-[.22em] text-cyan-500">{{ __('Service Datenbank') }}</p>
                    <h1 class="mt-1 text-2xl font-black text-[var(--ak-text)] sm:text-3xl">Aktien, Modelle und Predictions</h1>
                    <p class="mt-2 max-w-4xl text-sm leading-6 text-[var(--ak-muted)]">
                        {{ __('Angezeigt werden ausschließlich aktive Releases und freigegebene Predictions der Service Datenbank. Nicht geeignete Modell-/Horizont-Kombinationen erzeugen bewusst keine Prediction.') }}
                    </p>
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
                <form method="GET" x-cloak x-show="open" x-transition x-data="{ submitting: false }" @submit="submitting = true" @change="if ($event.target.matches('select')) $el.requestSubmit()" class="mt-2 grid gap-2 border-t border-[var(--ak-border)] pt-2 md:grid-cols-3 xl:grid-cols-9">
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
                    <select name="horizon" class="ak-input">
                        <option value="">{{ __('Alle Horizonte') }}</option>
                        @foreach([10, 20, 40] as $days)<option value="{{ $days }}" @selected(request('horizon') == $days)>{{ $days }}T</option>@endforeach
                    </select>
                    <select name="signal" class="ak-input">
                        <option value="">{{ __('Alle Signale') }}</option>
                        @foreach(['BUY', 'WATCH', 'HOLD', 'SELL'] as $signal)
                            <option value="{{ $signal }}" @selected(strtoupper((string) request('signal')) === $signal)>{{ $signal }}</option>
                        @endforeach
                    </select>
                    <div class="col-span-full grid grid-cols-3 gap-2">
                    <label class="min-w-0 rounded-xl border border-[var(--ak-border)] bg-[var(--ak-surface-muted)]/45 px-3 py-2" x-data="{ value: {{ (float) request('profit_per_trade_min', $metricRanges->profit_per_trade->min) }} }">
                        <span class="block text-[8px] font-black uppercase tracking-[.08em] text-[var(--ak-muted)]">{{ __('PF pro Trade ab') }}</span>
                        <div class="relative mt-2 h-2.5 rounded-full border border-slate-400/25 shadow-inner" style="background:linear-gradient(90deg,rgba(244,63,94,.30),rgba(251,191,36,.27) 50%,rgba(16,185,129,.32))">
                            <span class="pointer-events-none absolute top-1/2 h-4 w-4 -translate-x-1/2 -translate-y-1/2 rounded-full border-2 border-white bg-cyan-500 shadow-[0_1px_5px_rgba(15,23,42,.35)]" :style="`left:${Math.max(0,Math.min(100,((value-({{ $metricRanges->profit_per_trade->min }}))/(({{ $metricRanges->profit_per_trade->max }})-({{ $metricRanges->profit_per_trade->min }})))*100))}%`"></span>
                            <input type="range" name="profit_per_trade_min" value="{{ request('profit_per_trade_min', $metricRanges->profit_per_trade->min) }}" @input="value = Number($event.target.value)" @change="$el.form.requestSubmit()" min="{{ $metricRanges->profit_per_trade->min }}" max="{{ $metricRanges->profit_per_trade->max }}" step="{{ $metricRanges->profit_per_trade->step }}" class="absolute inset-0 z-10 h-full w-full cursor-pointer opacity-0" />
                        </div>
                        <span class="mt-1 flex justify-between text-[7px] tabular-nums text-[var(--ak-muted)]"><span>{{ number_format($metricRanges->profit_per_trade->min, 1, ',', '.') }} %</span><span>{{ number_format($metricRanges->profit_per_trade->max, 1, ',', '.') }} %</span></span>
                    </label>
                    <label class="min-w-0 rounded-xl border border-[var(--ak-border)] bg-[var(--ak-surface-muted)]/45 px-3 py-2" x-data="{ value: {{ (float) request('drawdown_max', $metricRanges->drawdown->max) }} }">
                        <span class="block text-[8px] font-black uppercase tracking-[.08em] text-[var(--ak-muted)]">{{ __('Drawdown bis') }}</span>
                        <div class="relative mt-2 h-2.5 rounded-full border border-slate-400/25 shadow-inner" style="background:linear-gradient(90deg,rgba(16,185,129,.32),rgba(251,191,36,.27) 50%,rgba(244,63,94,.30))">
                            <span class="pointer-events-none absolute top-1/2 h-4 w-4 -translate-x-1/2 -translate-y-1/2 rounded-full border-2 border-white bg-amber-500 shadow-[0_1px_5px_rgba(15,23,42,.35)]" :style="`left:${Math.max(0,Math.min(100,((value-{{ $metricRanges->drawdown->min }})/({{ $metricRanges->drawdown->max }}-{{ $metricRanges->drawdown->min }}))*100))}%`"></span>
                            <input type="range" name="drawdown_max" value="{{ request('drawdown_max', $metricRanges->drawdown->max) }}" @input="value = Number($event.target.value)" @change="$el.form.requestSubmit()" min="{{ $metricRanges->drawdown->min }}" max="{{ $metricRanges->drawdown->max }}" step="{{ $metricRanges->drawdown->step }}" class="absolute inset-0 z-10 h-full w-full cursor-pointer opacity-0" />
                        </div>
                        <span class="mt-1 flex justify-between text-[7px] tabular-nums text-[var(--ak-muted)]"><span>{{ number_format($metricRanges->drawdown->min, 1, ',', '.') }} %</span><span>{{ number_format($metricRanges->drawdown->max, 1, ',', '.') }} %</span></span>
                    </label>
                    <label class="min-w-0 rounded-xl border border-[var(--ak-border)] bg-[var(--ak-surface-muted)]/45 px-3 py-2" x-data="{ value: {{ (float) request('hit_rate_min', $metricRanges->hit_rate->min) }} }">
                        <span class="block text-[8px] font-black uppercase tracking-[.08em] text-[var(--ak-muted)]">{{ __('Hit-Rate ab') }}</span>
                        <div class="relative mt-2 h-2.5 rounded-full border border-slate-400/25 shadow-inner" style="background:linear-gradient(90deg,rgba(244,63,94,.30),rgba(251,191,36,.27) 50%,rgba(16,185,129,.32))">
                            <span class="pointer-events-none absolute top-1/2 h-4 w-4 -translate-x-1/2 -translate-y-1/2 rounded-full border-2 border-white bg-emerald-500 shadow-[0_1px_5px_rgba(15,23,42,.35)]" :style="`left:${Math.max(0,Math.min(100,((value-{{ $metricRanges->hit_rate->min }})/({{ $metricRanges->hit_rate->max }}-{{ $metricRanges->hit_rate->min }}))*100))}%`"></span>
                            <input type="range" name="hit_rate_min" value="{{ request('hit_rate_min', $metricRanges->hit_rate->min) }}" @input="value = Number($event.target.value)" @change="$el.form.requestSubmit()" min="{{ $metricRanges->hit_rate->min }}" max="{{ $metricRanges->hit_rate->max }}" step="{{ $metricRanges->hit_rate->step }}" class="absolute inset-0 z-10 h-full w-full cursor-pointer opacity-0" />
                        </div>
                        <span class="mt-1 flex justify-between text-[7px] tabular-nums text-[var(--ak-muted)]"><span>{{ number_format($metricRanges->hit_rate->min, 1, ',', '.') }} %</span><span>{{ number_format($metricRanges->hit_rate->max, 1, ',', '.') }} %</span></span>
                    </label>
                    </div>
                    <div class="col-span-full flex flex-nowrap items-center justify-end gap-2">
                        <span x-cloak x-show="submitting" class="mr-auto inline-flex items-center gap-1.5 text-[8px] font-black uppercase tracking-wide text-cyan-400"><span class="h-2 w-2 animate-pulse rounded-full bg-cyan-400"></span>{{ __('Filter werden aktualisiert') }}</span>
                        <button type="submit" class="inline-flex h-10 shrink-0 items-center justify-center gap-2 rounded-xl border border-cyan-400/40 bg-cyan-400/12 px-5 text-[10px] font-black uppercase tracking-[.1em] text-cyan-300 transition hover:border-cyan-300/65 hover:bg-cyan-400/20">
                            <x-heroicon-o-magnifying-glass class="h-4 w-4" />
                            <span>Suchen</span>
                        </button>
                        <a href="{{ route('predictions.index') }}" class="inline-flex h-10 shrink-0 items-center justify-center gap-2 rounded-xl border border-[var(--ak-border)] bg-[var(--ak-surface-muted)] px-4 text-[10px] font-black uppercase tracking-[.08em] text-[var(--ak-muted)] transition hover:border-slate-400/45 hover:text-[var(--ak-text)]">
                            <x-heroicon-o-arrow-path class="h-4 w-4" />
                            <span>Zurücksetzen</span>
                        </a>
                    </div>
                </form>
            </section>

            <section class="ak-card overflow-hidden border-cyan-400/25">
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[1320px] border-collapse text-left">
                        <thead class="bg-cyan-400/[.055] text-[9px] font-black uppercase tracking-[.12em] text-[var(--ak-muted)]">
                            @php
                                $sortUrl = fn(string $column) => route('predictions.index', array_merge(request()->except('page'), [
                                    'sort' => $column,
                                    'direction' => $sort === $column && $direction === 'asc' ? 'desc' : 'asc',
                                ]));
                            @endphp
                            <tr>
                                <th class="px-4 py-3"><a href="{{ $sortUrl('stock') }}">Aktie {{ $sort === 'stock' ? ($direction === 'asc' ? '↑' : '↓') : '↕' }}</a></th>
                                <th class="px-3 py-3"><a href="{{ $sortUrl('quality') }}">Qualität {{ $sort === 'quality' ? ($direction === 'asc' ? '↑' : '↓') : '↕' }}</a></th>
                                @foreach([10,20,40] as $horizon)<th class="px-3 py-3">{{ $horizon }}T · Champion / Backtest</th>@endforeach
                                <th class="px-3 py-3"><a href="{{ $sortUrl('predictions') }}">Letzte Prediction {{ $sort === 'predictions' ? ($direction === 'asc' ? '↑' : '↓') : '↕' }}</a></th>
                                <th class="px-3 py-3"><a href="{{ $sortUrl('released') }}">Release {{ $sort === 'released' ? ($direction === 'asc' ? '↑' : '↓') : '↕' }}</a></th>
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
                            <tr
                                x-data
                                data-detail-url="{{ route('stocks.models', ['symbol' => $stock->symbol]) }}"
                                @click="if (!$event.target.closest('a, button, input, select')) window.location.href = $el.dataset.detailUrl"
                                @keydown.enter.prevent="window.location.href = $el.dataset.detailUrl"
                                role="link"
                                tabindex="0"
                                aria-label="{{ __('Modelldetails für :stock öffnen', ['stock' => $stock->name ?: $stock->symbol]) }}"
                                class="cursor-pointer align-top text-xs text-[var(--ak-text)] transition hover:bg-cyan-400/[.06] focus-visible:bg-cyan-400/[.08] focus-visible:outline-none"
                            >
                                <td class="px-4 py-3">
                                    <a href="{{ route('stocks.models', ['symbol' => $stock->symbol]) }}" class="flex max-w-64 items-center gap-2 font-black hover:text-cyan-400">
                                        <span class="shrink-0 text-base leading-none" aria-label="{{ $stock->country_code ?: __('Land') }}" title="{{ $stock->country_code ?: __('Land') }}">{{ \App\Support\CountryFlag::emoji($stock->country_code) }}</span>
                                        <span class="min-w-0 truncate">{{ $stock->name ?: $stock->symbol }}</span>
                                    </a>
                                    <small class="mt-1 block text-[9px] text-[var(--ak-muted)]">{{ $stock->country_code }} · {{ $stock->exchange }} · {{ $stock->symbol }}</small>
                                    <small class="block text-[8px] text-[var(--ak-muted)]">{{ $stock->sector_code ?: '—' }}</small>
                                </td>
                                <td class="px-3 py-3">
                                    <span class="inline-flex rounded-md border px-2 py-1 text-[9px] font-black uppercase {{ $qualityTone }}">{{ $stock->quality_class === 'underperform' ? 'Nicht qual.' : $stock->quality_class }}</span>
                                    <small class="mt-2 block text-[8px] text-[var(--ak-muted)]">{{ $stock->eligible_horizon_count }}/{{ count($stock->completed_horizons) }} Horizonte freigegeben</small>
                                </td>
                                @foreach([10,20,40] as $horizon)
                                    @php
                                        $scope = $stock->horizons->get($horizon);
                                        $hasCurrentBuy = ($scope?->prediction_enabled ?? false) && $scope?->prediction?->signal === 'BUY';
                                        $matchesModelFilter = $scope?->matches_active_filter ?? true;
                                        $modelCellTone = $modelFilterActive && ! $matchesModelFilter
                                            ? 'opacity-25 grayscale'
                                            : ($hasCurrentBuy
                                                ? 'bg-emerald-400/[.10] shadow-[inset_0_0_0_1px_rgba(52,211,153,.38)]'
                                                : ($modelFilterActive ? 'bg-cyan-400/[.09] shadow-[inset_0_0_0_1px_rgba(34,211,238,.32)]' : ''));
                                    @endphp
                                    <td class="px-3 py-3 transition-all {{ $modelCellTone }}">
                                        @if($scope)
                                            <div class="flex items-center gap-2">
                                                <b class="{{ $scope->variant === 'pure_tcn' ? 'text-violet-400' : 'text-cyan-400' }}">{{ $scope->variant_label }}</b>
                                                <span class="rounded border px-1.5 py-0.5 text-[8px] font-black uppercase {{ $scope->prediction_enabled ? 'border-emerald-400/35 text-emerald-400' : 'border-rose-400/30 text-rose-400' }}">{{ $scope->prediction_enabled ? 'aktiv' : 'gesperrt' }}</span>
                                                @if($modelFilterActive && $matchesModelFilter)
                                                    <span class="rounded border px-1.5 py-0.5 text-[7px] font-black uppercase tracking-wide {{ $hasCurrentBuy ? 'border-emerald-400/50 bg-emerald-400/15 text-emerald-300' : 'border-cyan-400/45 bg-cyan-400/12 text-cyan-300' }}">{{ __('Treffer') }}</span>
                                                @endif
                                            </div>
                                            <small class="mt-1 block text-[9px] text-[var(--ak-muted)]">HR {{ is_numeric($scope->metrics->hit_rate) ? number_format($scope->metrics->hit_rate, 1, ',', '.').' %' : '—' }} · PF {{ is_numeric($scope->metrics->profit_factor) ? number_format($scope->metrics->profit_factor, 2, ',', '.') : '—' }}</small>
                                            <small class="block text-[8px] text-[var(--ak-muted)]">{{ $scope->metrics->trades }} Trades · Ø {{ is_numeric($scope->metrics->average_return) ? sprintf('%+.2f %%', $scope->metrics->average_return) : '—' }}</small>
                                        @else
                                            <span class="text-[var(--ak-muted)]">—</span>
                                        @endif
                                    </td>
                                @endforeach
                                <td class="px-3 py-3">
                                    @if($stock->latest_prediction)
                                        @php $prediction = $stock->latest_prediction; @endphp
                                        <b class="{{ $prediction->signal === 'BUY' ? 'text-emerald-400' : ($prediction->signal === 'SELL' ? 'text-rose-400' : 'text-amber-400') }}">{{ $prediction->signal }} · {{ $prediction->horizon }}T</b>
                                        <small class="mt-1 block text-[9px] text-[var(--ak-muted)]">{{ is_numeric($prediction->expected_return_percent) ? sprintf('%+.2f %%', $prediction->expected_return_percent) : '—' }} · {{ \Illuminate\Support\Carbon::parse($prediction->as_of)->format('d.m.Y H:i') }}</small>
                                    @else
                                        <b class="text-[var(--ak-muted)]">Keine Prediction</b>
                                        <small class="mt-1 block max-w-48 text-[8px] leading-4 text-[var(--ak-muted)]">Kein freigegebener Horizont oder noch kein neuer Prediction-Lauf.</small>
                                    @endif
                                </td>
                                <td class="px-3 py-3">
                                    <b class="block text-[10px]">{{ $stock->pipeline_version }}</b>
                                    <small class="mt-1 block text-[8px] text-[var(--ak-muted)]">{{ \Illuminate\Support\Carbon::parse($stock->released_at)->format('d.m.Y H:i') }}</small>
                                    <small class="block text-[8px] text-[var(--ak-muted)]">Cutoff {{ \Illuminate\Support\Carbon::parse($stock->dataset_cutoff)->format('d.m.Y') }}</small>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="px-6 py-14 text-center text-sm text-[var(--ak-muted)]">Keine aktiven Releases mit diesen Filtern vorhanden.</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </section>
            <div class="mt-5">{{ $stocks->links() }}</div>
            <p class="mt-3 text-[9px] text-[var(--ak-muted)]">Quelle: aktienki_serving_next · kein Fallback auf die alte Predictions-Tabelle.</p>
        </div>
    </main>
</x-app-layout>
