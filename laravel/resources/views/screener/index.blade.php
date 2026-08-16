<x-app-layout>
    <div class="screener-page mx-auto max-w-[96rem] px-3 py-5 text-[var(--ak-text)] sm:px-5 lg:py-8">
        <header class="mb-3 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <h1 class="text-3xl font-black tracking-tight">{{ __('Aktienscreener') }}</h1>
            <div class="flex flex-wrap gap-2"><a href="{{ route('screener.history') }}" class="inline-flex h-10 items-center justify-center rounded-xl border border-amber-400/35 bg-amber-400/[.08] px-4 text-xs font-black text-amber-300">{{ __('Ranking-Historie') }}</a><a href="{{ route('predictions.index') }}" class="inline-flex h-10 items-center justify-center rounded-xl border border-cyan-400/35 bg-cyan-400/[.08] px-4 text-xs font-black text-cyan-300 transition hover:bg-cyan-400/[.16]">{{ __('Prognosetabelle öffnen') }}</a></div>
        </header>

        @if($isFreeRegional)
            <div class="mb-4 flex flex-wrap items-center justify-between gap-3 rounded-xl border border-amber-400/25 bg-amber-400/[.06] px-4 py-3"><div><p class="text-[9px] font-black uppercase tracking-[.15em] text-amber-400">{{ __('Free · Regionales Top-100-Universum') }}</p><p class="mt-1 text-xs text-[var(--ak-muted)]">{{ __('Der Aktienscreener zeigt ausschließlich die 100 wichtigsten Aktien deiner Region (:country).', ['country' => $regionalCountry]) }}</p></div><a href="{{ route('pricing') }}" class="text-[9px] font-black text-amber-300">{{ __('Alle Aktien ab Plus') }} →</a></div>
        @endif

        @if (session('status'))
            <div class="mb-3 rounded-lg border border-emerald-400/25 bg-emerald-400/10 px-3 py-2 text-xs font-bold text-emerald-300">
                {{ match(session('status')) {
                    'watchlist-item-added' => __('Aktie wurde zur Watchlist hinzugefügt.'),
                    'watchlist-item-removed' => __('Aktie wurde aus der Watchlist entfernt.'),
                    'paper-depot-item-added' => __('Aktie wurde ins Musterdepot gelegt.'),
                    default => session('status'),
                } }}
            </div>
        @endif
        @if ($errors->any())
            <div class="mb-3 rounded-lg border border-rose-400/25 bg-rose-400/10 px-3 py-2 text-xs font-bold text-rose-300">{{ $errors->first() }}</div>
        @endif

        <form method="GET" action="{{ route('screener.index') }}" class="screener-filter-bar mb-5 flex flex-nowrap gap-2 overflow-x-auto rounded-lg border border-cyan-400/30 bg-[var(--ak-card)] p-3 shadow-[var(--ak-shadow)]">
            <label class="relative min-w-[180px] flex-[1.5]">
                <span class="sr-only">{{ __('Aktie suchen') }}</span>
                <input name="q" value="{{ request('q') }}" oninput="clearTimeout(this._filterTimer); this._filterTimer = setTimeout(() => this.form.requestSubmit(), 500)" placeholder="{{ __('Aktie oder Symbol') }}" class="ak-input h-10 w-full text-sm" />
            </label>
            <select name="country" onchange="this.form.requestSubmit()" class="ak-input h-10 min-w-[125px] flex-1 text-sm"><option value="">{{ __('Alle Länder') }}</option>@foreach($countries as $country)<option value="{{ $country }}" @selected(request('country') === $country)>{{ $country }}</option>@endforeach</select>
            <select name="sector" onchange="this.form.requestSubmit()" class="ak-input h-10 min-w-[125px] flex-1 text-sm"><option value="">{{ __('Alle Sektoren') }}</option>@foreach($sectors as $sector)<option value="{{ $sector }}" @selected(request('sector') === $sector)>{{ $sector }}</option>@endforeach</select>
            <select name="index" onchange="this.form.requestSubmit()" class="ak-input h-10 min-w-[125px] flex-1 text-sm"><option value="">{{ __('Alle Indizes') }}</option>@foreach($indices as $index)<option value="{{ $index->symbol }}" @selected(request('index') === $index->symbol)>{{ $index->name ?: $index->symbol }}</option>@endforeach</select>
            <select name="signal" onchange="this.form.requestSubmit()" class="ak-input h-10 min-w-[125px] flex-1 text-sm"><option value="">{{ __('BUY, WAIT und WATCH') }}</option>@foreach(['BUY','WAIT','WATCH'] as $signal)<option value="{{ $signal }}" @selected(request('signal') === $signal)>{{ $signal }}</option>@endforeach</select>
            <select name="transition_days" onchange="this.form.requestSubmit()" class="ak-input h-10 min-w-[150px] flex-1 text-sm">
                <option value="">{{ __('Alle Signalübergänge') }}</option>
                @foreach([1, 5, 10, 20] as $days)
                    <option value="{{ $days }}" @selected((int) request('transition_days') === $days)>{{ trans_choice('Letzter :days Tag|Letzte :days Tage', $days, ['days' => $days]) }}</option>
                @endforeach
            </select>
            <select name="limit" onchange="this.form.requestSubmit()" class="ak-input h-10 min-w-[105px] flex-1 text-sm">
                @foreach(['10' => 'Top 10', '25' => 'Top 25', '50' => 'Top 50', '100' => 'Top 100', 'all' => __('Alle')] as $value => $label)
                    <option value="{{ $value }}" @selected((string) request('limit', '10') === (string) $value)>{{ $label }}</option>
                @endforeach
            </select>
            <a href="{{ route('screener.index') }}" class="screener-filter-reset inline-flex h-10 shrink-0 items-center justify-center border border-amber-400/40 bg-amber-400/[.10] px-4 text-xs font-black text-amber-300 transition hover:bg-amber-400/[.18]">{{ __('Reset') }}</a>
        </form>

        <div class="screener-results-scroll">
        <section class="grid grid-cols-1 gap-4">
            @forelse($stocks as $stock)
                @php
                    // Top-10 ranking and its explanation are based on the model
                    // signal. Keep the visible badge consistent with that ranking
                    // instead of applying the user's stricter short-term overlay.
                    $signal = strtoupper((string) ($stock->personalized_signal ?: $stock->model_signal ?: 'HOLD'));
                    $tone = match ($signal) {
                        'BUY' => 'border-emerald-300/80 bg-emerald-500/[.35] text-white shadow-[0_0_18px_rgba(16,185,129,.42)]',
                        'SELL' => 'border-rose-400/45 bg-rose-400/[.10] text-rose-300',
                        'WATCH' => 'border-lime-400/40 bg-lime-400/[.08] text-lime-300',
                        'WAIT' => 'border-emerald-300/80 bg-emerald-500/[.35] text-white shadow-[0_0_18px_rgba(16,185,129,.42)]',
                        default => 'border-amber-400/40 bg-amber-400/[.08] text-amber-300',
                    };
                    $signalLabel = $signal;
                    $return = is_numeric($stock->expected_return_20d) ? (float) $stock->expected_return_20d : null;
                    $rankingScorePercent = is_numeric($stock->ranking_score)
                        ? max(0, min(100, (float) $stock->ranking_score))
                        : 0;
                    $qualityDonutColor = static function (float $percent): string {
                        $percent = max(0, min(100, $percent));
                        $hue = $percent <= 50
                            ? ($percent / 50) * 48
                            : 48 + (($percent - 50) / 50) * 94;
                        return sprintf('hsl(%.1f 78%% 52%%)', $hue);
                    };
                    $rankingScoreColor = $qualityDonutColor($rankingScorePercent);
                    $rankingConfidencePercent = is_numeric($stock->confidence_percent)
                        ? max(0, min(100, (float) $stock->confidence_percent))
                        : 0;
                    $rankingRiskPercent = \App\Support\RiskScore::toPercent($stock->risk_percent, $stock->ranking_drawdown ?? null);
                    $riskDonutColor = $rankingRiskPercent !== null ? $qualityDonutColor(100 - $rankingRiskPercent) : '#64748b';
                    $riskDonutTone = $rankingRiskPercent === null ? 'unknown' : ($rankingRiskPercent >= 50 ? 'high' : ($rankingRiskPercent >= 30 ? 'medium' : 'low'));
                    $rankingHitRateAvailable = is_numeric($stock->ranking_hit_rate);
                    $rankingHitRatePercent = $rankingHitRateAvailable
                        ? max(0, min(100, (float) $stock->ranking_hit_rate))
                        : 0;
                    $rankingProfitPerTradeAvailable = is_numeric($stock->display_profit_per_trade_percent);
                    $rankingProfitPerTrade = $rankingProfitPerTradeAvailable ? (float) $stock->display_profit_per_trade_percent : 0;
                    $rankingProfitPerTradePercent = $rankingProfitPerTradeAvailable
                        ? max(0, min(100, 50 + ($rankingProfitPerTrade * 25)))
                        : 0;
                    $rankingStabilityAvailable = (bool) $stock->ranking_stability_available;
                    $rankingStabilityPercent = $rankingStabilityAvailable
                        ? max(0, min(100, (float) $stock->ranking_stability_percent))
                        : 0;
                    $rankingConfidenceColor = $qualityDonutColor($rankingConfidencePercent);
                    $rankingHitRateColor = $rankingHitRateAvailable ? $qualityDonutColor($rankingHitRatePercent) : '#64748b';
                    $rankingProfitPerTradeColor = $rankingProfitPerTradeAvailable ? $qualityDonutColor($rankingProfitPerTradePercent) : '#64748b';
                    $rankingStabilityColor = $rankingStabilityAvailable ? $qualityDonutColor($rankingStabilityPercent) : '#64748b';
                    $dividendYield = is_numeric($stock->dividend_yield)
                        ? (float) $stock->dividend_yield * (abs((float) $stock->dividend_yield) <= 1 ? 100 : 1)
                        : null;
                    $priceEarningsRatio = is_numeric($stock->trailing_pe)
                        ? (float) $stock->trailing_pe
                        : (is_numeric($stock->forward_pe) ? (float) $stock->forward_pe : null);
                    $stockWatchlistIds = collect($watchlistMemberships->get($stock->instrument_id, []));
                    $stockPaperPortfolioIds = collect($paperPortfolioMemberships->get($stock->instrument_id, []));
                    $isOnWatchlist = $stockWatchlistIds->isNotEmpty();
                    $isInPaperPortfolio = $stockPaperPortfolioIds->isNotEmpty();
                    $matchingPaperPortfolios = $paperPortfolios->where('currency', $stock->currency);
                    $returnClass = $return !== null && $return >= 0 ? 'text-emerald-300' : 'text-rose-300';
                    $chartPoints = collect($stock->chart_points ?? []);
                    $predictionPrice = is_numeric($stock->predicted_price_20d) ? (float) $stock->predicted_price_20d : null;
                    $currencySymbol = static fn (?string $currency): string => match (strtoupper(trim((string) $currency))) {
                        'EUR' => '€',
                        'USD' => '$',
                        'GBP' => '£',
                        'JPY', 'CNY' => '¥',
                        'HKD' => 'HK$',
                        'CHF' => 'CHF',
                        'CAD' => 'C$',
                        'AUD' => 'A$',
                        'SEK' => 'kr',
                        default => strtoupper(trim((string) $currency)) ?: '—',
                    };
                    $displayCurrencySymbol = $currencySymbol($stock->currency);
                    $originalCurrencySymbol = $currencySymbol($stock->original_currency ?? null);
                    $currencyName = static fn (?string $currency): string => match (strtoupper(trim((string) $currency))) {
                        'EUR' => __('Euro'),
                        'USD' => __('US-Dollar'),
                        'GBP' => __('Britisches Pfund'),
                        'JPY' => __('Japanischer Yen'),
                        'CNY' => __('Chinesischer Renminbi'),
                        'HKD' => __('Hongkong-Dollar'),
                        'CHF' => __('Schweizer Franken'),
                        'CAD' => __('Kanadischer Dollar'),
                        'AUD' => __('Australischer Dollar'),
                        'SEK' => __('Schwedische Krone'),
                        default => strtoupper(trim((string) $currency)) ?: __('Unbekannt'),
                    };
                    $originalCurrencyName = $currencyName($stock->original_currency ?? null);
                    $showOriginalPrice = is_numeric($stock->original_price ?? null)
                        && filled($stock->original_currency ?? null)
                        && strtoupper((string) $stock->original_currency) !== strtoupper((string) $stock->currency);
                    $chartMin = $chartPoints->isNotEmpty() ? (float) $chartPoints->min() : 0;
                    $chartMax = $chartPoints->isNotEmpty() ? (float) $chartPoints->max() : 1;
                    if ($predictionPrice !== null) {
                        $chartMin = min($chartMin, $predictionPrice);
                        $chartMax = max($chartMax, $predictionPrice);
                    }
                    $chartRange = max($chartMax - $chartMin, 0.000001);
                    $predictionY = $predictionPrice !== null ? 104 - (($predictionPrice - $chartMin) / $chartRange) * 90 : null;
                    $signalTransitionX = is_numeric($stock->signal_transition_x) ? (float) $stock->signal_transition_x : null;
                    $signalTransitionDate = $stock->signal_transition_at
                        ? \Illuminate\Support\Carbon::parse($stock->signal_transition_at)->format('d.m.Y')
                        : null;
                    $chartPolyline = $chartPoints->count() > 1
                        ? $chartPoints->values()->map(fn (float $value, int $index): string => sprintf('%.1f,%.1f', $index * 600 / ($chartPoints->count() - 1), 104 - (($value - $chartMin) / $chartRange) * 90))->implode(' ')
                        : '';
                @endphp
                @php
                    $countryFlag = match (strtoupper((string) $stock->country)) {
                        'DE' => '🇩🇪', 'US' => '🇺🇸', 'GB' => '🇬🇧', 'CA' => '🇨🇦', 'JP' => '🇯🇵', 'CN' => '🇨🇳', 'BR' => '🇧🇷', 'CH' => '🇨🇭', 'AU' => '🇦🇺', default => '🌐',
                    };
                    $businessSummary = app()->getLocale() === 'en'
                        ? ($stock->business_description_en ?: $stock->business_summary_en ?: $stock->business_description ?: $stock->business_summary)
                        : ($stock->business_description ?: $stock->business_summary);
                    $ranking = (int) ($stock->screening_rank ?? 0);
                    $rankClass = match ($ranking) {
                        1 => 'border-amber-300/75 shadow-[0_0_26px_rgba(251,191,36,.20)]',
                        2 => 'border-slate-200/65 shadow-[0_0_22px_rgba(226,232,240,.14)]',
                        3 => 'border-orange-700/70 shadow-[0_0_22px_rgba(194,65,12,.18)]',
                        default => 'border-orange-400/35',
                    };
                    $hasLongCompanyName = mb_strlen((string) ($stock->name ?: $stock->symbol)) > 45;
                @endphp
                <article
                    data-ranking="{{ $ranking }}"
                    class="screener-stock-card {{ $hasLongCompanyName ? 'screener-stock-card-long-name' : '' }} ak-card ak-dashboard-card relative overflow-hidden p-3 {{ $rankClass }}"
                    x-data="{ signalInfoOpen: false }"
                >
                    <div class="grid h-full min-h-0 gap-2 md:grid-cols-2 xl:grid-cols-6">
                        <div class="screener-chart-panel relative h-full min-h-0 rounded-xl border border-transparent p-3 pt-5 xl:col-span-2">
                            <div class="grid gap-3 md:grid-cols-[.85fr_1fr]">
                                <div>
                                    <p class="screener-border-title text-amber-300">{{ __('Globales Ranking') }} @if($stock->screening_rank)<strong>#{{ $stock->screening_rank }}</strong>@endif</p>
                                    <div class="mt-1 flex items-center gap-2">
                                        <p class="text-base font-black">{{ $stock->name ?: $stock->symbol }}</p>
                                    </div>
                                    <p class="text-xs font-black uppercase tracking-[.12em] text-cyan-300">{{ $stock->symbol }}</p>
                                    <p class="mt-2 text-sm">{{ $countryFlag }} {{ $stock->country ?: '—' }}</p>
                                    <p class="mt-1 text-[10px] font-bold text-[var(--ak-muted)]">{{ $stock->exchange_name ?: $stock->exchange_code ?: __('Index nicht hinterlegt') }}</p>
                                    <p class="mt-1 flex items-center gap-1.5 text-[10px] font-bold text-cyan-300">
                                        <x-heroicon-o-squares-2x2 class="h-3.5 w-3.5 shrink-0" />
                                        <span>{{ $stock->sector ?: __('Sektor nicht hinterlegt') }}</span>
                                    </p>
                                    <span class="relative z-20 mt-3 inline-flex items-center gap-1.5">
                                        <span class="inline-flex w-28 justify-center rounded-lg border px-2.5 py-1 text-[10px] font-black tracking-[.08em] {{ $tone }}">{{ $signalLabel }}</span>
                                        <button type="button" @click.prevent.stop="signalInfoOpen = true" class="inline-grid h-7 w-7 place-items-center rounded-full border border-cyan-300/35 bg-cyan-400/[.08] text-cyan-300 transition hover:bg-cyan-400/[.16]" aria-label="{{ __('Signalbegründung anzeigen') }}">
                                            <x-heroicon-o-information-circle class="h-4 w-4" />
                                        </button>
                                    </span>
                                    <p class="mt-1 text-[8px] font-black uppercase tracking-[.1em] text-[var(--ak-muted)]">{{ __('Persönliches Profil') }}: {{ $stock->personal_risk_profile }}</p>
                                    <template x-teleport="body">
                                        <div x-cloak x-show="signalInfoOpen" x-transition.opacity @keydown.escape.window="signalInfoOpen = false" class="fixed inset-0 z-[100] grid place-items-center bg-slate-950/70 p-4 backdrop-blur-sm" role="dialog" aria-modal="true" aria-label="{{ __('Warum :signal?', ['signal' => $signal]) }}">
                                            <button type="button" class="absolute inset-0 cursor-default" @click="signalInfoOpen = false" aria-label="{{ __('Schließen') }}"></button>
                                            <section x-show="signalInfoOpen" x-transition.scale.origin.center class="relative z-10 w-full max-w-lg rounded-2xl border border-cyan-300/30 bg-[var(--ak-card)] p-5 text-left shadow-2xl">
                                                <div class="flex items-start justify-between gap-4">
                                                    <div>
                                                        <p class="text-[9px] font-black uppercase tracking-[.14em] text-cyan-300">{{ __('Warum dieser Signalstatus?') }}</p>
                                                        <h3 class="mt-1 text-xl font-black text-[var(--ak-text)]">{{ $stock->name ?: $stock->symbol }} · {{ $signalLabel }}</h3>
                                                        <p class="mt-1 text-[10px] font-bold uppercase tracking-[.08em] text-[var(--ak-muted)]">{{ __('Profil') }}: {{ $stock->personal_risk_profile }}</p>
                                                    </div>
                                                    <button type="button" @click="signalInfoOpen = false" class="inline-grid h-9 w-9 shrink-0 place-items-center rounded-lg border border-[var(--ak-border)] text-[var(--ak-muted)] transition hover:text-[var(--ak-text)]" aria-label="{{ __('Schließen') }}">
                                                        <x-heroicon-o-x-mark class="h-5 w-5" />
                                                    </button>
                                                </div>
                                                <div class="mt-4 rounded-xl border border-cyan-300/15 bg-cyan-400/[.06] p-4">
                                                    <p class="text-sm font-bold leading-5 text-[var(--ak-text)]">{{ $stock->personal_signal_breakdown['summary'] }}</p>
                                                    <div class="mt-4 grid gap-4 sm:grid-cols-2">
                                                        <div>
                                                            <p class="text-[10px] font-black uppercase tracking-[.12em] text-emerald-400">{{ __('Dafür spricht') }}</p>
                                                            <ul class="mt-2 space-y-2 text-xs leading-5 text-[var(--ak-text)]">
                                                                @forelse($stock->personal_signal_breakdown['pros'] as $point)
                                                                    <li class="flex gap-2"><span class="text-emerald-400">●</span><span>{{ $point }}</span></li>
                                                                @empty
                                                                    <li class="text-[var(--ak-muted)]">{{ __('Aktuell kein Faktor für ein stärkeres Signal.') }}</li>
                                                                @endforelse
                                                            </ul>
                                                        </div>
                                                        <div>
                                                            <p class="text-[10px] font-black uppercase tracking-[.12em] text-rose-400">{{ __('Dagegen spricht') }}</p>
                                                            <ul class="mt-2 space-y-2 text-xs leading-5 text-[var(--ak-text)]">
                                                                @forelse($stock->personal_signal_breakdown['cons'] as $point)
                                                                    <li class="flex gap-2"><span class="text-rose-400">●</span><span>{{ $point }}</span></li>
                                                                @empty
                                                                    <li class="text-[var(--ak-muted)]">{{ __('Keine wesentlichen Gegenargumente erkannt.') }}</li>
                                                                @endforelse
                                                            </ul>
                                                        </div>
                                                    </div>
                                                </div>
                                                <p class="mt-3 text-[10px] leading-4 text-[var(--ak-muted)]">{{ __('Das Signal berücksichtigt Quality Gate, erwartete Nettorendite, Nutzerprofil sowie die Volatilität im Vergleich zum jeweiligen Sektor.') }}</p>
                                            </section>
                                        </div>
                                    </template>
                                </div>
                                <div>
                                <p class="text-[9px] font-black uppercase tracking-[.12em] text-cyan-300">{{ __('Kurs') }}</p>
                                <div class="mt-2 flex flex-wrap items-baseline gap-x-2 gap-y-1">
                                    <p class="text-2xl font-black">{{ is_numeric($stock->current_price) ? number_format((float) $stock->current_price, 2, ',', '.') : '—' }} <span class="text-sm text-[var(--ak-muted)]">{{ $displayCurrencySymbol }}</span></p>
                                    @if($showOriginalPrice)
                                        <p class="whitespace-nowrap text-[10px] font-bold text-[var(--ak-muted)]" title="{{ __('Originalkurs') }} · {{ $stock->original_currency }}">
                                            {{ __('Originalwährung') }} ({{ $originalCurrencyName }}): {{ number_format((float) $stock->original_price, 2, ',', '.') }} {{ $originalCurrencySymbol }}
                                        </p>
                                    @endif
                                </div>
                                <p class="mt-3 text-[9px] font-black uppercase text-[var(--ak-muted)]">{{ __('Rendite · 20 Tage') }}</p>
                                <p class="mt-1 text-lg font-black {{ $returnClass }}">{{ $return !== null ? sprintf('%+.2f %%', $return) : '—' }}</p>
                                </div>
                            <div class="md:col-span-2">
                                <div class="mb-1 flex flex-wrap items-center justify-between gap-1 text-[9px] font-black uppercase tracking-[.1em] text-[var(--ak-muted)]"><span>{{ __('Chart · 1 Jahr') }}</span><span class="flex gap-2">@if ($signalTransitionDate)<span class="text-violet-300">│ {{ __('Signalwechsel') }} {{ $signalTransitionDate }}</span>@endif @if ($predictionPrice !== null)<span class="text-amber-300">— {{ __('Prognose 20 Tage') }}</span>@endif</span></div>
                                @if ($chartPolyline !== '')
                                    <svg viewBox="0 0 600 128" class="h-24 w-full" role="img" aria-label="{{ __('Kursverlauf des letzten Jahres mit Prognose') }}" preserveAspectRatio="none"><defs><linearGradient id="screener-line-{{ $stock->id }}" x1="0" x2="1" y1="0" y2="0"><stop offset="0" stop-color="#22d3ee"/><stop offset="1" stop-color="#67e8f9"/></linearGradient></defs><path d="M0 108H600" stroke="#67e8f9" stroke-opacity=".34" stroke-width="1.4"/>@foreach([0,150,300,450,600] as $tickX)<line x1="{{ $tickX }}" y1="108" x2="{{ $tickX }}" y2="112" stroke="#67e8f9" stroke-opacity=".34" stroke-width="1"/>@endforeach<g fill="#94a3b8" font-size="7" font-weight="700"><text x="0" y="124" text-anchor="start">−1J</text><text x="150" y="124" text-anchor="middle">−9M</text><text x="300" y="124" text-anchor="middle">−6M</text><text x="450" y="124" text-anchor="middle">−3M</text><text x="600" y="124" text-anchor="end">{{ __('Heute') }}</text></g>@if ($signalTransitionX !== null)<line x1="{{ number_format($signalTransitionX, 1, '.', '') }}" y1="4" x2="{{ number_format($signalTransitionX, 1, '.', '') }}" y2="108" stroke="#c084fc" stroke-width="2" stroke-dasharray="5 4"><title>{{ __('Signalwechsel') }} {{ $stock->signal_transition_from }} → {{ $signal }} · {{ $signalTransitionDate }}</title></line>@endif<polyline points="{{ $chartPolyline }}" fill="none" stroke="url(#screener-line-{{ $stock->id }})" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"/>@if ($predictionY !== null)<line x1="390" y1="{{ number_format($predictionY, 1, '.', '') }}" x2="600" y2="{{ number_format($predictionY, 1, '.', '') }}" stroke="#fbbf24" stroke-width="2" stroke-dasharray="7 5"/><circle cx="600" cy="{{ number_format($predictionY, 1, '.', '') }}" r="3" fill="#fbbf24"/>@endif</svg>
                                @else
                                    <div class="flex h-24 items-center justify-center text-xs italic text-[var(--ak-muted)]">{{ __('Keine Daten') }}</div>
                                @endif
                            </div>
                            <div class="absolute right-3 top-2 z-30 flex gap-2">
                                <details class="group relative">
                                    <summary title="{{ $isOnWatchlist ? __('In Watchlist') : __('Watchlist') }}" aria-label="{{ __('Watchlist') }}" class="flex h-7 w-7 cursor-pointer list-none items-center justify-center rounded-md border border-amber-400/30 bg-amber-400/[.08] text-amber-300 {{ $isOnWatchlist ? 'shadow-[0_0_12px_rgba(251,191,36,.30)]' : '' }}">
                                        @if($isOnWatchlist)<x-heroicon-s-star class="h-3.5 w-3.5" />@else<x-heroicon-o-star class="h-3.5 w-3.5" />@endif
                                    </summary>
                                    <div class="absolute right-0 top-10 z-40 min-w-52 space-y-1 rounded-xl border border-amber-400/25 bg-[var(--ak-card)] p-2 shadow-2xl">
                                        @forelse($userWatchlists as $watchlist)
                                            <form method="POST" action="{{ route('watchlists.items.toggle', ['watchlist' => $watchlist->id, 'instrument' => $stock->instrument_id]) }}">
                                                @csrf
                                                <input type="hidden" name="prediction_id" value="{{ $stock->id }}">
                                                <button type="submit" class="flex w-full items-center justify-between gap-3 rounded-lg px-2.5 py-2 text-left text-[10px] font-bold text-[var(--ak-text)] hover:bg-amber-400/10">
                                                    <span>{{ $watchlist->name }}</span>
                                                    <span class="text-amber-300">{{ $stockWatchlistIds->contains((int) $watchlist->id) ? '✓' : '+' }}</span>
                                                </button>
                                            </form>
                                        @empty
                                            <a href="{{ route('watchlists.index') }}" class="block rounded-lg px-2.5 py-2 text-[10px] font-bold text-amber-300">{{ __('Watchlist erstellen') }}</a>
                                        @endforelse
                                    </div>
                                </details>
                                <x-paper-depot-buy :portfolios="$paperPortfolios" :instrument-id="$stock->instrument_id" :instrument-name="$stock->name ?: $stock->symbol" :currency="$stock->currency" :price="$stock->current_price" :score="$rankingScorePercent" compact />
                                <a href="{{ route('stocks.show', ['symbol' => $stock->symbol, 'prediction' => $stock->id, 'return_to' => request()->getRequestUri()]) }}" title="{{ __('Zur Aktiendetailseite') }}" aria-label="{{ __('Details zu :stock anzeigen', ['stock' => $stock->name ?: $stock->symbol]) }}" class="inline-flex h-7 w-7 items-center justify-center rounded-md border border-violet-400/30 bg-violet-400/[.08] text-violet-300 transition hover:bg-violet-400/[.16]">
                                    <x-heroicon-o-arrow-top-right-on-square class="h-3.5 w-3.5" />
                                </a>
                            </div>
                            </div>
                        </div>
                        <div class="grid h-full min-h-0 gap-2 sm:grid-cols-2 xl:col-span-2 xl:grid-rows-[auto_auto_1fr]">
                            <div class="screener-transparent-panel relative rounded-xl border p-3 sm:col-span-2">
                            <div class="screener-ranking-donuts">
                                <div class="screener-metric-donut screener-metric-donut-score" style="--donut-value: {{ number_format($rankingScorePercent, 2, '.', '') }}%; --donut-color: {{ $rankingScoreColor }}" role="meter" aria-label="{{ __('Ranking-Score') }}" aria-valuemin="0" aria-valuemax="100" aria-valuenow="{{ number_format($rankingScorePercent, 1, '.', '') }}">
                                    <span>{{ number_format($rankingScorePercent, 0, ',', '.') }}</span><small>{{ __('KI-Score') }}</small>
                                </div>
                                <div class="screener-metric-donut" style="--donut-value: {{ number_format($rankingConfidencePercent, 2, '.', '') }}%; --donut-color: {{ $rankingConfidenceColor }}" role="meter" aria-label="{{ __('Konfidenz') }}" aria-valuemin="0" aria-valuemax="100" aria-valuenow="{{ number_format($rankingConfidencePercent, 1, '.', '') }}">
                                    <span>{{ number_format($rankingConfidencePercent, 0, ',', '.') }}%</span><small>{{ __('Konf.') }}</small>
                                </div>
                                <div class="screener-metric-donut" style="--donut-value: {{ number_format($rankingHitRatePercent, 2, '.', '') }}%; --donut-color: {{ $rankingHitRateColor }}" role="meter" aria-label="{{ __('Hit-Rate') }}" aria-valuemin="0" aria-valuemax="100" @if($rankingHitRateAvailable) aria-valuenow="{{ number_format($rankingHitRatePercent, 1, '.', '') }}" @endif>
                                    <span>{{ $rankingHitRateAvailable ? number_format($rankingHitRatePercent, 0, ',', '.').'%' : '—' }}</span><small>{{ __('Hit-Rate') }}</small>
                                </div>
                                <div class="screener-metric-donut" style="--donut-value: {{ number_format($rankingProfitPerTradePercent, 2, '.', '') }}%; --donut-color: {{ $rankingProfitPerTradeColor }}" role="meter" aria-label="{{ __('Durchschnittlicher Netto-Profit je Trade im dreijährigen Walk-Forward-Test') }}" @if($rankingProfitPerTradeAvailable) aria-valuenow="{{ number_format($rankingProfitPerTrade, 2, '.', '') }}" @endif>
                                    <span>{{ $rankingProfitPerTradeAvailable ? (($rankingProfitPerTrade > 0 ? '+' : '').number_format($rankingProfitPerTrade, 2, ',', '.').'%') : '—' }}</span><small>{{ __('Ø/Trade') }}</small>
                                </div>
                                <div class="screener-metric-donut" style="--donut-value: {{ number_format($rankingStabilityPercent, 2, '.', '') }}%; --donut-color: {{ $rankingStabilityColor }}" role="meter" aria-label="{{ __('Stabilitätsfilter') }}" aria-valuemin="0" aria-valuemax="100" @if($rankingStabilityAvailable) aria-valuenow="{{ number_format($rankingStabilityPercent, 1, '.', '') }}" @endif>
                                    <span>{{ $rankingStabilityAvailable ? number_format($rankingStabilityPercent, 0, ',', '.').'%' : '—' }}</span><small>{{ __('Stabilität') }}</small>
                                </div>
                                <div class="screener-metric-donut screener-risk-donut" data-risk-tone="{{ $riskDonutTone }}" style="--donut-value: {{ number_format($rankingRiskPercent ?? 0, 2, '.', '') }}%; --donut-color: {{ $riskDonutColor }}" role="meter" aria-label="{{ __('Risiko') }}" aria-valuemin="0" aria-valuemax="100" @if($rankingRiskPercent !== null) aria-valuenow="{{ number_format($rankingRiskPercent, 1, '.', '') }}" @endif>
                                    <span>{{ $rankingRiskPercent !== null ? number_format($rankingRiskPercent, 0, ',', '.').'%' : '—' }}</span><small>{{ __('Risiko') }}</small>
                                </div>
                            </div>
                            <div class="mt-16"></div>
                            </div>
                            <div class="screener-transparent-panel grid grid-cols-3 gap-2 rounded-xl border px-3 py-2 sm:col-span-2">
                                <div>
                                    <p class="text-[8px] font-black uppercase tracking-[.1em] text-[var(--ak-muted)]">{{ __('Dividende') }}</p>
                                    <p class="mt-0.5 text-xs font-black text-amber-200">{{ $dividendYield !== null ? number_format($dividendYield, 2, ',', '.').'%' : '—' }}</p>
                                </div>
                                <div class="border-l border-amber-400/15 pl-2">
                                    <p class="text-[8px] font-black uppercase tracking-[.1em] text-[var(--ak-muted)]">{{ __('KGV') }}</p>
                                    <p class="mt-0.5 text-xs font-black text-amber-200">{{ $priceEarningsRatio !== null ? number_format($priceEarningsRatio, 1, ',', '.') : '—' }}</p>
                                </div>
                                <div class="border-l border-amber-400/15 pl-2">
                                    <p class="text-[8px] font-black uppercase tracking-[.1em] text-[var(--ak-muted)]">{{ __('Sektorplatz') }}</p>
                                    <p class="mt-0.5 text-xs font-black text-amber-200">{{ $stock->sector_rank ? '#'.$stock->sector_rank : '—' }}</p>
                                </div>
                            </div>
                            <details class="screener-transparent-panel company-description-card screener-company-card relative z-20 flex h-full min-h-0 flex-col rounded-xl border p-3 sm:col-span-2">
                                <summary class="flex cursor-pointer list-none items-center justify-between gap-2">
                                    <div class="min-w-0 flex-1">
                                        <p class="text-[9px] font-black uppercase tracking-[.12em] text-cyan-300">{{ __('Unternehmen') }}</p>
                                        <p class="company-preview mt-2 text-xs leading-5 text-[var(--ak-muted)]">{{ $businessSummary ?: __('Unternehmensbeschreibung wird noch erstellt.') }}</p>
                                    </div>
                                    @if ($businessSummary)
                                        <span aria-label="{{ __('Vollständige Unternehmensbeschreibung anzeigen') }}" class="ml-2 inline-flex h-6 w-6 shrink-0 items-center justify-center rounded-full border border-cyan-300/50 bg-cyan-400/10 text-xs font-black text-cyan-200">i</span>
                                    @endif
                                </summary>
                                <p class="company-description mt-2 flex-1 text-xs leading-5 text-[var(--ak-muted)]">{{ $businessSummary ?: __('Unternehmensbeschreibung wird noch erstellt.') }}</p>
                            </details>
                        </div>
                        <div class="grid h-full min-h-0 gap-3 md:col-span-2 xl:col-span-2">
                        @if ($stock->assessment_is_detailed_buy)
                        <details class="screener-transparent-panel assessment-details-card relative z-20 h-full min-h-0 rounded-xl border p-3">
                            <summary class="flex min-h-0 cursor-pointer list-none flex-col">
                                <div class="flex items-start justify-between gap-2">
                                <div class="min-w-0 flex-1">
                                    <div class="flex flex-wrap items-center gap-x-2 gap-y-1">
                                        <p class="text-[9px] font-black uppercase tracking-[.12em] text-violet-300">{{ __('Ausführliche Bewertung · Chancen und Risiken') }}</p>
                                        @if ($stock->assessment_date)
                                            <span class="text-[9px] text-[var(--ak-muted)]">{{ \Illuminate\Support\Carbon::parse($stock->assessment_date)->format('d.m.Y') }}</span>
                                        @endif
                                    </div>
                                </div>
                                @if ($stock->assessment_summary)
                                    <span aria-label="{{ __('Vollständige Bewertung anzeigen') }}" class="ml-2 shrink-0 text-xs font-black text-violet-300">{{ __('Mehr') }} ↓</span>
                                @endif
                                </div>
                                <p class="assessment-preview mt-2 min-h-0 flex-1 overflow-hidden text-xs leading-5 text-[var(--ak-muted)]">{{ $stock->assessment_summary ?: __('Eine ausführliche Bewertung wird beim nächsten Signalübergang auf BUY erstellt.') }}</p>
                            </summary>
                            <div class="assessment-full mt-3">
                                @if ($stock->assessment_summary)
                                    <p class="text-xs leading-5 text-[var(--ak-muted)]">{{ $stock->assessment_summary }}</p>
                                @endif
                            <div class="mt-3 grid gap-3 sm:grid-cols-2">
                                @foreach ([
                                    [__('Chancen'), $stock->assessment_pros, 'text-emerald-300', ''],
                                    [__('Risiken'), $stock->assessment_cons, 'text-rose-300', ''],
                                ] as [$assessmentTitle, $assessmentItems, $assessmentTone, $assessmentBox])
                                    <div class="{{ $assessmentBox }}">
                                        <p class="text-[9px] font-black uppercase tracking-[.1em] {{ $assessmentTone }}">{{ $assessmentTitle }}</p>
                                        <ul class="mt-1.5 space-y-1">
                                            @forelse (array_slice($assessmentItems, 0, 3) as $assessmentItem)
                                                <li class="flex gap-1.5 text-[10px] leading-4 text-[var(--ak-muted)]">
                                                    <span class="mt-1.5 h-1 w-1 shrink-0 rounded-full bg-current {{ $assessmentTone }}"></span>
                                                    <span>{{ is_scalar($assessmentItem) ? $assessmentItem : json_encode($assessmentItem, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}</span>
                                                </li>
                                            @empty
                                                <li class="text-[10px] text-[var(--ak-muted)]">{{ __('Keine Daten') }}</li>
                                            @endforelse
                                        </ul>
                                    </div>
                                @endforeach
                            </div>
                            @if ($stock->assessment_date)
                                <p class="mt-2 text-[9px] text-[var(--ak-muted)]">
                                    {{ $stock->assessment_model }}
                                    · {{ $stock->assessment_recommendation }} ({{ $stock->assessment_confidence }}%)
                                </p>
                            @endif
                            </div>
                        </details>
                        @else
                        <details class="screener-transparent-panel simple-assessment-card relative z-20 h-full min-h-0 rounded-xl border p-3">
                            <summary class="flex min-h-0 cursor-pointer list-none flex-col">
                            <div class="flex items-start justify-between gap-2">
                                <div class="flex flex-wrap items-center gap-x-2 gap-y-1">
                                    <p class="text-[9px] font-black uppercase tracking-[.12em] text-violet-300">{{ __('Chancen und Risiken') }}</p>
                                    @if ($stock->simple_assessment_is_stored && $stock->assessment_date)
                                        <span class="text-[9px] text-[var(--ak-muted)]">{{ \Illuminate\Support\Carbon::parse($stock->assessment_date)->format('d.m.Y') }}</span>
                                    @endif
                                </div>
                                <span aria-label="{{ __('Vollständige Bewertung anzeigen') }}" class="ml-2 shrink-0 text-xs font-black text-violet-300">{{ __('Mehr') }} ↓</span>
                            </div>
                            <div class="simple-assessment-preview mt-2 grid min-h-0 flex-1 gap-3 overflow-hidden sm:grid-cols-2">
                                @foreach ([
                                    [__('Chancen'), $stock->simple_pros, 'text-emerald-300'],
                                    [__('Risiken'), $stock->simple_cons, 'text-rose-300'],
                                ] as [$previewTitle, $previewItems, $previewTone])
                                    <div class="min-w-0">
                                        <p class="text-[9px] font-black uppercase tracking-[.1em] {{ $previewTone }}">{{ $previewTitle }}</p>
                                        <ul class="mt-1.5 space-y-1">
                                            @foreach ($previewItems as $previewItem)
                                                <li class="flex gap-1.5 text-[10px] leading-4 text-[var(--ak-muted)]">
                                                    <span class="mt-1.5 h-1 w-1 shrink-0 rounded-full bg-current {{ $previewTone }}"></span>
                                                    <span>{{ $previewItem }}</span>
                                                </li>
                                            @endforeach
                                        </ul>
                                    </div>
                                @endforeach
                            </div>
                            </summary>
                            <div class="simple-assessment-full mt-3">
                            <div class="mt-2 grid gap-3 sm:grid-cols-2">
                                @foreach ([
                                    [__('Chancen'), $stock->simple_pros, 'text-emerald-300', ''],
                                    [__('Risiken'), $stock->simple_cons, 'text-rose-300', ''],
                                ] as [$assessmentTitle, $assessmentItems, $assessmentTone, $assessmentBox])
                                    <div class="{{ $assessmentBox }}">
                                        <p class="text-[9px] font-black uppercase tracking-[.1em] {{ $assessmentTone }}">{{ $assessmentTitle }}</p>
                                        <ul class="mt-1.5 space-y-1">
                                            @foreach ($assessmentItems as $assessmentItem)
                                                <li class="flex gap-1.5 text-[10px] leading-4 text-[var(--ak-muted)]">
                                                    <span class="mt-1.5 h-1 w-1 shrink-0 rounded-full bg-current {{ $assessmentTone }}"></span>
                                                    <span>{{ $assessmentItem }}</span>
                                                </li>
                                            @endforeach
                                        </ul>
                                    </div>
                                @endforeach
                            </div>
                            <p class="mt-2 text-[9px] text-[var(--ak-muted)]">
                                @if (! $stock->simple_assessment_is_stored)
                                    {{ __('Automatisch aus den aktuellen Modell- und Filterwerten abgeleitet.') }}
                                @endif
                            </p>
                            </div>
                        </details>
                        @endif
                        </div>
                    </div>
                </article>
            @empty
                <div class="rounded-2xl border border-cyan-400/25 bg-[var(--ak-card)] p-8 text-center text-sm text-[var(--ak-muted)] sm:col-span-2 xl:col-span-3">{{ __('Keine Aktien für diese Auswahl gefunden.') }}</div>
            @endforelse
        </section>
        </div>
    </div>
</x-app-layout>
