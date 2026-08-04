<x-app-layout>
    @php
        $setupMode = $setupMode ?? false;
        $shortMode = $shortMode ?? false;
        $qualitySetupMode = $qualitySetupMode ?? false;
        $heatmapFilterRoute = $qualitySetupMode ? 'setup.quality' : ($shortMode ? 'setup.short' : ($setupMode ? 'setup.filter' : 'predictions.heatmap'));
    @endphp
    <div class="flex h-[calc(100dvh-89px)] min-h-0 flex-col py-4 text-[var(--ak-text)]">
        <header class="mb-4 flex shrink-0 items-center justify-between gap-4">
            <div class="flex min-w-0 items-center gap-3">
                <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl border border-amber-300/25 bg-amber-300/[.08] text-amber-300">
                    <x-heroicon-o-squares-2x2 class="h-6 w-6" />
                </div>
                <div class="min-w-0">
                    <p class="text-[10px] font-black uppercase tracking-[.16em] {{ $shortMode ? 'text-rose-400' : ($qualitySetupMode ? 'text-amber-300' : 'text-teal-400') }}">{{ $qualitySetupMode ? __('Premium-Auswahlstatus') : ($shortMode ? __('Short-Strategietester') : ($setupMode ? __('Setup') : __('Historische Validierung'))) }}</p>
                    <h1 class="truncate text-2xl font-black">{{ $qualitySetupMode ? __('Smart Selection') : ($shortMode ? __('SELL-Prognosen testen') : ($setupMode ? __('Filter') : __('Historische Qualität nach KI-Score und Konfidenz'))) }}</h1>
                    <p class="mt-1 text-xs text-[var(--ak-muted)]">{{ $shortMode ? __('Ausschließlich echte SELL-Prognosen werden als Short-Einstieg berücksichtigt.') : __('Trefferquote, Profitfaktor, Drawdown und Volatilität; alle aktuellen Filter werden berücksichtigt.') }}</p>
                </div>
            </div>
            <div class="flex shrink-0 items-center gap-2">
                <div class="inline-flex h-10 items-center gap-2 rounded-lg border border-teal-400/15 bg-teal-400/[.07] px-3" title="{{ __('Erwartete Anzahl auf Basis des gewählten Backtest-Zeitraums') }}">
                    <x-heroicon-o-arrow-path class="h-4 w-4 text-teal-300" />
                    <span class="text-[9px] font-black uppercase tracking-wide text-[var(--ak-muted)]">{{ __('Trades/Monat') }}</span>
                    <strong class="text-sm font-black tabular-nums text-teal-200">{{ number_format((float) ($heatmapSummary?->trades_per_month ?? 0), 1, ',', '.') }}</strong>
                </div>
                <a href="{{ $setupMode ? route('dashboard') : route('predictions.index', request()->query()) }}" class="inline-flex h-10 shrink-0 items-center gap-2 rounded-lg border border-[var(--ak-border)] bg-[var(--ak-card)] px-4 text-xs font-black text-[var(--ak-muted)] transition hover:border-teal-500/35 hover:text-teal-400">
                    <x-heroicon-o-arrow-left class="h-4 w-4" />
                    {{ $setupMode ? __('Zurück zum Dashboard') : __('Zurück zu Prognosen') }}
                </a>
            </div>
        </header>

        <form
            id="prediction-heatmap-filters"
            method="GET"
            action="{{ route($heatmapFilterRoute) }}"
            x-data="{
                score: Number({{ (float) request('score_min', 0) }}),
                confidence: Number({{ (float) request('confidence_min', 0) }}),
                drawdown: Number({{ (float) request('drawdown_max', 50) }}),
                profitFactor: Number({{ (float) request('profit_factor_min', 0) }}),
                volatility: Number({{ (float) request('volatility_max', 100) }}),
                predictedReturn: Number({{ (float) request('predicted_return_min', -20) }}),
                pe: Number({{ (float) request('pe_max', 100) }}),
                dividend: Number({{ (float) request('dividend_yield_min', 0) }}),
                marketCap: Number({{ (float) request('market_cap_min', 0) }}),
                revenueGrowth: Number({{ (float) request('revenue_growth_min', -50) }}),
                hitRate: Number({{ (float) request('hit_rate_min', 0) }}),
                searchTimer: null,
                submitSearch() {
                    window.clearTimeout(this.searchTimer);
                    const form = this.$el.closest('form');
                    this.searchTimer = window.setTimeout(() => form?.requestSubmit(), 450);
                }
            }"
            class="ak-prediction-filterboard relative z-50 mb-3 flex shrink-0 flex-col gap-1 rounded-xl border border-[var(--ak-border)] bg-[var(--ak-card)] p-2 shadow-[var(--ak-shadow)]"
        >
            @if ($qualitySetupMode)<input type="hidden" name="quality_setup" value="1">@endif
            @if ($shortMode)<input type="hidden" name="signal" value="SELL">@endif
            @if (! $qualitySetupMode)
            <div class="flex min-w-0 items-center gap-1">
            <div
                class="grid min-w-0 flex-1 gap-1"
                style="grid-template-columns: {{ $setupMode
                    ? 'minmax(90px,1.05fr) repeat(6,minmax(66px,.85fr)) minmax(138px,1.45fr) minmax(82px,.85fr) 34px'
                    : 'minmax(115px,1.15fr) repeat(6,minmax(82px,1fr)) 38px' }};"
            >
            <input name="q" value="{{ request('q') }}" oninput="clearTimeout(this._filterTimer); this._filterTimer = setTimeout(() => this.form.requestSubmit(), 450)" placeholder="{{ __('Aktie') }}" class="ak-input h-10 min-w-0 rounded-[5px] px-2 text-[11px]">
            <select name="country" onchange="this.form.requestSubmit()" class="ak-input h-10 min-w-0 rounded-[5px] px-1.5 text-[11px]">
                <option value="">{{ __('Land') }}</option>
                @foreach ($countries as $country)<option value="{{ $country }}" @selected(strtoupper((string) request('country')) === strtoupper((string) $country))>{{ $country }}</option>@endforeach
            </select>
            <select name="exchange" onchange="this.form.requestSubmit()" class="ak-input h-10 min-w-0 rounded-[5px] px-1.5 text-[11px]">
                <option value="">{{ __('Börse') }}</option>
                @foreach ($exchanges as $exchange)<option value="{{ $exchange->code }}" @selected(strtoupper((string) request('exchange')) === strtoupper((string) $exchange->code))>{{ $exchange->code }}</option>@endforeach
            </select>
            <select name="sector" onchange="this.form.requestSubmit()" class="ak-input h-10 min-w-0 rounded-[5px] px-1.5 text-[11px]">
                <option value="">{{ __('Sektor') }}</option>
                @foreach ($sectors as $sector)<option value="{{ $sector }}" @selected((string) request('sector') === (string) $sector)>{{ __($sector) }}</option>@endforeach
            </select>
            <select name="ai_type" onchange="this.form.requestSubmit()" class="ak-input h-10 min-w-0 rounded-[5px] px-1.5 text-[11px]">
                <option value="">{{ __('KI-Typ') }}</option>
                @foreach ($aiTypes as $aiType)<option value="{{ $aiType }}" @selected(request('ai_type') === $aiType)>{{ ucfirst((string) $aiType) }}</option>@endforeach
            </select>
            @php
                $selectedModelIds = collect((array) request('model'))
                    ->map(fn ($id) => (int) $id)
                    ->filter()
                    ->unique();
            @endphp
            <div x-data="{ open: false }" class="relative min-w-0">
                <button type="button" @click="open = !open" class="ak-input flex h-10 w-full min-w-0 items-center justify-between rounded-[5px] px-2 text-[11px]" title="{{ __('Modelle') }}">
                    <span class="truncate">{{ $selectedModelIds->isEmpty() ? __('Modelle') : __(':count Modelle', ['count' => $selectedModelIds->count()]) }}</span>
                    <span class="ml-1 rounded bg-teal-400/15 px-1 text-[8px] font-black text-teal-300">{{ $selectedModelIds->count() }}</span>
                </button>
                <div x-show="open" x-cloak @click.outside="open = false" class="absolute left-0 top-9 z-[80] max-h-64 w-64 overflow-y-auto rounded-lg border border-[var(--ak-border)] bg-[#182238] p-2 shadow-2xl">
                    @foreach ($models as $model)
                        <label class="flex cursor-pointer items-center gap-2 rounded-md px-2 py-1.5 hover:bg-white/[.05]">
                            <input type="checkbox" name="model[]" value="{{ $model->id }}" @checked($selectedModelIds->contains((int) $model->id)) onchange="this.form.requestSubmit()" class="h-4 w-4 rounded border-slate-500 bg-slate-900 text-teal-500 focus:ring-teal-500/30">
                            <span class="truncate text-[10px] font-bold text-slate-200">{{ $model->public_alias }}</span>
                        </label>
                    @endforeach
                </div>
            </div>
            <select name="quality_tier" onchange="this.form.requestSubmit()" class="ak-input h-10 min-w-0 rounded-[5px] px-1.5 text-[11px]">
                <option value="">{{ __('Modellstufe mindestens') }}</option>
                @foreach ($qualityTiers as $qualityTier)<option value="{{ $qualityTier->code }}" @selected(request('quality_tier') === $qualityTier->code)>{{ __($qualityTier->name) }}</option>@endforeach
            </select>
            @if ($setupMode)
                <div class="ak-input flex h-10 min-w-0 items-center overflow-hidden rounded-[5px] p-0.5 {{ request('exit_strategy') === 'buy_and_hold' ? 'cursor-not-allowed opacity-45' : '' }}" title="{{ request('exit_strategy') === 'buy_and_hold' ? __('Money Manager ist bei Buy and Hold deaktiviert') : __('Maximaler Kapitalanteil je Aktie bei freiem Kapital') }}" role="radiogroup" aria-label="{{ __('Positionsanteil') }}">
                    <span class="shrink-0 px-1 text-[8px] font-bold text-slate-500">{{ __('Anteil') }}</span>
                    @for ($factor = 1; $factor <= 5; $factor++)
                        <label class="ak-position-factor flex h-full min-w-0 flex-1 cursor-pointer text-[9px] font-black">
                            <input type="radio" name="position_factor" value="{{ $factor }}" @checked((request('exit_strategy') === 'buy_and_hold' ? 1 : (int) request('position_factor', 1)) === $factor) @disabled(request('exit_strategy') === 'buy_and_hold') onchange="this.form.requestSubmit()" class="sr-only">
                            <span class="flex h-full w-full items-center justify-center rounded-[3px] text-slate-400 transition hover:bg-white/[.05] hover:text-slate-200">{{ $factor }}×</span>
                        </label>
                    @endfor
                </div>
                <div x-data="{ open: false }" class="relative min-w-0">
                    <button type="button" @click="open = !open" class="ak-input flex h-10 w-full min-w-0 items-center justify-between rounded-[5px] px-2 text-[11px]">
                        <span class="truncate">{{ __('KI-Rotation') }}</span>
                        <span class="ml-1 rounded bg-teal-400/15 px-1 text-[8px] font-black text-teal-300">{{ (int) request()->boolean('sector_score_rotation') + (int) request()->boolean('index_score_rotation') }}</span>
                    </button>
                    <div x-show="open" x-cloak @click.outside="open = false" class="absolute right-0 top-9 z-50 w-60 rounded-lg border border-[var(--ak-border)] bg-[#182238] p-2 shadow-2xl">
                        <label class="flex cursor-pointer items-center gap-2 rounded-md px-2 py-2 hover:bg-white/[.05]">
                            <input type="checkbox" name="sector_score_rotation" value="1" @checked(request()->boolean('sector_score_rotation')) onchange="this.form.requestSubmit()" class="h-4 w-4 rounded border-slate-500 bg-slate-900 text-teal-500 focus:ring-teal-500/30">
                            <span class="min-w-0"><b class="block text-[10px] text-slate-100">{{ __('Sektorrotation') }}</b><small class="block truncate text-[8px] text-slate-400">{{ __('Bester Ø KI-Score +50 %') }}</small></span>
                        </label>
                        <label class="mt-1 flex cursor-pointer items-center gap-2 rounded-md px-2 py-2 hover:bg-white/[.05]">
                            <input type="checkbox" name="index_score_rotation" value="1" @checked(request()->boolean('index_score_rotation')) onchange="this.form.requestSubmit()" class="h-4 w-4 rounded border-slate-500 bg-slate-900 text-teal-500 focus:ring-teal-500/30">
                            <span class="min-w-0"><b class="block text-[10px] text-slate-100">{{ __('Indexrotation') }}</b><small class="block truncate text-[8px] text-slate-400">{{ __('Bester Ø KI-Score +50 %') }}</small></span>
                        </label>
                    </div>
                </div>
            @endif
                <a href="{{ route($heatmapFilterRoute) }}" class="inline-flex h-10 items-center justify-center rounded-[5px] border border-[var(--ak-border)] text-[var(--ak-muted)] hover:border-teal-500/35 hover:text-teal-400" title="{{ __('Filter zurücksetzen') }}">
                    <x-heroicon-o-arrow-path class="h-4 w-4" />
                </a>
            </div>
            @if ($setupMode)
                <select name="gate_mode" onchange="this.form.requestSubmit()" class="ak-input h-10 w-32 shrink-0 rounded-[5px] px-1.5 text-[11px] {{ empty($hasPersonalQualityGate) ? 'cursor-not-allowed opacity-55' : '' }}" title="{{ __('Quality Gate für diesen Backtest') }}">
                    <option value="system" @selected(request('gate_mode', 'system') === 'system')>{{ __('System-Gate') }}</option>
                    <option value="personal" @selected(request('gate_mode') === 'personal') @disabled(empty($hasPersonalQualityGate))>{{ __('Mein Quality Gate') }}</option>
                </select>
                <div class="inline-flex h-10 shrink-0 items-center justify-center gap-1.5 rounded-[5px] border border-teal-300/25 bg-teal-400/[.10] px-2.5 text-[10px] font-black text-teal-100" title="{{ __('Aktien, die allen Filtern entsprechen') }}">
                    <x-heroicon-o-building-office-2 class="h-4 w-4 shrink-0 text-teal-300" />
                    <span class="tabular-nums">{{ number_format((int) ($heatmapSummary->instruments ?? 0), 0, ',', '.') }}</span>
                    <span class="text-teal-300/80">{{ __('Aktien') }}</span>
                </div>
            @endif
            </div>
            @endif
            <div class="grid {{ $qualitySetupMode ? 'grid-cols-6' : 'grid-cols-5' }} gap-1">
            <label class="ak-heatmap-range">
                <span>{{ __('KI-Score') }} ≥ <b x-text="score.toFixed(1).replace('.', ',')">{{ number_format((float) request('score_min', 0), 1, ',', '.') }}</b></span>
                <input name="score_min" type="range" min="0" max="10" step="0.5" value="{{ (float) request('score_min', 0) }}" x-model.number="score" onchange="this.form.requestSubmit()">
            </label>
            <label class="ak-heatmap-range">
                <span>{{ __('Konfidenz') }} ≥ <b x-text="`${confidence}%`">{{ number_format((float) request('confidence_min', 0), 0, ',', '.') }}%</b></span>
                <input name="confidence_min" type="range" min="0" max="100" step="5" value="{{ (float) request('confidence_min', 0) }}" x-model.number="confidence" onchange="this.form.requestSubmit()">
            </label>
            <label class="ak-heatmap-range">
                <span>{{ __('Drawdown') }} ≤ <b x-text="drawdown >= 50 ? '{{ __('Alle') }}' : `${drawdown}%`">{{ (float) request('drawdown_max', 50) >= 50 ? __('Alle') : number_format((float) request('drawdown_max'), 0, ',', '.').'%' }}</b></span>
                <input name="drawdown_max" type="range" min="0" max="50" step="5" value="{{ (float) request('drawdown_max', 50) }}" x-model.number="drawdown" onchange="this.form.requestSubmit()">
            </label>
            <label class="ak-heatmap-range">
                <span>{{ __('Profitfaktor') }} ≥ <b x-text="profitFactor <= 0 ? '{{ __('Alle') }}' : profitFactor.toFixed(1).replace('.', ',')">{{ (float) request('profit_factor_min', 0) <= 0 ? __('Alle') : number_format((float) request('profit_factor_min'), 1, ',', '.') }}</b></span>
                <input name="profit_factor_min" type="range" min="0" max="3" step="0.1" value="{{ (float) request('profit_factor_min', 0) }}" x-model.number="profitFactor" onchange="this.form.requestSubmit()">
            </label>
            <label class="ak-heatmap-range">
                <span>{{ __('Volatilität') }} ≤ <b x-text="volatility >= 100 ? '{{ __('Alle') }}' : `${volatility}%`">{{ (float) request('volatility_max', 100) >= 100 ? __('Alle') : number_format((float) request('volatility_max'), 0, ',', '.').'%' }}</b></span>
                <input name="volatility_max" type="range" min="0" max="100" step="5" value="{{ (float) request('volatility_max', 100) }}" x-model.number="volatility" onchange="this.form.requestSubmit()">
            </label>
            @if ($qualitySetupMode)
            <label class="ak-heatmap-range">
                <span>{{ __('Prognose') }} ≥ <b x-text="predictedReturn <= -20 ? '{{ __('Alle') }}' : `${predictedReturn.toFixed(1).replace('.', ',')}%`">{{ (float) request('predicted_return_min', -20) <= -20 ? __('Alle') : number_format((float) request('predicted_return_min'), 1, ',', '.').'%' }}</b></span>
                <input name="predicted_return_min" type="range" min="-20" max="20" step="0.5" value="{{ (float) request('predicted_return_min', -20) }}" x-model.number="predictedReturn" onchange="this.form.requestSubmit()">
            </label>
            @endif
            </div>

        @if ($setupMode && ! $shortMode && ! $qualitySetupMode)
            <div
                id="fundamental-heatmap-filters"
                class="min-w-0 overflow-x-auto"
            >
                <label class="ak-fundamental-range">
                    <span>{{ __('KGV') }} ≤ <b x-text="pe >= 100 ? '{{ __('Alle') }}' : pe.toFixed(0)">{{ (float) request('pe_max', 100) >= 100 ? __('Alle') : number_format((float) request('pe_max'), 0, ',', '.') }}</b></span>
                    <input name="pe_max" type="range" min="0" max="100" step="1" value="{{ (float) request('pe_max', 100) }}" x-model.number="pe" onchange="this.form.requestSubmit()">
                </label>
                <label class="ak-fundamental-range">
                    <span>{{ __('Dividendenrendite') }} ≥ <b x-text="`${dividend.toFixed(1).replace('.', ',')} %`">{{ number_format((float) request('dividend_yield_min', 0), 1, ',', '.') }} %</b></span>
                    <input name="dividend_yield_min" type="range" min="0" max="10" step="0.1" value="{{ (float) request('dividend_yield_min', 0) }}" x-model.number="dividend" onchange="this.form.requestSubmit()">
                </label>
                <label class="ak-fundamental-range">
                    <span>{{ __('Marktkapitalisierung') }} ≥ <b x-text="marketCap <= 0 ? '{{ __('Alle') }}' : `${marketCap.toFixed(0)} Mrd.`">{{ (float) request('market_cap_min', 0) <= 0 ? __('Alle') : number_format((float) request('market_cap_min'), 0, ',', '.').' Mrd.' }}</b></span>
                    <input name="market_cap_min" type="range" min="0" max="3000" step="25" value="{{ (float) request('market_cap_min', 0) }}" x-model.number="marketCap" onchange="this.form.requestSubmit()">
                </label>
                <label class="ak-fundamental-range">
                    <span>{{ __('Umsatzwachstum') }} ≥ <b x-text="revenueGrowth <= -50 ? '{{ __('Alle') }}' : `${revenueGrowth.toFixed(0)} %`">{{ (float) request('revenue_growth_min', -50) <= -50 ? __('Alle') : number_format((float) request('revenue_growth_min'), 0, ',', '.').' %' }}</b></span>
                    <input name="revenue_growth_min" type="range" min="-50" max="100" step="1" value="{{ (float) request('revenue_growth_min', -50) }}" x-model.number="revenueGrowth" onchange="this.form.requestSubmit()">
                </label>
                <label class="ak-fundamental-range">
                    <span>{{ __('Hitrate') }} ≥ <b x-text="hitRate <= 0 ? '{{ __('Alle') }}' : `${hitRate.toFixed(0)} %`">{{ (float) request('hit_rate_min', 0) <= 0 ? __('Alle') : number_format((float) request('hit_rate_min'), 0, ',', '.').' %' }}</b></span>
                    <input name="hit_rate_min" type="range" min="0" max="100" step="5" value="{{ (float) request('hit_rate_min', 0) }}" x-model.number="hitRate" onchange="this.form.requestSubmit()">
                </label>
            </div>
        @endif
        </form>

        @if ($shortMode)
            <section class="mb-3 flex shrink-0 items-center justify-between gap-4 rounded-xl border border-rose-300/20 bg-rose-400/[.055] px-4 py-3">
                <div class="flex min-w-0 items-center gap-3">
                    <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-rose-400/10 text-rose-300"><x-heroicon-o-arrow-trending-down class="h-5 w-5" /></span>
                    <div class="min-w-0">
                        <p class="text-[10px] font-black uppercase tracking-[.12em] text-rose-300">{{ __('Short-Datenbasis') }}</p>
                        <p class="truncate text-[10px] text-[var(--ak-muted)]">{{ __('Der Tester verwendet nur historisch berechnete SELL-Prognosen mit negativer 20-Tage-Prognose. Long-Trades werden ausgeschlossen.') }}</p>
                    </div>
                </div>
                <span class="shrink-0 rounded-lg border border-amber-300/20 bg-amber-300/[.08] px-3 py-2 text-[9px] font-black text-amber-200">{{ __('Historischer Short-Lauf erforderlich') }}</span>
            </section>
        @endif

        @if ($setupMode && ! $shortMode)
            @php
                $selectedExitStrategy = (string) request('exit_strategy', $editingSavedFilter?->filters['exit_strategy'] ?? 'fixed_20d');
            @endphp
            <section x-data="{ saveOpen: false, exitStrategy: @js($selectedExitStrategy), visibility: @js(old('visibility', $editingSavedFilter?->visibility ?? 'private')) }" @open-save-filter.window="saveOpen = true" class="contents">
                <div class="hidden">
                    <x-heroicon-o-bookmark class="h-4 w-4" />
                    {{ __('Gespeicherte Filter') }}
                    <span class="rounded-md bg-white/[.06] px-1.5 py-0.5 text-[9px] text-[var(--ak-muted)]">{{ $savedFilters->count() }} / {{ $savedFilterLimit }}</span>
                    @if ($editingSavedFilter)<span class="rounded-md border border-amber-300/20 bg-amber-300/[.08] px-2 py-1 normal-case tracking-normal text-amber-200">{{ __('Bearbeitung: :name', ['name' => $editingSavedFilter->name]) }}</span>@endif
                </div>
                <div class="hidden">
                    @forelse ($savedFilters as $savedFilter)
                        <div class="flex shrink-0 items-center overflow-hidden rounded-md border {{ (int) request('saved_filter') === (int) $savedFilter->id ? 'border-teal-300/40 bg-teal-400/[.12]' : 'border-white/[.08] bg-white/[.035]' }}">
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
                <button type="button" @click="saveOpen = true" class="hidden">
                    @if ($editingSavedFilter)<x-heroicon-o-check class="h-3.5 w-3.5" />{{ __('Änderungen speichern') }}@else<x-heroicon-o-plus class="h-3.5 w-3.5" />{{ __('Filter speichern') }}@endif
                </button>

                <template x-teleport="body">
                <div x-show="saveOpen" x-cloak class="fixed inset-0 z-[9999] flex items-center justify-center p-4" style="background: rgb(7, 17, 31) !important; opacity: 1 !important;" @keydown.escape.window="saveOpen = false">
                    <form method="POST" action="{{ $qualitySetupMode ? route('setup.quality.labels.store') : route('setup.filter.saved.store') }}" class="relative isolate w-full max-w-3xl overflow-hidden rounded-2xl border border-teal-300/20 p-5 shadow-2xl" style="background: rgba(21, 36, 58, 0.90) !important; background-image: none !important; backdrop-filter: none !important;" @click.outside="saveOpen = false">
                        @csrf
                        @if ($qualitySetupMode && request('backtest_run'))<input type="hidden" name="backtest_run" value="{{ request('backtest_run') }}">@endif
                        @if ($editingSavedFilter)<input type="hidden" name="saved_filter" value="{{ $editingSavedFilter->id }}">@endif
                        @foreach (\App\Http\Controllers\SavedPredictionFilterController::FILTER_KEYS as $filterKey)
                            @continue($filterKey === 'exit_strategy')
                            @php
                                $filterValue = request(
                                    $filterKey,
                                    \App\Http\Controllers\SavedPredictionFilterController::FILTER_DEFAULTS[$filterKey] ?? ''
                                );
                            @endphp
                            @if (is_array($filterValue))
                                @foreach ($filterValue as $item)<input type="hidden" name="{{ $filterKey }}[]" value="{{ $item }}">@endforeach
                            @else
                                <input type="hidden" name="{{ $filterKey }}" value="{{ $filterValue }}">
                            @endif
                        @endforeach
                        <div class="flex items-start justify-between gap-4">
                            <div><p class="text-[10px] font-black uppercase tracking-[.14em] text-teal-400">{{ $qualitySetupMode ? __('Smart Selection speichern') : __('Filter speichern') }}</p><h2 class="mt-1 text-xl font-black text-white">{{ $qualitySetupMode ? __('Name der Smart Selection') : __('Exitstrategie und Filtername') }}</h2></div>
                            <button type="button" @click="saveOpen = false" class="flex h-8 w-8 items-center justify-center rounded-lg border border-white/10 text-slate-400 hover:text-white"><x-heroicon-o-x-mark class="h-4 w-4" /></button>
                        </div>
                        @if (! $qualitySetupMode)
                        <div class="mt-5">
                            <div class="flex items-end justify-between gap-3">
                                <div><p class="text-[10px] font-black uppercase tracking-wide text-slate-400">{{ __('Exitstrategie') }}</p><p class="mt-1 text-[10px] text-slate-500">{{ __('Wähle genau eine Ausstiegslogik für diesen Filter.') }}</p></div>
                                <span class="rounded-md bg-teal-400/10 px-2 py-1 text-[9px] font-black text-teal-300">{{ __('Eine Auswahl') }}</span>
                            </div>
                            <div class="mt-2 grid grid-cols-4 gap-2">
                                @foreach ([
                                    ['fixed_20d', __('20 Tage'), __('Verkauf nach 20 Handelstagen'), 'save-exit-fixed'],
                                    ['winner_runner', __('Winner Runner'), __('Gewinne laufen lassen · maximal 90 Tage'), 'save-exit-winner'],
                                    ['prediction_target', __('Prognoseziel'), __('Verkauf beim Erreichen der erwarteten Rendite'), 'save-exit-target'],
                                    ['buy_and_hold', __('Buy and Hold'), __('Gekaufte Aktien werden dauerhaft gehalten'), 'save-exit-buy-hold'],
                                ] as [$exitValue, $exitLabel, $exitDescription, $exitMetricId])
                                    <label class="cursor-pointer rounded-lg border p-2.5 transition" :class="exitStrategy === '{{ $exitValue }}' ? 'border-teal-300/35 bg-teal-400/[.10]' : 'border-white/[.08] bg-white/[.025]'">
                                        <div class="flex items-start gap-2">
                                            <input type="radio" name="exit_strategy" value="{{ $exitValue }}" x-model="exitStrategy" @checked($selectedExitStrategy === $exitValue) required class="mt-0.5 h-4 w-4 border-slate-500 bg-slate-900 text-teal-500 focus:ring-teal-500/30">
                                            <span><b class="block text-[11px] text-white">{{ $exitLabel }}</b><small class="mt-1 block text-[9px] leading-4 text-slate-400">{{ $exitDescription }}</small></span>
                                        </div>
                                        <div id="{{ $exitMetricId }}" class="mt-2 border-t border-white/[.07] pt-2 text-[9px] font-bold text-slate-500">{{ __('Noch kein passendes Backtestergebnis') }}</div>
                                        <p x-show="exitStrategy === 'buy_and_hold' && '{{ $exitValue }}' === 'buy_and_hold'" class="mt-2 rounded bg-amber-300/[.08] px-2 py-1 text-[8px] font-bold text-amber-200">{{ __('Money Manager deaktiviert · Positionsfaktor 1×') }}</p>
                                    </label>
                                @endforeach
                            </div>
                        </div>
                        @endif
                        <div class="mt-5 grid {{ $qualitySetupMode ? 'grid-cols-[1fr_140px]' : 'grid-cols-1' }} gap-3">
                            <label class="block text-[10px] font-black uppercase tracking-wide text-slate-400">{{ __('Name') }}
                                <input name="name" value="{{ $editingSavedFilter?->name }}" maxlength="80" required autofocus class="ak-input mt-2 h-11 w-full rounded-lg px-3 text-sm font-bold text-white" placeholder="{{ $qualitySetupMode ? __('z. B. Momentum Favorit') : __('z. B. Quality Europa') }}">
                            </label>
                            @if ($qualitySetupMode)
                            <label x-data="{ color: '#14b8a6' }" class="block text-[10px] font-black uppercase tracking-wide text-slate-400">{{ __('Labelfarbe') }}
                                <span class="mt-2 flex h-11 items-center gap-2 rounded-lg border border-white/10 bg-slate-950/40 px-2">
                                    <input name="color" type="color" value="#14b8a6" x-model="color" required class="h-7 w-10 cursor-pointer rounded border-0 bg-transparent p-0">
                                    <span class="min-w-0 truncate font-mono text-[10px] normal-case tracking-normal text-slate-300" x-text="color.toUpperCase()"></span>
                                </span>
                            </label>
                            @endif
                        </div>
                        @if (! $qualitySetupMode)
                            <div class="mt-3 grid gap-3 md:grid-cols-[1fr_290px]">
                                <label class="block text-[10px] font-black uppercase tracking-wide text-slate-400">{{ __('Kommentar') }} <span class="normal-case text-slate-600">({{ __('optional') }})</span>
                                    <textarea name="description" maxlength="1000" rows="3" class="ak-input mt-2 w-full rounded-lg px-3 py-2 text-xs font-normal normal-case leading-5 text-white" placeholder="{{ __('Beschreibe kurz Ziel, Auswahlregeln und Besonderheiten der Strategie.') }}">{{ old('description', $editingSavedFilter?->description) }}</textarea>
                                </label>
                                <fieldset><legend class="text-[10px] font-black uppercase tracking-wide text-slate-400">{{ __('Sichtbarkeit') }}</legend><div class="mt-2 grid gap-2">
                                    <label class="cursor-pointer rounded-lg border p-3 transition" :class="visibility === 'private' ? 'border-teal-300/40 bg-teal-400/[.10]' : 'border-white/10 bg-slate-950/25'"><div class="flex items-start gap-2"><input type="radio" name="visibility" value="private" x-model="visibility" required class="mt-0.5 h-4 w-4 border-slate-500 bg-slate-900 text-teal-500"><span><b class="block text-xs text-white">{{ __('Privat') }}</b><small class="mt-1 block text-[9px] leading-4 text-slate-400">{{ __('Nur du kannst diese Strategie sehen und verwenden.') }}</small></span></div></label>
                                    <label class="cursor-pointer rounded-lg border p-3 transition" :class="visibility === 'pro_public' ? 'border-amber-300/45 bg-amber-300/[.10]' : 'border-white/10 bg-slate-950/25'"><div class="flex items-start gap-2"><input type="radio" name="visibility" value="pro_public" x-model="visibility" required class="mt-0.5 h-4 w-4 border-slate-500 bg-slate-900 text-amber-500"><span><b class="block text-xs text-white">{{ __('Öffentlich für Pro') }}</b><small class="mt-1 block text-[9px] leading-4 text-slate-400">{{ __('Pro-Nutzer können eine eigene Kopie importieren. Nur du bearbeitest das Original.') }}</small></span></div></label>
                                </div></fieldset>
                            </div>
                        @endif
                        @if ($qualitySetupMode)
                        <div x-data="{ icon: 'sparkles' }" class="mt-3">
                            <input type="hidden" name="icon" x-model="icon">
                            <p class="text-[10px] font-black uppercase tracking-wide text-slate-400">{{ __('Symbol') }}</p>
                            <div class="mt-2 grid grid-cols-6 gap-2">
                                @foreach ([
                                    ['sparkles', 'heroicon-o-sparkles', __('Favorit')],
                                    ['bolt', 'heroicon-o-bolt', __('Momentum')],
                                    ['trophy', 'heroicon-o-trophy', __('Top-Auswahl')],
                                    ['shield-check', 'heroicon-o-shield-check', __('Qualität')],
                                    ['chart-bar', 'heroicon-o-chart-bar', __('Analyse')],
                                    ['rocket-launch', 'heroicon-o-rocket-launch', __('Chance')],
                                ] as [$iconValue, $iconComponent, $iconLabel])
                                    <button type="button" @click="icon = '{{ $iconValue }}'" class="flex h-12 items-center justify-center gap-2 rounded-lg border transition" :class="icon === '{{ $iconValue }}' ? 'border-teal-300/50 bg-teal-400/15 text-teal-200' : 'border-white/10 bg-slate-950/30 text-slate-400 hover:border-white/20 hover:text-white'" title="{{ $iconLabel }}">
                                        <x-dynamic-component :component="$iconComponent" class="h-5 w-5" />
                                        <span class="hidden text-[9px] font-bold xl:inline">{{ $iconLabel }}</span>
                                    </button>
                                @endforeach
                            </div>
                        </div>
                        @endif
                        @error('exit_strategy')<p class="mt-2 text-[10px] font-bold text-rose-300">{{ $message }}</p>@enderror
                        <p class="mt-3 text-[10px] text-slate-400">{{ $qualitySetupMode ? __('Die Kriterien werden als eigene Smart-Selection-Kategorie gespeichert. Strategien und das systemweite Quality Gate bleiben unverändert.') : __('Dein Tarif erlaubt :count gespeicherte Filter.', ['count' => $savedFilterLimit]) }}</p>
                        <div class="mt-5 flex justify-end gap-2"><button type="button" @click="saveOpen = false" class="h-10 rounded-lg border border-white/10 px-4 text-xs font-bold text-slate-300">{{ __('Abbrechen') }}</button><button type="submit" class="h-10 rounded-lg border border-teal-300/30 bg-teal-400/15 px-5 text-xs font-black text-teal-200 hover:bg-teal-400/20">{{ __('Speichern') }}</button></div>
                    </form>
                </div>
                </template>
            </section>
            @error('saved_filter')<p class="-mt-2 mb-2 shrink-0 text-xs font-bold text-rose-300">{{ $message }}</p>@enderror
            @php
                $backtestIsActive = isset($activeBacktestRun) && in_array($activeBacktestRun?->status, ['queued', 'running'], true);
                $backtestIsComplete = isset($activeBacktestRun) && in_array($activeBacktestRun?->status, ['completed', 'completed_with_errors'], true);
                $backtestFilters = [
                    'q', 'country', 'exchange', 'sector', 'ai_type', 'model', 'quality_tier', 'signal',
                    'score_min', 'confidence_min', 'drawdown_max', 'profit_factor_min', 'volatility_max',
                    'pe_max', 'dividend_yield_min', 'market_cap_min', 'revenue_growth_min', 'hit_rate_min',
                    'risk_max', 'predicted_return_min', 'minimum_trades', 'positive_prediction_required', 'ensemble_veto_required', 'quality_gate_profile',
                    'gate_mode', 'quality_setup',
                    'sector_score_rotation', 'index_score_rotation', 'position_factor', 'exit_strategy',
                ];
            @endphp
            <section x-data="{ capitalOpen: false, capital: 10000, positions: Number({{ max(1, min(50, (int) request('max_positions', 5))) }}), positionFactor: Number({{ request('exit_strategy') === 'buy_and_hold' ? 1 : max(1, (int) request('position_factor', 1)) }}), tradeCost: 10, moneyManagerEnabled: @js(request('exit_strategy') !== 'buy_and_hold') }" class="relative mb-3 flex shrink-0 items-center justify-between gap-3 overflow-hidden rounded-xl border border-amber-300/20 bg-amber-300/[.055] px-3 py-2 {{ $backtestIsActive ? 'pb-4' : '' }}">
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
                    <form method="POST" action="{{ route('setup.filter.backtest.cancel', $activeBacktestRun->public_id) }}" x-data="{ cancelling: false }" @submit="cancelling = true" class="shrink-0">
                        @csrf
                        @if ($qualitySetupMode)<input type="hidden" name="quality_setup" value="1">@endif
                        @foreach ($backtestFilters as $filter)
                            @if (request()->filled($filter) || $filter === 'position_factor')
                                @if (is_array(request($filter)))
                                    @foreach (request($filter) as $item)<input type="hidden" name="{{ $filter }}[]" value="{{ $item }}">@endforeach
                                @else
                                    <input type="hidden" name="{{ $filter }}" value="{{ request($filter, 1) }}" @if ($filter === 'position_factor') x-model.number="positionFactor" @endif>
                                @endif
                            @endif
                        @endforeach
                        <button type="submit" :disabled="cancelling" class="inline-flex h-9 items-center gap-2 rounded-lg border border-rose-300/25 bg-rose-400/[.07] px-4 text-[10px] font-black uppercase tracking-[.08em] text-rose-200 transition hover:bg-rose-400/15 disabled:cursor-wait disabled:opacity-60">
                            <span x-show="cancelling" class="ak-backtest-spinner h-4 w-4" aria-hidden="true"></span>
                            <x-heroicon-o-x-mark x-show="!cancelling" class="h-4 w-4" />
                            <span x-text="cancelling ? @js(__('Wird abgebrochen …')) : @js(__('Abbrechen'))"></span>
                        </button>
                    </form>
                @elseif ($backtestIsComplete)
                    <div class="flex shrink-0 items-center gap-2">
                        <button type="button" onclick="window.dispatchEvent(new CustomEvent('open-save-filter'))" class="inline-flex h-9 items-center gap-2 rounded-lg border border-teal-300/25 bg-teal-400/[.09] px-3 text-[10px] font-black uppercase tracking-[.06em] text-teal-200 transition hover:bg-teal-400/[.16]">
                            <x-heroicon-o-bookmark class="h-4 w-4" />{{ $qualitySetupMode ? __('Smart Selection speichern') : ($editingSavedFilter ? __('Änderungen speichern') : __('Strategie speichern')) }}
                        </button>
                        @if (app(\App\Services\PlanAccessService::class)->allows(auth()->user(), \App\Enums\PlanLevel::Premium))
                            <a href="{{ route('setup.quality') }}" class="inline-flex h-9 items-center gap-2 rounded-lg border border-amber-300/25 bg-amber-300/[.09] px-3 text-[10px] font-black uppercase tracking-[.06em] text-amber-200 transition hover:bg-amber-300/[.16]">
                                <x-heroicon-o-shield-check class="h-4 w-4" />{{ __('Smart Selection') }}
                            </a>
                        @elseif (app(\App\Services\PlanAccessService::class)->allows(auth()->user(), \App\Enums\PlanLevel::Pro))
                            <button type="button" disabled title="{{ __('Verfügbar ab Premium') }}" class="inline-flex h-9 cursor-not-allowed items-center gap-2 rounded-lg border border-slate-500/15 bg-slate-500/[.05] px-3 text-[10px] font-black uppercase tracking-[.06em] text-slate-500 opacity-70">
                                <x-heroicon-o-lock-closed class="h-4 w-4" />{{ __('Smart Selection') }}
                                <span class="rounded bg-slate-400/10 px-1.5 py-0.5 text-[8px]">{{ __('Premium') }}</span>
                            </button>
                        @endif
                        <a href="{{ route($qualitySetupMode ? 'setup.quality' : 'setup.filter', request()->except(['backtest_run', 'initial_capital', 'trade_cost'])) }}" class="inline-flex h-9 items-center gap-2 rounded-lg border border-white/10 px-3 text-[10px] font-black uppercase tracking-[.06em] text-slate-300 hover:text-white">
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
                        <form method="POST" action="{{ route('setup.filter.backtest') }}" x-data="{ submitting: false }" @submit="submitting = true" class="w-full max-w-lg rounded-2xl border border-teal-300/20 bg-[#15243a]/90 p-5 shadow-2xl" @click.outside="capitalOpen = false">
                            @csrf
                            @if ($qualitySetupMode)<input type="hidden" name="quality_setup" value="1">@endif
                            @foreach ($backtestFilters as $filter)
                                @if (request()->filled($filter) || $filter === 'position_factor')
                                    @if (is_array(request($filter)))
                                        @foreach (request($filter) as $item)<input type="hidden" name="{{ $filter }}[]" value="{{ $item }}">@endforeach
                                    @else
                                        <input type="hidden" name="{{ $filter }}" value="{{ request($filter, 1) }}" @if ($filter === 'position_factor') x-model.number="positionFactor" @endif>
                                    @endif
                                @endif
                            @endforeach
                            <div class="mb-5 flex items-start justify-between gap-4">
                                <div>
                                    <p class="text-[10px] font-black uppercase tracking-[.14em] text-amber-300">{{ __('Backtest konfigurieren') }}</p>
                                    <h2 class="mt-1 text-xl font-black text-white">{{ $qualitySetupMode ? __('Testbetrag') : __('Kapital und Positionsgröße') }}</h2>
                                    <p class="mt-1 text-xs text-slate-300">{{ $qualitySetupMode ? __('Lege den Betrag für den Vergleich deiner Smart Selection mit dem S&P 500 fest.') : __('Es werden nur neue Positionen eröffnet, wenn Kapital und ein freier Aktienplatz verfügbar sind.') }}</p>
                                </div>
                                <button type="button" @click="capitalOpen = false" class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg border border-white/10 text-slate-300 hover:text-white"><x-heroicon-o-x-mark class="h-5 w-5" /></button>
                            </div>
                            @if ($qualitySetupMode)
                                <input type="hidden" name="max_positions" value="5">
                                <input type="hidden" name="trade_cost" value="10">
                            @endif
                            <div class="grid {{ $qualitySetupMode ? 'grid-cols-1' : 'grid-cols-3' }} gap-3">
                                <label class="text-[10px] font-black uppercase tracking-wide text-slate-400">
                                    {{ $qualitySetupMode ? __('Testbetrag') : __('Startkapital') }}
                                    <div class="relative mt-2"><input name="initial_capital" type="number" min="1000" max="1000000" step="100" x-model.number="capital" required class="ak-input h-11 w-full rounded-lg pr-8 text-sm font-bold text-white"><span class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400">€</span></div>
                                </label>
                                @if (! $qualitySetupMode)
                                <label class="text-[10px] font-black uppercase tracking-wide text-slate-400">
                                    {{ __('Max. Aktien') }}
                                    <input name="max_positions" type="number" min="1" max="50" step="1" x-model.number="positions" @input="positionFactor = Math.min(positionFactor, Math.max(1, positions))" required class="ak-input mt-2 h-11 w-full rounded-lg text-sm font-bold text-white">
                                </label>
                                <label class="text-[10px] font-black uppercase tracking-wide text-slate-400">
                                    {{ __('Kosten je Trade') }}
                                    <div class="relative mt-2"><input name="trade_cost" type="number" min="0" max="1000" step="0.01" x-model.number="tradeCost" required class="ak-input h-11 w-full rounded-lg pr-8 text-sm font-bold text-white"><span class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400">€</span></div>
                                </label>
                                @endif
                            </div>
                            @if (! $qualitySetupMode)
                            <div class="mt-4 rounded-xl border border-white/[.08] bg-white/[.035] px-4 py-3 text-xs text-slate-300">
                                {{ __('Grundanteil je Aktie') }}: <strong class="text-white" x-text="`${Math.max(0, capital / Math.max(1, positions)).toLocaleString('de-DE', { style: 'currency', currency: 'EUR' })}`"></strong>
                                <span class="mx-2 text-slate-600">·</span>
                                {{ __('Bei freiem Kapital maximal') }}:
                                <strong class="text-amber-200" x-text="`${Math.max(0, capital / Math.max(1, positions) * positionFactor).toLocaleString('de-DE', { style: 'currency', currency: 'EUR' })} (${positionFactor}/${Math.max(1, positions)})`"></strong>
                            </div>
                            @endif
                            <div class="mt-5 flex justify-end gap-2">
                                <button type="button" @click="capitalOpen = false" class="h-10 rounded-lg border border-white/10 px-4 text-xs font-bold text-slate-300">{{ __('Abbrechen') }}</button>
                                <button type="submit" :disabled="submitting" class="inline-flex h-10 items-center gap-2 rounded-lg border border-amber-300/30 bg-amber-300/15 px-5 text-xs font-black text-amber-200 hover:bg-amber-300/20 disabled:cursor-wait disabled:opacity-60">
                                    <span x-show="submitting" class="ak-backtest-spinner h-4 w-4" aria-hidden="true"></span>
                                    <x-heroicon-o-play x-show="!submitting" class="h-4 w-4" />
                                    <span x-text="submitting ? @js(__('Wird gestartet …')) : @js(__('Backtest ausführen'))"></span>
                                </button>
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
            $heatmapScoreFilter = max(0, min(10, (float) request('score_min', 0)));
            $heatmapConfidenceFilter = max(0, min(100, (float) request('confidence_min', 0)));
            $heatmapMetrics = [
                ['key' => 'hit_rate', 'label' => __('Hitrate'), 'suffix' => '%'],
                ['key' => 'profit_factor', 'label' => __('Profitfaktor'), 'suffix' => ''],
                ['key' => 'drawdown', 'label' => __('Drawdown'), 'suffix' => '%'],
                ['key' => 'volatility', 'label' => __('Volatilität'), 'suffix' => '%'],
            ];
            $averageBars = [
                'hit_rate' => [
                    'label' => __('Ø Hitrate'),
                    'display' => number_format((float) ($heatmapSummary?->hit_rate ?? 0), 1, ',', '.').' %',
                    'width' => max(0, min(100, (float) ($heatmapSummary?->hit_rate ?? 0))),
                    'color' => 'bg-emerald-400',
                    'colors' => ['bg-rose-500', 'bg-rose-400', 'bg-orange-400', 'bg-amber-400', 'bg-yellow-300', 'bg-lime-300', 'bg-lime-400', 'bg-green-400', 'bg-emerald-400', 'bg-emerald-500'],
                    'palette' => ['#f43f5e', '#fb7185', '#fb923c', '#fbbf24', '#fde047', '#bef264', '#a3e635', '#4ade80', '#34d399', '#10b981'],
                ],
                'profit_factor' => [
                    'label' => __('Ø Profitfaktor'),
                    'display' => number_format((float) ($heatmapSummary?->profit_factor ?? 0), 2, ',', '.'),
                    'width' => max(0, min(100, ((float) ($heatmapSummary?->profit_factor ?? 0) / 2.5) * 100)),
                    'color' => 'bg-teal-400',
                    'colors' => ['bg-rose-500', 'bg-rose-500', 'bg-rose-400', 'bg-rose-400', 'bg-yellow-300', 'bg-yellow-300', 'bg-lime-300', 'bg-lime-400', 'bg-green-400', 'bg-emerald-500'],
                    'palette' => ['#f43f5e', '#f43f5e', '#fb7185', '#fb7185', '#fde047', '#fde047', '#bef264', '#a3e635', '#4ade80', '#10b981'],
                ],
                'drawdown' => [
                    'label' => __('Max. Drawdown'),
                    'display' => number_format((float) ($heatmapSummary?->drawdown ?? 0), 1, ',', '.').' %',
                    'width' => max(0, min(100, ((float) ($heatmapSummary?->drawdown ?? 0) / 50) * 100)),
                    'color' => 'bg-rose-400',
                    'colors' => ['bg-emerald-400', 'bg-lime-400', 'bg-yellow-300', 'bg-yellow-300', 'bg-amber-300', 'bg-amber-300', 'bg-orange-400', 'bg-orange-400', 'bg-rose-400', 'bg-rose-500'],
                    'palette' => ['#34d399', '#a3e635', '#fde047', '#fde047', '#fcd34d', '#fcd34d', '#fb923c', '#fb923c', '#fb7185', '#f43f5e'],
                ],
                'volatility' => [
                    'label' => __('Ø Volatilität'),
                    'display' => number_format((float) ($heatmapSummary?->volatility ?? 0), 1, ',', '.').' %',
                    'width' => max(0, min(100, ((float) ($heatmapSummary?->volatility ?? 0) / 50) * 100)),
                    'color' => 'bg-cyan-300',
                    'palette' => ['#34d399', '#a3e635', '#fde047', '#fde047', '#fcd34d', '#fcd34d', '#fb923c', '#fb923c', '#fb7185', '#f43f5e'],
                ],
            ];
        @endphp

        <section class="grid min-h-0 w-full flex-1 grid-cols-4 items-start gap-3 overflow-hidden">
            @foreach ($heatmapMetrics as $metric)
                @php $bar = $averageBars[$metric['key']]; @endphp
                <article class="flex aspect-square min-h-0 min-w-0 flex-col overflow-hidden rounded-2xl border border-[var(--ak-border)] bg-[var(--ak-card)] p-3 shadow-[var(--ak-shadow)]">
                    <div class="mb-2 flex shrink-0 items-center justify-between gap-2">
                        <h2 class="text-sm font-black">{{ $metric['label'] }}</h2>
                        <div class="flex items-center gap-2 text-[7px] font-bold uppercase tracking-wide text-amber-300/60">
                            <span>{{ __('KI') }} ≥ <b data-heatmap-score-label>{{ number_format($heatmapScoreFilter, 1, ',', '.') }}</b></span>
                            <span>{{ __('Konf.') }} ≥ <b data-heatmap-confidence-label>{{ number_format($heatmapConfidenceFilter, 0, ',', '.') }}</b> %</span>
                        </div>
                    </div>
                    <div class="mb-2 shrink-0 rounded-md border border-white/[.06] bg-white/[.025] px-2 py-1.5">
                        <div class="mb-1 flex items-baseline justify-between gap-2">
                            <span class="truncate text-[8px] font-black uppercase tracking-[.08em] text-[var(--ak-muted)]">{{ $bar['label'] }}</span>
                            <strong class="shrink-0 text-xs font-black tabular-nums">{{ $bar['display'] }}</strong>
                        </div>
                        @php $reachedSegments = (int) ceil($bar['width'] / 10); @endphp
                        <div class="flex h-2 items-stretch gap-1">
                            @for ($segment = 1; $segment <= 10; $segment++)
                                @php
                                    $segmentHex = $bar['palette'][$segment - 1];
                                    $segmentOpacity = $segment < $reachedSegments
                                        ? .4
                                        : ($segment === $reachedSegments && $reachedSegments > 0 ? 1 : 0);
                                @endphp
                                <span class="min-w-0 flex-1 rounded-[2px] {{ $segmentOpacity <= 0 ? 'bg-slate-400/10' : '' }}" @if ($segmentOpacity > 0) style="background-color: {{ $segmentHex }}; opacity: {{ $segmentOpacity }};" @endif></span>
                            @endfor
                        </div>
                    </div>
                    <div class="grid min-h-0 flex-1 grid-cols-[30px_repeat(10,minmax(0,1fr))] grid-rows-[repeat(10,minmax(0,1fr))_12px] gap-1" style="position: relative;">
                        <div
                            aria-hidden="true"
                            style="position: absolute; inset: 0 0 16px 34px; z-index: 20; overflow: hidden; pointer-events: none;"
                        >
                            <span
                                data-heatmap-score-line
                                style="position: absolute; top: 0; bottom: 0; left: {{ max(1, min(99, $heatmapScoreFilter * 10)) }}%; display: block; width: 1px; background: repeating-linear-gradient(to bottom, rgba(251, 191, 36, .48) 0 4px, transparent 4px 8px);"
                            ></span>
                            <span
                                data-heatmap-confidence-line
                                style="position: absolute; right: 0; bottom: {{ max(1, min(99, $heatmapConfidenceFilter)) }}%; left: 0; display: block; height: 1px; background: repeating-linear-gradient(to right, rgba(251, 191, 36, .48) 0 4px, transparent 4px 8px);"
                            ></span>
                        </div>
                        @for ($confidenceBucket = 9; $confidenceBucket >= 0; $confidenceBucket--)
                            <div class="flex items-center justify-end pr-0.5 text-[7px] font-bold tabular-nums text-[var(--ak-muted)]">
                                {{ $confidenceBucket * 10 }}–{{ ($confidenceBucket + 1) * 10 }}
                            </div>
                            @for ($scoreBucket = 0; $scoreBucket <= 9; $scoreBucket++)
                                @php
                                    $cell = $heatmap->get($scoreBucket.'-'.$confidenceBucket);
                                    $samples = (int) ($cell->samples ?? 0);
                                    $rawValue = is_numeric(data_get($cell, $metric['key'])) ? (float) data_get($cell, $metric['key']) : null;
                                    $hasValue = $metric['key'] === 'drawdown'
                                        ? $samples > 0
                                        : $samples >= 5 && $rawValue !== null;
                                    $good = match ($metric['key']) {
                                        'hit_rate' => $rawValue > 50,
                                        'profit_factor' => $rawValue >= 1.05,
                                        'drawdown' => $rawValue <= 25,
                                        'volatility' => $rawValue <= 25,
                                    };
                                    $weak = match ($metric['key']) {
                                        'hit_rate' => $rawValue < 50,
                                        'profit_factor' => $rawValue <= 1,
                                        'drawdown' => $rawValue > 45,
                                        'volatility' => $rawValue > 40,
                                    };
                                    $drawdownLimit = max(0.0, min(50.0, (float) request('drawdown_max', 50)));
                                    $drawdownRatio = $drawdownLimit > 0 ? $rawValue / $drawdownLimit : ($rawValue <= 0 ? 0 : INF);
                                    $drawdownExcessRatio = $rawValue > $drawdownLimit
                                        ? ($rawValue - $drawdownLimit) / max(1.0, 50.0 - $drawdownLimit)
                                        : 0.0;
                                    $drawdownClass = $rawValue <= $drawdownLimit
                                        ? match (true) {
                                            $drawdownRatio <= .20 => 'border-emerald-300/25 bg-emerald-400/38 text-emerald-50',
                                            $drawdownRatio <= .40 => 'border-emerald-300/25 bg-emerald-400/30 text-emerald-50',
                                            $drawdownRatio <= .60 => 'border-emerald-300/25 bg-emerald-400/23 text-emerald-100',
                                            $drawdownRatio <= .80 => 'border-emerald-300/25 bg-green-400/17 text-green-100',
                                            default => 'border-emerald-300/25 bg-lime-400/12 text-lime-100',
                                        }
                                        : match (true) {
                                            $drawdownExcessRatio <= .25 => 'border-amber-300/20 bg-yellow-300/18 text-yellow-50',
                                            $drawdownExcessRatio <= .50 => 'border-amber-300/20 bg-amber-400/23 text-amber-50',
                                            $drawdownExcessRatio <= .75 => 'border-amber-300/20 bg-orange-400/27 text-orange-50',
                                            default => 'border-rose-400/20 bg-rose-500/30 text-rose-50',
                                        };
                                    $profitFactorClass = match (true) {
                                        $rawValue < 1.01 => 'border-rose-300/45 bg-rose-500/30 text-rose-50',
                                        $rawValue <= 1.10 => 'border-yellow-300/40 bg-yellow-300/22 text-yellow-50',
                                        default => 'border-emerald-300/30 bg-emerald-400/20 text-emerald-100',
                                    };
                                    $volatilityLimit = max(0.0, min(100.0, (float) request('volatility_max', 100)));
                                    $volatilityRatio = $volatilityLimit > 0 ? $rawValue / $volatilityLimit : ($rawValue <= 0 ? 0 : INF);
                                    $volatilityExcessRatio = $rawValue > $volatilityLimit
                                        ? ($rawValue - $volatilityLimit) / max(1.0, 100.0 - $volatilityLimit)
                                        : 0.0;
                                    $volatilityClass = $rawValue <= $volatilityLimit
                                        ? match (true) {
                                            $volatilityRatio <= .20 => 'border-emerald-300/25 bg-emerald-400/38 text-emerald-50',
                                            $volatilityRatio <= .40 => 'border-emerald-300/25 bg-emerald-400/30 text-emerald-50',
                                            $volatilityRatio <= .60 => 'border-emerald-300/25 bg-emerald-400/23 text-emerald-100',
                                            $volatilityRatio <= .80 => 'border-emerald-300/25 bg-green-400/17 text-green-100',
                                            default => 'border-emerald-300/25 bg-lime-400/12 text-lime-100',
                                        }
                                        : match (true) {
                                            $volatilityExcessRatio <= .25 => 'border-amber-300/20 bg-yellow-300/18 text-yellow-50',
                                            $volatilityExcessRatio <= .50 => 'border-amber-300/20 bg-amber-400/23 text-amber-50',
                                            $volatilityExcessRatio <= .75 => 'border-amber-300/20 bg-orange-400/27 text-orange-50',
                                            default => 'border-rose-400/20 bg-rose-500/30 text-rose-50',
                                        };
                                    $hitRateCellPalette = [
                                        'bg-rose-500/30 text-rose-50', 'bg-rose-400/28 text-rose-50',
                                        'bg-orange-400/25 text-orange-50', 'bg-amber-400/23 text-amber-50',
                                        'bg-yellow-300/20 text-yellow-50', 'bg-lime-300/18 text-lime-50',
                                        'bg-lime-400/20 text-lime-50', 'bg-green-400/22 text-green-50',
                                        'bg-emerald-400/25 text-emerald-50', 'bg-emerald-500/30 text-emerald-50',
                                    ];
                                    $profitFactorCellPalette = [
                                        'bg-rose-500/30 text-rose-50', 'bg-rose-500/30 text-rose-50',
                                        'bg-rose-400/27 text-rose-50', 'bg-rose-400/27 text-rose-50',
                                        'bg-yellow-300/20 text-yellow-50', 'bg-yellow-300/20 text-yellow-50',
                                        'bg-lime-300/18 text-lime-50', 'bg-lime-400/20 text-lime-50',
                                        'bg-green-400/24 text-green-50', 'bg-emerald-500/30 text-emerald-50',
                                    ];
                                    $drawdownCellPalette = [
                                        'bg-emerald-400/30 text-emerald-50', 'bg-lime-400/26 text-lime-50',
                                        'bg-yellow-300/20 text-yellow-50', 'bg-yellow-300/20 text-yellow-50',
                                        'bg-amber-300/22 text-amber-50', 'bg-amber-300/22 text-amber-50',
                                        'bg-orange-400/25 text-orange-50', 'bg-orange-400/25 text-orange-50',
                                        'bg-rose-400/27 text-rose-50', 'bg-rose-500/30 text-rose-50',
                                    ];
                                    $volatilityCellPalette = [
                                        'bg-cyan-300/[.08] text-cyan-100', 'bg-cyan-300/[.10] text-cyan-100',
                                        'bg-cyan-300/[.12] text-cyan-100', 'bg-cyan-300/[.14] text-cyan-100',
                                        'bg-cyan-300/[.16] text-cyan-50', 'bg-cyan-300/[.18] text-cyan-50',
                                        'bg-cyan-300/[.20] text-cyan-50', 'bg-cyan-300/[.23] text-cyan-50',
                                        'bg-cyan-300/[.26] text-cyan-50', 'bg-cyan-300/[.30] text-cyan-50',
                                    ];
                                    $heatmapPaletteIndex = match ($metric['key']) {
                                        'hit_rate' => (int) max(0, min(9, floor((float) $rawValue / 10))),
                                        'profit_factor' => (int) max(0, min(9, floor((float) $rawValue / .25))),
                                        'drawdown' => (int) max(0, min(9, floor((float) $rawValue / 5))),
                                        'volatility' => (int) max(0, min(9, floor((float) $rawValue / 5))),
                                    };
                                    $metricCellClass = match ($metric['key']) {
                                        'hit_rate' => $hitRateCellPalette[$heatmapPaletteIndex],
                                        'profit_factor' => $profitFactorCellPalette[$heatmapPaletteIndex],
                                        'drawdown' => $drawdownCellPalette[$heatmapPaletteIndex],
                                        'volatility' => $volatilityCellPalette[$heatmapPaletteIndex],
                                    };
                                    $metricCellHex = $bar['palette'][$heatmapPaletteIndex];
                                    $outsideSelectedArea = $scoreBucket < $heatmapScoreFilter
                                        || ($confidenceBucket * 10) < $heatmapConfidenceFilter;
                                    $cellClass = ! $hasValue
                                        ? 'border-white/[.05] bg-slate-500/[.07] text-slate-500'
                                        : 'border-teal-400/10 '.$metricCellClass;
                                    $displayValue = ! $hasValue
                                        ? ($samples ?: '—')
                                        : ($metric['key'] === 'profit_factor'
                                            ? number_format($rawValue, 2, ',', '.')
                                            : number_format($rawValue, 0, ',', '.').$metric['suffix']);
                                @endphp
                                <div class="flex aspect-square min-h-0 min-w-0 cursor-default items-center justify-center self-center rounded-[4px] border {{ $outsideSelectedArea ? 'border-white/[.05] bg-slate-500/[.07] text-slate-500' : $cellClass.($hasValue && $metric['key'] !== 'drawdown' ? ' !border-teal-400/10' : '') }}"
                                     @if (! $outsideSelectedArea && $hasValue) style="background-color: color-mix(in srgb, {{ $metricCellHex }} 24%, transparent); color: color-mix(in srgb, {{ $metricCellHex }} 42%, white); border-color: rgba(45, 212, 191, .10);" @endif
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
        <script>
            (() => {
                const filterForm = document.getElementById('prediction-heatmap-filters');
                const scoreInput = filterForm?.querySelector('input[name="score_min"]');
                const confidenceInput = filterForm?.querySelector('input[name="confidence_min"]');

                const updateHeatmapFilterLines = () => {
                    const score = Math.max(0, Math.min(10, Number(scoreInput?.value ?? 0)));
                    const confidence = Math.max(0, Math.min(100, Number(confidenceInput?.value ?? 0)));
                    const scorePosition = Math.max(1, Math.min(99, score * 10));
                    const confidencePosition = Math.max(1, Math.min(99, confidence));

                    document.querySelectorAll('[data-heatmap-score-line]').forEach(line => {
                        line.style.left = `${scorePosition}%`;
                    });
                    document.querySelectorAll('[data-heatmap-confidence-line]').forEach(line => {
                        line.style.bottom = `${confidencePosition}%`;
                    });
                    document.querySelectorAll('[data-heatmap-score-label]').forEach(label => {
                        label.textContent = score.toLocaleString('de-DE', { minimumFractionDigits: 1, maximumFractionDigits: 1 });
                    });
                    document.querySelectorAll('[data-heatmap-confidence-label]').forEach(label => {
                        label.textContent = Math.round(confidence).toLocaleString('de-DE');
                    });
                };

                scoreInput?.addEventListener('input', updateHeatmapFilterLines);
                confidenceInput?.addEventListener('input', updateHeatmapFilterLines);
                updateHeatmapFilterLines();
            })();
        </script>

        <p class="mt-2 shrink-0 text-center text-[10px] text-[var(--ak-muted)]">{{ __('Graue Felder enthalten zu wenige validierte Prognosen für eine belastbare Bewertung.') }}</p>
    </div>

    @if (($setupMode ?? false) && ($backtestIsComplete ?? false))
        @php $runSummary = json_decode((string) ($activeBacktestRun->summary ?? '{}'), true) ?: []; @endphp
        <div x-data="{ open: true }" x-show="open" x-cloak class="fixed inset-0 z-[100] flex items-center justify-center bg-slate-950/75 p-4 backdrop-blur-sm" @open-backtest-result.window="open = true" @keydown.escape.window="open = false">
            <section class="w-full max-w-5xl rounded-2xl border border-teal-300/20 bg-[#15243a]/90 p-5 shadow-2xl" @click.outside="open = false">
                <header class="mb-4 flex items-start justify-between gap-4">
                    <div>
                        <p class="text-[10px] font-black uppercase tracking-[.14em] text-amber-300">{{ __('Persönlicher 3-Jahres-Backtest') }}</p>
                        <h2 class="mt-1 text-xl font-black text-white">{{ __('Backtest-Ergebnis') }}</h2>
                        <p class="mt-1 text-xs text-slate-300">{{ __('Strategie und S&P 500 starten mit demselben gewählten Kapital.') }}</p>
                    </div>
                    <div class="flex items-center gap-2">
                        @if (! $qualitySetupMode)
                        <a href="{{ route('setup.filter.backtest.report', $activeBacktestRun->public_id) }}" class="inline-flex h-9 items-center gap-2 rounded-lg border border-teal-300/20 bg-teal-400/10 px-3 text-[10px] font-black uppercase tracking-wide text-teal-200 hover:bg-teal-400/15">
                            <x-heroicon-o-arrow-down-tray class="h-4 w-4" />{{ __('PDF-Bericht') }}
                        </a>
                        @endif
                        <button type="button" @click="open = false" class="flex h-9 w-9 items-center justify-center rounded-lg border border-white/10 text-slate-300 hover:text-white" aria-label="{{ __('Schließen') }}">
                            <x-heroicon-o-x-mark class="h-5 w-5" />
                        </button>
                    </div>
                </header>

                @if (! $qualitySetupMode)
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
                @else
                <div class="mb-4 grid grid-cols-5 gap-2">
                    @foreach ([
                        [__('Prognose erreicht'), 'filtered-backtest-prediction-reached', 'text-amber-200'],
                        [__('Gewinner'), 'filtered-backtest-winner-trades', 'text-emerald-300'],
                        [__('Verlierer'), 'filtered-backtest-loser-trades', 'text-rose-300'],
                        [__('Ø Gewinnfaktor'), 'filtered-backtest-average-gain-factor', 'text-teal-200'],
                        [__('Min. Drawdown'), 'filtered-backtest-minimum-drawdown', 'text-emerald-200'],
                        [__('Max. Drawdown'), 'filtered-backtest-quality-maximum-drawdown', 'text-rose-200'],
                        [__('Ø Rendite'), 'filtered-backtest-average-return', 'text-cyan-200'],
                        [__('Gesamtkapital'), 'filtered-backtest-quality-total-capital', 'text-white'],
                        [__('Gewinn/Verlust-Ratio'), 'filtered-backtest-win-loss-ratio', 'text-violet-200'],
                        [__('Gesamt-Investmentdauer'), 'filtered-backtest-investment-days', 'text-amber-200'],
                    ] as [$label, $metricId, $color])
                        <div class="rounded-xl border border-white/[.08] bg-white/[.035] px-3 py-2">
                            <span class="block text-[9px] font-black uppercase tracking-wide text-slate-400">{{ $label }}</span>
                            <strong id="{{ $metricId }}" class="mt-1 block text-lg font-black tabular-nums {{ $color }}">…</strong>
                        </div>
                    @endforeach
                </div>
                @endif
                @if (! $qualitySetupMode)
                <div id="filtered-backtest-result-chart" class="h-[360px] w-full"></div>
                <div class="mt-3 flex items-center justify-between gap-4 text-[10px] text-slate-400">
                    <span>{{ __('Portfolio-Verlauf auf Basis gleich gewichteter, am jeweiligen Ausstiegstag zusammengefasster Trades.') }}</span>
                    <span id="filtered-backtest-benchmark-performance" class="shrink-0 font-bold text-slate-300"></span>
                </div>
                @endif
            </section>
        </div>
        <script>
            document.addEventListener('DOMContentLoaded', async () => {
                const target = document.querySelector('#filtered-backtest-result-chart');
                @if (! $qualitySetupMode)
                if (!target || !window.ApexCharts) return;
                @endif
                const response = await fetch(@json(route('setup.filter.backtest.result', $activeBacktestRun->public_id)), {
                    cache: 'no-store',
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
                const predictionReached = document.querySelector('#filtered-backtest-prediction-reached');
                const winnerTrades = document.querySelector('#filtered-backtest-winner-trades');
                const loserTrades = document.querySelector('#filtered-backtest-loser-trades');
                const averageGainFactor = document.querySelector('#filtered-backtest-average-gain-factor');
                const minimumDrawdown = document.querySelector('#filtered-backtest-minimum-drawdown');
                const qualityMaximumDrawdown = document.querySelector('#filtered-backtest-quality-maximum-drawdown');
                const averageReturn = document.querySelector('#filtered-backtest-average-return');
                const qualityTotalCapital = document.querySelector('#filtered-backtest-quality-total-capital');
                const winLossRatio = document.querySelector('#filtered-backtest-win-loss-ratio');
                const investmentDays = document.querySelector('#filtered-backtest-investment-days');
                const benchmarkPerformance = document.querySelector('#filtered-backtest-benchmark-performance');
                const updateExitMetric = (id, strategyPerformance, tradesPerMonth, strategyDrawdown) => {
                    const element = document.querySelector(`#${id}`);
                    if (!element) return;
                    element.classList.remove('text-slate-500');
                    element.classList.add('text-slate-300');
                    element.textContent = `${Number(strategyPerformance).toLocaleString('de-DE', { maximumFractionDigits: 2 })} % · ${Number(tradesPerMonth).toLocaleString('de-DE', { maximumFractionDigits: 2 })} Trades/Monat · DD ${Number(strategyDrawdown).toLocaleString('de-DE', { maximumFractionDigits: 2 })} %`;
                };
                if (initialCapital) initialCapital.textContent = Number(result.initial_capital).toLocaleString('de-DE', { style: 'currency', currency: 'EUR' });
                if (finalCapital) finalCapital.textContent = Number(result.final_capital).toLocaleString('de-DE', { style: 'currency', currency: 'EUR' });
                if (executedTrades) executedTrades.textContent = Number(result.executed_trades).toLocaleString('de-DE');
                if (skippedTrades) skippedTrades.textContent = Number(result.skipped_trades).toLocaleString('de-DE');
                if (totalCosts) totalCosts.textContent = Number(result.total_costs).toLocaleString('de-DE', { style: 'currency', currency: 'EUR' });
                if (hitRate) hitRate.textContent = `${Number(result.hit_rate).toLocaleString('de-DE', { maximumFractionDigits: 1 })} %`;
                if (profitFactor) profitFactor.textContent = result.profit_factor === null ? '∞' : Number(result.profit_factor).toLocaleString('de-DE', { maximumFractionDigits: 2 });
                if (drawdown) drawdown.textContent = `${Number(result.max_drawdown).toLocaleString('de-DE', { maximumFractionDigits: 1 })} %`;
                if (predictionReached) predictionReached.textContent = Number(result.prediction_reached_trades || 0).toLocaleString('de-DE');
                if (winnerTrades) winnerTrades.textContent = Number(result.winner_trades || 0).toLocaleString('de-DE');
                if (loserTrades) loserTrades.textContent = Number(result.loser_trades || 0).toLocaleString('de-DE');
                if (averageGainFactor) averageGainFactor.textContent = result.average_gain_factor === null ? '∞' : Number(result.average_gain_factor).toLocaleString('de-DE', { maximumFractionDigits: 2 });
                if (minimumDrawdown) minimumDrawdown.textContent = `${Number(result.minimum_trade_drawdown || 0).toLocaleString('de-DE', { maximumFractionDigits: 2 })} %`;
                if (qualityMaximumDrawdown) qualityMaximumDrawdown.textContent = `${Number(result.max_drawdown || 0).toLocaleString('de-DE', { maximumFractionDigits: 2 })} %`;
                if (averageReturn) averageReturn.textContent = `${Number(result.average_trade_return || 0).toLocaleString('de-DE', { maximumFractionDigits: 2 })} %`;
                if (qualityTotalCapital) qualityTotalCapital.textContent = Number(result.final_capital || 0).toLocaleString('de-DE', { style: 'currency', currency: 'EUR' });
                if (winLossRatio) winLossRatio.textContent = result.win_loss_ratio === null ? '∞' : Number(result.win_loss_ratio).toLocaleString('de-DE', { maximumFractionDigits: 2 });
                if (investmentDays) investmentDays.textContent = `${Number(result.total_investment_days || 0).toLocaleString('de-DE')} {{ __('Tage') }}`;
                @if (! $qualitySetupMode)
                updateExitMetric('save-exit-fixed', result.strategy_performance, result.trades_per_month, result.portfolio_max_drawdown);
                updateExitMetric('save-exit-winner', result.winner_runner_performance, result.winner_runner_trades_per_month, result.winner_runner_max_drawdown);
                updateExitMetric('save-exit-target', result.prediction_target_performance, result.prediction_target_trades_per_month, result.prediction_target_max_drawdown);
                if (benchmarkPerformance && result.benchmark_performance !== null) {
                    const benchmarkProfit = Number(result.benchmark_profit || 0);
                    benchmarkPerformance.textContent = `S&P 500: ${Number(result.benchmark_final_capital).toLocaleString('de-DE', { style: 'currency', currency: 'EUR' })} · ${benchmarkProfit >= 0 ? '+' : ''}${benchmarkProfit.toLocaleString('de-DE', { style: 'currency', currency: 'EUR' })} · ${result.benchmark_performance >= 0 ? '+' : ''}${Number(result.benchmark_performance).toLocaleString('de-DE', { maximumFractionDigits: 2 })} %`;
                }
                if (target._akChart) await target._akChart.destroy();
                const chart = new window.ApexCharts(target, {
                    chart: { type: 'line', height: 360, background: 'transparent', toolbar: { show: false }, zoom: { enabled: false }, animations: { enabled: false } },
                    @if ($qualitySetupMode)
                    series: [
                        { name: '{{ __('Smart Selection') }}', data: result.strategy_chart },
                        { name: `S&P 500 (${Number(result.benchmark_performance) >= 0 ? '+' : ''}${Number(result.benchmark_performance).toLocaleString('de-DE', { maximumFractionDigits: 2 })} %)`, data: result.benchmark_chart },
                    ],
                    colors: ['#2dd4bf', '#fbbf24'],
                    stroke: { width: [3, 2], curve: 'straight', dashArray: [0, 5] },
                    @else
                    series: [
                        { name: '20 Tage', data: result.strategy_chart },
                        { name: 'Winner Runner', data: result.winner_runner_chart },
                        { name: 'Prognoseziel', data: result.prediction_target_chart },
                        { name: 'Adaptive Rotation', data: result.adaptive_rotation_chart },
                        { name: 'Buy and Hold', data: result.buy_and_hold_chart },
                        { name: `S&P 500 Buy & Hold (${Number(result.benchmark_performance) >= 0 ? '+' : ''}${Number(result.benchmark_performance).toLocaleString('de-DE', { maximumFractionDigits: 2 })} %)`, data: result.benchmark_chart },
                    ],
                    colors: ['#2dd4bf', '#818cf8', '#fb7185', '#4ade80', '#38bdf8', '#fbbf24'],
                    stroke: { width: [3, 3, 3, 3, 2.5, 2], curve: 'straight', dashArray: [0, 0, 0, 0, 0, 5] },
                    @endif
                    xaxis: { type: 'datetime', min: result.period_start, max: result.period_end, labels: { style: { colors: '#94a3b8', fontSize: '10px' } }, axisBorder: { color: 'rgba(148,163,184,.16)' }, axisTicks: { color: 'rgba(148,163,184,.16)' } },
                    yaxis: { labels: { formatter: value => `${value >= 0 ? '+' : ''}${value.toLocaleString('de-DE', { maximumFractionDigits: 0 })} %`, style: { colors: '#94a3b8', fontSize: '10px' } } },
                    grid: { borderColor: 'rgba(148,163,184,.10)', strokeDashArray: 4 },
                    annotations: @if ($qualitySetupMode) {} @else result.buy_and_hold_entry_at ? { xaxis: [{
                        x: Number(result.buy_and_hold_entry_at),
                        borderColor: 'rgba(56,189,248,.75)',
                        strokeDashArray: 4,
                        label: { text: 'Buy & Hold Kauf', orientation: 'horizontal', offsetY: -4, style: { background: '#0ea5e9', color: '#f8fafc', fontSize: '9px', fontWeight: 700 } },
                    }] } : {} @endif,
                    legend: { labels: { colors: '#cbd5e1' }, markers: { size: 5 } },
                    tooltip: { theme: 'dark', x: { format: 'dd.MM.yyyy' }, y: { formatter: value => `${value >= 0 ? '+' : ''}${value.toLocaleString('de-DE', { minimumFractionDigits: 2, maximumFractionDigits: 2 })} %` } },
                    dataLabels: { enabled: false },
                    noData: { text: '{{ __('Keine Vergleichsdaten verfügbar') }}', style: { color: '#94a3b8' } },
                });
                target._akChart = chart;
                await chart.render();
                @endif
            });
        </script>
    @endif

    <style>
        /* Keep the current tester frame visible until the server-rendered
           filter result is ready. This removes the blank repaint between
           consecutive GET filter requests without animating the content. */
        @view-transition {
            navigation: auto;
        }

        ::view-transition-old(root),
        ::view-transition-new(root),
        ::view-transition-group(root) {
            animation: none !important;
            mix-blend-mode: normal;
        }

        ::view-transition-old(root) {
            opacity: 1;
        }

        ::view-transition-new(root) {
            opacity: 1;
        }

        #prediction-heatmap-filters .ak-position-factor input:checked + span {
            background: rgba(45, 212, 191, .22) !important;
            color: #5eead4 !important;
            -webkit-text-fill-color: #5eead4 !important;
            box-shadow: inset 0 0 0 1px rgba(45, 212, 191, .32), 0 1px 3px rgba(15, 118, 110, .18);
        }

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
            color: #06b6d4;
            font-weight: 900;
        }

        #fundamental-heatmap-filters .ak-fundamental-range input {
            width: 100%;
            height: 12px;
            margin: 0;
            cursor: pointer;
            accent-color: #06b6d4;
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
            color: #06b6d4;
            font-weight: 900;
        }

        #prediction-heatmap-filters .ak-heatmap-range input {
            width: 100%;
            height: 12px;
            margin: 0;
            cursor: pointer;
            accent-color: #06b6d4;
        }

        #prediction-heatmap-filters .ak-heatmap-range:has(input[name="volatility_max"]) b {
            color: #06b6d4;
        }

        #prediction-heatmap-filters .ak-heatmap-range input[name="volatility_max"] {
            accent-color: #06b6d4;
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
