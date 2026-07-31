<x-app-layout>
    @php $setupMode = $setupMode ?? false; @endphp
    <div class="flex h-[calc(100dvh-89px)] min-h-0 flex-col py-4 text-[var(--ak-text)]">
        <header class="mb-4 flex shrink-0 items-center justify-between gap-4">
            <div class="flex min-w-0 items-center gap-3">
                <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl border border-amber-300/25 bg-amber-300/[.08] text-amber-300">
                    <x-heroicon-o-squares-2x2 class="h-6 w-6" />
                </div>
                <div class="min-w-0">
                    <p class="text-[10px] font-black uppercase tracking-[.16em] text-teal-400">{{ $setupMode ? __('Setup') : __('Historische Validierung') }}</p>
                    <h1 class="truncate text-2xl font-black">{{ $setupMode ? __('Filter') : __('Historische Qualität nach KI-Score und Konfidenz') }}</h1>
                    <p class="mt-1 text-xs text-[var(--ak-muted)]">{{ __('Trefferquote, Profitfaktor, Drawdown und Trades; alle aktuellen Filter werden berücksichtigt.') }}</p>
                </div>
            </div>
            <a href="{{ $setupMode ? route('dashboard') : route('predictions.index', request()->query()) }}" class="inline-flex h-10 shrink-0 items-center gap-2 rounded-lg border border-[var(--ak-border)] bg-[var(--ak-card)] px-4 text-xs font-black text-[var(--ak-muted)] transition hover:border-teal-500/35 hover:text-teal-400">
                <x-heroicon-o-arrow-left class="h-4 w-4" />
                {{ $setupMode ? __('Zurück zum Dashboard') : __('Zurück zu Prognosen') }}
            </a>
        </header>

        <form
            id="prediction-heatmap-filters"
            method="GET"
            action="{{ $setupMode ? route('setup.filter') : route('predictions.heatmap') }}"
            x-data="{
                score: Number({{ (float) request('score_min', 0) }}),
                confidence: Number({{ (float) request('confidence_min', 0) }}),
                drawdown: Number({{ (float) request('drawdown_max', 50) }}),
                profitFactor: Number({{ (float) request('profit_factor_min', 0) }}),
                volatility: Number({{ (float) request('volatility_max', 100) }}),
                pe: Number({{ (float) request('pe_max', 100) }}),
                dividend: Number({{ (float) request('dividend_yield_min', 0) }}),
                marketCap: Number({{ (float) request('market_cap_min', 0) }}),
                revenueGrowth: Number({{ (float) request('revenue_growth_min', -50) }}),
                hitRate: Number({{ (float) request('hit_rate_min', 0) }}),
                searchTimer: null,
                submitSearch() {
                    window.clearTimeout(this.searchTimer);
                    this.searchTimer = window.setTimeout(() => this.$root.requestSubmit(), 450);
                }
            }"
            class="mb-3 flex shrink-0 flex-col gap-1 rounded-xl border border-[var(--ak-border)] bg-[var(--ak-card)] p-2"
        >
            <div class="grid grid-cols-[1.15fr_repeat(7,minmax(0,1fr))_38px] gap-1">
            <input name="q" value="{{ request('q') }}" @input="submitSearch()" placeholder="{{ __('Aktie') }}" class="ak-input h-8 min-w-0 rounded-[5px] px-2 text-[10px]">
            <select name="country" @change="$root.requestSubmit()" class="ak-input h-8 min-w-0 rounded-[5px] px-1 text-[10px]">
                <option value="">{{ __('Land') }}</option>
                @foreach ($countries as $country)<option value="{{ $country }}" @selected(strtoupper((string) request('country')) === strtoupper((string) $country))>{{ $country }}</option>@endforeach
            </select>
            <select name="exchange" @change="$root.requestSubmit()" class="ak-input h-8 min-w-0 rounded-[5px] px-1 text-[10px]">
                <option value="">{{ __('Börse') }}</option>
                @foreach ($exchanges as $exchange)<option value="{{ $exchange->code }}" @selected(strtoupper((string) request('exchange')) === strtoupper((string) $exchange->code))>{{ $exchange->code }}</option>@endforeach
            </select>
            <select name="sector" @change="$root.requestSubmit()" class="ak-input h-8 min-w-0 rounded-[5px] px-1 text-[10px]">
                <option value="">{{ __('Sektor') }}</option>
                @foreach ($sectors as $sector)<option value="{{ $sector }}" @selected((string) request('sector') === (string) $sector)>{{ __($sector) }}</option>@endforeach
            </select>
            <select name="ai_type" @change="$root.requestSubmit()" class="ak-input h-8 min-w-0 rounded-[5px] px-1 text-[10px]">
                <option value="">{{ __('KI-Typ') }}</option>
                @foreach ($aiTypes as $aiType)<option value="{{ $aiType }}" @selected(request('ai_type') === $aiType)>{{ ucfirst((string) $aiType) }}</option>@endforeach
            </select>
            <select name="model" @change="$root.requestSubmit()" class="ak-input h-8 min-w-0 rounded-[5px] px-1 text-[10px]">
                <option value="">{{ __('Modell') }}</option>
                @foreach ($models as $model)<option value="{{ $model->id }}" @selected((int) request('model') === (int) $model->id)>{{ $model->public_alias }}</option>@endforeach
            </select>
            <select name="quality_tier" @change="$root.requestSubmit()" class="ak-input h-8 min-w-0 rounded-[5px] px-1 text-[10px]">
                <option value="">{{ __('Modellstufe mindestens') }}</option>
                @foreach ($qualityTiers as $qualityTier)<option value="{{ $qualityTier->code }}" @selected(request('quality_tier') === $qualityTier->code)>{{ __($qualityTier->name) }}</option>@endforeach
            </select>
            <select name="signal" @change="$root.requestSubmit()" class="ak-input h-8 min-w-0 rounded-[5px] px-1 text-[10px]">
                <option value="">{{ __('Signal') }}</option>
                @foreach (['BUY', 'WATCH', 'HOLD', 'SELL'] as $signal)@continue(! $signals->contains($signal))<option value="{{ $signal }}" @selected(strtoupper((string) request('signal')) === $signal)>{{ $signal }}</option>@endforeach
            </select>
                <a href="{{ $setupMode ? route('setup.filter') : route('predictions.heatmap') }}" class="inline-flex h-8 items-center justify-center rounded-[5px] border border-[var(--ak-border)] text-[var(--ak-muted)] hover:border-teal-500/35 hover:text-teal-400" title="{{ __('Filter zurücksetzen') }}">
                    <x-heroicon-o-arrow-path class="h-4 w-4" />
                </a>
            </div>
            <div class="grid grid-cols-5 gap-1">
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
                <input name="profit_factor_min" type="range" min="0" max="3" step="0.1" x-model.number="profitFactor" @change="$root.requestSubmit()">
            </label>
            <label class="ak-heatmap-range">
                <span>{{ __('Volatilität') }} ≤ <b x-text="volatility >= 100 ? '{{ __('Alle') }}' : `${volatility}%`"></b></span>
                <input name="volatility_max" type="range" min="0" max="100" step="5" x-model.number="volatility" @change="$root.requestSubmit()">
            </label>
            </div>

        @if ($setupMode)
            <div
                id="fundamental-heatmap-filters"
                class="min-w-0 overflow-x-auto"
            >
                <label class="ak-fundamental-range">
                    <span>{{ __('KGV') }} ≤ <b x-text="pe >= 100 ? '{{ __('Alle') }}' : pe.toFixed(0)"></b></span>
                    <input name="pe_max" type="range" min="0" max="100" step="1" x-model.number="pe" @input="submitSearch()">
                </label>
                <label class="ak-fundamental-range">
                    <span>{{ __('Dividendenrendite') }} ≥ <b x-text="`${dividend.toFixed(1).replace('.', ',')} %`"></b></span>
                    <input name="dividend_yield_min" type="range" min="0" max="10" step="0.1" x-model.number="dividend" @input="submitSearch()">
                </label>
                <label class="ak-fundamental-range">
                    <span>{{ __('Marktkapitalisierung') }} ≥ <b x-text="marketCap <= 0 ? '{{ __('Alle') }}' : `${marketCap.toFixed(0)} Mrd.`"></b></span>
                    <input name="market_cap_min" type="range" min="0" max="3000" step="25" x-model.number="marketCap" @input="submitSearch()">
                </label>
                <label class="ak-fundamental-range">
                    <span>{{ __('Umsatzwachstum') }} ≥ <b x-text="revenueGrowth <= -50 ? '{{ __('Alle') }}' : `${revenueGrowth.toFixed(0)} %`"></b></span>
                    <input name="revenue_growth_min" type="range" min="-50" max="100" step="1" x-model.number="revenueGrowth" @input="submitSearch()">
                </label>
                <label class="ak-fundamental-range">
                    <span>{{ __('Hitrate') }} ≥ <b x-text="hitRate <= 0 ? '{{ __('Alle') }}' : `${hitRate.toFixed(0)} %`"></b></span>
                    <input name="hit_rate_min" type="range" min="0" max="100" step="5" x-model.number="hitRate" @input="submitSearch()">
                </label>
            </div>
        @endif
        </form>

        @if ($setupMode)
            <section x-data="{ saveOpen: false }" class="mb-3 flex shrink-0 items-center gap-2 overflow-hidden rounded-xl border border-[var(--ak-border)] bg-[var(--ak-card)] px-3 py-2">
                <div class="flex shrink-0 items-center gap-2 text-[10px] font-black uppercase tracking-[.1em] text-teal-400">
                    <x-heroicon-o-bookmark class="h-4 w-4" />
                    {{ __('Gespeicherte Filter') }}
                    <span class="rounded-md bg-white/[.06] px-1.5 py-0.5 text-[9px] text-[var(--ak-muted)]">{{ $savedFilters->count() }} / {{ $savedFilterLimit }}</span>
                    @if ($editingSavedFilter)<span class="rounded-md border border-amber-300/20 bg-amber-300/[.08] px-2 py-1 normal-case tracking-normal text-amber-200">{{ __('Bearbeitung: :name', ['name' => $editingSavedFilter->name]) }}</span>@endif
                </div>
                <div class="flex min-w-0 flex-1 items-center gap-1.5 overflow-x-auto py-0.5">
                    @forelse ($savedFilters as $savedFilter)
                        <div class="flex shrink-0 items-center overflow-hidden rounded-md border border-white/[.08] bg-white/[.035]">
                            <a href="{{ route('setup.filter', $savedFilter->filters ?? []) }}" class="px-2.5 py-1.5 text-[10px] font-bold text-slate-200 hover:bg-teal-400/10 hover:text-teal-300">{{ $savedFilter->name }}</a>
                            <form method="POST" action="{{ route('setup.filter.saved.destroy', $savedFilter) }}" class="border-l border-white/[.08]">
                                @csrf @method('DELETE')
                                <button type="submit" class="flex h-7 w-7 items-center justify-center text-slate-500 hover:bg-rose-400/10 hover:text-rose-300" title="{{ __('Filter löschen') }}"><x-heroicon-o-x-mark class="h-3.5 w-3.5" /></button>
                            </form>
                        </div>
                    @empty
                        <span class="text-[10px] text-[var(--ak-muted)]">{{ __('Noch kein Filter gespeichert.') }}</span>
                    @endforelse
                </div>
                <button type="button" @click="saveOpen = true" class="inline-flex h-8 shrink-0 items-center gap-1.5 rounded-md border border-teal-300/25 bg-teal-400/[.08] px-3 text-[10px] font-black text-teal-300 hover:bg-teal-400/15">
                    @if ($editingSavedFilter)<x-heroicon-o-check class="h-3.5 w-3.5" />{{ __('Änderungen speichern') }}@else<x-heroicon-o-plus class="h-3.5 w-3.5" />{{ __('Filter speichern') }}@endif
                </button>

                <div x-show="saveOpen" x-cloak class="fixed inset-0 z-[120] flex items-center justify-center bg-slate-950/75 p-4 backdrop-blur-sm" @keydown.escape.window="saveOpen = false">
                    <form method="POST" action="{{ route('setup.filter.saved.store') }}" class="w-full max-w-md rounded-2xl border border-teal-300/20 bg-[#15243a] p-5 shadow-2xl" @click.outside="saveOpen = false">
                        @csrf
                        @if ($editingSavedFilter)<input type="hidden" name="saved_filter" value="{{ $editingSavedFilter->id }}">@endif
                        @foreach (\App\Http\Controllers\SavedPredictionFilterController::FILTER_KEYS as $filterKey)
                            <input type="hidden" name="{{ $filterKey }}" value="{{ request($filterKey, \App\Http\Controllers\SavedPredictionFilterController::FILTER_DEFAULTS[$filterKey] ?? '') }}">
                        @endforeach
                        <div class="flex items-start justify-between gap-4">
                            <div><p class="text-[10px] font-black uppercase tracking-[.14em] text-teal-400">{{ __('Filter speichern') }}</p><h2 class="mt-1 text-xl font-black text-white">{{ __('Filter benennen') }}</h2></div>
                            <button type="button" @click="saveOpen = false" class="flex h-8 w-8 items-center justify-center rounded-lg border border-white/10 text-slate-400 hover:text-white"><x-heroicon-o-x-mark class="h-4 w-4" /></button>
                        </div>
                        <label class="mt-5 block text-[10px] font-black uppercase tracking-wide text-slate-400">{{ __('Name') }}
                            <input name="name" value="{{ $editingSavedFilter?->name }}" maxlength="80" required autofocus class="ak-input mt-2 h-11 w-full rounded-lg px-3 text-sm font-bold text-white" placeholder="{{ __('z. B. Quality Europa') }}">
                        </label>
                        <p class="mt-3 text-[10px] text-slate-400">{{ __('Dein Tarif erlaubt :count gespeicherte Filter.', ['count' => $savedFilterLimit]) }}</p>
                        <div class="mt-5 flex justify-end gap-2"><button type="button" @click="saveOpen = false" class="h-10 rounded-lg border border-white/10 px-4 text-xs font-bold text-slate-300">{{ __('Abbrechen') }}</button><button type="submit" class="h-10 rounded-lg border border-teal-300/30 bg-teal-400/15 px-5 text-xs font-black text-teal-200 hover:bg-teal-400/20">{{ __('Speichern') }}</button></div>
                    </form>
                </div>
            </section>
            @error('saved_filter')<p class="-mt-2 mb-2 shrink-0 text-xs font-bold text-rose-300">{{ $message }}</p>@enderror
            @php
                $backtestIsActive = isset($activeBacktestRun) && in_array($activeBacktestRun?->status, ['queued', 'running'], true);
                $backtestIsComplete = isset($activeBacktestRun) && in_array($activeBacktestRun?->status, ['completed', 'completed_with_errors'], true);
                $backtestFilters = [
                    'q', 'country', 'exchange', 'sector', 'ai_type', 'model', 'quality_tier', 'signal',
                    'score_min', 'confidence_min', 'drawdown_max', 'profit_factor_min', 'volatility_max',
                    'pe_max', 'dividend_yield_min', 'market_cap_min', 'revenue_growth_min', 'hit_rate_min',
                ];
            @endphp
            <section x-data="{ capitalOpen: false, capital: 10000, positions: 10, tradeCost: 10 }" class="relative mb-3 flex shrink-0 items-center justify-between gap-3 overflow-hidden rounded-xl border border-amber-300/20 bg-amber-300/[.055] px-3 py-2 {{ $backtestIsActive ? 'pb-4' : '' }}">
                <div class="min-w-0">
                    <div class="flex items-center gap-2">
                        @if ($backtestIsActive)
                            <span class="ak-backtest-spinner" aria-hidden="true"></span>
                        @endif
                        <p class="text-[10px] font-black uppercase tracking-[.12em] text-amber-300">{{ __('Persönlicher 3-Jahres-Backtest') }}</p>
                        @if ($backtestIsActive)
                            <span class="ak-backtest-dots" aria-hidden="true"><i></i><i></i><i></i></span>
                        @endif
                    </div>
                    <p class="truncate text-[10px] text-[var(--ak-muted)]">
                        @if ($backtestIsActive)
                            <span id="ak-backtest-status-text">{{ __('Der Backtest wird im Hintergrund berechnet …') }}</span>
                            <span id="ak-backtest-status-count" class="ml-1 font-bold text-amber-100"></span>
                        @elseif ($backtestIsComplete)
                            {{ __('Abgeschlossen: :trades Trades aus :instruments Aktien', ['trades' => number_format((int) $activeBacktestRun->trades_count, 0, ',', '.'), 'instruments' => number_format((int) $activeBacktestRun->instruments_completed, 0, ',', '.')]) }}
                        @elseif (isset($activeBacktestRun) && $activeBacktestRun?->status === 'failed')
                            {{ __('Der Backtest konnte nicht abgeschlossen werden.') }}
                        @elseif (isset($activeBacktestRun) && $activeBacktestRun?->status === 'cancelled')
                            {{ __('Der Backtest wurde abgebrochen.') }}
                        @else
                            {{ __('Nur Aktien, die alle gewählten Kriterien erfüllen, werden berücksichtigt. Ausstieg nach 20 Handelstagen.') }}
                        @endif
                    </p>
                </div>
                @if ($backtestIsActive)
                    <form method="POST" action="{{ route('setup.filter.backtest.cancel', $activeBacktestRun->public_id) }}" class="shrink-0">
                        @csrf
                        <button type="submit" class="inline-flex h-9 items-center gap-2 rounded-lg border border-rose-300/25 bg-rose-400/[.07] px-4 text-[10px] font-black uppercase tracking-[.08em] text-rose-200 transition hover:bg-rose-400/15">
                            <x-heroicon-o-x-mark class="h-4 w-4" />{{ __('Abbrechen') }}
                        </button>
                    </form>
                @elseif ($backtestIsComplete)
                    <div class="flex shrink-0 items-center gap-2">
                        <a href="{{ route('setup.filter', request()->except(['backtest_run', 'initial_capital', 'max_positions', 'trade_cost'])) }}" class="inline-flex h-9 items-center gap-2 rounded-lg border border-white/10 px-3 text-[10px] font-black uppercase tracking-[.06em] text-slate-300 hover:text-white">
                            <x-heroicon-o-arrow-path class="h-4 w-4" />{{ __('Neu berechnen') }}
                        </a>
                        <button type="button" onclick="window.dispatchEvent(new CustomEvent('open-backtest-result'))" class="inline-flex h-9 items-center gap-2 rounded-lg border border-amber-300/30 bg-amber-300/12 px-4 text-[10px] font-black uppercase tracking-[.08em] text-amber-200 transition hover:bg-amber-300/20">
                            <x-heroicon-o-chart-bar class="h-4 w-4" />{{ __('Ergebnis anzeigen') }}
                        </button>
                    </div>
                @else
                        <button type="button" @click="capitalOpen = true" class="inline-flex h-9 shrink-0 items-center gap-2 rounded-lg border border-amber-300/30 bg-amber-300/12 px-4 text-[10px] font-black uppercase tracking-[.08em] text-amber-200 transition hover:bg-amber-300/20">
                            <x-heroicon-o-play class="h-4 w-4" />
                            {{ __('Backtest starten') }}
                    </button>
                    <div x-show="capitalOpen" x-cloak class="fixed inset-0 z-[110] flex items-center justify-center bg-slate-950/75 p-4 backdrop-blur-sm" @keydown.escape.window="capitalOpen = false">
                        <form method="POST" action="{{ route('setup.filter.backtest') }}" class="w-full max-w-lg rounded-2xl border border-teal-300/20 bg-[#15243a] p-5 shadow-2xl" @click.outside="capitalOpen = false">
                            @csrf
                            @foreach ($backtestFilters as $filter)
                                @if (request()->filled($filter))
                                    <input type="hidden" name="{{ $filter }}" value="{{ request($filter) }}">
                                @endif
                            @endforeach
                            <div class="mb-5 flex items-start justify-between gap-4">
                                <div>
                                    <p class="text-[10px] font-black uppercase tracking-[.14em] text-amber-300">{{ __('Backtest konfigurieren') }}</p>
                                    <h2 class="mt-1 text-xl font-black text-white">{{ __('Kapital und Positionsgröße') }}</h2>
                                    <p class="mt-1 text-xs text-slate-300">{{ __('Es werden nur neue Positionen eröffnet, wenn Kapital und ein freier Aktienplatz verfügbar sind.') }}</p>
                                </div>
                                <button type="button" @click="capitalOpen = false" class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg border border-white/10 text-slate-300 hover:text-white"><x-heroicon-o-x-mark class="h-5 w-5" /></button>
                            </div>
                            <div class="grid grid-cols-3 gap-3">
                                <label class="text-[10px] font-black uppercase tracking-wide text-slate-400">
                                    {{ __('Startkapital') }}
                                    <div class="relative mt-2"><input name="initial_capital" type="number" min="1000" max="1000000" step="100" x-model.number="capital" required class="ak-input h-11 w-full rounded-lg pr-8 text-sm font-bold text-white"><span class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400">€</span></div>
                                </label>
                                <label class="text-[10px] font-black uppercase tracking-wide text-slate-400">
                                    {{ __('Max. Aktien') }}
                                    <input name="max_positions" type="number" min="1" max="50" step="1" x-model.number="positions" required class="ak-input mt-2 h-11 w-full rounded-lg text-sm font-bold text-white">
                                </label>
                                <label class="text-[10px] font-black uppercase tracking-wide text-slate-400">
                                    {{ __('Kosten je Trade') }}
                                    <div class="relative mt-2"><input name="trade_cost" type="number" min="0" max="1000" step="0.01" x-model.number="tradeCost" required class="ak-input h-11 w-full rounded-lg pr-8 text-sm font-bold text-white"><span class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400">€</span></div>
                                </label>
                            </div>
                            <div class="mt-4 rounded-xl border border-white/[.08] bg-white/[.035] px-4 py-3 text-xs text-slate-300">
                                {{ __('Kapital je Aktie') }}: <strong class="text-white" x-text="`${Math.max(0, capital / Math.max(1, positions)).toLocaleString('de-DE', { style: 'currency', currency: 'EUR' })}`"></strong>
                            </div>
                            <div class="mt-5 flex justify-end gap-2">
                                <button type="button" @click="capitalOpen = false" class="h-10 rounded-lg border border-white/10 px-4 text-xs font-bold text-slate-300">{{ __('Abbrechen') }}</button>
                                <button type="submit" class="inline-flex h-10 items-center gap-2 rounded-lg border border-amber-300/30 bg-amber-300/15 px-5 text-xs font-black text-amber-200 hover:bg-amber-300/20"><x-heroicon-o-play class="h-4 w-4" />{{ __('Backtest ausführen') }}</button>
                            </div>
                        </form>
                    </div>
                @endif
                @if ($backtestIsActive)
                    <div class="ak-backtest-progress" role="progressbar" aria-label="{{ __('Backtest läuft') }}" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0">
                        <span id="ak-backtest-progress-bar"></span>
                    </div>
                @endif
            </section>
            @if ($backtestIsActive)
                <script>
                    (() => {
                        const statusUrl = @json(route('setup.filter.backtest.status', $activeBacktestRun->public_id));
                        const statusCount = document.getElementById('ak-backtest-status-count');
                        const progressBar = document.getElementById('ak-backtest-progress-bar');
                        const progressTrack = progressBar?.parentElement;
                        const poll = async () => {
                            try {
                                const response = await fetch(statusUrl, {
                                    headers: { Accept: 'application/json' },
                                    cache: 'no-store',
                                });
                                if (response.ok) {
                                    const result = await response.json();
                                    if (result.finished) {
                                        window.location.reload();
                                        return;
                                    }
                                    if (result.instruments_total > 0) {
                                        const completed = Math.min(result.instruments_completed, result.instruments_total);
                                        const percent = Math.round((completed / result.instruments_total) * 100);
                                        statusCount.textContent = `${completed} / ${result.instruments_total} Aktien · ${percent} %`;
                                        progressBar.classList.add('is-determinate');
                                        progressBar.style.width = `${percent}%`;
                                        progressTrack?.setAttribute('aria-valuenow', String(percent));
                                    }
                                }
                            } catch (_) {
                                // A temporary connection interruption must not reload the complete page.
                            }
                            window.setTimeout(poll, 2500);
                        };
                        window.setTimeout(poll, 1200);
                    })();
                </script>
            @endif
        @endif

        @php
            $heatmapMetrics = [
                ['key' => 'hit_rate', 'label' => __('Hitrate'), 'suffix' => '%'],
                ['key' => 'profit_factor', 'label' => __('Profitfaktor'), 'suffix' => ''],
                ['key' => 'drawdown', 'label' => __('Drawdown'), 'suffix' => '%'],
                ['key' => 'samples', 'label' => __('Trades'), 'suffix' => ''],
            ];
        @endphp

        <section class="grid min-h-0 w-full grid-cols-4 gap-3">
            @foreach ($heatmapMetrics as $metric)
                <article class="flex aspect-square min-h-0 min-w-0 flex-col rounded-2xl border border-[var(--ak-border)] bg-[var(--ak-card)] p-3 shadow-[var(--ak-shadow)]">
                    <div class="mb-2 flex shrink-0 items-center justify-between gap-2">
                        <h2 class="text-sm font-black">{{ $metric['label'] }}</h2>
                        <span class="text-[7px] font-bold uppercase tracking-wide text-[var(--ak-muted)]">{{ __('Y: Konfidenz') }} · {{ __('X: KI-Score') }}</span>
                    </div>
                    <div class="grid min-h-0 flex-1 grid-cols-[30px_repeat(10,minmax(0,1fr))] grid-rows-[repeat(10,minmax(0,1fr))_12px] gap-1">
                        @for ($confidenceBucket = 9; $confidenceBucket >= 0; $confidenceBucket--)
                            <div class="flex items-center justify-end pr-0.5 text-[7px] font-bold tabular-nums text-[var(--ak-muted)]">
                                {{ $confidenceBucket * 10 }}–{{ ($confidenceBucket + 1) * 10 }}
                            </div>
                            @for ($scoreBucket = 0; $scoreBucket <= 9; $scoreBucket++)
                                @php
                                    $cell = $heatmap->get($scoreBucket.'-'.$confidenceBucket);
                                    $samples = (int) ($cell->samples ?? 0);
                                    $rawValue = $metric['key'] === 'samples'
                                        ? $samples
                                        : (is_numeric(data_get($cell, $metric['key'])) ? (float) data_get($cell, $metric['key']) : null);
                                    $hasValue = in_array($metric['key'], ['samples', 'drawdown'], true)
                                        ? $samples > 0
                                        : $samples >= 5 && $rawValue !== null;
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
                                    $drawdownClass = match (true) {
                                        $rawValue >= 45 => 'border-rose-400/40 bg-rose-500/28 text-rose-50',
                                        $rawValue >= 40 => 'border-rose-300/35 bg-rose-400/24 text-rose-50',
                                        $rawValue >= 35 => 'border-orange-300/55 bg-orange-400/38 text-orange-50',
                                        $rawValue >= 30 => 'border-orange-300/45 bg-orange-400/30 text-orange-50',
                                        $rawValue >= 25 => 'border-amber-200/50 bg-amber-300/34 text-amber-50',
                                        $rawValue >= 20 => 'border-amber-200/40 bg-amber-300/27 text-amber-50',
                                        $rawValue >= 15 => 'border-yellow-200/50 bg-yellow-300/34 text-yellow-50',
                                        $rawValue >= 10 => 'border-yellow-200/40 bg-yellow-300/27 text-yellow-50',
                                        $rawValue >= 5 => 'border-lime-300/30 bg-lime-400/17 text-lime-50',
                                        default => 'border-emerald-300/20 bg-emerald-400/10 text-emerald-100',
                                    };
                                    $cellClass = ! $hasValue
                                        ? 'border-white/[.05] bg-slate-500/[.07] text-slate-500'
                                        : ($metric['key'] === 'samples'
                                            ? ($rawValue >= 30
                                                ? 'border-cyan-300/35 bg-cyan-400/25 text-cyan-50'
                                                : ($rawValue >= 15
                                                    ? 'border-cyan-400/25 bg-cyan-400/16 text-cyan-100'
                                                    : 'border-cyan-400/15 bg-cyan-400/[.08] text-cyan-200'))
                                            : ($metric['key'] === 'drawdown'
                                                ? $drawdownClass
                                                : ($good
                                                    ? 'border-emerald-300/25 bg-emerald-400/20 text-emerald-100'
                                                    : ($weak
                                                        ? 'border-rose-400/20 bg-rose-400/15 text-rose-200'
                                                        : 'border-amber-300/20 bg-amber-300/12 text-amber-100'))));
                                    $displayValue = ! $hasValue
                                        ? ($samples ?: '—')
                                        : ($metric['key'] === 'profit_factor'
                                            ? number_format($rawValue, 2, ',', '.')
                                            : ($metric['key'] === 'samples'
                                                ? number_format($rawValue, 0, ',', '.')
                                                : number_format($rawValue, 0, ',', '.').$metric['suffix']));
                                @endphp
                                <div class="flex aspect-square min-h-0 min-w-0 cursor-default items-center justify-center self-center rounded-[4px] border {{ $cellClass }}"
                                     title="{{ __('Score :scoreFrom–:scoreTo · Konfidenz :confidenceFrom–:confidenceTo % · :metric: :value · :samples Trades', [
                                         'scoreFrom' => $scoreBucket,
                                         'scoreTo' => $scoreBucket + 1,
                                         'confidenceFrom' => $confidenceBucket * 10,
                                         'confidenceTo' => ($confidenceBucket + 1) * 10,
                                         'metric' => $metric['label'],
                                         'value' => $displayValue,
                                         'samples' => $samples,
                                    ]) }}">
                                    <span class="text-[7px] font-black tabular-nums sm:text-[8px]">{{ $displayValue }}</span>
                                </div>
                            @endfor
                        @endfor
                        <div></div>
                        @for ($scoreBucket = 0; $scoreBucket <= 9; $scoreBucket++)
                            <div class="text-center text-[7px] font-bold tabular-nums text-[var(--ak-muted)]">{{ $scoreBucket }}–{{ $scoreBucket + 1 }}</div>
                        @endfor
                    </div>
                </article>
            @endforeach
        </section>

        @php
            $averageBars = [
                [
                    'label' => __('Ø Hitrate'),
                    'value' => is_numeric($heatmapSummary?->hit_rate) ? (float) $heatmapSummary->hit_rate : 0,
                    'display' => number_format((float) ($heatmapSummary?->hit_rate ?? 0), 1, ',', '.').' %',
                    'width' => max(0, min(100, (float) ($heatmapSummary?->hit_rate ?? 0))),
                    'color' => 'bg-emerald-400',
                ],
                [
                    'label' => __('Ø Profitfaktor'),
                    'value' => is_numeric($heatmapSummary?->profit_factor) ? (float) $heatmapSummary->profit_factor : 0,
                    'display' => number_format((float) ($heatmapSummary?->profit_factor ?? 0), 2, ',', '.'),
                    'width' => max(0, min(100, ((float) ($heatmapSummary?->profit_factor ?? 0) / 3) * 100)),
                    'color' => 'bg-teal-400',
                ],
                [
                    'label' => __('Max. Drawdown'),
                    'value' => is_numeric($heatmapSummary?->drawdown) ? (float) $heatmapSummary->drawdown : 0,
                    'display' => number_format((float) ($heatmapSummary?->drawdown ?? 0), 1, ',', '.').' %',
                    'width' => max(0, min(100, ((float) ($heatmapSummary?->drawdown ?? 0) / 50) * 100)),
                    'color' => 'bg-rose-400',
                    'colors' => [
                        'bg-emerald-400', 'bg-lime-400',
                        'bg-yellow-300', 'bg-yellow-300',
                        'bg-amber-300', 'bg-amber-300',
                        'bg-orange-400', 'bg-orange-400',
                        'bg-rose-400', 'bg-rose-500',
                    ],
                ],
                [
                    'label' => __('Trades/Monat gesamt'),
                    'value' => (float) ($heatmapSummary?->trades_per_month ?? 0),
                    'display' => number_format((float) ($heatmapSummary?->trades_per_month ?? 0), 1, ',', '.'),
                    'width' => max(0, min(100, ((float) ($heatmapSummary?->trades_per_month ?? 0) / 100) * 100)),
                    'color' => 'bg-cyan-400',
                ],
            ];
        @endphp
        <section class="mt-3 grid shrink-0 grid-cols-4 gap-3 rounded-2xl border border-[var(--ak-border)] bg-[var(--ak-card)] p-3 shadow-[var(--ak-shadow)]">
            @foreach ($averageBars as $bar)
                <div class="min-w-0">
                    <div class="mb-2 flex items-baseline justify-between gap-2">
                        <span class="truncate text-[9px] font-black uppercase tracking-[.1em] text-[var(--ak-muted)]">{{ $bar['label'] }}</span>
                        <strong class="shrink-0 text-sm font-black tabular-nums">{{ $bar['display'] }}</strong>
                    </div>
                    @php $reachedSegments = (int) ceil($bar['width'] / 10); @endphp
                    <div class="flex h-2.5 items-stretch gap-1">
                        @for ($segment = 1; $segment <= 10; $segment++)
                            @php $segmentColor = $bar['colors'][$segment - 1] ?? $bar['color']; @endphp
                            <span class="min-w-0 flex-1 rounded-[2px] {{
                                $segment < $reachedSegments
                                    ? $segmentColor.' opacity-40'
                                    : ($segment === $reachedSegments && $reachedSegments > 0
                                        ? $segmentColor.' opacity-100'
                                        : 'bg-slate-400/10')
                            }}"></span>
                        @endfor
                    </div>
                </div>
            @endforeach
        </section>
        <p class="mt-2 shrink-0 text-center text-[10px] text-[var(--ak-muted)]">{{ __('Graue Felder enthalten zu wenige validierte Prognosen für eine belastbare Bewertung.') }}</p>
    </div>

    @if (($setupMode ?? false) && ($backtestIsComplete ?? false))
        @php $runSummary = json_decode((string) ($activeBacktestRun->summary ?? '{}'), true) ?: []; @endphp
        <div x-data="{ open: true }" x-show="open" x-cloak class="fixed inset-0 z-[100] flex items-center justify-center bg-slate-950/75 p-4 backdrop-blur-sm" @open-backtest-result.window="open = true" @keydown.escape.window="open = false">
            <section class="w-full max-w-5xl rounded-2xl border border-teal-300/20 bg-[#15243a] p-5 shadow-2xl" @click.outside="open = false">
                <header class="mb-4 flex items-start justify-between gap-4">
                    <div>
                        <p class="text-[10px] font-black uppercase tracking-[.14em] text-amber-300">{{ __('Persönlicher 3-Jahres-Backtest') }}</p>
                        <h2 class="mt-1 text-xl font-black text-white">{{ __('Backtest-Ergebnis') }}</h2>
                        <p class="mt-1 text-xs text-slate-300">{{ __('Strategie und S&P 500 starten mit demselben gewählten Kapital.') }}</p>
                    </div>
                    <div class="flex items-center gap-2">
                        <a href="{{ route('setup.filter.backtest.report', $activeBacktestRun->public_id) }}" class="inline-flex h-9 items-center gap-2 rounded-lg border border-teal-300/20 bg-teal-400/10 px-3 text-[10px] font-black uppercase tracking-wide text-teal-200 hover:bg-teal-400/15">
                            <x-heroicon-o-arrow-down-tray class="h-4 w-4" />{{ __('PDF-Bericht') }}
                        </a>
                        <button type="button" @click="open = false" class="flex h-9 w-9 items-center justify-center rounded-lg border border-white/10 text-slate-300 hover:text-white" aria-label="{{ __('Schließen') }}">
                            <x-heroicon-o-x-mark class="h-5 w-5" />
                        </button>
                    </div>
                </header>

                <div class="mb-4 grid grid-cols-8 gap-2">
                    @foreach ([
                        [__('Startkapital'), '…', 'filtered-backtest-initial-capital'],
                        [__('Endkapital'), '…', 'filtered-backtest-final-capital'],
                        [__('Ausgeführte Trades'), '…', 'filtered-backtest-executed-trades'],
                        [__('Übersprungen'), '…', 'filtered-backtest-skipped-trades'],
                        [__('Gesamtkosten'), '…', 'filtered-backtest-total-costs'],
                        [__('Hitrate'), '…', 'filtered-backtest-hit-rate'],
                        [__('Profitfaktor'), '…', 'filtered-backtest-profit-factor'],
                        [__('Max. Drawdown'), '…', 'filtered-backtest-drawdown'],
                    ] as $index => $metric)
                        @php [$label, $value, $metricId] = array_pad($metric, 3, ''); @endphp
                        <div class="rounded-xl border border-white/[.08] bg-white/[.035] px-3 py-2">
                            <span class="block text-[9px] font-black uppercase tracking-wide text-slate-400">{{ $label }}</span>
                            <strong @if ($metricId) id="{{ $metricId }}" @endif class="mt-1 block text-base font-black text-white">{{ $value }}</strong>
                        </div>
                    @endforeach
                </div>
                <div id="filtered-backtest-result-chart" class="h-[360px] w-full"></div>
                <div class="mt-3 flex items-center justify-between gap-4 text-[10px] text-slate-400">
                    <span>{{ __('Portfolio-Verlauf auf Basis gleich gewichteter, am jeweiligen Ausstiegstag zusammengefasster Trades.') }}</span>
                    <span id="filtered-backtest-benchmark-performance" class="shrink-0 font-bold text-slate-300"></span>
                </div>
            </section>
        </div>
        <script>
            document.addEventListener('DOMContentLoaded', async () => {
                const target = document.querySelector('#filtered-backtest-result-chart');
                if (!target || !window.ApexCharts) return;
                const response = await fetch(@json(route('setup.filter.backtest.result', $activeBacktestRun->public_id)), {
                    headers: { Accept: 'application/json' },
                });
                if (!response.ok) return;
                const result = await response.json();
                const performance = document.querySelector('#filtered-backtest-performance');
                const finalCapital = document.querySelector('#filtered-backtest-final-capital');
                const initialCapital = document.querySelector('#filtered-backtest-initial-capital');
                const executedTrades = document.querySelector('#filtered-backtest-executed-trades');
                const skippedTrades = document.querySelector('#filtered-backtest-skipped-trades');
                const totalCosts = document.querySelector('#filtered-backtest-total-costs');
                const hitRate = document.querySelector('#filtered-backtest-hit-rate');
                const profitFactor = document.querySelector('#filtered-backtest-profit-factor');
                const drawdown = document.querySelector('#filtered-backtest-drawdown');
                const benchmarkPerformance = document.querySelector('#filtered-backtest-benchmark-performance');
                if (initialCapital) initialCapital.textContent = Number(result.initial_capital).toLocaleString('de-DE', { style: 'currency', currency: 'EUR' });
                if (finalCapital) finalCapital.textContent = Number(result.final_capital).toLocaleString('de-DE', { style: 'currency', currency: 'EUR' });
                if (executedTrades) executedTrades.textContent = Number(result.executed_trades).toLocaleString('de-DE');
                if (skippedTrades) skippedTrades.textContent = Number(result.skipped_trades).toLocaleString('de-DE');
                if (totalCosts) totalCosts.textContent = Number(result.total_costs).toLocaleString('de-DE', { style: 'currency', currency: 'EUR' });
                if (hitRate) hitRate.textContent = `${Number(result.hit_rate).toLocaleString('de-DE', { maximumFractionDigits: 1 })} %`;
                if (profitFactor) profitFactor.textContent = result.profit_factor === null ? '∞' : Number(result.profit_factor).toLocaleString('de-DE', { maximumFractionDigits: 2 });
                if (drawdown) drawdown.textContent = `${Number(result.max_drawdown).toLocaleString('de-DE', { maximumFractionDigits: 1 })} %`;
                if (benchmarkPerformance && result.benchmark_performance !== null) benchmarkPerformance.textContent = `S&P 500: ${result.benchmark_performance >= 0 ? '+' : ''}${Number(result.benchmark_performance).toLocaleString('de-DE', { maximumFractionDigits: 2 })} %`;
                new window.ApexCharts(target, {
                    chart: { type: 'line', height: 360, background: 'transparent', toolbar: { show: false }, zoom: { enabled: false }, animations: { enabled: true, speed: 650 } },
                    series: [
                        { name: '20 Tage', data: result.strategy },
                        { name: 'Winner Runner', data: result.winner_runner },
                        { name: 'Prognoseziel', data: result.prediction_target },
                        { name: 'S&P 500 Buy & Hold', data: result.benchmark },
                    ],
                    colors: ['#2dd4bf', '#818cf8', '#fb7185', '#fbbf24'],
                    stroke: { width: [3, 3, 3, 2], curve: 'smooth', dashArray: [0, 0, 0, 5] },
                    xaxis: { type: 'datetime', labels: { style: { colors: '#94a3b8', fontSize: '10px' } }, axisBorder: { color: 'rgba(148,163,184,.16)' }, axisTicks: { color: 'rgba(148,163,184,.16)' } },
                    yaxis: { labels: { formatter: value => `${value.toLocaleString('de-DE', { maximumFractionDigits: 0 })} €`, style: { colors: '#94a3b8', fontSize: '10px' } } },
                    grid: { borderColor: 'rgba(148,163,184,.10)', strokeDashArray: 4 },
                    legend: { labels: { colors: '#cbd5e1' }, markers: { size: 5 } },
                    tooltip: { theme: 'dark', x: { format: 'dd.MM.yyyy' }, y: { formatter: value => value.toLocaleString('de-DE', { style: 'currency', currency: 'EUR' }) } },
                    dataLabels: { enabled: false },
                    noData: { text: '{{ __('Keine Vergleichsdaten verfügbar') }}', style: { color: '#94a3b8' } },
                }).render();
            });
        </script>
    @endif

    <style>
        #prediction-heatmap-filters .ak-input {
            border-radius: 5px !important;
            color: #f8fafc !important;
            -webkit-text-fill-color: #f8fafc !important;
        }

        #fundamental-heatmap-filters .ak-input {
            border-radius: 5px !important;
            color: #f8fafc !important;
            -webkit-text-fill-color: #f8fafc !important;
            background-color: #182238 !important;
        }

        .ak-backtest-spinner {
            width: 13px;
            height: 13px;
            flex: 0 0 auto;
            border: 2px solid rgba(251, 191, 36, .18);
            border-top-color: rgba(45, 212, 191, .95);
            border-right-color: rgba(251, 191, 36, .9);
            border-radius: 999px;
            animation: ak-backtest-spin .9s linear infinite;
        }
        .ak-backtest-dots { display: inline-flex; align-items: center; gap: 3px; height: 12px; }
        .ak-backtest-dots i {
            width: 3px;
            height: 3px;
            border-radius: 999px;
            background: rgba(251, 191, 36, .9);
            animation: ak-backtest-dot 1.2s ease-in-out infinite;
        }
        .ak-backtest-dots i:nth-child(2) { animation-delay: .16s; }
        .ak-backtest-dots i:nth-child(3) { animation-delay: .32s; }
        .ak-backtest-progress {
            position: absolute;
            right: 12px;
            bottom: 5px;
            left: 12px;
            height: 6px;
            overflow: hidden;
            border: 1px solid rgba(251, 191, 36, .18);
            border-radius: 999px;
            background: rgba(15, 23, 42, .62);
            box-shadow: inset 0 1px 2px rgba(0, 0, 0, .35);
        }
        .ak-backtest-progress span {
            display: block;
            width: 34%;
            height: 100%;
            border-radius: 999px;
            background: linear-gradient(90deg, transparent, rgba(45, 212, 191, .95), rgba(251, 191, 36, 1), transparent);
            box-shadow: 0 0 8px rgba(251, 191, 36, .35);
            animation: ak-backtest-progress 1.8s ease-in-out infinite;
        }
        .ak-backtest-progress span.is-determinate {
            transform: none;
            animation: none;
            background: linear-gradient(90deg, rgba(45, 212, 191, .78), rgba(251, 191, 36, .88));
            transition: width .35s ease;
        }
        @keyframes ak-backtest-spin { to { transform: rotate(360deg); } }
        @keyframes ak-backtest-dot {
            0%, 65%, 100% { opacity: .25; transform: translateY(0); }
            32% { opacity: 1; transform: translateY(-2px); }
        }
        @keyframes ak-backtest-progress {
            from { transform: translateX(-110%); }
            to { transform: translateX(310%); }
        }
        @media (prefers-reduced-motion: reduce) {
            .ak-backtest-spinner, .ak-backtest-dots i, .ak-backtest-progress span { animation-duration: 3.5s; }
        }

        #fundamental-heatmap-filters {
            display: grid !important;
            grid-template-columns: repeat(5, minmax(175px, 1fr)) !important;
            gap: 4px;
        }

        #fundamental-heatmap-filters > input[type="hidden"] {
            display: none;
        }

        #fundamental-heatmap-filters .ak-input::placeholder {
            color: #cbd5e1 !important;
            opacity: 1;
        }

        #fundamental-heatmap-filters .ak-fundamental-range {
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

        #fundamental-heatmap-filters .ak-fundamental-range span {
            overflow: hidden;
            color: #cbd5e1;
            font-size: 8px;
            font-weight: 800;
            line-height: 1;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        #fundamental-heatmap-filters .ak-fundamental-range b {
            color: #5eead4;
            font-weight: 900;
        }

        #fundamental-heatmap-filters .ak-fundamental-range input {
            width: 100%;
            height: 12px;
            margin: 0;
            cursor: pointer;
            accent-color: #14b8a6;
        }

        #prediction-heatmap-filters select.ak-input {
            appearance: none;
            -webkit-appearance: none;
            padding: 0 13px 0 5px !important;
            background-color: #182238 !important;
            background-image:
                linear-gradient(45deg, transparent 50%, #cbd5e1 50%),
                linear-gradient(135deg, #cbd5e1 50%, transparent 50%) !important;
            background-position: calc(100% - 7px) 50%, calc(100% - 4px) 50% !important;
            background-size: 3px 3px, 3px 3px !important;
            background-repeat: no-repeat !important;
        }

        #prediction-heatmap-filters select.ak-input option {
            background: #182238;
            color: #f8fafc;
        }

        #prediction-heatmap-filters .ak-input::placeholder {
            color: #f8fafc !important;
            opacity: 1;
        }

        #prediction-heatmap-filters input.ak-input:not([type="range"]):not([type="hidden"]) {
            padding-top: 0 !important;
            padding-bottom: 0 !important;
            line-height: 30px !important;
        }

        #prediction-heatmap-filters .ak-heatmap-range {
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

        #prediction-heatmap-filters .ak-heatmap-range span {
            overflow: hidden;
            color: #cbd5e1;
            font-size: 8px;
            font-weight: 800;
            line-height: 1;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        #prediction-heatmap-filters .ak-heatmap-range b {
            color: #5eead4;
            font-weight: 900;
        }

        #prediction-heatmap-filters .ak-heatmap-range input {
            width: 100%;
            height: 12px;
            margin: 0;
            cursor: pointer;
            accent-color: #14b8a6;
        }

        :root[data-theme="light"] #prediction-heatmap-filters .ak-input {
            color: #0f172a !important;
            -webkit-text-fill-color: #0f172a !important;
        }

        :root[data-theme="light"] #fundamental-heatmap-filters .ak-input {
            color: #0f172a !important;
            -webkit-text-fill-color: #0f172a !important;
            background-color: #f8fafc !important;
        }

        :root[data-theme="light"] #fundamental-heatmap-filters .ak-fundamental-range {
            background: #f8fafc;
        }

        :root[data-theme="light"] #fundamental-heatmap-filters .ak-fundamental-range span {
            color: #334155;
        }

        :root[data-theme="light"] #prediction-heatmap-filters select.ak-input {
            background-color: #f8fafc !important;
        }

        :root[data-theme="light"] #prediction-heatmap-filters .ak-heatmap-range {
            background: #f8fafc;
        }

        :root[data-theme="light"] #prediction-heatmap-filters .ak-heatmap-range span {
            color: #334155;
        }
    </style>
</x-app-layout>
