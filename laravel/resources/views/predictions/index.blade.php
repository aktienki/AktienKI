<x-app-layout>
    <div id="predictions-page" class="flex h-[calc(100dvh-89px)] min-h-0 max-h-[calc(100dvh-89px)] flex-col overflow-hidden py-2 text-[var(--ak-text)]">
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
                    [__('Prognosen'), (int) ($summary->total ?? 0)],
                    [__('Aktien'), (int) ($summary->instruments ?? 0)],
                    [__('Validiert'), (int) ($summary->validated ?? 0)],
                    [__('Ältestes Training'), $summary?->oldest_training ? \Illuminate\Support\Carbon::parse($summary->oldest_training)->timezone(config('app.timezone'))->format('d.m.Y') : '—'],
                ] as [$label, $value])
                    <div class="ak-predictions-card-surface min-w-0 rounded-lg px-2 py-1.5 sm:px-2.5 xl:min-w-28">
                        <p class="truncate text-[8px] font-black uppercase tracking-[.08em] text-[var(--ak-muted)] sm:text-[9px] sm:tracking-[.12em]">{{ $label }}</p>
                        <p class="mt-1 truncate text-xs font-black tabular-nums sm:text-sm">{{ is_int($value) ? number_format($value, 0, ',', '.') : $value }}</p>
                    </div>
                @endforeach
            </div>
        </div>

        <section class="flex min-h-0 flex-1 flex-col gap-3">
            <form
                id="prediction-filterboard"
                method="GET"
                action="{{ route('predictions.index') }}"
                x-data="{
                    score: Number({{ (float) request('score_min', 0) }}),
                    confidence: Number({{ (float) request('confidence_min', 0) }}),
                    drawdown: Number({{ (float) request('drawdown_max', 50) }}),
                    profitFactor: Number({{ (float) request('profit_factor_min', 0) }}),
                    hitRate: Number({{ (float) request('hit_rate_min', 0) }}),
                    volatility: Number({{ (float) request('volatility_max', 100) }}),
                    searchTimer: null,
                    submitSearch() { window.clearTimeout(this.searchTimer); this.searchTimer = window.setTimeout(() => this.$root.requestSubmit(), 450) }
                }"
                class="ak-prediction-filterboard z-50 flex shrink-0 flex-col gap-1 rounded-xl border border-[var(--ak-border)] bg-[var(--ak-card)] p-2 shadow-[var(--ak-shadow)]"
            >
                <input type="hidden" name="sort" value="{{ $sort }}">
                <input type="hidden" name="direction" value="{{ $direction }}">
                <div class="ak-prediction-filterboard-main grid min-w-[760px] items-center gap-1" style="grid-template-columns:minmax(150px,1.35fr) repeat({{ $canUseSmartLabels ? 6 : 5 }},minmax(105px,1fr)) 34px">
                <label class="relative min-w-0">
                    <span class="sr-only">{{ __('Aktie suchen') }}</span>
                    <x-heroicon-o-magnifying-glass class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-[var(--ak-muted)]" />
                    <input name="q" value="{{ request('q') }}" @input="submitSearch()" placeholder="{{ __('Aktie / Symbol') }}" class="ak-input h-10 w-full pl-8 pr-2 text-xs">
                </label>
                <select name="country" @change="$root.requestSubmit()" class="ak-input h-10 w-full min-w-0 px-1.5 text-[11px]" title="{{ __('Land') }}">
                    <option value="">{{ __('Land') }}</option>
                    @foreach ($countries as $country)
                        <option value="{{ $country }}" @selected(strtoupper((string) request('country')) === strtoupper((string) $country))>{{ $country }}</option>
                    @endforeach
                </select>
                <select name="exchange" @change="$root.requestSubmit()" class="ak-input h-10 w-full min-w-0 px-1.5 text-[11px]" title="{{ __('Exchange') }}">
                    <option value="">{{ __('Exchange') }}</option>
                    @foreach ($exchanges as $exchange)
                        <option value="{{ $exchange->code }}" @selected(strtoupper((string) request('exchange')) === strtoupper((string) $exchange->code))>{{ $exchange->code }}</option>
                    @endforeach
                </select>
                <select name="sector" @change="$root.requestSubmit()" class="ak-input h-10 w-full min-w-0 px-1.5 text-[11px]" title="{{ __('Sektor') }}">
                    <option value="">{{ __('Sektor') }}</option>
                    @foreach ($sectors as $sector)
                        <option value="{{ $sector }}" @selected((string) request('sector') === (string) $sector)>{{ __($sector) }}</option>
                    @endforeach
                </select>
                @if ($canUseSmartLabels)
                    <select name="smart_label" @change="$root.requestSubmit()" class="ak-input h-10 w-full min-w-0 px-1.5 text-[11px]" title="{{ __('Smart Selection') }}">
                        <option value="">{{ __('Label') }}</option>
                        @foreach ($smartLabels as $smartLabel)
                            <option value="{{ $smartLabel->id }}" @selected((int) request('smart_label') === (int) $smartLabel->id)>{{ $smartLabel->name }}</option>
                        @endforeach
                    </select>
                @endif
                <select name="quality_tier" @change="$root.requestSubmit()" class="ak-input h-10 w-full min-w-0 px-1.5 text-[11px]" title="{{ __('Modellstufe mindestens') }}">
                    <option value="">{{ __('Modellstufe') }}</option>
                    @foreach ($qualityTiers as $qualityTier)
                        <option value="{{ $qualityTier->code }}" @selected(request('quality_tier') === $qualityTier->code)>{{ __($qualityTier->name) }}</option>
                    @endforeach
                </select>
                <select name="signal" @change="$root.requestSubmit()" class="ak-input h-10 w-full min-w-0 px-1.5 text-[11px]" title="{{ __('Signal') }}">
                    <option value="">{{ __('Signal') }}</option>
                    @foreach (['BUY', 'WATCH', 'HOLD', 'SELL'] as $signal)
                        @continue(! $signals->contains($signal))
                        <option value="{{ $signal }}" @selected(strtoupper((string) request('signal')) === $signal)>{{ $signal }}</option>
                    @endforeach
                </select>
                <div class="flex min-w-0">
                    <a href="{{ route('predictions.index') }}" class="inline-flex h-8 w-full shrink-0 items-center justify-center rounded-[5px] border border-[var(--ak-border)] text-[var(--ak-muted)] transition hover:border-teal-500/35 hover:bg-teal-500/10 hover:text-teal-700" title="{{ __('Filter zurücksetzen') }}">
                        <x-heroicon-o-arrow-path class="h-4 w-4 shrink-0" />
                    </a>
                </div>
                </div>
                <div class="grid grid-cols-6 gap-1">
                    <label class="ak-heatmap-range">
                        <span>{{ __('KI-Score') }} ≥ <b x-text="score.toFixed(1).replace('.', ',')"></b></span>
                        <input name="score_min" type="range" min="0" max="10" step="0.5" x-model.number="score" @change="$root.requestSubmit()">
                    </label>
                    <label class="ak-heatmap-range">
                        <span>{{ __('Konfidenz') }} ≥ <b x-text="`${confidence}%`"></b></span>
                        <input name="confidence_min" type="range" min="0" max="100" step="5" x-model.number="confidence" @change="$root.requestSubmit()">
                    </label>
                    <label class="ak-heatmap-range">
                        <span>{{ __('Drawdown') }} ≤ <b x-text="drawdown >= 50 ? '{{ __('Alle') }}' : `${drawdown}%`"></b></span>
                        <input name="drawdown_max" type="range" min="0" max="50" step="5" x-model.number="drawdown" @change="$root.requestSubmit()">
                    </label>
                    <label class="ak-heatmap-range">
                        <span>{{ __('Profitfaktor') }} ≥ <b x-text="profitFactor <= 0 ? '{{ __('Alle') }}' : profitFactor.toFixed(1).replace('.', ',')"></b></span>
                        <input name="profit_factor_min" type="range" min="0" max="10" step="0.1" x-model.number="profitFactor" @change="$root.requestSubmit()">
                    </label>
                    <label class="ak-heatmap-range">
                        <span>{{ __('Trefferquote') }} ≥ <b x-text="hitRate <= 0 ? '{{ __('Alle') }}' : `${hitRate}%`"></b></span>
                        <input name="hit_rate_min" type="range" min="0" max="100" step="5" x-model.number="hitRate" @change="$root.requestSubmit()">
                    </label>
                    <label class="ak-heatmap-range">
                        <span>{{ __('Volatilität') }} ≤ <b x-text="volatility >= 100 ? '{{ __('Alle') }}' : `${volatility}%`"></b></span>
                        <input name="volatility_max" type="range" min="0" max="100" step="5" x-model.number="volatility" @change="$root.requestSubmit()">
                    </label>
                </div>
            </form>

            <div id="predictions-table-scroll" class="min-h-0 flex-1 overscroll-contain overflow-y-auto overflow-x-hidden rounded-xl border border-[var(--ak-border)] bg-[var(--ak-card)] shadow-[var(--ak-shadow)]">
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
                <table class="ak-stocks-table w-full table-fixed border-separate border-spacing-0 text-left text-[11px]">
                    <colgroup>
                        @if ($canUseSmartLabels)
                        <col style="width: 3.5%;"><col style="width: 8%;"><col style="width: 14%;"><col style="width: 6%;"><col style="width: 10%;"><col style="width: 10%;"><col style="width: 10%;"><col style="width: 8%;"><col style="width: 7%;"><col style="width: 7%;"><col style="width: 8%;"><col style="width: 8.5%;">
                        @else
                        <col style="width: 3.5%;">
                        <col style="width: 9%;">
                        <col style="width: 16%;">
                        <col style="width: 7%;">
                        <col style="width: 11%;">
                        <col style="width: 13%;">
                        <col style="width: 8%;">
                        <col style="width: 8%;">
                        <col style="width: 8%;">
                        <col style="width: 8%;">
                        <col style="width: 8.5%;">
                        @endif
                    </colgroup>
                    <thead class="sticky top-0 z-20 bg-[#12343b] text-[10px] font-black uppercase tracking-[.1em] text-slate-300 shadow-[0_1px_0_rgba(45,212,191,.20),0_8px_18px_rgba(0,0,0,.22)]">
                        <tr class="ak-predictions-heading-row h-11">
                            <th class="border-b border-[var(--ak-border)] px-3 py-3 text-center" aria-label="{{ __('Watchlist') }}">
                                <x-heroicon-o-star class="mx-auto h-4 w-4 text-[var(--ak-muted)]" />
                            </th>
                            @foreach ([
                                ['time', __('Zeitpunkt'), 'text-left'],
                                ['stock', __('Aktie'), 'text-left'],
                                ['signal', __('Signal'), 'text-center'],
                                ['score', __('KI-Score'), 'text-center'],
                                ...($canUseSmartLabels ? [[null, __('Label'), 'text-left']] : []),
                                ['model', __('Modellqualität'), 'text-center'],
                                ['price', __('Kurs'), 'text-right'],
                                ['return_5d', __('5 Tage'), 'text-right'],
                                ['return_20d', __('20 Tage'), 'text-right'],
                                ['confidence', __('Konfidenz'), 'text-center'],
                                ['risk', __('Risiko'), 'text-center'],
                            ] as [$column, $heading, $alignment])
                                <th class="border-b border-[var(--ak-border)] px-2 py-3 {{ $alignment }}">
                                    @if ($column)
                                    <a href="{{ $sortUrl($column) }}" class="inline-flex max-w-full items-center gap-1 whitespace-nowrap transition hover:text-teal-200 {{ $sort === $column ? 'text-teal-200' : '' }}">
                                        <span class="truncate">{{ $heading }}</span>
                                        <span class="inline-block w-3 shrink-0 text-center text-[11px] {{ $sort === $column ? 'text-teal-200' : 'text-slate-600' }}">{{ $sortIndicator($column) }}</span>
                                    </a>
                                    @else<span class="truncate">{{ $heading }}</span>@endif
                                </th>
                            @endforeach
                        </tr>
                        <tr class="ak-predictions-filter-row hidden bg-[#12343b]" aria-hidden="true">
                            <th colspan="{{ $canUseSmartLabels ? 12 : 11 }}" class="border-b border-[var(--ak-border)] p-1.5 normal-case tracking-normal">
                                <div class="flex min-w-0 items-center gap-1.5 whitespace-nowrap">
                                    <a href="{{ route('predictions.index') }}" class="inline-flex h-8 w-9 shrink-0 items-center justify-center rounded-[5px] border border-[var(--ak-border)] text-[var(--ak-muted)] hover:bg-teal-500/10 hover:text-teal-500" title="{{ __('Filter zurücksetzen') }}"><x-heroicon-o-arrow-path class="h-4 w-4" /></a>
                                    <label class="relative min-w-28 flex-[1.35]"><x-heroicon-o-magnifying-glass class="pointer-events-none absolute left-2 top-1/2 h-3.5 w-3.5 -translate-y-1/2 text-[var(--ak-muted)]" /><input form="prediction-table-filters" name="q" value="{{ request('q') }}" placeholder="{{ __('Aktie') }}" class="ak-input ak-table-filter h-8 w-full min-w-0 pl-7 pr-1 text-[10px]" oninput="window.clearTimeout(this._filterTimer);this._filterTimer=window.setTimeout(()=>this.form.requestSubmit(),450)"></label>
                                    <select form="prediction-table-filters" name="country" onchange="this.form.requestSubmit()" class="ak-input ak-table-filter h-8 min-w-0 flex-1 px-1 text-[9px]" title="{{ __('Land') }}"><option value="">{{ __('Land') }}</option>@foreach ($countries as $country)<option value="{{ $country }}" @selected(strtoupper((string) request('country')) === strtoupper((string) $country))>{{ $country }}</option>@endforeach</select>
                                    <select form="prediction-table-filters" name="exchange" onchange="this.form.requestSubmit()" class="ak-input ak-table-filter h-8 min-w-0 flex-1 px-1 text-[9px]" title="{{ __('Exchange') }}"><option value="">{{ __('Börse') }}</option>@foreach ($exchanges as $exchange)<option value="{{ $exchange->code }}" @selected(strtoupper((string) request('exchange')) === strtoupper((string) $exchange->code))>{{ $exchange->code }}</option>@endforeach</select>
                                    <select form="prediction-table-filters" name="sector" onchange="this.form.requestSubmit()" class="ak-input ak-table-filter h-8 min-w-0 flex-[1.2] px-1 text-[9px]" title="{{ __('Sektor') }}"><option value="">{{ __('Sektor') }}</option>@foreach ($sectors as $sector)<option value="{{ $sector }}" @selected((string) request('sector') === (string) $sector)>{{ __($sector) }}</option>@endforeach</select>
                                    @if ($canUseSmartLabels)<select form="prediction-table-filters" name="smart_label" onchange="this.form.requestSubmit()" class="ak-input ak-table-filter h-8 min-w-0 flex-1 px-1 text-[9px]" title="{{ __('Smart Selection') }}"><option value="">{{ __('Label') }}</option>@foreach ($smartLabels as $smartLabel)<option value="{{ $smartLabel->id }}" @selected((int) request('smart_label') === (int) $smartLabel->id)>{{ $smartLabel->name }}</option>@endforeach</select>@endif
                                    <select form="prediction-table-filters" name="model" onchange="this.form.requestSubmit()" class="ak-input ak-table-filter h-8 min-w-0 flex-[1.2] px-1 text-[9px]" title="{{ __('Modell') }}"><option value="">{{ __('Modell') }}</option>@foreach ($models as $model)<option value="{{ $model->id }}" @selected((int) request('model') === (int) $model->id)>{{ $model->public_alias }}</option>@endforeach</select>
                                    <select form="prediction-table-filters" name="ai_type" onchange="this.form.requestSubmit()" class="ak-input ak-table-filter h-8 min-w-0 flex-1 px-1 text-[9px]" title="{{ __('KI-Typ') }}"><option value="">{{ __('KI') }}</option>@foreach ($aiTypes as $aiType)<option value="{{ $aiType }}" @selected(request('ai_type') === $aiType)>{{ ucfirst((string) $aiType) }}</option>@endforeach</select>
                                    <select form="prediction-table-filters" name="quality_tier" onchange="this.form.requestSubmit()" class="ak-input ak-table-filter h-8 min-w-0 flex-[1.15] px-1 text-[9px]" title="{{ __('Modellstufe mindestens') }}"><option value="">{{ __('Min. Stufe') }}</option>@foreach ($qualityTiers as $qualityTier)<option value="{{ $qualityTier->code }}" @selected(request('quality_tier') === $qualityTier->code)>{{ __($qualityTier->name) }}</option>@endforeach</select>
                                    <select form="prediction-table-filters" name="signal" onchange="this.form.requestSubmit()" class="ak-input ak-table-filter h-8 min-w-0 flex-1 px-1 text-[9px]" title="{{ __('Signal') }}"><option value="">{{ __('Signal') }}</option>@foreach (['BUY', 'WATCH', 'HOLD', 'SELL'] as $signal)@continue(! $signals->contains($signal))<option value="{{ $signal }}" @selected(strtoupper((string) request('signal')) === $signal)>{{ $signal }}</option>@endforeach</select>
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
                                $signalStyle = match ($signal) {
                                    'BUY' => 'background:rgba(20,184,166,.16);border-color:#2dd4bf;color:#5eead4;box-shadow:0 0 11px rgba(45,212,191,.20)',
                                    'WATCH' => 'background:rgba(132,169,94,.12);border-color:#84a95e;color:#a8c879;box-shadow:0 0 9px rgba(132,169,94,.12)',
                                    'SELL' => 'background:transparent;border-color:#177c75;color:#55cfc2;box-shadow:none',
                                    default => 'background:rgba(217,119,6,.09);border-color:#d97706;color:#f59e0b;box-shadow:none',
                                };
                                $signalIcon = match ($signal) {
                                    'BUY' => 'heroicon-o-arrow-trending-up',
                                    'WATCH' => 'heroicon-o-eye',
                                    'SELL' => 'heroicon-o-arrow-trending-down',
                                    default => 'heroicon-o-pause',
                                };
                                $currency = $prediction->currency ?: 'EUR';
                                $score = is_numeric($prediction->score_10) ? max(0, min(10, (float) $prediction->score_10)) : null;
                                $scorePercent = is_numeric($prediction->score_10) ? max(0, min(100, (float) $prediction->score_10 * 10)) : null;
                                $confidencePercent = is_numeric($prediction->confidence_percent) ? max(0, min(100, (float) $prediction->confidence_percent)) : null;
                                $riskPercent = is_numeric($prediction->risk_percent) ? max(0, min(100, (float) $prediction->risk_percent)) : null;
                                $confidenceColor = match (true) {
                                    $confidencePercent === null => '#64748b',
                                    $confidencePercent < 40 => '#278983',
                                    $confidencePercent < 60 => '#229b93',
                                    $confidencePercent < 75 => '#1fb0a6',
                                    $confidencePercent < 88 => '#2dd4bf',
                                    default => '#99f6e4',
                                };
                                $riskColor = match (true) {
                                    $riskPercent === null => '#64748b',
                                    $riskPercent < 10 => '#99f6e4',
                                    $riskPercent < 20 => '#5eead4',
                                    $riskPercent < 30 => '#2dd4bf',
                                    $riskPercent < 40 => '#1fa39b',
                                    default => '#277c77',
                                };
                                $modelTierCode = $prediction->model_quality_tier_code ?: 'unqualified';
                                $modelTierName = $prediction->model_quality_tier_name ? __($prediction->model_quality_tier_name) : __('Nicht qualifiziert');
                                $modelTierClass = match ($modelTierCode) {
                                    'top' => 'ak-model-tier-top',
                                    'strong' => 'ak-model-tier-strong',
                                    'solid' => 'ak-model-tier-solid',
                                    'test' => 'ak-model-tier-test',
                                    default => 'ak-model-tier-unqualified',
                                };
                                $predictionWatchlistIds = $watchlistMemberships->get((int) $prediction->instrument_id, collect());
                                $isWatched = $predictionWatchlistIds->isNotEmpty();
                                $predictionSmartLabels = collect(is_array($prediction->smart_labels ?? null)
                                    ? $prediction->smart_labels
                                    : (json_decode((string) ($prediction->smart_labels ?? '[]'), true) ?: []));
                            @endphp
                            <tr onclick="window.location='{{ route('stocks.show', ['symbol' => $prediction->symbol, 'prediction' => $prediction->id, 'return_to' => request()->getRequestUri()]) }}'" class="prediction-row cursor-pointer transition hover:bg-teal-500/[.075]">
                                <td onclick="event.stopPropagation()" class="relative border-b border-[var(--ak-border)] px-3 py-3 text-center">
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
                                </td>
                                <td class="border-b border-[var(--ak-border)] px-2 py-2 tabular-nums text-[var(--ak-muted)]">
                                    @if ($prediction->prediction_time)
                                        @php
                                            $predictionTime = \Illuminate\Support\Carbon::parse($prediction->prediction_time);
                                        @endphp
                                        <span class="block whitespace-nowrap font-bold text-[var(--ak-text)]">{{ $predictionTime->format('d.m.Y') }}</span>
                                        <span class="mt-0.5 block whitespace-nowrap text-[9px]">{{ $predictionTime->format('H:i') }}</span>
                                    @else
                                        <span class="block">—</span>
                                    @endif
                                </td>
                                <td class="border-b border-[var(--ak-border)] px-2 py-3">
                                    <div class="flex min-w-0 items-center gap-2">
                                        <img src="{{ route('stocks.icon', $prediction->instrument_id) }}" alt="" class="h-7 w-7 shrink-0 rounded-lg object-contain">
                                        <div class="min-w-0"><p class="truncate font-black text-teal-400">{{ $prediction->symbol }}</p><p class="mt-0.5 truncate text-[9px] text-[var(--ak-muted)]">{{ $prediction->name }}</p></div>
                                    </div>
                                </td>
                                <td class="border-b border-[var(--ak-border)] px-1 py-3 text-center">
                                    <span class="inline-flex h-8 w-8 items-center justify-center rounded-lg border" style="{{ $signalStyle }}" title="{{ $signal }}" aria-label="{{ $signal }}">
                                        <x-dynamic-component :component="$signalIcon" class="h-5 w-5" />
                                        <span class="sr-only">{{ $signal }}</span>
                                    </span>
                                </td>
                                <td class="border-b border-[var(--ak-border)] px-2 py-2">
                                    @if ($score !== null)
                                        <div class="flex h-full flex-col justify-center">
                                            <div class="mb-1 flex items-baseline justify-between"><strong class="text-xs font-black text-[var(--ak-text)]">{{ number_format($score, 1, ',', '.') }}</strong><small class="text-[8px] text-[var(--ak-muted)]">/ 10</small></div>
                                            <x-dashboard.score-stripes :percent="$scorePercent" palette="teal" />
                                        </div>
                                    @else<span class="block text-center text-[var(--ak-muted)]">—</span>@endif
                                </td>
                                @if ($canUseSmartLabels)
                                <td class="border-b border-[var(--ak-border)] px-2 py-2">
                                    <div class="flex min-w-0 flex-wrap gap-1">
                                        @forelse ($predictionSmartLabels as $smartLabel)
                                            @php
                                                $smartLabelIcon = in_array($smartLabel['icon'] ?? '', ['sparkles', 'bolt', 'trophy', 'shield-check', 'chart-bar', 'rocket-launch'], true)
                                                    ? $smartLabel['icon']
                                                    : 'sparkles';
                                            @endphp
                                            <span class="inline-flex max-w-full items-center gap-1 truncate rounded-[4px] border px-1.5 py-1 text-[8px] font-black" style="border-color: {{ $smartLabel['color'] }}66; color: {{ $smartLabel['color'] }}; background: {{ $smartLabel['color'] }}12;">
                                                <x-dynamic-component :component="'heroicon-o-'.$smartLabelIcon" class="h-3 w-3 shrink-0" />
                                                <span class="truncate">{{ $smartLabel['name'] }}</span>
                                            </span>
                                        @empty
                                            <span class="text-[var(--ak-muted)]">—</span>
                                        @endforelse
                                    </div>
                                </td>
                                @endif
                                <td class="border-b border-[var(--ak-border)] px-2 py-2">
                                    <div class="flex min-w-0 items-center justify-center gap-1">
                                        <span class="ak-model-tier {{ $modelTierClass }}">{{ $modelTierName }}</span>
                                    </div>
                                </td>
                                <td class="truncate border-b border-[var(--ak-border)] px-2 py-3 text-right font-bold tabular-nums text-[var(--ak-text)]">{{ is_numeric($prediction->current_price) ? number_format($prediction->current_price, 2, ',', '.').' '.$currency : '—' }}</td>
                                @foreach (['expected_return_5d', 'expected_return_20d'] as $returnField)
                                    @php $return = $prediction->{$returnField}; @endphp
                                    <td class="truncate border-b border-[var(--ak-border)] px-2 py-3 text-right font-black tabular-nums {{ is_numeric($return) ? ($return >= 0 ? 'text-emerald-400' : 'text-rose-400') : 'text-[var(--ak-muted)]' }}">{{ is_numeric($return) ? ($return >= 0 ? '+' : '').number_format($return, 2, ',', '.').' %' : '—' }}</td>
                                @endforeach
                                <td class="border-b border-[var(--ak-border)] px-2 py-2">
                                    <div class="flex h-full items-center justify-center">
                                        @if ($confidencePercent !== null)
                                            <div class="ak-prediction-donut" style="--value:{{ $confidencePercent }}%;--color:{{ $confidenceColor }}" role="meter" aria-label="{{ __('Konfidenz') }}" aria-valuenow="{{ round($confidencePercent) }}">
                                                <svg viewBox="0 0 44 44" aria-hidden="true">
                                                    <circle class="ak-prediction-donut-track" cx="22" cy="22" r="17" pathLength="100" />
                                                    <circle class="ak-prediction-donut-value" cx="22" cy="22" r="17" pathLength="100" stroke-dasharray="{{ $confidencePercent }} 100" />
                                                </svg>
                                                <span>{{ number_format($confidencePercent, 0, ',', '.') }}<small>%</small></span>
                                            </div>
                                        @else<span class="text-[var(--ak-muted)]">—</span>@endif
                                    </div>
                                </td>
                                <td class="border-b border-[var(--ak-border)] px-2 py-2">
                                    <div class="flex h-full items-center justify-center">
                                        @if ($riskPercent !== null)
                                            <div class="ak-prediction-donut" style="--value:{{ $riskPercent }}%;--color:{{ $riskColor }}" role="meter" aria-label="{{ __('Risiko') }}" aria-valuenow="{{ round($riskPercent) }}">
                                                <svg viewBox="0 0 44 44" aria-hidden="true">
                                                    <circle class="ak-prediction-donut-track" cx="22" cy="22" r="17" pathLength="100" />
                                                    <circle class="ak-prediction-donut-value" cx="22" cy="22" r="17" pathLength="100" stroke-dasharray="{{ $riskPercent }} 100" />
                                                </svg>
                                                <span>{{ number_format($riskPercent, 0, ',', '.') }}<small>%</small></span>
                                            </div>
                                        @else<span class="text-[var(--ak-muted)]">—</span>@endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="{{ $canUseSmartLabels ? 12 : 11 }}" class="px-6 py-16 text-center text-sm text-[var(--ak-muted)]">{{ __('Keine Prognosen für diese Filter gefunden.') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <style>
                #predictions-page {
                    --ak-muted: #b8c2d4;
                    --ak-predictions-card: color-mix(in srgb, var(--ak-card) 88%, #0f766e 12%);
                    --ak-predictions-card-even: color-mix(in srgb, var(--ak-card) 93%, #0d9488 7%);
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
                    border: 0 !important;
                    box-shadow: 0 14px 34px rgba(2, 44, 42, .24), 0 4px 12px rgba(0, 0, 0, .16) !important;
                }

                :root[data-theme="light"] #predictions-page .ak-predictions-card-surface {
                    box-shadow: 0 12px 28px rgba(15, 118, 110, .13), 0 3px 9px rgba(15, 23, 42, .08) !important;
                }

                #predictions-page .ak-stocks-table tbody tr:nth-child(even) {
                    background: var(--ak-card) !important;
                }

                #predictions-page .ak-stocks-table tbody tr:hover {
                    background: color-mix(in srgb, var(--ak-card) 84%, #14b8a6 16%) !important;
                }

                #predictions-page .ak-stocks-table thead {
                    position: sticky;
                    top: 0;
                    z-index: 40;
                    background: #12343b !important;
                    isolation: isolate;
                }

                #predictions-page .ak-stocks-table .ak-predictions-heading-row th {
                    position: sticky;
                    top: 0;
                    z-index: 42;
                    height: 44px;
                    background: #12343b !important;
                }

                #predictions-page .ak-stocks-table .ak-predictions-filter-row th {
                    position: sticky;
                    top: 44px;
                    z-index: 41;
                    background: #12343b !important;
                    box-shadow: 0 1px 0 rgba(45, 212, 191, .20), 0 8px 14px rgba(0, 0, 0, .18);
                }

                :root[data-theme="light"] #predictions-page {
                    --ak-muted: #64748b;
                }

                :root[data-theme="light"] #predictions-page .ak-stocks-table tbody tr:hover {
                    background: color-mix(in srgb, var(--ak-card) 84%, #14b8a6 16%) !important;
                }

                #predictions-page .ak-table-filter {
                    border-radius: 5px !important;
                    color: #f8fafc !important;
                    -webkit-text-fill-color: #f8fafc !important;
                    opacity: 1 !important;
                }

                #predictions-page select.ak-table-filter option {
                    background: #182238;
                    color: #f8fafc;
                }

                #predictions-page select.ak-table-filter {
                    appearance: none !important;
                    -webkit-appearance: none !important;
                    padding: 0 13px 0 5px !important;
                    font-size: 10px !important;
                    line-height: 28px !important;
                    background-color: #182238 !important;
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
                    width: 44px;
                    height: 44px;
                    flex: 0 0 44px;
                    place-items: center;
                    border-radius: 999px;
                    background: transparent;
                    filter: drop-shadow(0 0 5px color-mix(in srgb, var(--color) 20%, transparent));
                }

                .ak-prediction-donut svg {
                    position: absolute;
                    inset: 0;
                    display: block;
                    width: 44px;
                    height: 44px;
                    overflow: visible;
                    transform: rotate(-90deg);
                }

                .ak-prediction-donut circle {
                    fill: none;
                    stroke-width: 5;
                    vector-effect: non-scaling-stroke;
                }

                .ak-prediction-donut-track {
                    stroke: rgba(45, 212, 191, .17);
                }

                .ak-prediction-donut-value {
                    stroke: var(--color);
                    stroke-linecap: round;
                    transition: stroke-dasharray .2s ease;
                }

                .ak-prediction-donut span {
                    position: relative;
                    z-index: 1;
                    color: var(--ak-text);
                    font-size: 10px;
                    font-weight: 900;
                    line-height: 1;
                }

                .ak-prediction-donut small {
                    margin-left: 1px;
                    color: var(--ak-muted);
                    font-size: 7px;
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
            accent-color: #fb923c;
        }

        @media (max-width: 1100px) {
            #prediction-filterboard {
                overflow-x: auto;
            }

            #prediction-filterboard .ak-prediction-filterboard-labels,
            #prediction-filterboard .ak-prediction-filterboard-main {
                grid-template-columns: minmax(140px, 1.35fr) repeat({{ $canUseSmartLabels ? 6 : 5 }}, minmax(105px, 1fr)) 34px !important;
            }
        }
    </style>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const tableScroll = document.querySelector('#predictions-table-scroll');
            if (!tableScroll) return;

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
