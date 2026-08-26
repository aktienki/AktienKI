<x-app-layout>
    @php
        $countryFlag = fn (?string $country): string => strlen((string) $country) === 2
            ? mb_chr(127397 + ord(strtoupper($country[0]))) . mb_chr(127397 + ord(strtoupper($country[1])))
            : '🌐';
    @endphp
    <div id="predictions-page" class="flex h-[calc(100dvh-89px)] min-h-0 max-h-[calc(100dvh-89px)] flex-col overflow-hidden py-2 text-[var(--ak-text)]">
        <div id="prediction-page-loading" class="pointer-events-none fixed inset-0 z-[9999] hidden items-center justify-center bg-slate-950/55 p-4 backdrop-blur-[3px]" aria-live="polite">
            <div class="flex flex-col items-center gap-3 text-xs font-black text-amber-300 drop-shadow-lg">
                <svg class="prediction-loading-spinner h-10 w-10" viewBox="0 0 40 40" aria-hidden="true"><circle cx="20" cy="20" r="16" fill="none" stroke="rgba(251,191,36,.22)" stroke-width="3"/><path d="M36 20a16 16 0 0 0-16-16" fill="none" stroke="#fbbf24" stroke-width="3.5" stroke-linecap="round"/></svg>
                <span>{{ __('Prognosen werden aktualisiert …') }}</span>
            </div>
        </div>
        <div class="mb-2 flex shrink-0 flex-col gap-2 xl:flex-row xl:items-center xl:justify-between">
            <div class="flex items-center gap-2.5">
                <div class="prediction-page-icon flex h-9 w-9 items-center justify-center rounded-xl border border-amber-300/25 bg-amber-300/[.08] text-amber-300 shadow-[0_0_18px_rgba(245,158,11,.07)]">
                    <x-heroicon-o-chart-bar class="h-5 w-5" />
                </div>
                <div>
                    <h1 class="text-xl font-bold tracking-tight">{{ __('Prognosen') }}</h1>
                    <p class="mt-0.5 text-xs text-[var(--ak-muted)]">{{ __('Historische KI-Prognosen, Modellwerte und Validierungsergebnisse.') }}</p>
                </div>
            </div>

            <div class="grid w-full grid-cols-4 gap-2 xl:w-auto">
                @foreach ([
                    [__('Prognosen'), (int) ($summary->total ?? 0), 'xl:min-w-28'],
                    [__('Aktien'), (int) ($summary->instruments ?? 0), 'xl:min-w-28'],
                    [__('Validiert'), (int) ($summary->validated ?? 0), 'xl:min-w-28'],
                    [__('Letzte Prognose'), $summary?->latest_prediction ? \Illuminate\Support\Carbon::parse($summary->latest_prediction)->timezone(config('app.timezone'))->format('d.m.Y H:i') : '—', 'xl:min-w-40'],
                ] as [$label, $value, $widthClass])
                    <div class="ak-predictions-card-surface min-w-0 rounded-lg px-2 py-1.5 sm:px-2.5 {{ $widthClass }}">
                        <p class="truncate text-[8px] font-black uppercase tracking-[.08em] text-[var(--ak-muted)] sm:text-[9px] sm:tracking-[.12em]">{{ $label }}</p>
                        <p class="mt-1 truncate whitespace-nowrap text-xs font-black tabular-nums sm:text-sm">{{ is_int($value) ? number_format($value, 0, ',', '.') : $value }}</p>
                    </div>
                @endforeach
            </div>
        </div>

        <section x-data="{ saveFilterOpen: false, filtersOpen: false }" class="flex min-h-0 flex-1 flex-col gap-3">
            <button type="button" @click="filtersOpen = ! filtersOpen" :aria-expanded="filtersOpen" class="ak-prediction-filter-toggle flex h-10 shrink-0 items-center justify-between rounded-xl border border-cyan-400/25 bg-transparent px-4 text-xs font-black text-cyan-300">
                <span class="inline-flex items-center gap-2"><x-heroicon-o-adjustments-horizontal class="h-4 w-4" />{{ __('Filter anzeigen') }}</span>
                <x-heroicon-o-chevron-down class="h-4 w-4 transition" x-bind:class="filtersOpen && 'rotate-180'" />
            </button>
            <form
                id="prediction-filterboard"
                onsubmit="event.preventDefault(); const l=document.getElementById('prediction-page-loading'); if(l){l.classList.remove('hidden');l.classList.add('flex');l.style.display='flex';} const f=this; setTimeout(() => HTMLFormElement.prototype.submit.call(f), 500);"
                method="GET"
                action="{{ route('predictions.index') }}"
                x-data="{
                    score: Number({{ (float) request('score_min', 0) }}),
                    confidence: Number({{ (float) request('confidence_min', 0) }}),
                    drawdown: Number({{ (float) request('drawdown_max', 50) }}),
                    profitFactor: Number({{ (float) request('profit_factor_min', 0) }}),
                    hitRate: Number({{ (float) request('hit_rate_min', 0) }}),
                    volatility: Number({{ (float) request('volatility_max', 100) }})
                }"
                x-show="filtersOpen"
                x-cloak
                class="ak-prediction-filterboard z-50 flex shrink-0 flex-col gap-1 rounded-xl border border-[var(--ak-border)] bg-[var(--ak-card)] p-2 shadow-[var(--ak-shadow)]"
            >
                <input type="hidden" name="sort" value="{{ $sort }}">
                <input type="hidden" name="direction" value="{{ $direction }}">
                <div class="ak-prediction-filterboard-main grid min-w-[1040px] items-center gap-1" style="grid-template-columns:minmax(150px,1.35fr) repeat(5,minmax(105px,1fr)) 286px">
                <label class="relative min-w-0">
                    <span class="sr-only">{{ __('Aktie suchen') }}</span>
                    <x-heroicon-o-magnifying-glass class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-[var(--ak-muted)]" />
                    <input name="q" value="{{ request('q') }}" placeholder="{{ __('Aktie / Symbol') }}" class="ak-input h-10 w-full pl-8 pr-2 text-xs">
                </label>
                <select name="country" class="ak-input h-10 w-full min-w-0 px-1.5 text-[11px]" title="{{ __('Land') }}">
                    <option value="">{{ __('Land') }}</option>
                    @foreach ($countries as $country)
                        <option value="{{ $country }}" @selected(strtoupper((string) request('country')) === strtoupper((string) $country))>{{ $country }}</option>
                    @endforeach
                </select>
                <select name="exchange" class="ak-input h-10 w-full min-w-0 px-1.5 text-[11px]" title="{{ __('Exchange') }}">
                    <option value="">{{ __('Exchange') }}</option>
                    @foreach ($exchanges as $exchange)
                        <option value="{{ $exchange->code }}" @selected(strtoupper((string) request('exchange')) === strtoupper((string) $exchange->code))>{{ $exchange->name ?: $exchange->code }}</option>
                    @endforeach
                </select>
                <select name="sector" class="ak-input h-10 w-full min-w-0 px-1.5 text-[11px]" title="{{ __('Sektor') }}">
                    <option value="">{{ __('Sektor') }}</option>
                    @foreach ($sectors as $sector)
                        <option value="{{ $sector }}" @selected((string) request('sector') === (string) $sector)>{{ __($sector) }}</option>
                    @endforeach
                </select>
                <select name="quality_tier" class="ak-input h-10 w-full min-w-0 px-1.5 text-[11px]" title="{{ __('Modellstufe mindestens') }}">
                    <option value="">{{ __('Modellstufe') }}</option>
                    @foreach ($qualityTiers as $qualityTier)
                        <option value="{{ $qualityTier->code }}" @selected(request('quality_tier') === $qualityTier->code)>{{ __($qualityTier->name) }}</option>
                    @endforeach
                </select>
                <select name="signal" class="ak-input h-10 w-full min-w-0 px-1.5 text-[11px]" title="{{ __('Signal') }}">
                    <option value="">{{ __('Signal') }}</option>
                    @foreach (['BUY', 'WAIT', 'WATCH', 'HOLD', 'SELL'] as $signal)
                        @continue(! $signals->contains($signal))
                        <option value="{{ $signal }}" @selected(strtoupper((string) request('signal')) === $signal)>{{ $signal }}</option>
                    @endforeach
                </select>
                <div class="flex min-w-0 gap-1">
                    @if ($canUseSmartLabels)
                        @php
                            $smartLabelSymbols = [
                                'sparkles' => '✨',
                                'bolt' => '⚡',
                                'trophy' => '🏆',
                                'shield-check' => '🛡',
                                'chart-bar' => '▥',
                                'rocket-launch' => '🚀',
                            ];
                        @endphp
                        <select name="smart_label" class="ak-input h-10 min-w-0 flex-1 rounded-lg px-2 text-[9px] font-bold" title="{{ __('Smart Selection Label') }}">
                            <option value="">{{ __('Label') }}</option>
                            @foreach ($smartLabels as $smartLabel)
                                <option value="{{ $smartLabel->id }}" @selected((int) request('smart_label') === (int) $smartLabel->id)>{{ $smartLabelSymbols[$smartLabel->icon ?: 'sparkles'] ?? '✨' }} {{ $smartLabel->name }}</option>
                            @endforeach
                        </select>
                    @else
                        <span class="ak-input inline-flex h-10 min-w-0 flex-1 items-center rounded-lg px-2 text-[9px] font-bold text-[var(--ak-muted)]">{{ __('Label') }}</span>
                    @endif
                </div>
                </div>
                <div class="ak-prediction-range-grid grid grid-cols-6 gap-1">
                    <label class="ak-heatmap-range">
                        <span>{{ __('KI-Score') }} ≥ <b x-text="score.toFixed(1).replace('.', ',')"></b></span>
                        <input name="score_min" type="range" min="0" max="10" step="0.5" x-model.number="score">
                    </label>
                    <label class="ak-heatmap-range">
                        <span>{{ __('Konfidenz') }} ≥ <b x-text="`${confidence}%`"></b></span>
                        <input name="confidence_min" type="range" min="0" max="100" step="5" x-model.number="confidence">
                    </label>
                    <label class="ak-heatmap-range">
                        <span>{{ __('Drawdown') }} ≤ <b x-text="drawdown >= 50 ? '{{ __('Alle') }}' : `${drawdown}%`"></b></span>
                        <input name="drawdown_max" type="range" min="0" max="50" step="5" x-model.number="drawdown">
                    </label>
                    <label class="ak-heatmap-range">
                        <span>{{ __('Profitfaktor') }} ≥ <b x-text="profitFactor <= 0 ? '{{ __('Alle') }}' : profitFactor.toFixed(1).replace('.', ',')"></b></span>
                        <input name="profit_factor_min" type="range" min="0" max="3" step="0.1" x-model.number="profitFactor">
                    </label>
                    <label class="ak-heatmap-range">
                        <span>{{ __('Trefferquote') }} ≥ <b x-text="hitRate <= 0 ? '{{ __('Alle') }}' : `${hitRate}%`"></b></span>
                        <input name="hit_rate_min" type="range" min="0" max="100" step="5" x-model.number="hitRate">
                    </label>
                    <label class="ak-heatmap-range">
                        <span>{{ __('Volatilität') }} ≤ <b x-text="volatility >= 100 ? '{{ __('Alle') }}' : `${volatility}%`"></b></span>
                        <input name="volatility_max" type="range" min="0" max="100" step="5" x-model.number="volatility">
                    </label>
                </div>
                <div class="ak-prediction-filter-actions flex justify-end gap-2">
                    <button type="submit" class="inline-flex h-9 items-center justify-center gap-2 rounded-lg bg-cyan-500 px-4 text-xs font-black text-white shadow-[0_8px_20px_rgba(6,182,212,.18)] transition hover:bg-cyan-400" style="color:#fff">
                        <x-heroicon-o-magnifying-glass class="h-4 w-4" />{{ __('Suchen') }}
                    </button>
                    <a href="{{ route('predictions.index') }}" onclick="event.preventDefault(); const l=document.getElementById('prediction-page-loading'); if(l){l.classList.remove('hidden');l.classList.add('flex');l.style.display='flex';} const h=this.href; setTimeout(function(){window.location.href=h;},500);" class="inline-flex h-9 items-center justify-center gap-2 rounded-lg border border-amber-300/55 bg-amber-400/[.16] px-4 text-xs font-black text-amber-700 shadow-[0_8px_20px_rgba(245,158,11,.12)] transition hover:border-amber-200 hover:bg-amber-400/[.28] dark:text-amber-200" title="{{ __('Filter zurücksetzen') }}">
                        <x-heroicon-o-arrow-path class="h-4 w-4 shrink-0" /><span>{{ __('Reset') }}</span>
                    </a>
                </div>
            </form>

            <template x-teleport="body">
                <div x-show="saveFilterOpen" x-cloak class="fixed inset-0 z-[9999] flex items-center justify-center bg-slate-950/55 p-4 backdrop-blur-sm" @keydown.escape.window="saveFilterOpen = false">
                    <form method="POST" action="{{ route('predictions.filters.store') }}" class="w-full max-w-md rounded-2xl border border-teal-500/25 bg-[var(--ak-card-strong)] p-5 shadow-2xl" @click.outside="saveFilterOpen = false">
                        @csrf
                        @foreach (['q', 'country', 'exchange', 'sector', 'smart_label', 'quality_tier', 'signal', 'score_min', 'confidence_min', 'drawdown_max', 'profit_factor_min', 'hit_rate_min', 'volatility_max', 'sort', 'direction'] as $filterKey)
                            <input type="hidden" name="{{ $filterKey }}" value="{{ request($filterKey) }}">
                        @endforeach
                        @foreach ((array) request('symbols', []) as $symbol)<input type="hidden" name="symbols[]" value="{{ $symbol }}">@endforeach
                        <div class="flex items-start justify-between gap-4">
                            <div>
                                <p class="text-[9px] font-black uppercase tracking-[.16em] text-teal-600">{{ __('Filter speichern') }}</p>
                                <h2 class="mt-1 text-lg font-black text-[var(--ak-text)]">{{ __('Aktuelle Auswahl merken') }}</h2>
                                <p class="mt-1 text-xs text-[var(--ak-muted)]">{{ __('Alle Dropdown- und Range-Filter werden gemeinsam gespeichert.') }}</p>
                            </div>
                            <button type="button" @click="saveFilterOpen = false" class="grid h-8 w-8 shrink-0 place-items-center rounded-lg border border-[var(--ak-border)] text-[var(--ak-muted)] hover:text-[var(--ak-text)]"><x-heroicon-o-x-mark class="h-4 w-4" /></button>
                        </div>
                        <label class="mt-5 block text-[9px] font-black uppercase tracking-wide text-[var(--ak-muted)]">
                            {{ __('Name') }}
                            <input name="name" maxlength="80" required autofocus class="ak-input mt-2 h-11 w-full rounded-lg px-3 text-sm font-bold" placeholder="{{ __('z. B. Europa · hoher KI-Score') }}">
                        </label>
                        <div class="mt-5 flex justify-end gap-2">
                            <button type="button" @click="saveFilterOpen = false" class="inline-flex h-10 items-center rounded-lg border border-[var(--ak-border)] px-4 text-xs font-bold text-[var(--ak-muted)]">{{ __('Abbrechen') }}</button>
                            <button type="submit" class="inline-flex h-10 items-center gap-2 rounded-lg bg-teal-700 px-4 text-xs font-black text-white hover:bg-teal-600"><x-heroicon-o-bookmark class="h-4 w-4" />{{ __('Filter speichern') }}</button>
                        </div>
                    </form>
                </div>
            </template>

            <div id="predictions-table-scroll" class="min-h-0 flex-1 touch-pan-x overscroll-contain overflow-x-auto overflow-y-auto rounded-xl border border-[var(--ak-border)] bg-[var(--ak-card)] shadow-[var(--ak-shadow)]">
                <form id="prediction-table-filters" method="GET" action="{{ route('predictions.index') }}">
                    <input type="hidden" name="sort" value="{{ $sort }}">
                    <input type="hidden" name="direction" value="{{ $direction }}">
                </form>
                @php
                    $sortUrl = fn (string $column): string => route('predictions.index', array_merge(
                        request()->except('page'),
                        [
                            'sort' => $column,
                            'direction' => $sort === $column && $direction === 'asc' ? 'desc' : 'asc',
                        ],
                    ));
                    $sortIndicator = fn (string $column): string => $sort === $column
                        ? ($direction === 'asc' ? '↑' : '↓')
                        : '↕';
                @endphp
                <table class="ak-stocks-table w-full min-w-0 table-fixed border-separate border-spacing-0 text-left text-[11px]" data-smart-labels="{{ $canUseSmartLabels ? '1' : '0' }}">
                    <colgroup>
                        <col class="ak-mobile-hidden-column" style="width:34px">
                        <col style="width:38px">
                        <col style="width:160px">
                        <col style="width:45px">
                        <col class="ak-mobile-hidden-column" style="width:60px">
                        <col style="width:90px">
                        <col style="width:50px">
                        <col style="width:70px">
                        <col style="width:160px">
                        <col style="width:95px">
                        <col style="width:135px">
                        <col style="width:70px">
                        <col style="width:253px">
                    </colgroup>
                    <thead class="sticky top-0 z-20 bg-[#12343b] text-[10px] font-black uppercase tracking-[.1em] text-slate-300 shadow-[0_1px_0_rgba(34, 211, 238,.20),0_8px_18px_rgba(0,0,0,.22)]">
                        <tr class="ak-predictions-heading-row h-11">
                            <th class="ak-mobile-hidden-column border-b border-[var(--ak-border)] px-3 py-3 text-center" aria-label="{{ __('Watchlist') }}">
                                <x-heroicon-o-star class="mx-auto h-4 w-4 text-[var(--ak-muted)]" />
                            </th>
                            <th class="border-b border-[var(--ak-border)] px-1.5 py-3 text-left">{{ __('Label') }}</th>
                            @foreach ([
                                ['stock', __('Name'), 'text-left'],
                                [null, __('Land'), 'text-center'],
                                [null, __('Börse'), 'text-left'],
                                [null, __('Übergang'), 'text-center'],
                                ['signal', __('Signal'), 'text-center'],
                                ['score', __('KI-Score'), 'text-center'],
                                [null, __('Prognosen'), 'text-center'],
                                ['price', __('Kurs'), 'text-right'],
                                [null, __('52-Wochen-Spanne'), 'text-left'],
                                [null, __('Chart'), 'text-center'],
                                [null, __('Technische Indikatoren'), 'text-left'],
                            ] as $headingDefinition)
                                @php
                                    [$column, $heading, $alignment, $headingColspan] = array_pad($headingDefinition, 4, 1);
                                @endphp
                                <th colspan="{{ $headingColspan }}" class="border-b border-[var(--ak-border)] px-2 py-3 {{ $alignment }} {{ $heading === __('Börse') ? 'ak-mobile-hidden-column' : '' }} {{ $heading === __('Prognosehorizonte') ? 'ak-horizon-column' : '' }}">
                                    @if ($column)
                                    <a href="{{ $sortUrl($column) }}" class="inline-flex max-w-full items-center gap-1 whitespace-nowrap transition hover:text-teal-200 {{ $sort === $column ? 'text-teal-200' : '' }}">
                                        <span class="truncate">{{ $heading }}</span>
                                        <span class="inline-block w-3 shrink-0 text-center text-[11px] {{ $sort === $column ? 'text-teal-200' : 'text-slate-600' }}">{{ $sortIndicator($column) }}</span>
                                    </a>
                                    @elseif ($heading === __('Prognosen'))
                                        <span class="ak-desktop-prognosis-heading truncate">{{ $heading }}</span>
                                        <a href="{{ $sortUrl('score') }}" class="ak-mobile-score-sort hidden items-center justify-center gap-1 transition hover:text-teal-200 {{ $sort === 'score' ? 'text-teal-200' : '' }}">
                                            <span>{{ __('KI-Score') }}</span>
                                            <span class="text-[11px]">{{ $sortIndicator('score') }}</span>
                                        </a>
                                    @else<span class="truncate">{{ $heading }}</span>@endif
                                </th>
                            @endforeach
                        </tr>
                        <tr class="ak-predictions-filter-row hidden bg-[#12343b]" aria-hidden="true">
                            <th colspan="13" class="border-b border-[var(--ak-border)] p-1.5 normal-case tracking-normal">
                                <div class="flex min-w-0 items-center gap-1.5 whitespace-nowrap">
                                    <a href="{{ route('predictions.index') }}" class="inline-flex h-8 w-9 shrink-0 items-center justify-center rounded-[5px] border border-[var(--ak-border)] text-[var(--ak-muted)] hover:bg-teal-500/10 hover:text-teal-500" title="{{ __('Filter zurücksetzen') }}"><x-heroicon-o-arrow-path class="h-4 w-4" /></a>
                                    <label class="relative min-w-28 flex-[1.35]"><x-heroicon-o-magnifying-glass class="pointer-events-none absolute left-2 top-1/2 h-3.5 w-3.5 -translate-y-1/2 text-[var(--ak-muted)]" /><input form="prediction-table-filters" name="q" value="{{ request('q') }}" placeholder="{{ __('Aktie') }}" class="ak-input ak-table-filter h-8 w-full min-w-0 pl-7 pr-1 text-[10px]" oninput="window.clearTimeout(this._filterTimer);this._filterTimer=window.setTimeout(()=>this.form.requestSubmit(),450)"></label>
                                    <select form="prediction-table-filters" name="country" onchange="this.form.requestSubmit()" class="ak-input ak-table-filter h-8 min-w-0 flex-1 px-1 text-[9px]" title="{{ __('Land') }}"><option value="">{{ __('Land') }}</option>@foreach ($countries as $country)<option value="{{ $country }}" @selected(strtoupper((string) request('country')) === strtoupper((string) $country))>{{ $country }}</option>@endforeach</select>
                                    <select form="prediction-table-filters" name="exchange" onchange="this.form.requestSubmit()" class="ak-input ak-table-filter h-8 min-w-0 flex-1 px-1 text-[9px]" title="{{ __('Exchange') }}"><option value="">{{ __('Börse') }}</option>@foreach ($exchanges as $exchange)<option value="{{ $exchange->code }}" @selected(strtoupper((string) request('exchange')) === strtoupper((string) $exchange->code))>{{ $exchange->name ?: $exchange->code }}</option>@endforeach</select>
                                    <select form="prediction-table-filters" name="sector" onchange="this.form.requestSubmit()" class="ak-input ak-table-filter h-8 min-w-0 flex-[1.2] px-1 text-[9px]" title="{{ __('Sektor') }}"><option value="">{{ __('Sektor') }}</option>@foreach ($sectors as $sector)<option value="{{ $sector }}" @selected((string) request('sector') === (string) $sector)>{{ __($sector) }}</option>@endforeach</select>
                                    <select form="prediction-table-filters" name="model" onchange="this.form.requestSubmit()" class="ak-input ak-table-filter h-8 min-w-0 flex-[1.2] px-1 text-[9px]" title="{{ __('Modell') }}"><option value="">{{ __('Modell') }}</option>@foreach ($models as $model)<option value="{{ $model->id }}" @selected((int) request('model') === (int) $model->id)>{{ $model->public_alias }}</option>@endforeach</select>
                                    <select form="prediction-table-filters" name="ai_type" onchange="this.form.requestSubmit()" class="ak-input ak-table-filter h-8 min-w-0 flex-1 px-1 text-[9px]" title="{{ __('KI-Typ') }}"><option value="">{{ __('KI') }}</option>@foreach ($aiTypes as $aiType)<option value="{{ $aiType }}" @selected(request('ai_type') === $aiType)>{{ ucfirst((string) $aiType) }}</option>@endforeach</select>
                                    <select form="prediction-table-filters" name="quality_tier" onchange="this.form.requestSubmit()" class="ak-input ak-table-filter h-8 min-w-0 flex-[1.15] px-1 text-[9px]" title="{{ __('Modellstufe mindestens') }}"><option value="">{{ __('Min. Stufe') }}</option>@foreach ($qualityTiers as $qualityTier)<option value="{{ $qualityTier->code }}" @selected(request('quality_tier') === $qualityTier->code)>{{ __($qualityTier->name) }}</option>@endforeach</select>
                                    <select form="prediction-table-filters" name="signal" onchange="this.form.requestSubmit()" class="ak-input ak-table-filter h-8 min-w-0 flex-1 px-1 text-[9px]" title="{{ __('Signal') }}"><option value="">{{ __('Signal') }}</option>@foreach (['BUY', 'WAIT', 'WATCH', 'HOLD', 'SELL'] as $signal)@continue(! $signals->contains($signal))<option value="{{ $signal }}" @selected(strtoupper((string) request('signal')) === $signal)>{{ $signal }}</option>@endforeach</select>
                                    <select form="prediction-table-filters" name="score_min" onchange="this.form.requestSubmit()" class="ak-input ak-table-filter h-8 min-w-0 flex-1 px-1 text-[9px]" title="{{ __('KI-Score') }}"><option value="">{{ __('KI-Score') }}</option>@foreach ([8 => '≥ 8', 7 => '≥ 7', 6 => '≥ 6', 5 => '≥ 5'] as $value => $label)<option value="{{ $value }}" @selected((string) request('score_min') === (string) $value)>{{ $label }}</option>@endforeach</select>
                                    <select form="prediction-table-filters" name="confidence_min" onchange="this.form.requestSubmit()" class="ak-input ak-table-filter h-8 min-w-0 flex-1 px-1 text-[9px]" title="{{ __('Konfidenz') }}"><option value="">{{ __('Konfidenz') }}</option>@foreach ([90, 80, 70, 60, 50] as $value)<option value="{{ $value }}" @selected((string) request('confidence_min') === (string) $value)>≥ {{ $value }} %</option>@endforeach</select>
                                </div>
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($predictions as $prediction)
                            @php
                                $signal = strtoupper((string) ($prediction->personalized_signal ?? 'HOLD'));
                                $isQualityGateRestrictedBuy = $signal === 'HOLD'
                                    && strtoupper((string) ($prediction->signal ?? '')) === 'BUY'
                                    && ! (bool) ($prediction->quality_gate_passed ?? false);
                                $signalIcon = match ($signal) {
                                    'BUY' => 'heroicon-o-arrow-trending-up',
                                    'WAIT' => 'heroicon-o-clock',
                                    'WATCH' => 'heroicon-o-eye',
                                    'SELL' => 'heroicon-o-arrow-trending-down',
                                    default => 'heroicon-o-pause',
                                };
                                $signalLabel = $signal;
                                $currency = $prediction->currency ?: 'EUR';
                                $score = is_numeric($prediction->score_10) ? max(0, min(10, (float) $prediction->score_10)) : null;
                                $scorePercent = is_numeric($prediction->score_10) ? max(0, min(100, (float) $prediction->score_10 * 10)) : null;
                                $scoreGrade = \App\Support\QualityGrade::fromPercent($scorePercent);
                                $signalStrength = \App\Support\SignalStrength::label(is_numeric($prediction->expected_return_20d ?? null) ? (float) $prediction->expected_return_20d : null);
                                $scoreStatisticsTitle = implode(' · ', [
                                    __('Profit-Faktor').': '.(is_numeric($prediction->ranking_profit_factor) ? number_format(\App\Support\ProfitFactor::cap($prediction->ranking_profit_factor), 2, ',', '.') : '—'),
                                    __('Hit-Rate').': '.(is_numeric($prediction->ranking_hit_rate) ? number_format((float) $prediction->ranking_hit_rate, 1, ',', '.').' %' : '—'),
                                    __('Drawdown').': '.(is_numeric($prediction->ranking_drawdown) ? number_format((float) $prediction->ranking_drawdown, 1, ',', '.').' %' : '—'),
                                    __('Modellqualität').': '.number_format((float) $prediction->ranking_model_quality, 1, ',', '.').' %',
                                    __('Noise-Filter').': '.($prediction->ranking_noise_passed ? __('Bestanden') : __('Nicht bestanden')),
                                    __('Stabilitätsfilter').': '.($prediction->ranking_stability_passed ? number_format((float) $prediction->ranking_stability_percent, 1, ',', '.').' %' : __('Nicht bestanden')),
                                ]);
                                // Continuous red -> orange -> green scale. Orange marks the
                                // neutral score range without the previous amber/olive cast.
                                $scoreColorDark = $scorePercent === null
                                    ? '#64748b'
                                    : sprintf('hsl(%.1f 78%% 40%%)', $scorePercent * 1.2);
                                $scoreColor = $scorePercent === null
                                    ? '#64748b'
                                    : ($scorePercent <= 50
                                        ? sprintf('color-mix(in srgb, #f97316 %.1f%%, #ef4444)', ($scorePercent / 50) * 100)
                                        : sprintf('color-mix(in srgb, #10b981 %.1f%%, #f97316)', (($scorePercent - 50) / 50) * 100));
                                $confidencePercent = is_numeric($prediction->confidence_percent) ? max(0, min(100, (float) $prediction->confidence_percent)) : null;
                                $riskPercent = \App\Support\RiskScore::toPercent($prediction->risk_percent, $prediction->ranking_drawdown ?? null);
                                $riskGrade = \App\Support\QualityGrade::riskLevel($riskPercent);
                                $hitRatePercent = is_numeric($prediction->ranking_hit_rate) ? max(0, min(100, (float) $prediction->ranking_hit_rate)) : null;
                                $profitPerTrade = is_numeric($prediction->ranking_profit_per_trade) ? (float) $prediction->ranking_profit_per_trade : null;
                                $profitPerTradePercent = $profitPerTrade === null ? null : max(0, min(100, 50 + ($profitPerTrade * 12.5)));
                                $stabilityPercent = is_numeric($prediction->ranking_stability_percent) && $prediction->ranking_stability_passed
                                    ? max(0, min(100, (float) $prediction->ranking_stability_percent))
                                    : null;
                                $confidenceColor = $confidencePercent === null
                                    ? '#64748b'
                                    : sprintf('hsl(%.1f 72%% 43%%)', $confidencePercent * 1.2);
                                $hitRateColor = $hitRatePercent === null ? '#64748b' : sprintf('hsl(%.1f 72%% 43%%)', $hitRatePercent * 1.2);
                                $profitPerTradeColor = $profitPerTradePercent === null ? '#64748b' : sprintf('hsl(%.1f 72%% 43%%)', $profitPerTradePercent * 1.2);
                                $stabilityColor = $stabilityPercent === null ? '#64748b' : sprintf('hsl(%.1f 72%% 43%%)', $stabilityPercent * 1.2);
                                $riskColorDark = match (true) {
                                    $riskPercent === null => '#64748b',
                                    $riskPercent < 30 => '#22d3ee',
                                    $riskPercent < 50 => '#eab308',
                                    default => '#ef526b',
                                };
                                $riskColor = $riskPercent === null
                                    ? '#64748b'
                                    : ($riskPercent <= 50
                                        ? sprintf('color-mix(in srgb, #f97316 %.1f%%, #10b981)', ($riskPercent / 50) * 100)
                                        : sprintf('color-mix(in srgb, #ef4444 %.1f%%, #f97316)', (($riskPercent - 50) / 50) * 100));
                                $riskTitle = implode(' · ', [
                                    __('Risiko').': '.($riskPercent !== null ? number_format($riskPercent, 1, ',', '.').' %' : '—'),
                                    __('Profit-Faktor').': '.(is_numeric($prediction->ranking_profit_factor) ? number_format(\App\Support\ProfitFactor::cap($prediction->ranking_profit_factor), 2, ',', '.') : '—'),
                                    __('Drawdown').': '.(is_numeric($prediction->ranking_drawdown) ? number_format((float) $prediction->ranking_drawdown, 1, ',', '.').' %' : '—'),
                                    __('Volatilität').': '.(is_numeric($prediction->ranking_volatility ?? null) ? number_format((float) $prediction->ranking_volatility, 1, ',', '.').' %' : '—'),
                                    __('Hit-Rate').': '.(is_numeric($prediction->ranking_hit_rate) ? number_format((float) $prediction->ranking_hit_rate, 1, ',', '.').' %' : '—'),
                                    __('Konfidenz').': '.($confidencePercent !== null ? number_format($confidencePercent, 1, ',', '.').' %' : '—'),
                                ]);
                                $forecastReturns = collect([
                                    is_numeric($prediction->expected_return_5d ?? null) ? (float) $prediction->expected_return_5d : null,
                                    is_numeric($prediction->expected_return_20d ?? null) ? (float) $prediction->expected_return_20d : null,
                                ])->filter(fn ($value): bool => $value !== null)->values();
                                $negativeForecast = $forecastReturns->contains(fn (float $value): bool => $value < 0);
                                $shortTrend = $forecastReturns->count() >= 2
                                    && $forecastReturns->every(fn (float $value): bool => $value < 0)
                                    && $forecastReturns->min() <= -3;
                                $forecastBadge = $shortTrend ? 'SHORT-TREND' : ($negativeForecast ? 'NEGATIVE PROGNOSE' : null);
                                $modelTierCode = $prediction->model_quality_tier_code ?: 'unqualified';
                                $modelTierName = $prediction->model_quality_tier_name ? __($prediction->model_quality_tier_name) : __('Nicht qualifiziert');
                                $modelTierClass = match ($modelTierCode) {
                                    'top' => 'ak-model-tier-top',
                                    'strong' => 'ak-model-tier-strong',
                                    'solid' => 'ak-model-tier-solid',
                                    'test' => 'ak-model-tier-test',
                                    default => 'ak-model-tier-unqualified',
                                };
                                $modelTierIcon = match ($modelTierCode) {
                                    'top' => 'heroicon-s-trophy',
                                    'strong' => 'heroicon-s-shield-check',
                                    'solid' => 'heroicon-s-check-badge',
                                    'test' => 'heroicon-s-beaker',
                                    default => 'heroicon-s-exclamation-triangle',
                                };
                                $previousSignal = strtoupper((string) ($prediction->previous_signal ?? ''));
                                $hasTransition = $previousSignal !== '' && $previousSignal !== $signal;
                                $transitionTone = fn (string $value): string => match ($value) {
                                    'BUY' => 'text-teal-600',
                                    'SELL' => 'text-rose-500',
                                    'WATCH' => 'text-lime-600',
                                    'WAIT' => 'text-emerald-500',
                                    default => 'text-amber-600',
                                };
                                $predictionWatchlistIds = $watchlistMemberships->get((int) $prediction->instrument_id, collect());
                                $isWatched = $predictionWatchlistIds->isNotEmpty();
                                $predictionSmartLabels = collect(is_array($prediction->smart_labels ?? null)
                                    ? $prediction->smart_labels
                                    : (json_decode((string) ($prediction->smart_labels ?? '[]'), true) ?: []));
                                $mobileSmartLabel = $predictionSmartLabels->first();
                                $mobileSmartLabelColor = preg_match('/^#[0-9A-Fa-f]{6}$/', (string) ($mobileSmartLabel['color'] ?? '')) ? $mobileSmartLabel['color'] : '#64748b';
                                $mobileSmartLabelIcon = match ($mobileSmartLabel['icon'] ?? 'sparkles') {
                                    'bolt' => 'heroicon-o-bolt',
                                    'trophy' => 'heroicon-o-trophy',
                                    'shield-check' => 'heroicon-o-shield-check',
                                    'chart-bar' => 'heroicon-o-chart-bar',
                                    'rocket-launch' => 'heroicon-o-rocket-launch',
                                    default => 'heroicon-o-sparkles',
                                };
                                $week52Low = is_numeric($prediction->week_52_low) ? (float) $prediction->week_52_low : null;
                                $week52High = is_numeric($prediction->week_52_high) ? (float) $prediction->week_52_high : null;
                                $week52Position = $week52Low !== null && $week52High !== null && $week52High > $week52Low && is_numeric($prediction->current_price)
                                    ? max(0, min(100, (((float) $prediction->current_price - $week52Low) / ($week52High - $week52Low)) * 100))
                                    : null;
                                $rsi = is_numeric($prediction->rsi_14) ? (float) $prediction->rsi_14 : null;
                                $macdBullish = is_numeric($prediction->macd) && is_numeric($prediction->macd_signal)
                                    ? (float) $prediction->macd >= (float) $prediction->macd_signal
                                    : null;
                                $smaTrendBullish = is_numeric($prediction->sma_50) && is_numeric($prediction->sma_200)
                                    ? (float) $prediction->sma_50 >= (float) $prediction->sma_200
                                    : null;
                                $adx = is_numeric($prediction->adx_14) ? (float) $prediction->adx_14 : null;
                                $horizonDirections = collect([5, 10, 15, 20])->mapWithKeys(function (int $days) use ($prediction): array {
                                    $field = "horizon_target_{$days}d";
                                    $target = $prediction->{$field} ?? null;
                                    $return = is_numeric($target) && is_numeric($prediction->current_price) && (float) $prediction->current_price > 0
                                        ? (((float) $target - (float) $prediction->current_price) / (float) $prediction->current_price) * 100
                                        : null;
                                    return [$days => $return];
                                });
                                $allHorizonsPositive = $horizonDirections->count() === 4
                                    && $horizonDirections->every(fn ($return): bool => is_numeric($return) && (float) $return > 0);
                                $isStrongBuy = $signal === 'BUY'
                                    && (bool) ($prediction->quality_gate_passed ?? false)
                                    && $allHorizonsPositive
                                    && $score !== null && $score >= 8.0
                                    && is_numeric($prediction->ranking_profit_factor ?? null) && (float) $prediction->ranking_profit_factor >= 1.5
                                    && $hitRatePercent !== null && $hitRatePercent >= 60
                                    && $confidencePercent !== null && $confidencePercent >= 75
                                    && $riskPercent !== null && $riskPercent <= 30
                                    && (bool) ($prediction->ranking_stability_passed ?? false)
                                    && (int) ($prediction->ranking_trade_count ?? 0) >= 20;
                                if ($isStrongBuy) {
                                    $signalLabel = 'STRONG BUY';
                                    $signalIcon = 'heroicon-s-bolt';
                                }
                                $mobilePriceDirection = $horizonDirections->get(20);
                                $chartPatterns = collect($prediction->recent_chart_patterns ?? []);
                                $chartPatternBullish = $chartPatterns->contains(fn (string $pattern): bool =>
                                    str_contains($pattern, 'Bullish') || str_contains($pattern, 'oben'));
                            @endphp
                            <tr onclick="window.location='{{ route('stocks.show', ['symbol' => $prediction->symbol, 'prediction' => $prediction->id, 'return_to' => request()->getRequestUri()]) }}'" class="prediction-row cursor-pointer transition hover:bg-teal-500/[.075]">
                                <td onclick="event.stopPropagation()" class="ak-mobile-hidden-column relative border-b border-[var(--ak-border)] px-0 py-3 text-center align-middle">
                                    <div class="flex w-full items-center justify-center">
                                    @if ($userWatchlists->count() === 1)
                                        @php $singleWatchlist = $userWatchlists->first(); @endphp
                                        <form method="POST" action="{{ route('watchlists.items.toggle', [$singleWatchlist->id, $prediction->instrument_id]) }}" data-prediction-watchlist-form>
                                            @csrf
                                            <input type="hidden" name="prediction_id" value="{{ $prediction->id }}">
                                            <button type="submit" class="inline-flex h-8 w-8 items-center justify-center rounded-lg transition hover:bg-amber-300/10 {{ $isWatched ? 'text-amber-300' : 'text-slate-600 hover:text-amber-300' }}" title="{{ $isWatched ? __('Aus Watchlist entfernen') : __('Zur Watchlist hinzufügen') }}">
                                                @if ($isWatched)<x-heroicon-s-star class="h-5 w-5" />@else<x-heroicon-o-star class="h-5 w-5" />@endif
                                            </button>
                                        </form>
                                    @elseif ($userWatchlists->count() > 1)
                                        <button
                                            type="button"
                                            data-open-watchlist-picker
                                            data-instrument-id="{{ $prediction->instrument_id }}"
                                            data-prediction-id="{{ $prediction->id }}"
                                            data-symbol="{{ $prediction->symbol }}"
                                            data-name="{{ $prediction->name }}"
                                            data-memberships='@json($predictionWatchlistIds->values())'
                                            class="inline-flex h-8 w-8 items-center justify-center rounded-lg transition hover:bg-amber-300/10 {{ $isWatched ? 'text-amber-300' : 'text-slate-600 hover:text-amber-300' }}"
                                            title="{{ __('Watchlist auswählen') }}"
                                        >
                                            @if ($isWatched)<x-heroicon-s-star class="h-5 w-5" />@else<x-heroicon-o-star class="h-5 w-5" />@endif
                                        </button>
                                    @else
                                        <a href="{{ route('watchlists.index') }}" class="inline-flex h-8 w-8 items-center justify-center rounded-lg text-slate-600 transition hover:bg-teal-500/10 hover:text-teal-700" title="{{ __('Zuerst Watchlist erstellen') }}">
                                            <x-heroicon-o-star class="h-5 w-5" />
                                        </a>
                                    @endif
                                    </div>
                                </td>
                                <td class="border-b border-[var(--ak-border)] px-1.5 py-2">
                                    @if ($canUseSmartLabels && $predictionSmartLabels->isNotEmpty())
                                        <span class="flex max-w-full flex-wrap items-center gap-1" title="{{ $predictionSmartLabels->pluck('name')->implode(', ') }}">
                                            @foreach ($predictionSmartLabels as $predictionLabel)
                                                @php
                                                    $labelColor = preg_match('/^#[0-9A-Fa-f]{6}$/', (string) ($predictionLabel['color'] ?? '')) ? $predictionLabel['color'] : '#06b6d4';
                                                    $labelIcon = match ($predictionLabel['icon'] ?? 'sparkles') {
                                                        'bolt' => 'heroicon-o-bolt',
                                                        'trophy' => 'heroicon-o-trophy',
                                                        'shield-check' => 'heroicon-o-shield-check',
                                                        'chart-bar' => 'heroicon-o-chart-bar',
                                                        'rocket-launch' => 'heroicon-o-rocket-launch',
                                                        default => 'heroicon-o-sparkles',
                                                    };
                                                @endphp
                                                <span class="inline-flex h-7 w-7 items-center justify-center rounded-md border shadow-sm" title="{{ $predictionLabel['name'] }}" aria-label="{{ $predictionLabel['name'] }}" style="color: {{ $labelColor }}; border-color: {{ $labelColor }}66; background: {{ $labelColor }}18">
                                                    <x-dynamic-component :component="$labelIcon" class="h-3.5 w-3.5 shrink-0" />
                                                </span>
                                            @endforeach
                                        </span>
                                    @else
                                        <span class="text-[var(--ak-muted)]">—</span>
                                    @endif
                                </td>
                                <td class="relative border-b border-[var(--ak-border)] px-2 py-3">
                                    <span onclick="event.stopPropagation()" class="ak-tablet-name-watchlist absolute right-1 top-1 hidden">
                                        @if ($userWatchlists->count() === 1)
                                            @php $singleWatchlist = $userWatchlists->first(); @endphp
                                            <form method="POST" action="{{ route('watchlists.items.toggle', [$singleWatchlist->id, $prediction->instrument_id]) }}" data-prediction-watchlist-form>
                                                @csrf
                                                <input type="hidden" name="prediction_id" value="{{ $prediction->id }}">
                                                <button type="submit" class="inline-flex h-6 w-6 items-center justify-center rounded-md bg-[var(--ak-card)]/90 transition hover:bg-amber-300/10 {{ $isWatched ? 'text-amber-300' : 'text-slate-500 hover:text-amber-300' }}" title="{{ $isWatched ? __('Aus Watchlist entfernen') : __('Zur Watchlist hinzufügen') }}">
                                                    @if ($isWatched)<x-heroicon-s-star class="h-4 w-4" />@else<x-heroicon-o-star class="h-4 w-4" />@endif
                                                </button>
                                            </form>
                                        @elseif ($userWatchlists->count() > 1)
                                            <button type="button" data-open-watchlist-picker data-instrument-id="{{ $prediction->instrument_id }}" data-prediction-id="{{ $prediction->id }}" data-symbol="{{ $prediction->symbol }}" data-name="{{ $prediction->name }}" data-memberships='@json($predictionWatchlistIds->values())' class="inline-flex h-6 w-6 items-center justify-center rounded-md bg-[var(--ak-card)]/90 transition hover:bg-amber-300/10 {{ $isWatched ? 'text-amber-300' : 'text-slate-500 hover:text-amber-300' }}" title="{{ __('Watchlist auswählen') }}">
                                                @if ($isWatched)<x-heroicon-s-star class="h-4 w-4" />@else<x-heroicon-o-star class="h-4 w-4" />@endif
                                            </button>
                                        @else
                                            <a href="{{ route('watchlists.index') }}" class="inline-flex h-6 w-6 items-center justify-center rounded-md bg-[var(--ak-card)]/90 text-slate-500 transition hover:text-amber-300" title="{{ __('Zuerst Watchlist erstellen') }}"><x-heroicon-o-star class="h-4 w-4" /></a>
                                        @endif
                                    </span>
                                    <div class="flex min-w-0 items-center gap-2">
                                        <span class="ak-mobile-flag-label-stack hidden shrink-0">
                                            <span class="ak-mobile-row-flag" title="{{ $prediction->country ?: __('Unbekannt') }}">{{ $prediction->country ? $countryFlag($prediction->country) : '🌐' }}</span>
                                            @if ($mobileSmartLabel)
                                                <span class="ak-mobile-row-label" title="{{ $mobileSmartLabel['name'] ?? __('Label') }}" style="color:{{ $mobileSmartLabelColor }};border-color:{{ $mobileSmartLabelColor }}66;background:{{ $mobileSmartLabelColor }}18">
                                                    <x-dynamic-component :component="$mobileSmartLabelIcon" class="h-2.5 w-2.5" />
                                                </span>
                                            @else
                                                <span class="ak-mobile-row-label ak-mobile-row-label-empty" title="{{ __('Kein Label') }}">—</span>
                                            @endif
                                        </span>
                                        <span class="ak-stock-logo relative grid h-8 w-8 shrink-0 place-items-center overflow-hidden rounded-lg border border-teal-500/20 bg-gradient-to-br from-teal-500/[.10] to-cyan-500/[.04] text-[9px] font-black uppercase tracking-tight text-teal-700">
                                            {{ strtoupper(substr((string) $prediction->symbol, 0, 2)) }}
                                            <img src="{{ route('stocks.icon', $prediction->instrument_id) }}" alt="" class="absolute inset-0 h-full w-full bg-white object-contain p-1" loading="lazy" onerror="this.remove()">
                                        </span>
                                        <div class="min-w-0">
                                            <p class="ak-prediction-stock-name truncate text-[11px] font-black leading-tight text-[var(--ak-text)] dark:text-cyan-300" title="{{ $prediction->name }}">{{ $prediction->name }}</p>
                                            <p class="ak-stock-symbol mt-0.5 truncate text-[9px] font-black uppercase tracking-[.06em] text-cyan-300" title="{{ $prediction->symbol }}">{{ $prediction->symbol }}</p>
                                            <p class="ak-mobile-stock-price mt-1 hidden font-black tabular-nums">
                                                <span class="ak-mobile-price-label">{{ __('Kurs') }}</span>
                                                <span class="ak-mobile-price-value">{{ is_numeric($prediction->current_price) ? rtrim(rtrim(number_format($prediction->current_price, 2, ',', '.'), '0'), ',').($currency === 'EUR' ? ' €' : ' '.$currency) : '—' }}</span>
                                                @if ($mobilePriceDirection !== null)
                                                    <span class="ak-mobile-price-direction {{ $mobilePriceDirection >= 0 ? 'ak-mobile-price-up' : 'ak-mobile-price-down' }}" title="{{ __('20-Tage-Prognose') }}: {{ ($mobilePriceDirection >= 0 ? '+' : '').number_format($mobilePriceDirection, 2, ',', '.') }} %" aria-label="{{ $mobilePriceDirection >= 0 ? __('Kursprognose steigend') : __('Kursprognose fallend') }}">
                                                        @if ($mobilePriceDirection >= 0)
                                                            <x-heroicon-o-arrow-trending-up />
                                                        @else
                                                            <x-heroicon-o-arrow-trending-down />
                                                        @endif
                                                    </span>
                                                @endif
                                            </p>
                                        </div>
                                    </div>
                                </td>
                                <td class="border-b border-[var(--ak-border)] px-1 py-3 text-center">
                                    <span class="inline-flex items-center justify-center text-lg leading-none" title="{{ $prediction->country ?: __('Unbekannt') }}" aria-label="{{ $prediction->country ?: __('Unbekannt') }}">
                                        {{ $prediction->country ? $countryFlag($prediction->country) : '🌐' }}
                                    </span>
                                </td>
                                <td class="ak-mobile-hidden-column border-b border-[var(--ak-border)] px-2 py-3">
                                    <span class="block truncate text-[9px] font-black text-teal-600" title="{{ $prediction->exchange_code ?: __('Keine Börse zugeordnet') }}">
                                        {{ $prediction->exchange_code ?: '—' }}
                                    </span>
                                </td>
                                <td class="border-b border-[var(--ak-border)] px-1 py-1.5 text-center">
                                    @if ($hasTransition)
                                        <span class="inline-flex min-w-0 flex-col items-center gap-0.5 whitespace-nowrap text-[7px] font-black leading-none" title="{{ __('Signalübergang') }}">
                                            <span class="text-[6px] font-bold text-[var(--ak-muted)]">{{ $prediction->previous_signal_time ? \Illuminate\Support\Carbon::parse($prediction->previous_signal_time)->format('d.m.') : '—' }}</span>
                                            <span><span class="{{ $transitionTone($previousSignal) }}">{{ $previousSignal }}</span><span class="px-0.5 text-[var(--ak-muted)]">→</span><span class="{{ $transitionTone($signal) }}">{{ $signalLabel }}</span></span>
                                        </span>
                                    @else
                                        <span class="sr-only">{{ __('Kein Signalübergang') }}</span>
                                    @endif
                                </td>
                                <td class="border-b border-[var(--ak-border)] px-1 py-2 text-center">
                                    <div class="flex flex-col items-center justify-center gap-1">
                                        <span class="ak-prediction-signal-badge" data-signal="{{ strtolower($signal) }}" data-strong-buy="{{ $isStrongBuy ? 'true' : 'false' }}" data-restricted-buy="{{ $isQualityGateRestrictedBuy ? 'true' : 'false' }}" title="{{ $isQualityGateRestrictedBuy ? __('BUY durch Quality Gate eingeschränkt') : $signalLabel }}" aria-label="{{ $isQualityGateRestrictedBuy ? __('HOLD – BUY durch Quality Gate eingeschränkt') : $signalLabel }}">
                                            <x-dynamic-component :component="$signalIcon" class="h-3.5 w-3.5" />
                                            <b class="ak-notebook-signal-label hidden">{{ $signalLabel }}</b>
                                        </span>
                                        <small class="text-[8px] font-black text-[var(--ak-muted)]">{{ __('Stärke') }} {{ $signalStrength }}</small>
                                    </div>
                                </td>
                                <td class="border-b border-[var(--ak-border)] px-2 py-2">
                                    @if ($score !== null)
                                        <div class="flex h-full flex-col items-center justify-center gap-1" title="{{ __('Rohwert') }}: {{ number_format($score, 1, ',', '.') }}/10 · {{ $scoreStatisticsTitle }}">
                                            <span class="ak-mobile-score" style="--score-color: {{ $scoreColorDark }}" aria-label="{{ __('KI-Score') }} {{ number_format($score, 1, ',', '.') }} von 10">
                                                {{ $scoreGrade ?? '—' }}
                                            </span>
                                            <div class="ak-prediction-donut" style="--value:{{ $scorePercent }}%;--color:{{ $scoreColorDark }};--light-color:{{ $scoreColor }}" role="meter" aria-label="{{ __('KI-Score') }}" aria-valuemin="0" aria-valuemax="10" aria-valuenow="{{ $score }}">
                                                <svg viewBox="0 0 44 44" aria-hidden="true">
                                                    <circle class="ak-prediction-donut-track" cx="22" cy="22" r="17" pathLength="100" />
                                                    <circle class="ak-prediction-donut-value" cx="22" cy="22" r="17" pathLength="100" stroke-dasharray="{{ $scorePercent }} 100" />
                                                </svg>
                                                <span>{{ $scoreGrade ?? '—' }}</span><small>{{ __('KI-Qualität') }}</small>
                                            </div>
                                        </div>
                                    @else<span class="block text-center text-[var(--ak-muted)]">—</span>@endif
                                </td>
                                <td class="ak-horizon-column border-b border-[var(--ak-border)] px-2 py-2">
                                    <div class="flex w-full flex-nowrap items-center justify-center gap-2">
                                        @if ($score !== null)
                                            <div class="ak-prediction-donut ak-mobile-horizon-score hidden" data-label="{{ __('KI-Score') }}" style="--value:{{ $scorePercent }}%;--color:{{ $scoreColorDark }};--light-color:{{ $scoreColor }}" role="meter" aria-label="{{ __('KI-Score') }}" aria-valuemin="0" aria-valuemax="10" aria-valuenow="{{ $score }}">
                                                <svg viewBox="0 0 44 44" aria-hidden="true"><circle class="ak-prediction-donut-track" cx="22" cy="22" r="17" pathLength="100" /><circle class="ak-prediction-donut-value" cx="22" cy="22" r="17" pathLength="100" stroke-dasharray="{{ $scorePercent }} 100" /></svg>
                                                <span>{{ $scoreGrade ?? '—' }}</span>
                                            </div>
                                        @endif
                                        <span class="ak-prediction-signal-badge ak-mobile-horizon-signal hidden" data-label="{{ __('Signal') }}" data-signal="{{ strtolower($signal) }}" data-strong-buy="{{ $isStrongBuy ? 'true' : 'false' }}" data-restricted-buy="{{ $isQualityGateRestrictedBuy ? 'true' : 'false' }}" title="{{ $isQualityGateRestrictedBuy ? __('BUY durch Quality Gate eingeschränkt') : $signalLabel }}" aria-label="{{ $isQualityGateRestrictedBuy ? __('HOLD – BUY durch Quality Gate eingeschränkt') : $signalLabel }}">
                                            <b class="ak-mobile-signal-letter">{{ mb_substr($signalLabel, 0, 1) }}</b>
                                        </span>
                                        @foreach ($horizonDirections as $days => $horizonReturn)
                                            <span class="ak-horizon-direction" data-label="{{ __('Prognose') }}" data-days="{{ $days }}" data-direction="{{ $horizonReturn === null ? 'missing' : ($horizonReturn >= 0 ? 'up' : 'down') }}" title="{{ $horizonReturn === null ? __('Keine Prognose verfügbar') : (($horizonReturn >= 0 ? '+' : '').number_format($horizonReturn, 2, ',', '.').' %') }}">
                                                <b>{{ $days }}d</b>
                                                @if ($horizonReturn === null)
                                                    <x-heroicon-o-minus class="h-4 w-4" />
                                                @elseif ($horizonReturn >= 0)
                                                    <x-heroicon-o-arrow-trending-up class="h-4 w-4" />
                                                @else
                                                    <x-heroicon-o-arrow-trending-down class="h-4 w-4" />
                                                @endif
                                            </span>
                                        @endforeach
                                        <span class="ak-prediction-donut ak-mobile-risk-logo hidden" data-label="{{ __('Risiko') }}" title="{{ $riskTitle }}" aria-label="{{ __('Risiko') }} {{ $riskGrade ?? __('nicht verfügbar') }}" role="meter" aria-valuemin="0" aria-valuemax="100" aria-valuenow="{{ $riskPercent ?? 0 }}" style="--value:{{ $riskPercent ?? 0 }}%;--color:{{ $riskColorDark }};--light-color:{{ $riskColor }};--risk-logo-color:{{ $riskColor }}">
                                            <span>{{ $riskGrade ?? '—' }}</span><small>{{ __('Risiko') }}</small>
                                        </span>
                                    </div>
                                </td>
                                <td class="border-b border-[var(--ak-border)] px-2 py-3 text-right tabular-nums">
                                    <span class="block truncate font-bold text-[var(--ak-text)]">{{ is_numeric($prediction->current_price) ? number_format($prediction->current_price, 2, ',', '.').' '.$currency : '—' }}</span>
                                    <span class="mt-1 block whitespace-nowrap text-[8px] font-bold text-[var(--ak-muted)]">{{ $prediction->prediction_time ? \Illuminate\Support\Carbon::parse($prediction->prediction_time)->format('d.m.Y') : '—' }}</span>
                                </td>
                                <td class="border-b border-[var(--ak-border)] px-3 py-2">
                                    @if ($week52Low !== null && $week52High !== null)
                                        <div class="flex items-center justify-between text-[8px] font-bold tabular-nums text-[var(--ak-muted)]">
                                            <span>{{ number_format($week52Low, 2, ',', '.') }}</span><span>{{ number_format($week52High, 2, ',', '.') }}</span>
                                        </div>
                                        <div class="relative mt-1.5 h-1.5 rounded-full ring-1 ring-slate-400/15" style="background: linear-gradient(90deg, rgba(244,63,94,.78), rgba(251,191,36,.74), rgba(52,211,153,.78))" title="{{ __('Position des aktuellen Kurses innerhalb der 52-Wochen-Spanne') }}">
                                            <span class="absolute top-1/2 h-3 w-1.5 -translate-x-1/2 -translate-y-1/2 rounded-full border border-white/60 shadow-[0_0_4px_rgba(15,23,42,.35)]" style="left: clamp(3px, {{ number_format($week52Position ?? 0, 2, '.', '') }}%, calc(100% - 3px)); background: hsl({{ number_format(($week52Position ?? 0) * 1.2, 1, '.', '') }} 58% 52%)"></span>
                                        </div>
                                        <p class="mt-1 text-center text-[7px] font-black uppercase tracking-wide text-[var(--ak-muted)]">{{ number_format($week52Position ?? 0, 0, ',', '.') }} % {{ __('der Spanne') }}</p>
                                    @else
                                        <span class="text-[var(--ak-muted)]">—</span>
                                    @endif
                                </td>
                                <td class="border-b border-[var(--ak-border)] px-2 py-2 text-center">
                                    <span class="inline-flex h-8 w-8 items-center justify-center rounded-md {{ $chartPatterns->isEmpty() ? 'text-[var(--ak-muted)]' : ($chartPatternBullish ? 'text-emerald-400' : 'text-rose-400') }}" style="border: .5px solid currentColor" title="{{ $chartPatterns->isNotEmpty() ? __('Chartformation in den letzten fünf Handelstagen: :patterns', ['patterns' => $chartPatterns->implode(', ')]) : __('Keine Chartformation in den letzten fünf Handelstagen erkannt') }}" aria-label="{{ $chartPatterns->isNotEmpty() ? ($chartPatternBullish ? __('Bullische Chartformation erkannt') : __('Bearische Chartformation erkannt')) : __('Keine Chartformation erkannt') }}">
                                        @if ($chartPatterns->isNotEmpty())
                                            <x-heroicon-s-chart-bar-square class="h-6 w-6" />
                                        @else
                                            <x-heroicon-o-chart-bar-square class="h-6 w-6" />
                                        @endif
                                    </span>
                                </td>
                                <td class="border-b border-[var(--ak-border)] px-2 py-2">
                                    <div class="grid grid-cols-2 gap-1.5">
                                        <span class="ak-indicator-chip" data-tone="{{ $rsi === null ? 'neutral' : ($rsi >= 70 ? 'warning' : ($rsi <= 30 ? 'positive' : 'neutral')) }}">RSI {{ $rsi !== null ? number_format($rsi, 0, ',', '.') : '—' }}</span>
                                        <span class="ak-indicator-chip" data-tone="{{ $macdBullish === null ? 'neutral' : ($macdBullish ? 'positive' : 'negative') }}">MACD {{ $macdBullish === null ? '—' : ($macdBullish ? '↑' : '↓') }}</span>
                                        <span class="ak-indicator-chip" data-tone="{{ $smaTrendBullish === null ? 'neutral' : ($smaTrendBullish ? 'positive' : 'negative') }}">SMA 50/200 {{ $smaTrendBullish === null ? '—' : ($smaTrendBullish ? '↑' : '↓') }}</span>
                                        <span class="ak-indicator-chip" data-tone="{{ $adx !== null && $adx >= 25 ? 'positive' : 'neutral' }}">ADX {{ $adx !== null ? number_format($adx, 0, ',', '.') : '—' }}</span>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="13" class="px-6 py-16 text-center text-sm text-[var(--ak-muted)]">{{ __('Keine Prognosen für diese Filter gefunden.') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if ($predictions instanceof \Illuminate\Contracts\Pagination\Paginator && $predictions->hasPages())
                @php
                    $paginationStart = max(1, $predictions->currentPage() - 2);
                    $paginationEnd = min($predictions->lastPage(), $predictions->currentPage() + 2);
                @endphp
                <div class="ak-prediction-pagination" role="navigation" aria-label="{{ __('Seitennavigation') }}">
                    <span class="ak-pagination-summary">
                        {{ number_format($predictions->firstItem(), 0, ',', '.') }}–{{ number_format($predictions->lastItem(), 0, ',', '.') }}
                        <span aria-hidden="true">/</span>
                        {{ number_format($predictions->total(), 0, ',', '.') }}
                    </span>
                    <div class="ak-pagination-pages">
                        @if ($predictions->onFirstPage())
                            <span class="ak-pagination-button is-disabled" aria-disabled="true">‹</span>
                        @else
                            <a class="ak-pagination-button" href="{{ $predictions->previousPageUrl() }}" rel="prev" aria-label="{{ __('Vorherige Seite') }}">‹</a>
                        @endif

                        @for ($page = $paginationStart; $page <= $paginationEnd; $page++)
                            <a class="ak-pagination-button {{ $page === $predictions->currentPage() ? 'is-current' : '' }}"
                               href="{{ $predictions->url($page) }}"
                               @if ($page === $predictions->currentPage()) aria-current="page" @endif>{{ $page }}</a>
                        @endfor

                        @if ($predictions->hasMorePages())
                            <a class="ak-pagination-button" href="{{ $predictions->nextPageUrl() }}" rel="next" aria-label="{{ __('Nächste Seite') }}">›</a>
                        @else
                            <span class="ak-pagination-button is-disabled" aria-disabled="true">›</span>
                        @endif
                    </div>
                </div>
            @endif

            <style>
                #predictions-page {
                    --ak-muted: #b8c2d4;
                    --ak-predictions-card: rgba(52, 65, 95, .98);
                    --ak-predictions-card-even: rgba(47, 59, 88, .99);
                    --ak-predictions-head: #26324d;
                    --ak-predictions-head-text: #f8fafc;
                }

                #predictions-page .ak-prediction-pagination {
                    display: flex;
                    align-items: center;
                    justify-content: flex-end;
                    gap: .75rem;
                    width: max-content;
                    max-width: 100%;
                    margin: .65rem 0 0 auto;
                    color: var(--ak-muted);
                    font-size: .75rem;
                    font-weight: 700;
                }

                #predictions-page .ak-pagination-summary {
                    white-space: nowrap;
                    opacity: .82;
                }

                #predictions-page .ak-pagination-pages {
                    display: flex;
                    align-items: center;
                    gap: .25rem;
                }

                #predictions-page .ak-pagination-button {
                    display: inline-grid;
                    width: 2rem;
                    height: 2rem;
                    place-items: center;
                    border: 1px solid transparent;
                    border-radius: .45rem;
                    background: transparent;
                    color: var(--ak-muted);
                    line-height: 1;
                    transition: border-color .16s ease, color .16s ease, background-color .16s ease;
                }

                #predictions-page .ak-pagination-button:hover {
                    border-color: color-mix(in srgb, var(--ak-accent) 45%, transparent);
                    color: var(--ak-accent);
                }

                #predictions-page .ak-pagination-button.is-current {
                    border-color: color-mix(in srgb, var(--ak-accent) 58%, transparent);
                    background: color-mix(in srgb, var(--ak-accent) 10%, transparent);
                    color: var(--ak-accent);
                }

                #predictions-page .ak-pagination-button.is-disabled {
                    opacity: .28;
                }

                @media (max-width: 640px) {
                    #predictions-page .ak-prediction-pagination {
                        justify-content: space-between;
                        width: 100%;
                    }
                }

                :root:not([data-theme="light"]) #predictions-page .ak-prediction-filter-toggle,
                :root:not([data-theme="light"]) #predictions-page .ak-prediction-filterboard {
                    border-color: rgba(251, 146, 60, .26) !important;
                    background: transparent !important;
                    box-shadow: none !important;
                }

                :root:not([data-theme="light"]) #predictions-page .ak-prediction-filter-toggle {
                    color: #fb923c !important;
                }

                :root:not([data-theme="light"]) #predictions-page .ak-prediction-filterboard .ak-input,
                :root:not([data-theme="light"]) #predictions-page .ak-table-filter {
                    border-color: rgba(251, 191, 36, .20) !important;
                    background-color: transparent !important;
                    box-shadow: none !important;
                }

                :root:not([data-theme="light"]) #predictions-page .ak-stocks-table .ak-predictions-filter-row,
                :root:not([data-theme="light"]) #predictions-page .ak-stocks-table .ak-predictions-filter-row th {
                    background: transparent !important;
                    box-shadow: none !important;
                }

                #predictions-page .ak-stocks-table {
                    font-size: 10px;
                }

                #predictions-page .ak-stocks-table tbody td {
                    padding-top: .4rem;
                    padding-bottom: .4rem;
                }

                #predictions-page .ak-predictions-card-surface,
                #predictions-page .ak-predictions-card-surface {
                    background: var(--ak-predictions-card) !important;
                }

                #predictions-page .ak-stocks-table,
                #predictions-page .ak-stocks-table tbody {
                    background: var(--ak-card) !important;
                }

                #predictions-page .ak-stocks-table tbody tr {
                    background: var(--ak-card) !important;
                }

                #predictions-page .ak-predictions-card-surface {
                    border: 1px solid color-mix(in srgb, var(--ak-border) 82%, #06b6d4 18%) !important;
                    box-shadow: 0 10px 26px rgba(2, 44, 42, .16), 0 3px 10px rgba(0, 0, 0, .10) !important;
                }

                :root[data-theme="light"] #predictions-page .ak-predictions-card-surface {
                    box-shadow: 0 12px 28px rgba(14, 116, 144, .13), 0 3px 9px rgba(15, 23, 42, .08) !important;
                }

                #predictions-page .ak-stocks-table tbody tr:nth-child(even) {
                    background: var(--ak-card) !important;
                }

                #predictions-page .ak-stocks-table tbody tr:hover {
                    background: color-mix(in srgb, var(--ak-card) 84%, #06b6d4 16%) !important;
                }

                /* Compact dashboard-card treatment: each prediction reads like a bordered transaction card. */
                #predictions-page #predictions-table-scroll {
                    border-color: rgba(251, 146, 60, .34) !important;
                    border-radius: 1.25rem !important;
                    background: rgba(15, 23, 42, .72) !important;
                    box-shadow: inset 3px 0 0 rgba(251, 146, 60, .70), 0 16px 36px rgba(0, 0, 0, .22) !important;
                }

                #predictions-page .ak-stocks-table {
                    border-collapse: separate !important;
                    border-spacing: 0 7px !important;
                    padding: 0 .65rem .65rem !important;
                }

                #predictions-page .ak-stocks-table tbody .prediction-row td {
                    background: transparent !important;
                    border-top: 1px solid rgba(251, 191, 36, .18) !important;
                    border-bottom: 1px solid rgba(251, 191, 36, .18) !important;
                    padding-top: .7rem !important;
                    padding-bottom: .7rem !important;
                }

                :root:not([data-theme="light"]) #predictions-page .ak-stocks-table tbody .prediction-row:nth-child(even) td {
                    background: transparent !important;
                }

                #predictions-page .ak-stocks-table tbody .prediction-row td:first-child {
                    border-left: 1px solid rgba(251, 146, 60, .30) !important;
                    border-radius: .8rem 0 0 .8rem;
                }

                #predictions-page .ak-stocks-table tbody .prediction-row td:last-child {
                    border-right: 1px solid rgba(251, 146, 60, .30) !important;
                    border-radius: 0 .8rem .8rem 0;
                }

                #predictions-page .ak-stocks-table tbody .prediction-row:hover td {
                    background: rgba(66, 78, 108, .99) !important;
                    border-color: rgba(251, 146, 60, .48) !important;
                }

                #predictions-page .ak-stocks-table tbody .prediction-row td:nth-child(3) p:first-child,
                #predictions-page .ak-stocks-table tbody .prediction-row td:nth-child(4),
                #predictions-page .ak-stocks-table tbody .prediction-row td:nth-child(5) {
                    color: #fb923c !important;
                }

                #predictions-page .ak-stocks-table tbody .prediction-row td:nth-child(3) p:last-child {
                    color: #b7c8d8 !important;
                }

                #predictions-page .ak-prediction-signal-badge {
                    height: 2rem;
                    min-width: 2rem;
                    width: 2rem;
                    max-width: 2rem;
                    border-radius: .65rem;
                    padding: 0;
                }

                #predictions-page .ak-prediction-signal-badge[data-signal="hold"] {
                    border-color: rgba(234, 179, 8, .72) !important;
                    background: rgba(250, 204, 21, .18) !important;
                    color: #ca8a04 !important;
                }

                :root:not([data-theme="light"]) #predictions-page .ak-prediction-signal-badge[data-signal="hold"] {
                    border-color: rgba(250, 204, 21, .78) !important;
                    background: rgba(250, 204, 21, .20) !important;
                    color: #fde047 !important;
                }

                #predictions-page .ak-forecast-badge {
                    display: inline-flex;
                    min-height: 1.15rem;
                    max-width: 7.4rem;
                    align-items: center;
                    justify-content: center;
                    border: 1px solid rgba(245, 158, 11, .55);
                    border-radius: .45rem;
                    background: rgba(245, 158, 11, .12);
                    padding: .18rem .4rem;
                    color: #f6bd55;
                    font-size: .47rem;
                    font-weight: 900;
                    letter-spacing: .07em;
                    line-height: 1;
                    text-transform: uppercase;
                    white-space: nowrap;
                }

                #predictions-page .ak-forecast-badge[data-forecast="short"] {
                    border-color: rgba(244, 114, 132, .62);
                    background: rgba(244, 114, 132, .14);
                    color: #f59aaa;
                }

                :root[data-theme="light"] #predictions-page #predictions-table-scroll {
                    border-color: rgba(34, 211, 238, .42) !important;
                    background: rgba(255, 255, 255, .82) !important;
                    box-shadow: inset 4px 0 0 rgba(34, 211, 238, .72), 0 16px 36px rgba(0, 0, 0, .18) !important;
                }

                :root[data-theme="light"] #predictions-page .ak-stocks-table tbody .prediction-row td {
                    background: #ffffff !important;
                    border-color: rgba(100, 116, 139, .18) !important;
                }

                :root[data-theme="light"] #predictions-page .ak-stocks-table tbody .prediction-row:nth-child(even) td {
                    background: #f1f3f5 !important;
                }

                :root[data-theme="light"] #predictions-page .ak-stocks-table tbody .prediction-row:hover td {
                    background: #e8f1f2 !important;
                }

                :root[data-theme="light"] #predictions-page .ak-forecast-badge {
                    border-color: rgba(217, 119, 6, .42);
                    background: rgba(245, 158, 11, .11);
                    color: #a45a08;
                }

                :root[data-theme="light"] #predictions-page .ak-forecast-badge[data-forecast="short"] {
                    border-color: rgba(190, 70, 91, .4);
                    background: rgba(225, 93, 115, .1);
                    color: #b63f55;
                }

                #predictions-page .ak-stocks-table thead {
                    position: sticky;
                    top: 0;
                    z-index: 40;
                    background: var(--ak-predictions-head) !important;
                    isolation: isolate;
                }

                #predictions-page .ak-stocks-table .ak-predictions-heading-row th {
                    position: sticky;
                    top: 0;
                    z-index: 42;
                    height: 44px;
                    background: var(--ak-predictions-head) !important;
                    color: var(--ak-predictions-head-text) !important;
                    pointer-events: auto;
                }

                #predictions-page .ak-predictions-heading-row th a {
                    position: relative;
                    z-index: 60;
                    pointer-events: auto !important;
                    cursor: pointer;
                }

                /* Keep the compact transaction-card rhythm without oversized metrics. */
                #predictions-page .ak-prediction-donut {
                    width: 40px !important;
                    height: 40px !important;
                    flex-basis: 40px !important;
                }

                #predictions-page .ak-prediction-donut span {
                    font-size: 10px;
                }

                #predictions-page .ak-prediction-donut small {
                    font-size: 7px;
                }

                #predictions-page .ak-mobile-score {
                    display: none;
                }

                /* The compact score inside the horizon group belongs only to
                   the phone layout. Keep it hidden on notebook/desktop even
                   though the generic donut rule uses display:grid. */
                #predictions-page .ak-mobile-horizon-score.hidden {
                    display: none !important;
                }

                @media (min-width: 640px) and (max-width: 1535px) {
                    #predictions-page #predictions-table-scroll .ak-prediction-signal-badge:not(.ak-mobile-horizon-signal) {
                        width: 4.5rem !important;
                        min-width: 4.5rem !important;
                        max-width: 4.5rem !important;
                        border-radius: .55rem !important;
                    }
                    #predictions-page #predictions-table-scroll .ak-prediction-signal-badge:not(.ak-mobile-horizon-signal) > svg {
                        display: none !important;
                    }
                    #predictions-page #predictions-table-scroll .ak-notebook-signal-label {
                        display: inline !important;
                        font-size: .58rem;
                        font-weight: 950;
                        letter-spacing: .04em;
                    }
                }

                #predictions-page .ak-stocks-table .ak-predictions-filter-row th {
                    position: sticky;
                    top: 44px;
                    z-index: 41;
                    background: var(--ak-predictions-head) !important;
                    box-shadow: 0 1px 0 rgba(34, 211, 238, .20), 0 8px 14px rgba(0, 0, 0, .18);
                }

                :root[data-theme="light"] #predictions-page {
                    --ak-muted: #64748b;
                    --ak-predictions-head: #dff2ef;
                    --ak-predictions-head-text: #214e49;
                }

                :root[data-theme="light"] #predictions-page .ak-predictions-heading-row th,
                :root[data-theme="light"] #predictions-page .ak-predictions-heading-row th a {
                    color: #214e49 !important;
                    -webkit-text-fill-color: #214e49 !important;
                    text-shadow: none !important;
                }

                :root[data-theme="light"] #predictions-page .ak-stocks-table tbody tr:hover {
                    background: color-mix(in srgb, var(--ak-card) 84%, #06b6d4 16%) !important;
                }

                #predictions-page .ak-table-filter {
                    border-radius: 5px !important;
                    color: #f8fafc !important;
                    -webkit-text-fill-color: #f8fafc !important;
                    opacity: 1 !important;
                }

                #predictions-page select.ak-table-filter option {
                    background: #26324d;
                    color: #f8fafc;
                }

                #predictions-page select.ak-table-filter {
                    appearance: none !important;
                    -webkit-appearance: none !important;
                    padding: 0 13px 0 5px !important;
                    font-size: 10px !important;
                    line-height: 28px !important;
                    border-color: rgba(251, 146, 60, .22) !important;
                    background-color: #26324d !important;
                    background-image:
                        linear-gradient(45deg, transparent 50%, #cbd5e1 50%),
                        linear-gradient(135deg, #cbd5e1 50%, transparent 50%) !important;
                    background-position:
                        calc(100% - 7px) 50%,
                        calc(100% - 4px) 50% !important;
                    background-size: 3px 3px, 3px 3px !important;
                    background-repeat: no-repeat !important;
                }

                #predictions-page .ak-table-filter::placeholder {
                    color: #f8fafc !important;
                    opacity: 1;
                }

                :root:not([data-theme="light"]) #predictions-page .ak-table-filter:focus {
                    border-color: rgba(251, 146, 60, .62) !important;
                    box-shadow: 0 0 0 3px rgba(251, 146, 60, .10) !important;
                }

                :root[data-theme="light"] #predictions-page .ak-table-filter {
                    color: #0f172a !important;
                    -webkit-text-fill-color: #0f172a !important;
                }

                :root[data-theme="light"] #predictions-page select.ak-table-filter option {
                    background: #f8fafc;
                    color: #0f172a;
                }

                :root[data-theme="light"] #predictions-page select.ak-table-filter {
                    background-color: #f8fafc !important;
                    background-image:
                        linear-gradient(45deg, transparent 50%, #475569 50%),
                        linear-gradient(135deg, #475569 50%, transparent 50%) !important;
                }

                :root[data-theme="light"] #predictions-page .ak-table-filter::placeholder {
                    color: #0f172a !important;
                }

                .ak-prediction-donut {
                    position: relative;
                    display: grid;
                    width: 46px;
                    height: 46px;
                    flex: 0 0 46px;
                    place-items: center;
                    border-radius: 999px;
                    background: conic-gradient(
                        var(--color) 0 var(--value),
                        color-mix(in srgb, var(--ak-border) 74%, var(--ak-muted) 26%) var(--value) 100%
                    );
                    box-shadow:
                        0 0 0 1px var(--ak-border),
                        0 3px 8px rgba(15, 23, 42, .10);
                    filter: none !important;
                }

                .ak-prediction-donut::after {
                    inset: 3px;
                    background: var(--ak-card);
                }

                .ak-prediction-donut svg {
                    display: none;
                }

                .ak-prediction-donut circle {
                    fill: none;
                    stroke-width: 6;
                    vector-effect: non-scaling-stroke;
                }

                .ak-prediction-donut-track {
                    stroke: color-mix(in srgb, var(--ak-border) 76%, var(--ak-muted) 24%);
                    opacity: .72;
                }

                .ak-prediction-donut-value {
                    stroke: var(--color);
                    stroke-linecap: round;
                    filter: none;
                    transition: stroke-dasharray .2s ease;
                }

                :root[data-theme="light"] #predictions-page .ak-prediction-donut {
                    background:conic-gradient(
                        var(--light-color, var(--color)) 0 var(--value),
                        color-mix(in srgb, var(--ak-border) 74%, var(--ak-muted) 26%) var(--value) 100%
                    ) !important;
                }

                :root[data-theme="light"] #predictions-page .ak-prediction-donut-value {
                    stroke:var(--light-color, var(--color)) !important;
                }

                .ak-prediction-donut span {
                    position: relative;
                    z-index: 1;
                    color: var(--ak-text);
                    font-size: 11px;
                    font-weight: 900;
                    line-height: 1;
                }

                .ak-prediction-donut small {
                    position: absolute;
                    top: calc(100% + 3px);
                    bottom: auto;
                    z-index: 2;
                    width: 100%;
                    margin: 0;
                    text-align: center;
                    color: var(--ak-muted);
                    font-size: 6px;
                    font-weight: 800;
                    line-height: 1;
                }

                #predictions-page .ak-prediction-donut span {
                    margin-top: 0;
                    color: var(--color);
                    font-size: 9px;
                }

                #predictions-page .ak-prediction-donut {
                    overflow: visible;
                }

                #predictions-page .ak-stocks-table tbody .prediction-row {
                    height: 64px;
                }

                #predictions-page .ak-stocks-table tbody .prediction-row td {
                    padding-top: .35rem !important;
                    padding-bottom: .35rem !important;
                }

                #predictions-page .ak-stocks-table tbody .prediction-row td:nth-child(7) > span {
                    border: 1px solid color-mix(in srgb, var(--ak-border) 70%, #a78bfa 30%);
                    border-radius: .5rem;
                    background: rgba(167, 139, 250, .08);
                    padding: .3rem .4rem;
                }

                #predictions-page .ak-indicator-chip {
                    display: inline-flex;
                    min-height: 1.55rem;
                    align-items: center;
                    justify-content: center;
                    border: 1px solid var(--ak-border);
                    border-radius: .35rem;
                    background: color-mix(in srgb, var(--ak-card) 88%, #64748b 12%);
                    padding: .25rem .55rem;
                    color: var(--ak-muted);
                    font-size: 8px;
                    font-weight: 900;
                    line-height: 1;
                    white-space: nowrap;
                    width: 100%;
                }

                #predictions-page .ak-indicator-chip[data-tone="positive"] {
                    border-color: rgba(34, 211, 238, .72);
                    background: rgba(8, 145, 178, .24);
                    color: #67f5df;
                }

                #predictions-page .ak-indicator-chip[data-tone="negative"] {
                    border-color: rgba(251, 113, 133, .72);
                    background: rgba(225, 29, 72, .24);
                    color: #ffb1bd;
                }

                #predictions-page .ak-indicator-chip[data-tone="warning"] {
                    border-color: rgba(251, 113, 133, .72);
                    background: rgba(225, 29, 72, .24);
                    color: #ffb1bd;
                }

                :root[data-theme="light"] #predictions-page .ak-indicator-chip[data-tone="positive"] { color: #0e7490; }
                :root[data-theme="light"] #predictions-page .ak-indicator-chip[data-tone="negative"] { color: #be4059; }
                :root[data-theme="light"] #predictions-page .ak-indicator-chip[data-tone="warning"] { color: #be4059; }

                #predictions-page .ak-horizon-direction {
                    display: inline-flex;
                    box-sizing: border-box;
                    width: 30px;
                    height: 30px;
                    min-width: 30px;
                    flex: 0 0 30px;
                    justify-self: center;
                    flex-direction: column;
                    align-items: center;
                    justify-content: center;
                    gap: .1rem;
                    border: 2px solid var(--ak-border);
                    border-radius: .45rem;
                    padding: .2rem;
                    font-size: 8px;
                    font-weight: 900;
                    line-height: 1;
                    box-shadow: inset 0 1px 0 rgba(255, 255, 255, .14), 0 3px 8px rgba(2, 8, 23, .18);
                }

                #predictions-page .ak-horizon-direction svg {
                    stroke-width: 2.5;
                    filter: none;
                }

                #predictions-page .ak-horizon-column {
                    box-sizing: border-box;
                    width: 160px;
                    min-width: 160px;
                    white-space: nowrap;
                }

                #predictions-page .ak-horizon-direction[data-direction="up"] {
                    border-color: rgba(74, 222, 128, .52);
                    background: rgba(22, 163, 74, .12);
                    color: #6edb9c;
                    box-shadow:
                        inset 0 1px 0 rgba(190, 255, 214, .10),
                        0 0 7px rgba(34, 197, 94, .12);
                    text-shadow: none;
                }

                #predictions-page .ak-horizon-direction[data-direction="down"] {
                    border-color: rgba(251, 113, 133, .72);
                    background: rgba(225, 29, 72, .24);
                    color: #ffb1bd;
                }

                #predictions-page .ak-horizon-direction[data-direction="missing"] {
                    color: var(--ak-muted);
                }

                :root[data-theme="light"] #predictions-page .ak-horizon-direction[data-direction="up"] {
                    border-color: rgba(5, 150, 105, .62);
                    background: rgba(16, 185, 129, .13);
                    color: #047857;
                    box-shadow: inset 0 1px 0 rgba(255, 255, 255, .55), 0 3px 8px rgba(15, 23, 42, .10);
                    text-shadow: none;
                }
                :root[data-theme="light"] #predictions-page .ak-horizon-direction[data-direction="down"] { color: #be4059; }

                /* Kompakte Kerntabelle: nur entscheidungsrelevante Spalten. */
                #predictions-page #predictions-table-scroll .ak-stocks-table {
                    width:100% !important;
                    min-width:0 !important;
                    table-layout:fixed !important;
                }
                #predictions-page #predictions-table-scroll .ak-prediction-signal-badge {
                    box-sizing:border-box !important;
                    width:2rem !important;
                    min-width:0 !important;
                    max-width:100% !important;
                    overflow:hidden !important;
                }
                #predictions-page .ak-stocks-table colgroup col:nth-child(4),
                #predictions-page .ak-stocks-table colgroup col:nth-child(5),
                #predictions-page .ak-stocks-table colgroup col:nth-child(6),
                #predictions-page .ak-stocks-table colgroup col:nth-child(11),
                #predictions-page .ak-stocks-table colgroup col:nth-child(12),
                #predictions-page .ak-predictions-heading-row th:nth-child(4),
                #predictions-page .ak-predictions-heading-row th:nth-child(5),
                #predictions-page .ak-predictions-heading-row th:nth-child(6),
                #predictions-page .ak-predictions-heading-row th:nth-child(11),
                #predictions-page .ak-predictions-heading-row th:nth-child(12),
                #predictions-page .prediction-row > td:nth-child(4),
                #predictions-page .prediction-row > td:nth-child(5),
                #predictions-page .prediction-row > td:nth-child(6),
                #predictions-page .prediction-row > td:nth-child(11),
                #predictions-page .prediction-row > td:nth-child(12) { display:none !important; }
                #predictions-page .ak-stocks-table colgroup col:nth-child(1) { width:4% !important; }
                #predictions-page .ak-stocks-table colgroup col:nth-child(2) { width:5% !important; }
                #predictions-page .ak-stocks-table colgroup col:nth-child(3) { width:18% !important; }
                #predictions-page .ak-stocks-table colgroup col:nth-child(7) { width:9% !important; }
                #predictions-page .ak-stocks-table colgroup col:nth-child(8) { width:8% !important; }
                #predictions-page .ak-stocks-table colgroup col:nth-child(9) { width:18% !important; }
                #predictions-page .ak-stocks-table colgroup col:nth-child(10) { width:11% !important; }
                #predictions-page .ak-stocks-table colgroup col:nth-child(13) { width:27% !important; }
                #predictions-page #predictions-table-scroll { overflow-x:hidden !important; }

                @media (max-width: 639px) {
                    #predictions-page #predictions-table-scroll {
                        width: 100%;
                        max-width: 100%;
                        overflow-x: hidden !important;
                        overscroll-behavior-x: contain;
                        -webkit-overflow-scrolling: touch;
                        touch-action: pan-x pan-y;
                    }

                    #predictions-page .ak-mobile-hidden-column {
                        display: none !important;
                    }

                    #predictions-page .ak-stocks-table {
                        width: 100% !important;
                        min-width: 0 !important;
                        table-layout: fixed !important;
                        border-spacing: 0 6px !important;
                        padding: 0 .3rem .5rem !important;
                    }

                    #predictions-page .ak-stocks-table colgroup col:nth-child(1),
                    #predictions-page .ak-stocks-table colgroup col:nth-child(2),
                    #predictions-page .ak-stocks-table colgroup col:nth-child(10),
                    #predictions-page .ak-stocks-table colgroup col:nth-child(13),
                    #predictions-page .ak-predictions-heading-row th:nth-child(1),
                    #predictions-page .ak-predictions-heading-row th:nth-child(2),
                    #predictions-page .ak-predictions-heading-row th:nth-child(10),
                    #predictions-page .ak-predictions-heading-row th:nth-child(13),
                    #predictions-page .prediction-row > td:nth-child(1),
                    #predictions-page .prediction-row > td:nth-child(2),
                    #predictions-page .prediction-row > td:nth-child(10),
                    #predictions-page .prediction-row > td:nth-child(13) {
                        display: none !important;
                    }

                    #predictions-page .ak-stocks-table colgroup col:nth-child(3) { width: 46% !important; }
                    #predictions-page .ak-stocks-table colgroup col:nth-child(7),
                    #predictions-page .ak-stocks-table colgroup col:nth-child(8),
                    #predictions-page .ak-predictions-heading-row th:nth-child(7),
                    #predictions-page .ak-predictions-heading-row th:nth-child(8),
                    #predictions-page .prediction-row > td:nth-child(7),
                    #predictions-page .prediction-row > td:nth-child(8) { display:none !important; }
                    #predictions-page .ak-stocks-table colgroup col:nth-child(9) { width: 54% !important; }

                    #predictions-page .ak-predictions-heading-row th {
                        height: 38px;
                        padding: .45rem .2rem !important;
                        font-size: 8px;
                        letter-spacing: .04em;
                    }

                    #predictions-page .ak-predictions-heading-row th:nth-child(8) a {
                        gap: 0;
                    }

                    #predictions-page .ak-predictions-heading-row th:nth-child(8) a > span:first-child {
                        white-space: normal;
                        line-height: 1.05;
                    }

                    #predictions-page .prediction-row {
                        height: 82px !important;
                    }

                    #predictions-page .prediction-row > td {
                        min-width: 0 !important;
                        padding: .35rem .2rem !important;
                    }

                    :root[data-theme="light"] #predictions-page .prediction-row:nth-child(odd) > td {
                        background:#ffffff !important;
                    }

                    :root[data-theme="light"] #predictions-page .prediction-row:nth-child(even) > td {
                        background:#f1f5f4 !important;
                    }

                    :root[data-theme="light"] #predictions-page .prediction-row:hover > td {
                        background:#e8f5f4 !important;
                    }

                    #predictions-page .prediction-row > td:nth-child(3) {
                        border-left: 1px solid rgba(34, 211, 238, .42) !important;
                        border-radius: .7rem 0 0 .7rem !important;
                        text-align: center;
                    }

                    #predictions-page .prediction-row > td:nth-child(9) {
                        border-right: 1px solid rgba(34, 211, 238, .42) !important;
                        border-radius: 0 .7rem .7rem 0 !important;
                    }

                    #predictions-page .ak-stock-symbol { display:none !important; }
                    #predictions-page .ak-mobile-stock-price {
                        display:flex !important;
                        align-items:center;
                        gap:.22rem;
                        min-width:0;
                    }
                    #predictions-page .prediction-row > td:nth-child(3) .ak-mobile-stock-price {
                        line-height:1.1 !important;
                        opacity:1 !important;
                    }
                    #predictions-page .ak-mobile-price-label {
                        color:var(--ak-muted);
                        font-size:7px;
                        font-weight:900;
                        letter-spacing:.06em;
                        text-transform:uppercase;
                    }
                    #predictions-page .ak-mobile-price-value {
                        overflow:hidden;
                        color:#075985;
                        font-size:12px;
                        font-weight:950;
                        line-height:1;
                        overflow:hidden;
                        text-overflow:ellipsis;
                        white-space:nowrap;
                    }
                    :root:not([data-theme="light"]) #predictions-page .ak-mobile-price-value {
                        color:#a5f3fc;
                    }
                    #predictions-page .ak-mobile-price-direction {
                        display:inline-grid;
                        width:1rem;
                        height:1rem;
                        flex:0 0 1rem;
                        place-items:center;
                        border-radius:.3rem;
                    }
                    #predictions-page .ak-mobile-price-direction svg {
                        width:.72rem;
                        height:.72rem;
                        stroke-width:2.2;
                    }
                    #predictions-page .ak-mobile-price-up {
                        color:#059669;
                        background:rgba(16,185,129,.12);
                    }
                    #predictions-page .ak-mobile-price-down {
                        color:#e11d48;
                        background:rgba(244,63,94,.11);
                    }
                    :root:not([data-theme="light"]) #predictions-page .prediction-row > td:nth-child(3) .ak-mobile-stock-price {
                        color:#67e8f9 !important;
                    }

                    #predictions-page .prediction-row > td:nth-child(3) > div {
                        gap: .35rem;
                    }

                    #predictions-page .prediction-row > td:nth-child(3) .ak-stock-logo {
                        width: 1.65rem;
                        height: 1.65rem;
                    }

                    #predictions-page .ak-mobile-flag-label-stack {
                        display:grid !important;
                        width:1.35rem;
                        grid-template-rows:1rem 1rem;
                        place-items:center;
                        gap:.16rem;
                    }

                    #predictions-page .ak-mobile-row-flag {
                        display:inline-flex;
                        justify-content:center;
                        font-size:.8rem;
                        line-height:1;
                    }

                    #predictions-page .ak-mobile-row-label {
                        display:inline-flex;
                        width:1rem;
                        height:1rem;
                        align-items:center;
                        justify-content:center;
                        overflow:hidden;
                        border:1px solid;
                        border-radius:.3rem;
                    }

                    #predictions-page .ak-mobile-row-label-empty {
                        border-color:rgba(100,116,139,.3);
                        background:rgba(100,116,139,.06);
                        color:var(--ak-muted);
                        font-size:.48rem;
                    }

                    #predictions-page .prediction-row > td:nth-child(3) > div {
                        gap:.28rem !important;
                    }

                    #predictions-page .prediction-row > td:nth-child(3) p:first-child {
                        display:-webkit-box !important;
                        overflow:hidden !important;
                        font-size:11px !important;
                        line-height:1.2 !important;
                        text-overflow:ellipsis;
                        white-space:normal !important;
                        -webkit-box-orient:vertical;
                        -webkit-line-clamp:2;
                    }

                    #predictions-page .prediction-row > td:nth-child(3) p:last-child {
                        font-size: 8px !important;
                    }

                    #predictions-page #predictions-table-scroll .ak-prediction-donut {
                        display:grid !important;
                        width:2.25rem !important;
                        height:2.25rem !important;
                        flex:0 0 2.25rem !important;
                    }

                    #predictions-page .ak-mobile-score {
                        display:none !important;
                    }
                    #predictions-page #predictions-table-scroll .ak-prediction-donut span { font-size:.58rem !important; }
                    #predictions-page #predictions-table-scroll .ak-prediction-donut small { font-size:.32rem !important; }

                    #predictions-page #predictions-table-scroll .ak-mobile-horizon-signal {
                        display:inline-flex !important;
                        width:2rem !important;
                        min-width:2rem !important;
                        max-width:2rem !important;
                        height:2rem !important;
                        padding:0 !important;
                        border:1px solid currentColor !important;
                        border-radius:.55rem !important;
                        background:color-mix(in srgb,currentColor 13%,transparent) !important;
                        box-shadow:inset 0 1px 0 rgba(255,255,255,.2),0 2px 6px rgba(15,23,42,.1) !important;
                    }
                    #predictions-page .ak-mobile-horizon-signal svg {
                        width:1.15rem !important;
                        height:1.15rem !important;
                        stroke-width:2.2;
                    }
                    #predictions-page .ak-mobile-signal-letter {
                        font-size:.68rem;
                        font-weight:950;
                        line-height:1;
                    }

                    #predictions-page .ak-horizon-column > div {
                        justify-content:flex-end !important;
                        gap:.4rem !important;
                        padding:.15rem .35rem .9rem 0;
                    }
                    #predictions-page .ak-horizon-column .ak-horizon-direction[data-days="5"],
                    #predictions-page .ak-horizon-column .ak-horizon-direction[data-days="10"],
                    #predictions-page .ak-horizon-column .ak-horizon-direction[data-days="15"] {
                        display:none !important;
                    }
                    #predictions-page #predictions-table-scroll .ak-horizon-direction {
                        min-width:2rem !important;
                        width:2rem !important;
                        max-width:2rem !important;
                        flex:0 0 2rem !important;
                        height:2rem !important;
                        padding:.1rem !important;
                        border-width:1px !important;
                    }
                    #predictions-page .ak-horizon-direction b { font-size:6px !important; }
                    #predictions-page .ak-horizon-direction svg { width:.75rem !important; height:.75rem !important; }
                    #predictions-page .ak-mobile-horizon-score {
                        display:grid !important;
                        position:relative !important;
                        width:2rem !important;
                        min-width:2rem !important;
                        height:2rem !important;
                        flex:0 0 2rem !important;
                        margin-right:.25rem !important;
                    }
                    #predictions-page .ak-mobile-horizon-score span {
                        position:absolute !important;
                        inset:0 !important;
                        display:grid !important;
                        margin:0 !important;
                        place-items:center !important;
                        color:var(--ak-text) !important;
                        font-size:.68rem !important;
                        font-weight:950 !important;
                        line-height:1 !important;
                        text-align:center !important;
                    }
                    #predictions-page .ak-mobile-risk-logo {
                        display:grid !important;
                        width:2rem;
                        min-width:2rem;
                        height:2rem;
                        flex:0 0 2rem !important;
                        place-items:center;
                        border-radius:999px;
                    }
                    #predictions-page .ak-mobile-score-sort { display:inline-flex !important; }
                    #predictions-page .ak-desktop-prognosis-heading { display:none !important; }
                    #predictions-page .ak-mobile-horizon-score::before,
                    #predictions-page .ak-mobile-horizon-signal::after,
                    #predictions-page .ak-horizon-direction[data-days="20"]::after,
                    #predictions-page .ak-mobile-risk-logo::before {
                        content:attr(data-label);
                        position:absolute;
                        left:50%;
                        top:calc(100% + .22rem);
                        transform:translateX(-50%);
                        color:var(--ak-muted);
                        font-size:5.5px;
                        font-weight:900;
                        line-height:1;
                        letter-spacing:.01em;
                        text-transform:uppercase;
                        white-space:nowrap;
                    }
                    #predictions-page .ak-mobile-horizon-score::before,
                    #predictions-page .ak-mobile-risk-logo::before {
                        background:transparent;
                    }
                    #predictions-page .ak-mobile-risk-logo::before { content:none; }
                    #predictions-page .ak-mobile-horizon-signal,
                    #predictions-page .ak-horizon-direction[data-days="20"],
                    #predictions-page .ak-mobile-risk-logo { position:relative !important; overflow:visible !important; }
                    #predictions-page .ak-predictions-heading-row th:nth-child(9) {
                        white-space:normal !important;
                        font-size:7px !important;
                        line-height:1.05 !important;
                    }
                    #predictions-page .prediction-row > td:nth-child(3) {
                        text-align:left !important;
                    }
                }
            </style>

        </section>
    </div>

    <div id="prediction-heatmap-modal" class="fixed inset-0 z-[95] hidden place-items-center bg-slate-950/75 p-4 backdrop-blur-sm" role="dialog" aria-modal="true" aria-labelledby="prediction-heatmap-title">
        <section class="flex max-h-[94dvh] w-full max-w-[1500px] flex-col overflow-hidden rounded-3xl border border-teal-400/25 bg-[#102d34]/90 shadow-2xl shadow-black/70">
            <div class="flex shrink-0 items-start justify-between gap-4 border-b border-white/10 px-5 py-4">
                <div>
                    <p class="text-[10px] font-black uppercase tracking-[.16em] text-teal-300">{{ __('Historische Validierung') }}</p>
                    <h2 id="prediction-heatmap-title" class="mt-1 text-xl font-black text-white">{{ __('Historische Qualität nach KI-Score und Konfidenz') }}</h2>
                    <p class="mt-1 text-xs text-slate-400">{{ __('Trefferquote, Profitfaktor, Drawdown und Trades; alle aktuellen Filter werden berücksichtigt.') }}</p>
                </div>
                <button type="button" data-close-prediction-heatmap class="inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-xl text-slate-500 transition hover:bg-white/5 hover:text-white" aria-label="{{ __('Schließen') }}">
                    <x-heroicon-o-x-mark class="h-5 w-5" />
                </button>
            </div>

            <div class="min-h-0 flex-1 overflow-auto p-4">
                @php
                    $heatmapMetrics = [
                        ['key' => 'hit_rate', 'label' => __('Hitrate'), 'suffix' => '%'],
                        ['key' => 'profit_factor', 'label' => __('Profitfaktor'), 'suffix' => ''],
                        ['key' => 'drawdown', 'label' => __('Drawdown'), 'suffix' => '%'],
                        ['key' => 'samples', 'label' => __('Trades'), 'suffix' => ''],
                    ];
                @endphp
                <div class="mx-auto grid min-w-[1050px] max-w-[1440px] grid-cols-2 gap-4">
                    @foreach ($heatmapMetrics as $metric)
                        <article class="rounded-2xl border border-white/[.08] bg-white/[.025] p-3">
                            <div class="mb-2 flex items-center justify-between gap-3">
                                <h3 class="text-sm font-black text-white">{{ $metric['label'] }}</h3>
                                <span class="text-[9px] font-bold uppercase tracking-wide text-slate-500">{{ __('Y: Konfidenz') }} · {{ __('X: KI-Score') }}</span>
                            </div>
                            <div class="grid grid-cols-[42px_repeat(10,minmax(34px,1fr))] gap-1">
                                @for ($confidenceBucket = 9; $confidenceBucket >= 0; $confidenceBucket--)
                                    <div class="flex items-center justify-end pr-1 text-[8px] font-bold tabular-nums text-slate-500">
                                        {{ $confidenceBucket * 10 }}–{{ ($confidenceBucket + 1) * 10 }}
                                    </div>
                                    @for ($scoreBucket = 0; $scoreBucket <= 9; $scoreBucket++)
                                        @php
                                            $cell = $heatmap->get($scoreBucket.'-'.$confidenceBucket);
                                            $samples = (int) ($cell->samples ?? 0);
                                            $rawValue = $metric['key'] === 'samples'
                                                ? $samples
                                                : (is_numeric(data_get($cell, $metric['key'])) ? (float) data_get($cell, $metric['key']) : null);
                                            $hasValue = $metric['key'] === 'samples' ? $samples > 0 : ($samples >= 5 && $rawValue !== null);
                                            $good = match ($metric['key']) {
                                                'hit_rate' => $rawValue >= 55,
                                                'profit_factor' => $rawValue >= 1.25,
                                                'drawdown' => $rawValue <= 25,
                                                'samples' => $rawValue >= 20,
                                            };
                                            $weak = match ($metric['key']) {
                                                'hit_rate' => $rawValue < 45,
                                                'profit_factor' => $rawValue < 1,
                                                'drawdown' => $rawValue > 45,
                                                'samples' => $rawValue < 5,
                                            };
                                            $cellClass = ! $hasValue
                                                ? 'border-white/[.05] bg-slate-500/[.07] text-slate-600'
                                                : ($good
                                                    ? 'border-emerald-300/25 bg-emerald-400/20 text-emerald-100'
                                                    : ($weak
                                                        ? 'border-rose-400/20 bg-rose-400/15 text-rose-200'
                                                        : 'border-amber-300/20 bg-amber-300/12 text-amber-100'));
                                            $displayValue = ! $hasValue
                                                ? ($samples ?: '—')
                                                : ($metric['key'] === 'profit_factor'
                                                    ? number_format($rawValue, 2, ',', '.')
                                                    : number_format($rawValue, 0, ',', '.').$metric['suffix']);
                                        @endphp
                                        <div
                                            class="flex min-h-7 items-center justify-center rounded-[5px] border {{ $cellClass }}"
                                            title="{{ __('Score :scoreFrom–:scoreTo · Konfidenz :confidenceFrom–:confidenceTo % · :metric: :value · :samples Trades', [
                                                'scoreFrom' => $scoreBucket,
                                                'scoreTo' => $scoreBucket + 1,
                                                'confidenceFrom' => $confidenceBucket * 10,
                                                'confidenceTo' => ($confidenceBucket + 1) * 10,
                                                'metric' => $metric['label'],
                                                'value' => $displayValue,
                                                'samples' => $samples,
                                            ]) }}"
                                        >
                                            <span class="text-[9px] font-black tabular-nums">{{ $displayValue }}</span>
                                        </div>
                                    @endfor
                                @endfor
                                <div></div>
                                @for ($scoreBucket = 0; $scoreBucket <= 9; $scoreBucket++)
                                    <div class="text-center text-[8px] font-bold tabular-nums text-slate-500">{{ $scoreBucket }}–{{ $scoreBucket + 1 }}</div>
                                @endfor
                            </div>
                        </article>
                    @endforeach
                </div>
                <p class="mt-3 text-center text-[10px] text-slate-500">{{ __('Graue Felder enthalten zu wenige validierte Prognosen für eine belastbare Bewertung.') }}</p>
            </div>
        </section>
    </div>

    @if ($userWatchlists->count() > 1)
        <div id="prediction-watchlist-picker" class="fixed inset-0 z-[90] hidden place-items-center bg-slate-950/70 p-4 backdrop-blur-sm" role="dialog" aria-modal="true" aria-labelledby="prediction-watchlist-picker-title">
            <section class="w-full max-w-sm overflow-hidden rounded-2xl border border-teal-400/25 bg-[#102d34]/90 shadow-2xl shadow-black/60">
                <div class="flex items-start justify-between gap-4 border-b border-white/10 px-5 py-4">
                    <div class="min-w-0">
                        <p id="prediction-watchlist-picker-symbol" class="text-[10px] font-black uppercase tracking-[.16em] text-teal-300"></p>
                        <h2 id="prediction-watchlist-picker-title" class="mt-1 text-lg font-black text-white">{{ __('Watchlist auswählen') }}</h2>
                        <p id="prediction-watchlist-picker-name" class="mt-1 truncate text-xs text-slate-400"></p>
                    </div>
                    <button type="button" data-close-watchlist-picker class="inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-lg text-slate-500 transition hover:bg-white/5 hover:text-white" aria-label="{{ __('Schließen') }}">
                        <x-heroicon-o-x-mark class="h-5 w-5" />
                    </button>
                </div>
                <p class="px-5 pt-4 text-xs text-slate-400">{{ __('In welche Watchlist soll die Aktie übernommen werden?') }}</p>
                <div class="max-h-80 space-y-2 overflow-y-auto p-3">
                    @foreach ($userWatchlists as $watchlist)
                        <form method="POST" data-picker-watchlist-form data-watchlist-id="{{ $watchlist->id }}" data-url-template="{{ url('/watchlists/'.$watchlist->id.'/items/__instrument__') }}" data-prediction-watchlist-form>
                            @csrf
                            <input type="hidden" name="prediction_id" value="">
                            <button type="submit" class="flex w-full items-center justify-between gap-3 rounded-xl border border-teal-300/10 bg-teal-950/20 px-4 py-3 text-left transition hover:border-teal-400/30 hover:bg-teal-500/10">
                                <span class="min-w-0">
                                    <strong class="block truncate text-sm text-white">{{ $watchlist->name }}</strong>
                                    @if ($watchlist->is_default)<small class="text-[10px] font-bold text-teal-300">{{ __('Standard') }}</small>@endif
                                </span>
                                <span class="shrink-0">
                                    <x-heroicon-s-star data-picker-filled class="hidden h-5 w-5 text-amber-300" />
                                    <x-heroicon-o-star data-picker-empty class="h-5 w-5 text-slate-600" />
                                </span>
                            </button>
                        </form>
                    @endforeach
                </div>
            </section>
        </div>
    @endif

    <style>
        .prediction-loading-spinner { animation: prediction-spinner-rotate .8s linear infinite; }
        @keyframes prediction-spinner-rotate { to { transform: rotate(360deg); } }
        .aki-thinking-dots {
            display: inline-block;
            min-width: 1.6em;
            letter-spacing: .12em;
            animation: aki-thinking-pulse 1.1s steps(4, end) infinite;
        }
        @keyframes aki-thinking-pulse {
            0%, 20% { opacity: .25; }
            40% { opacity: .65; }
            60%, 100% { opacity: 1; }
        }
        body:has(#predictions-page) {
            overflow: hidden;
        }

        #prediction-filterboard .ak-input {
            height: 2.4rem;
            min-width: 0;
            border-radius: 5px;
            padding-left: .4rem;
            padding-right: .4rem;
            font-size: 10px;
            color: #f8fafc !important;
            -webkit-text-fill-color: #f8fafc !important;
            opacity: 1 !important;
        }

        #prediction-filterboard input[name="q"] {
            padding-left: 2rem;
        }

        #prediction-filterboard .ak-prediction-filterboard-labels span {
            padding: 0 .35rem;
            color: #94a3b8;
            font-size: 7px;
            font-weight: 900;
            line-height: 10px;
            letter-spacing: .07em;
            text-overflow: clip;
            text-transform: uppercase;
            white-space: nowrap;
        }

        #prediction-filterboard select.ak-input {
            appearance: auto !important;
            -webkit-appearance: menulist !important;
            overflow: visible !important;
            padding-right: .25rem !important;
            background-image: none !important;
            font-size: 9px !important;
            white-space: normal !important;
            text-overflow: initial !important;
        }

        #prediction-filterboard .ak-prediction-filterboard-main > div:last-child > a {
            height: 2.4rem;
        }

        #prediction-filterboard .ak-prediction-filter-actions > * {
            width: 8.5rem;
            max-width: calc(50% - .25rem);
        }

        #prediction-filterboard .ak-input::placeholder {
            color: #cbd5e1 !important;
            -webkit-text-fill-color: #cbd5e1 !important;
            opacity: 1 !important;
        }

        #prediction-filterboard select.ak-input option {
            background: #182238;
            color: #f8fafc;
        }

        :root[data-theme="light"] #prediction-filterboard .ak-input,
        :root[data-theme="light"] #prediction-filterboard .ak-input::placeholder {
            color: #334155 !important;
            -webkit-text-fill-color: #334155 !important;
        }

        :root[data-theme="light"] #prediction-filterboard select.ak-input option {
            background: #f8fafc;
            color: #0f172a;
        }

        #prediction-filterboard .ak-heatmap-range {
            display: grid;
            min-width: 0;
            height: 32px;
            grid-template-rows: 13px 1fr;
            align-items: center;
            border: 1px solid var(--ak-border);
            border-radius: 5px;
            padding: 2px 7px 3px;
            background: #182238;
        }

        #prediction-filterboard .ak-heatmap-range span {
            overflow: hidden;
            color: #cbd5e1;
            font-size: 8px;
            font-weight: 800;
            line-height: 1;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        #prediction-filterboard .ak-heatmap-range b {
            color: #fb923c;
            font-weight: 900;
        }

        #prediction-filterboard .ak-heatmap-range input {
            width: 100%;
            height: 12px;
            margin: 0;
            cursor: pointer;
            accent-color: #0ea5b7;
            opacity: .78;
        }

        :root[data-theme="light"] #prediction-filterboard .ak-heatmap-range {
            border-color: rgba(8, 145, 178, .24) !important;
            background: rgba(247, 252, 251, .96) !important;
        }

        :root[data-theme="light"] #prediction-filterboard .ak-heatmap-range span {
            color: #475569 !important;
            -webkit-text-fill-color: #475569 !important;
            font-weight: 850;
            opacity: 1 !important;
        }

        :root[data-theme="light"] #prediction-filterboard .ak-heatmap-range b {
            color: #0e7490 !important;
            -webkit-text-fill-color: #0e7490 !important;
        }

        :root[data-theme="light"] #prediction-filterboard .ak-heatmap-range input {
            filter: contrast(.82) saturate(.82);
            opacity: .82;
        }

        @media (max-width: 1100px) {
            #prediction-filterboard {
                overflow-x: auto;
            }

                #prediction-filterboard .ak-prediction-filterboard-labels,
                #prediction-filterboard .ak-prediction-filterboard-main {
                    grid-template-columns: minmax(140px, 1.35fr) repeat({{ $canUseSmartLabels ? 6 : 5 }}, minmax(105px, 1fr)) 286px !important;
                }
        }

        @media (max-width: 639px) {
            body:has(#predictions-page) {
                overflow-y:auto !important;
            }

            #predictions-page {
                height:auto !important;
                max-height:none !important;
                min-height:calc(100dvh - 89px) !important;
                overflow:visible !important;
            }

            #predictions-page > section {
                min-height:0;
                flex:none;
                overflow:visible;
            }

            #prediction-filterboard {
                width:100%;
                max-height:none;
                overflow-x:hidden !important;
                overflow-y:visible !important;
                gap:.5rem;
                padding:.75rem;
            }

            #prediction-filterboard .ak-prediction-filterboard-main,
            #prediction-filterboard .ak-prediction-range-grid {
                display:grid !important;
                min-width:0 !important;
                width:100% !important;
                grid-template-columns:minmax(0,1fr) !important;
                gap:.55rem !important;
            }

            #prediction-filterboard .ak-prediction-filterboard-main > *,
            #prediction-filterboard .ak-prediction-range-grid > * {
                width:100% !important;
                min-width:0 !important;
            }

            #prediction-filterboard .ak-prediction-filterboard-main > div:last-child {
                display:flex !important;
                width:100%;
                flex-direction:column;
                gap:.55rem;
            }

            #prediction-filterboard .ak-prediction-filterboard-main > div:last-child > a,
            #prediction-filterboard .ak-prediction-filterboard-main > div:last-child > select,
            #prediction-filterboard .ak-prediction-filterboard-main > div:last-child > span {
                width:100% !important;
                flex:none !important;
            }

            #prediction-filterboard .ak-heatmap-range {
                height:2.75rem;
                grid-template-rows:1rem 1fr;
                padding:.35rem .75rem .45rem;
            }

            #prediction-filterboard .ak-heatmap-range span {
                font-size:.62rem;
                text-overflow:initial;
            }

            #prediction-filterboard .ak-prediction-filter-actions {
                width:100%;
                margin-top:.25rem;
            }
        }
    </style>

    <div id="aki-chat-modal" class="fixed inset-0 z-[200] hidden place-items-center bg-slate-950/60 p-4 backdrop-blur-sm" data-aki-chat-modal>
        <section class="max-h-[88vh] w-full max-w-2xl overflow-hidden rounded-2xl border border-teal-300/45 text-slate-100 shadow-2xl" style="background:linear-gradient(145deg,rgba(14,38,57,.98),rgba(8,25,42,.98)) !important;">
            <header class="flex items-center justify-between border-b border-teal-300/25 px-4 py-3" style="background:linear-gradient(110deg,rgba(6, 182, 212,.18),rgba(245,158,11,.12)) !important;">
                <div><p class="text-[10px] font-black uppercase tracking-[.16em] text-amber-500">{{ __('Assistent') }}</p><h2 class="text-base font-black">{{ __('AKI fragen') }}</h2></div>
                <button type="button" data-aki-chat-clear class="ml-auto mr-1 rounded-lg border border-amber-300/80 bg-amber-500 px-2.5 py-1.5 text-[9px] font-black text-white shadow-sm hover:bg-amber-400">{{ __('Verlauf löschen') }}</button>
                <button type="button" data-aki-chat-close class="rounded-lg p-2 text-[var(--ak-muted)] hover:bg-[var(--ak-surface-muted)]" aria-label="{{ __('Chat schließen') }}"><x-heroicon-o-x-mark class="h-5 w-5" /></button>
            </header>
            <div id="aki-chat-messages" class="max-h-[72vh] space-y-2 overflow-y-auto p-5" style="background:rgba(9,28,45,.82) !important;"><p class="max-w-[92%] rounded-xl border border-teal-300/20 bg-slate-700/70 px-3 py-2 text-xs leading-5 text-slate-100">{{ __('Ich helfe dir bei der Auswahl und Erklärung deiner Prognosefilter.') }}</p></div>
            <form onsubmit="return window.akiAsk(event)" class="flex gap-2 border-t border-teal-300/20 p-3" style="background:rgba(10,30,47,.96) !important;">
                <input id="aki-chat-input" type="text" class="min-w-0 flex-1 rounded-lg border border-teal-300/30 bg-slate-950/65 px-3 py-2 text-xs text-slate-100 placeholder:text-slate-400" placeholder="{{ __('Wie setze ich den Profitfaktor?') }}" autocomplete="off">
                <button type="submit" class="rounded-lg bg-teal-600 px-3 py-2 text-xs font-black text-white hover:bg-teal-500">{{ __('Senden') }}</button>
            </form>
        </section>
    </div>

    <script>
        const akiChatStorageKey = 'aktienki:prediction-chat';
        const akiChatHistory = JSON.parse(localStorage.getItem(akiChatStorageKey) || '[]');
        const akiChatMessages = document.getElementById('aki-chat-messages');
        if (akiChatMessages && akiChatHistory.length) {
            akiChatMessages.innerHTML = '';
            akiChatHistory.forEach((entry) => { const item = document.createElement('p'); item.className = `max-w-[88%] whitespace-pre-line rounded-xl border px-3 py-2 text-xs ${entry.role === 'user' ? 'ml-auto border-teal-300/30 bg-teal-600 text-white' : 'border-teal-300/15 bg-slate-700/70 text-slate-100'}`; item.textContent = entry.content; akiChatMessages.appendChild(item); });
        }
        document.querySelector('[data-aki-chat-clear]')?.addEventListener('click', () => { localStorage.removeItem(akiChatStorageKey); akiChatHistory.length = 0; if (akiChatMessages) akiChatMessages.innerHTML = '<p class="max-w-[88%] rounded-xl border border-teal-300/15 bg-slate-700/70 px-3 py-2 text-xs text-slate-100">{{ __('Der Chatverlauf wurde gelöscht.') }}</p>'; });
        window.akiAsk = window.akiAsk || async function (event) {
            event.preventDefault();
            const input = document.getElementById('aki-chat-input');
            const messages = document.getElementById('aki-chat-messages');
            const question = (input?.value || '').trim();
            if (!question || !messages) return false;
            const bubble = document.createElement('p');
            bubble.className = 'ml-auto max-w-[88%] rounded-xl bg-teal-500 px-3 py-2 text-xs text-white';
            bubble.textContent = question;
            messages.appendChild(bubble); input.value = '';
            akiChatHistory.push({ role: 'user', content: question });
            const pending = document.createElement('p');
            pending.id = 'aki-chat-pending';
            pending.className = 'flex max-w-[92%] items-center gap-2 rounded-xl border border-amber-300/20 bg-slate-700/70 px-3 py-2 text-xs text-amber-200';
            pending.innerHTML = '<span>AKI denkt</span><span class="aki-thinking-dots" aria-hidden="true">•••</span>';
            messages.appendChild(pending);
            try {
                const response = await fetch('{{ route('aki.chat') }}', { method: 'POST', headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '' }, body: JSON.stringify({ question, messages: akiChatHistory.slice(-8), filters: Object.fromEntries(new URLSearchParams(window.location.search)), mode: 'standard' }) });
                const payload = await response.json();
                pending.remove();
                const answerText = response.ok ? (payload.answer || '{{ __('Keine Antwort erhalten.') }}') : (payload.message || '{{ __('Die KI ist gerade nicht erreichbar.') }}');
                const answer = document.createElement('p'); answer.className = 'max-w-[88%] whitespace-pre-line rounded-xl border border-teal-300/15 bg-slate-700/70 px-3 py-2 text-xs text-slate-100'; answer.textContent = answerText; messages.appendChild(answer); akiChatHistory.push({ role: 'assistant', content: answerText }); localStorage.setItem(akiChatStorageKey, JSON.stringify(akiChatHistory.slice(-16)));
                if (response.ok && payload.filter_suggestion && Object.keys(payload.filter_suggestion).length) {
                    const apply = document.createElement('button'); apply.type = 'button'; apply.className = 'mt-2 rounded-lg border border-teal-500/40 bg-teal-500/15 px-3 py-2 text-[10px] font-black text-teal-700 dark:text-teal-200'; apply.textContent = '{{ __('Filter anwenden') }}';
                    const applyFilters = () => { const loading = document.getElementById('prediction-page-loading'); if (loading) { loading.classList.remove('hidden'); loading.classList.add('flex'); loading.style.display = 'flex'; } const params = new URLSearchParams(); Object.entries(payload.filter_suggestion).forEach(([key, value]) => { if (Array.isArray(value)) value.forEach((item) => params.append(`${key}[]`, item)); else if (value !== null && value !== '') params.set(key, value); }); window.location.href = '{{ route('predictions.index') }}?' + params.toString(); };
                    apply.addEventListener('click', applyFilters); messages.appendChild(apply);
                    if (Array.isArray(payload.filter_suggestion.symbols) && payload.filter_suggestion.symbols.length) window.setTimeout(applyFilters, 900);
                }
                messages.scrollTop = messages.scrollHeight;
            } catch (_) { pending.remove(); const answer = document.createElement('p'); answer.className = 'max-w-[88%] rounded-xl bg-rose-500/15 px-3 py-2 text-xs text-rose-500'; answer.textContent = '{{ __('Die Verbindung zur KI konnte nicht hergestellt werden.') }}'; messages.appendChild(answer); }
            return false;
        };

        document.addEventListener('DOMContentLoaded', () => {
            const tableScroll = document.querySelector('#predictions-table-scroll');
            if (!tableScroll) return;
            const loading = document.querySelector('#prediction-page-loading');
            const showLoading = () => { if (!loading) return; loading.classList.remove('hidden'); loading.classList.add('flex'); loading.style.display = 'flex'; };
            const delayedSubmit = (event) => {
                event.preventDefault();
                const form = event.currentTarget;
                showLoading();
                window.setTimeout(() => HTMLFormElement.prototype.submit.call(form), 500);
            };
            document.querySelector('#prediction-filterboard')?.addEventListener('submit', delayedSubmit);
            document.querySelector('#prediction-table-filters')?.addEventListener('submit', delayedSubmit);
            document.querySelectorAll('a[href$="/predictions"], a[title*="zurücksetzen"], a[title*="reset"]').forEach((link) => link.addEventListener('click', (event) => {
                event.preventDefault();
                const href = link.href;
                showLoading();
                window.setTimeout(() => { window.location.href = href; }, 120);
            }));

            const storageKey = `aktienki:predictions-scroll:${window.location.pathname}${window.location.search}`;

            try {
                const savedPosition = JSON.parse(sessionStorage.getItem(storageKey) || 'null');
                if (savedPosition) {
                    requestAnimationFrame(() => {
                        tableScroll.scrollTop = Number(savedPosition.top) || 0;
                        tableScroll.scrollLeft = Number(savedPosition.left) || 0;
                    });
                    sessionStorage.removeItem(storageKey);
                }
            } catch (_) {
                sessionStorage.removeItem(storageKey);
            }

            document.querySelectorAll('[data-prediction-watchlist-form]').forEach(form => {
                form.addEventListener('submit', () => {
                    sessionStorage.setItem(storageKey, JSON.stringify({
                        top: tableScroll.scrollTop,
                        left: tableScroll.scrollLeft,
                    }));
                });
            });

            const picker = document.querySelector('#prediction-watchlist-picker');
            const pickerSymbol = document.querySelector('#prediction-watchlist-picker-symbol');
            const pickerName = document.querySelector('#prediction-watchlist-picker-name');
            const pickerForms = document.querySelectorAll('[data-picker-watchlist-form]');
            const closePicker = () => {
                picker?.classList.add('hidden');
                picker?.classList.remove('grid');
            };

            document.querySelectorAll('[data-open-watchlist-picker]').forEach(button => {
                button.addEventListener('click', () => {
                    const memberships = JSON.parse(button.dataset.memberships || '[]').map(Number);
                    pickerSymbol.textContent = button.dataset.symbol || '';
                    pickerName.textContent = button.dataset.name || '';

                    pickerForms.forEach(form => {
                        const isMember = memberships.includes(Number(form.dataset.watchlistId));
                        form.action = form.dataset.urlTemplate.replace('__instrument__', button.dataset.instrumentId);
                        form.querySelector('input[name="prediction_id"]').value = button.dataset.predictionId;
                        form.querySelector('[data-picker-filled]').classList.toggle('hidden', !isMember);
                        form.querySelector('[data-picker-empty]').classList.toggle('hidden', isMember);
                    });

                    picker.classList.remove('hidden');
                    picker.classList.add('grid');
                });
            });

            document.querySelectorAll('[data-close-watchlist-picker]').forEach(button => button.addEventListener('click', closePicker));
            picker?.addEventListener('click', event => {
                if (event.target === picker) closePicker();
            });
            document.addEventListener('keydown', event => {
                if (event.key === 'Escape') closePicker();
            });

            const heatmapModal = document.querySelector('#prediction-heatmap-modal');
            const closeHeatmap = () => {
                heatmapModal?.classList.add('hidden');
                heatmapModal?.classList.remove('grid');
            };
            document.querySelectorAll('[data-open-prediction-heatmap]').forEach(button => button.addEventListener('click', () => {
                heatmapModal?.classList.remove('hidden');
                heatmapModal?.classList.add('grid');
            }));
            document.querySelectorAll('[data-close-prediction-heatmap]').forEach(button => button.addEventListener('click', closeHeatmap));
            heatmapModal?.addEventListener('click', event => {
                if (event.target === heatmapModal) closeHeatmap();
            });
            document.addEventListener('keydown', event => {
                if (event.key === 'Escape') closeHeatmap();
            });
        });
    </script>
</x-app-layout>
