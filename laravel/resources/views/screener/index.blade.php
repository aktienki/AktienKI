<x-app-layout>
    <div class="screener-page mx-auto max-w-[96rem] px-3 py-5 text-[var(--ak-text)] sm:px-5 lg:py-8">
        <header class="mb-3 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <h1 class="text-3xl font-black tracking-tight">{{ __('Aktienscreener') }}</h1>
            <div class="flex flex-wrap gap-2"><a href="{{ route('predictions.index') }}" class="inline-flex h-10 items-center justify-center rounded-xl border border-cyan-400/35 bg-cyan-400/[.08] px-4 text-xs font-black text-cyan-300 transition hover:bg-cyan-400/[.16]">{{ __('Prognosetabelle öffnen') }}</a></div>
        </header>

        @if($isFreeRegional)
            <div class="mb-4 flex flex-wrap items-center justify-between gap-3 rounded-xl border border-amber-400/25 bg-amber-400/[.06] px-4 py-3"><div><p class="text-[9px] font-black uppercase tracking-[.15em] text-amber-400">{{ __('Free · Regionales Top-100-Portfolio') }}</p><p class="mt-1 text-xs text-[var(--ak-muted)]">{{ __('Der Aktienscreener zeigt ausschließlich die 100 wichtigsten Aktien deiner Region (:country).', ['country' => $regionalCountry]) }}</p></div><a href="{{ route('pricing') }}" class="text-[9px] font-black text-amber-300">{{ __('Alle Aktien ab Plus') }} →</a></div>
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

        <section x-data="{ filtersOpen: false }" class="screener-filter-shell mb-5 shrink-0">
        <button type="button" @click="filtersOpen = ! filtersOpen" :aria-expanded="filtersOpen" class="flex h-10 w-full items-center justify-between rounded-xl border border-cyan-400/30 bg-[var(--ak-card)] px-4 text-xs font-black text-cyan-300 shadow-[var(--ak-shadow)]">
            <span class="inline-flex items-center gap-2"><x-heroicon-o-adjustments-horizontal class="h-4 w-4" />{{ __('Filter anzeigen') }}</span>
            <x-heroicon-o-chevron-down class="h-4 w-4 transition" x-bind:class="filtersOpen && 'rotate-180'" />
        </button>
        <form x-cloak x-show="filtersOpen" method="GET" action="{{ route('screener.index') }}" class="screener-filter-bar mt-2 flex flex-nowrap gap-2 overflow-x-auto rounded-lg border border-cyan-400/30 bg-[var(--ak-card)] p-3 shadow-[var(--ak-shadow)]">
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
        </section>

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
                    $recentNews = $recentNewsByInstrument->get((int) $stock->instrument_id);
                    $recentNewsSentiment = is_numeric($recentNews?->sentiment_score) ? (float) $recentNews->sentiment_score : null;
                    [$recentNewsTone, $recentNewsLabel] = match (true) {
                        $recentNews === null => ['border-slate-400/20 bg-slate-400/[.04] text-slate-500', __('Keine News in den letzten 48 Stunden')],
                        $recentNewsSentiment !== null && $recentNewsSentiment >= .35 => ['border-emerald-400/45 bg-emerald-400/[.14] text-emerald-400 shadow-[0_0_12px_rgba(52,211,153,.18)]', __('Positive News in den letzten 48 Stunden')],
                        $recentNewsSentiment !== null && $recentNewsSentiment <= -.35 => ['border-rose-400/45 bg-rose-400/[.14] text-rose-400 shadow-[0_0_12px_rgba(251,113,133,.18)]', __('Negative News in den letzten 48 Stunden')],
                        default => ['border-amber-400/45 bg-amber-400/[.14] text-amber-400 shadow-[0_0_12px_rgba(251,191,36,.16)]', __('Neutrale News in den letzten 48 Stunden')],
                    };
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
                    $rankingProfitFactorAvailable = is_numeric($stock->ranking_profit_factor);
                    $rankingProfitFactor = $rankingProfitFactorAvailable ? \App\Support\ProfitFactor::cap($stock->ranking_profit_factor) : 0;
                    $rankingProfitFactorPercent = $rankingProfitFactorAvailable
                        ? max(0, min(100, ($rankingProfitFactor / 3) * 100))
                        : 0;
                    $rankingStabilityAvailable = (bool) $stock->ranking_stability_available;
                    $rankingStabilityPercent = $rankingStabilityAvailable
                        ? max(0, min(100, (float) $stock->ranking_stability_percent))
                        : 0;
                    $rankingConfidenceColor = $qualityDonutColor($rankingConfidencePercent);
                    $rankingHitRateColor = $rankingHitRateAvailable ? $qualityDonutColor($rankingHitRatePercent) : '#64748b';
                    $rankingProfitFactorColor = $rankingProfitFactorAvailable
                        ? sprintf(
                            'hsl(%.1f 78%% 47%%)',
                            24 + (min(1, $rankingProfitFactor / 1.8) * 118)
                        )
                        : '#64748b';
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
                    // Price bars use the instrument's original listing currency, while
                    // the visible quote can already have been converted to EUR. Anchor
                    // the forecast to the last chart close and apply the model return so
                    // both parts of the chart always use the same currency and scale.
                    $latestChartPrice = $chartPoints->isNotEmpty() ? (float) $chartPoints->last() : null;
                    $chartPredictionPrice = $latestChartPrice !== null && $return !== null
                        ? $latestChartPrice * (1 + ($return / 100))
                        : null;
                    $chartMin = $chartPoints->isNotEmpty() ? (float) $chartPoints->min() : 0;
                    $chartMax = $chartPoints->isNotEmpty() ? (float) $chartPoints->max() : 1;
                    if ($chartPredictionPrice !== null) {
                        $chartMin = min($chartMin, $chartPredictionPrice);
                        $chartMax = max($chartMax, $chartPredictionPrice);
                    }
                    $chartRange = max($chartMax - $chartMin, 0.000001);
                    $chartScalePadding = $chartRange * 0.16;
                    $chartMin -= $chartScalePadding;
                    $chartMax += $chartScalePadding;
                    $chartRange = max($chartMax - $chartMin, 0.000001);
                    // Keep a visible vertical safety margin. Without it, extrema sit
                    // directly on the SVG edge and are clipped on wide desktop cards.
                    $latestChartY = $latestChartPrice !== null ? 100 - (($latestChartPrice - $chartMin) / $chartRange) * 76 : null;
                    $predictionY = $chartPredictionPrice !== null ? 100 - (($chartPredictionPrice - $chartMin) / $chartRange) * 76 : null;
                    $forecastStartX = 500.0;
                    $predictionSignalDate = filled($stock->prediction_time ?? null)
                        ? \Illuminate\Support\Carbon::parse($stock->prediction_time)->format('d.m.Y')
                        : null;
                    $signalTransitionX = is_numeric($stock->signal_transition_x) ? (float) $stock->signal_transition_x : null;
                    $signalTransitionDate = $stock->signal_transition_at
                        ? \Illuminate\Support\Carbon::parse($stock->signal_transition_at)->format('d.m.Y')
                        : null;
                    $chartPolyline = $chartPoints->count() > 1
                        ? $chartPoints->values()->map(fn (float $value, int $index): string => sprintf('%.1f,%.1f', $index * $forecastStartX / ($chartPoints->count() - 1), 100 - (($value - $chartMin) / $chartRange) * 76))->implode(' ')
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
                    $mobileForecasts = collect([5, 10, 15, 20])->mapWithKeys(function (int $days) use ($stock): array {
                        $value = $stock->{"expected_return_{$days}d"} ?? null;
                        return [$days => is_numeric($value) ? (float) $value : null];
                    });
                    $calibratedSignalQuality = data_get($stock->stock_signal_calibration, 'quality_percent');
                    $buySignalRating = \App\Support\DirectionalSignalRating::calculate(
                        $mobileForecasts->all(),
                        is_numeric($calibratedSignalQuality) ? (float) $calibratedSignalQuality : $rankingScorePercent,
                    );
                    $buySignalScorePercent = (float) $buySignalRating['percent'];
                    $buySignalScoreLabel = (string) $buySignalRating['label'];
                    $buySignalScoreColor = $qualityDonutColor($buySignalScorePercent);
                    $modelQualityBadge = match ((string) ($stock->model_quality_tier_code ?? '')) {
                        'top' => 'Top Quality',
                        'strong' => 'Quality',
                        'solid' => 'Solid',
                        'test' => 'Basic',
                        default => ($stock->model_quality_tier_name ?? __('Validiert')),
                    };
                    $riskClassBadge = match (true) {
                        $rankingRiskPercent === null => __('Nicht bewertet'),
                        $rankingRiskPercent <= 25 => __('Defensiv'),
                        $rankingRiskPercent <= 50 => __('Ausgewogen'),
                        $rankingRiskPercent <= 75 => __('Dynamisch'),
                        default => __('Spekulativ'),
                    };
                    $buySignalSectorStart = max(0, $buySignalScorePercent - 5);
                    $buySignalSectorEnd = max(1, $buySignalScorePercent);
                    $riskSectorStart = max(0, (float) ($rankingRiskPercent ?? 0) - 5);
                    $riskSectorEnd = max(1, (float) ($rankingRiskPercent ?? 0));
                    $signalStrength = \App\Support\SignalStrength::label($mobileForecasts[20]);
                    $priceChange = is_numeric($stock->price_change_percent ?? null) ? (float) $stock->price_change_percent : null;
                @endphp
                <article
                    data-ranking="{{ $ranking }}"
                    class="screener-stock-card {{ $hasLongCompanyName ? 'screener-stock-card-long-name' : '' }} ak-card ak-dashboard-card relative overflow-hidden p-3 {{ $rankClass }}"
                    x-data="{ signalInfoOpen: false, mobileExpanded: false }"
                >
                    <button type="button" class="screener-mobile-summary md:hidden" @click="mobileExpanded = ! mobileExpanded" :aria-expanded="mobileExpanded.toString()">
                        <span class="screener-mobile-summary-top">
                            <span class="screener-mobile-title-context">
                                <span class="screener-mobile-name-row">
                                    <b class="screener-mobile-rank">{{ $ranking > 0 ? '#'.$ranking : '—' }}</b>
                                    <span class="screener-mobile-flag" aria-label="{{ $stock->country ?: __('Land') }}">{{ $countryFlag }}</span>
                                    <span class="screener-mobile-name" title="{{ $stock->name ?: $stock->symbol }}">{{ $stock->name ?: $stock->symbol }}</span>
                                    <x-stock-risk-status :status="$stock->risk_status ?? null" compact :interactive="false" />
                                </span>
                                <span class="screener-mobile-header-meta">
                                    <span title="{{ $stock->sector ?: __('Sektor nicht hinterlegt') }}"><x-sector-icon :sector="$stock->sector" class="h-3 w-3 shrink-0" /><small>{{ $stock->sector ?: '—' }}</small></span>
                                    <span title="{{ $stock->primary_index_name ?: ($stock->primary_index_symbol ?: __('Index nicht hinterlegt')) }}"><x-heroicon-o-chart-bar-square class="h-3 w-3 shrink-0" /><small>{{ $stock->primary_index_name ?: ($stock->primary_index_symbol ?: '—') }}</small></span>
                                </span>
                            </span>
                            <span class="screener-mobile-header-metric">
                                <span class="screener-mobile-price"><b>{{ is_numeric($stock->current_price) ? number_format((float) $stock->current_price, 2, ',', '.') : '—' }} {{ $displayCurrencySymbol }}</b></span>
                                <small>{{ __('Kurs') }}</small>
                            </span>
                            <span class="screener-mobile-header-metric">
                                <span class="screener-mobile-change" data-tone="{{ $priceChange === null ? 'neutral' : ($priceChange > 0 ? 'positive' : ($priceChange < 0 ? 'negative' : 'neutral')) }}">{{ $priceChange !== null ? (($priceChange > 0 ? '+' : '').number_format($priceChange, 2, ',', '.').' %') : '—' }}</span>
                                <small>{{ __('Änderung') }}</small>
                            </span>
                            <x-heroicon-o-chevron-down class="screener-mobile-chevron h-4 w-4" x-bind:class="mobileExpanded && 'rotate-180'" />
                        </span>
                        <span class="screener-mobile-summary-middle">
                            <span class="screener-mobile-score-group">
                                <span class="screener-mobile-donut" title="{{ __('Rohwert') }}: {{ number_format($rankingScorePercent, 0, ',', '.') }}/100" style="--mobile-donut-value:{{ number_format($rankingScorePercent, 2, '.', '') }}%;--mobile-donut-color:{{ $rankingScoreColor }}" role="meter" aria-label="{{ __('KI-Score') }}" aria-valuemin="0" aria-valuemax="100" aria-valuenow="{{ number_format($rankingScorePercent, 1, '.', '') }}"><span><b>{{ \App\Support\QualityGrade::fromPercent($rankingScorePercent) ?? '—' }}</b><small>{{ __('Score') }}</small></span></span>
                                <span class="screener-mobile-donut screener-risk-donut" title="{{ __('Rohwert') }}: {{ $rankingRiskPercent !== null ? number_format($rankingRiskPercent, 0, ',', '.').' %' : '—' }}" style="--mobile-donut-value:{{ number_format($rankingRiskPercent ?? 0, 2, '.', '') }}%;--mobile-donut-color:{{ $riskDonutColor }}" role="meter" aria-label="{{ __('Risiko') }}" aria-valuemin="0" aria-valuemax="100" aria-valuenow="{{ number_format($rankingRiskPercent ?? 0, 1, '.', '') }}"><span><b>{{ \App\Support\QualityGrade::riskLevel($rankingRiskPercent) ?? '—' }}</b><small>{{ __('Risiko') }}</small></span></span>
                            </span>
                            <span class="screener-mobile-signal-group">
                                <span class="screener-mobile-summary-metric">
                                    <span class="screener-mobile-signal" data-signal="{{ strtolower($signal) }}">{{ $signalLabel }}</span>
                                    <small>{{ __('Stärke') }} {{ $signalStrength }}</small>
                                </span>
                                <span class="screener-mobile-summary-metric">
                                    <span class="screener-mobile-return" data-tone="{{ $mobileForecasts[20] === null ? 'neutral' : ($mobileForecasts[20] > 0 ? 'positive' : ($mobileForecasts[20] < 0 ? 'negative' : 'neutral')) }}" title="{{ __('Mögliche Rendite in 20 Tagen') }}">
                                        {{ $mobileForecasts[20] === null ? '—' : (($mobileForecasts[20] > 0 ? '+' : '').number_format($mobileForecasts[20], 1, ',', '.').' %') }}
                                    </span>
                                    <small>{{ __('Mögliche Rendite') }}</small>
                                </span>
                                <span class="screener-mobile-summary-metric">
                                    <span class="screener-mobile-indicator-arrow" data-tone="{{ $stock->indicator_ranking_direction }}" title="{{ __('Indikatorranking') }}">
                                        <b>{{ $stock->indicator_ranking_direction === 'up' ? '↗' : ($stock->indicator_ranking_direction === 'down' ? '↘' : '→') }}</b>
                                    </span>
                                    <small>{{ __('Indikator') }}</small>
                                </span>
                            </span>
                        </span>
                    </button>
                    <div class="screener-mobile-details grid h-full min-h-0 gap-2 md:grid-cols-2 xl:grid-cols-6" x-bind:class="mobileExpanded && 'is-mobile-open'">
                        <div class="screener-chart-panel relative h-full min-h-0 rounded-xl border border-transparent p-3 pt-5 xl:col-span-2">
                            <div class="grid gap-3 md:grid-cols-[.7fr_1.3fr]">
                                <div class="screener-expanded-identity">
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
                                        <button type="button" @click.prevent.stop="signalInfoOpen = true" class="screener-signal-info-button inline-grid h-7 w-7 place-items-center rounded-lg border border-cyan-300/35 bg-cyan-400/[.08] text-cyan-300 transition hover:bg-cyan-400/[.16]" aria-label="{{ __('Signalbegründung anzeigen') }}">
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
                                <div class="screener-expanded-price">
                                <p class="text-[9px] font-black uppercase tracking-[.12em] text-cyan-300">{{ __('Kurs') }}</p>
                                <div class="mt-2 flex flex-wrap items-baseline gap-x-2 gap-y-1">
                                    <p class="text-2xl font-black">{{ is_numeric($stock->current_price) ? number_format((float) $stock->current_price, 2, ',', '.') : '—' }} <span class="text-sm text-[var(--ak-muted)]">{{ $displayCurrencySymbol }}</span></p>
                                    @if($showOriginalPrice)
                                        <p class="whitespace-nowrap text-[10px] font-bold text-[var(--ak-muted)]" title="{{ __('Originalkurs') }} · {{ $stock->original_currency }}">
                                            {{ __('Originalwährung') }} ({{ $originalCurrencyName }}): {{ number_format((float) $stock->original_price, 2, ',', '.') }} {{ $originalCurrencySymbol }}
                                        </p>
                                    @endif
                                </div>
                                <p class="mt-3 text-[9px] font-black uppercase text-[var(--ak-muted)]">{{ __('Performance · Prognosehorizonte') }}</p>
                                <div class="screener-performance-horizons mt-1.5 grid grid-cols-2 gap-1 sm:grid-cols-4">
                                    @foreach($mobileForecasts as $days => $forecast)
                                        @php
                                            $forecastBadgeTone = $forecast === null
                                                ? 'border-slate-400/20 bg-slate-400/[.06] text-[var(--ak-muted)]'
                                                : ($forecast > 0
                                                    ? 'border-emerald-400/35 bg-emerald-400/[.10] text-emerald-400'
                                                    : ($forecast < 0
                                                        ? 'border-rose-400/35 bg-rose-400/[.10] text-rose-400'
                                                        : 'border-amber-400/35 bg-amber-400/[.10] text-amber-400'));
                                        @endphp
                                        <span class="flex min-w-0 flex-col items-center justify-center rounded-md border px-1 py-1 {{ $forecastBadgeTone }}" title="{{ __('Mögliche Rendite in :days Tagen', ['days' => $days]) }}">
                                            <small class="text-[7px] font-black uppercase tracking-wide opacity-75">{{ $days }}T</small>
                                            <b class="max-w-full truncate text-[9px] font-black tabular-nums">{{ $forecast === null ? '—' : sprintf('%+.1f%%', $forecast) }}</b>
                                        </span>
                                    @endforeach
                                </div>
                                </div>
                            <div class="screener-expanded-chart md:col-span-2">
                                <div class="mb-3 md:hidden">
                                    <p class="text-[10px] font-black uppercase tracking-[.14em] text-cyan-300">{{ __('Mögliche Renditen') }}</p>
                                    <div class="mt-2 grid grid-cols-4 gap-1.5">
                                        @foreach($mobileForecasts as $days => $forecast)
                                            <div class="rounded-lg border border-cyan-300/15 bg-cyan-400/[.05] px-1.5 py-2 text-center">
                                                <p class="text-[8px] font-black uppercase tracking-[.08em] text-[var(--ak-muted)]">{{ $days }} {{ __('Tage') }}</p>
                                                <p class="mt-1 text-[11px] font-black {{ $forecast === null ? 'text-slate-400' : ($forecast > 0 ? 'text-emerald-400' : ($forecast < 0 ? 'text-rose-400' : 'text-slate-400')) }}">
                                                    {{ $forecast === null ? '—' : (($forecast > 0 ? '↗ +' : ($forecast < 0 ? '↘ ' : '→ ')).number_format($forecast, 2, ',', '.').' %') }}
                                                </p>
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                                <div class="mb-1 flex flex-wrap items-center justify-between gap-1 text-[9px] font-black uppercase tracking-[.1em] text-[var(--ak-muted)]"><span>{{ __('Chart · 1 Jahr') }} · {{ $stock->chart_currency ?? $stock->currency }}</span><span class="flex gap-2">@if ($signalTransitionDate)<span class="text-violet-300">│ {{ __('Signalwechsel') }} {{ $signalTransitionDate }}</span>@endif @if ($predictionPrice !== null)<span class="text-amber-300">— {{ __('Prognose 20 Tage') }}</span>@endif</span></div>
                                @if ($chartPolyline !== '')
                                    <svg viewBox="0 0 600 128" class="h-24 w-full" role="img" aria-label="{{ __('Kursverlauf des letzten Jahres mit Prognose') }}" preserveAspectRatio="none"><defs><linearGradient id="screener-line-{{ $stock->id }}" x1="0" x2="1" y1="0" y2="0"><stop offset="0" stop-color="#2563eb"/><stop offset="1" stop-color="#0d9488"/></linearGradient></defs><path d="M0 108H600" stroke="#0d9488" stroke-opacity=".28" stroke-width="1.4"/>@foreach([0,125,250,375,500] as $tickX)<line x1="{{ $tickX }}" y1="108" x2="{{ $tickX }}" y2="112" stroke="#0d9488" stroke-opacity=".28" stroke-width="1"/>@endforeach<g fill="#94a3b8" font-size="7" font-weight="700"><text x="0" y="124" text-anchor="start">−1J</text><text x="125" y="124" text-anchor="middle">−9M</text><text x="250" y="124" text-anchor="middle">−6M</text><text x="375" y="124" text-anchor="middle">−3M</text><text x="500" y="124" text-anchor="middle">{{ __('Heute') }}</text><text x="600" y="124" text-anchor="end">20T</text></g>@if ($signalTransitionX !== null)<line x1="{{ number_format($signalTransitionX * (500 / 600), 1, '.', '') }}" y1="4" x2="{{ number_format($signalTransitionX * (500 / 600), 1, '.', '') }}" y2="108" stroke="#c084fc" stroke-width="1.5" stroke-dasharray="4 4"><title>{{ __('Signalwechsel') }} {{ $stock->signal_transition_from }} → {{ $signal }} · {{ $signalTransitionDate }}</title></line>@endif<polyline points="{{ $chartPolyline }}" fill="none" stroke="url(#screener-line-{{ $stock->id }})" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"/>@if ($predictionY !== null && $latestChartY !== null)<line x1="500" y1="4" x2="500" y2="108" stroke="#fbbf24" stroke-opacity=".85" stroke-width="1.5" stroke-dasharray="4 4"><title>{{ __('Signaldatum') }} {{ $predictionSignalDate ?: '—' }}</title></line><text x="494" y="11" text-anchor="end" fill="#fbbf24" font-size="7" font-weight="800">{{ __('Signal') }} {{ $predictionSignalDate }}</text><line x1="500" y1="{{ number_format($latestChartY, 1, '.', '') }}" x2="600" y2="{{ number_format($predictionY, 1, '.', '') }}" stroke="#fbbf24" stroke-width="2.5" stroke-dasharray="7 5"/><circle cx="500" cy="{{ number_format($latestChartY, 1, '.', '') }}" r="2.5" fill="#fbbf24"/><circle cx="600" cy="{{ number_format($predictionY, 1, '.', '') }}" r="3" fill="#fbbf24"/>@endif</svg>
                                @else
                                    <div class="flex h-24 items-center justify-center text-xs italic text-[var(--ak-muted)]">{{ __('Keine Daten') }}</div>
                                @endif
                            </div>
                            <div class="screener-card-actions absolute right-3 top-2 z-30 flex gap-2">
                                <a href="{{ route('setup.labels.index') }}" title="{{ __('Labels') }}" aria-label="{{ __('Labels verwalten') }}" class="inline-flex h-8 w-8 items-center justify-center rounded-xl border transition {{ $stock->has_matching_label ? 'border-cyan-400/30 bg-cyan-400/[.08] text-cyan-300 hover:bg-cyan-400/[.16]' : 'border-slate-500/15 bg-slate-500/[.04] text-slate-500/40 hover:text-cyan-300' }}">
                                    <x-heroicon-o-tag class="h-4 w-4" />
                                </a>
                                <a href="{{ route('setup.saved-filters.index') }}" title="{{ __('Strategielabels') }}" aria-label="{{ __('Strategielabels verwalten') }}" class="inline-flex h-8 w-8 items-center justify-center rounded-xl border transition {{ $stock->has_matching_strategy ? 'border-teal-400/30 bg-teal-400/[.08] text-teal-300 hover:bg-teal-400/[.16]' : 'border-slate-500/15 bg-slate-500/[.04] text-slate-500/40 hover:text-teal-300' }}">
                                    <x-heroicon-o-bookmark-square class="h-4 w-4" />
                                </a>
                                <details class="group relative">
                                    <summary title="{{ $isOnWatchlist ? __('In Watchlist') : __('Watchlist') }}" aria-label="{{ __('Watchlist') }}" class="flex h-8 w-8 cursor-pointer list-none items-center justify-center rounded-xl border border-amber-400/30 bg-amber-400/[.08] text-amber-300 {{ $isOnWatchlist ? 'shadow-[0_0_12px_rgba(251,191,36,.30)]' : '' }}">
                                        @if($isOnWatchlist)<x-heroicon-s-star class="h-4 w-4" />@else<x-heroicon-o-star class="h-4 w-4" />@endif
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
                                <x-paper-depot-buy :portfolios="$paperPortfolios" :instrument-id="$stock->instrument_id" :instrument-name="$stock->name ?: $stock->symbol" :currency="$stock->currency" :price="$stock->current_price" :score="$rankingScorePercent" :active="$isInPaperPortfolio" compact />
                                <a href="{{ route('news.index', ['q' => $stock->symbol, 'days' => 2]) }}" title="{{ $recentNewsLabel }}{{ $recentNews ? ' · '.($recentNews->news_count ?? 1).' · '.$recentNews->headline : '' }}" aria-label="{{ $recentNewsLabel }}" class="relative inline-flex h-8 w-8 items-center justify-center rounded-xl border transition hover:brightness-110 {{ $recentNewsTone }}">
                                    <x-heroicon-o-newspaper class="h-4 w-4" />
                                    @if($recentNews && ($recentNews->news_count ?? 1) > 1)<span class="absolute -right-1.5 -top-1.5 grid h-4 min-w-4 place-items-center rounded-full bg-[var(--ak-card-strong)] px-1 text-[7px] font-black text-current">{{ $recentNews->news_count }}</span>@endif
                                </a>
                                @if(Route::has('certificates.index') && $certificateInstrumentIds->contains((int) $stock->instrument_id))
                                    <a href="{{ route('certificates.index', ['underlying' => $stock->instrument_id]) }}" title="{{ __('Zertifikate zu :stock', ['stock' => $stock->name ?: $stock->symbol]) }}" aria-label="{{ __('Zertifikate zu :stock anzeigen', ['stock' => $stock->name ?: $stock->symbol]) }}" class="inline-flex h-8 w-8 items-center justify-center rounded-xl border border-amber-400/30 bg-amber-400/[.08] text-amber-300 transition hover:bg-amber-400/[.16]">
                                        <x-heroicon-o-document-chart-bar class="h-4 w-4" />
                                    </a>
                                @else
                                    <span title="{{ __('Keine Zertifikate für :stock verfügbar', ['stock' => $stock->name ?: $stock->symbol]) }}" aria-label="{{ __('Keine Zertifikate verfügbar') }}" aria-disabled="true" class="inline-flex h-8 w-8 cursor-not-allowed items-center justify-center rounded-xl border border-slate-500/15 bg-slate-500/[.04] text-slate-500/40">
                                        <x-heroicon-o-document-chart-bar class="h-4 w-4" />
                                    </span>
                                @endif
                                <a href="{{ route('stocks.show', ['symbol' => $stock->symbol, 'prediction' => $stock->id, 'return_to' => request()->getRequestUri()]) }}" title="{{ __('Zur Aktiendetailseite') }}" aria-label="{{ __('Details zu :stock anzeigen', ['stock' => $stock->name ?: $stock->symbol]) }}" class="inline-flex h-8 w-8 items-center justify-center rounded-xl border border-violet-400/30 bg-violet-400/[.08] text-violet-300 transition hover:bg-violet-400/[.16]">
                                    <x-heroicon-o-arrow-top-right-on-square class="h-4 w-4" />
                                </a>
                            </div>
                            </div>
                        </div>
                        <div class="screener-desktop-analysis grid h-full min-h-0 gap-2 sm:grid-cols-2 xl:col-span-2">
                            <div class="screener-transparent-panel relative rounded-xl border p-3 sm:col-span-2">
                            <div class="screener-ranking-donuts screener-stock-primary-donuts">
                                <div class="screener-model-badges" aria-label="{{ __('Modell- und Risikoeinstufung') }}">
                                    <span class="screener-classification-badge screener-classification-badge-quality"><small>{{ __('Modell') }}</small><strong>{{ $modelQualityBadge }}</strong></span>
                                    <span class="screener-classification-badge screener-classification-badge-risk"><small>{{ __('Risikoklasse') }}</small><strong>{{ $riskClassBadge }}</strong></span>
                                </div>
                                <div class="screener-metric-wrap screener-metric-wrap-primary" title="{{ __('Gewichtete Prognose') }}: {{ number_format((float) $buySignalRating['weighted_return'], 2, ',', '.') }} % · {{ __('Modellqualität') }}: {{ number_format((float) $buySignalRating['quality'], 0, ',', '.') }}/100">
                                    @php
                                        $buySignalSectorCenter = max(0, min(100, (float) $buySignalScorePercent));
                                        $buySignalSectorStart = max(0, $buySignalSectorCenter - 3.5);
                                        $buySignalSectorEnd = min(100, $buySignalSectorCenter + 3.5);
                                    @endphp
                                    <div class="screener-metric-donut screener-buy-signal-donut" style="--donut-value: {{ number_format($buySignalScorePercent, 2, '.', '') }}%; --donut-color: {{ $buySignalScoreColor }}; --active-sector-start: {{ $buySignalSectorStart }}%; --active-sector-end: {{ $buySignalSectorEnd }}%; --active-sector-color: {{ $buySignalScoreColor }}" role="meter" aria-label="{{ __('KI-Qualität') }} {{ $buySignalScoreLabel }}" aria-valuemin="0" aria-valuemax="100" aria-valuenow="{{ number_format($buySignalScorePercent, 1, '.', '') }}"><span>{{ $buySignalScoreLabel }}</span></div>
                                    <small>{{ __('KI-Qualität') }}</small>
                                </div>
                                <div class="screener-metric-wrap" title="{{ __('Rohwert') }}: {{ $rankingRiskPercent !== null ? number_format($rankingRiskPercent, 0, ',', '.').' %' : '—' }}">
                                    <div class="screener-metric-donut screener-risk-donut" style="--donut-value: {{ number_format($rankingRiskPercent ?? 0, 2, '.', '') }}%; --donut-color: {{ $riskDonutColor }}; --active-sector-start: {{ $riskSectorStart }}%; --active-sector-end: {{ $riskSectorEnd }}%; --active-sector-color: {{ $riskDonutColor }}" role="meter" aria-label="{{ __('Risiko') }}" aria-valuemin="0" aria-valuemax="100" @if($rankingRiskPercent !== null) aria-valuenow="{{ number_format($rankingRiskPercent, 1, '.', '') }}" @endif><span>{{ \App\Support\QualityGrade::riskLevel($rankingRiskPercent) ?? '—' }}</span></div>
                                    <small>{{ __('Risiko') }}</small>
                                </div>
                            </div>
                            <div class="screener-donut-spacer"></div>
                            </div>
                            <div class="screener-fundamental-strip screener-transparent-panel grid grid-cols-3 overflow-hidden rounded-xl border sm:col-span-2">
                                <div class="px-3 py-2">
                                    <small>{{ __('Dividende') }}</small>
                                    <b>{{ $dividendYield !== null ? number_format($dividendYield, 2, ',', '.').' %' : '—' }}</b>
                                </div>
                                <div class="border-x border-cyan-300/15 px-3 py-2">
                                    <small>{{ __('KGV') }}</small>
                                    <b>{{ $priceEarningsRatio !== null ? number_format($priceEarningsRatio, 1, ',', '.') : '—' }}</b>
                                </div>
                                <div class="px-3 py-2">
                                    <small>{{ __('Sektorplatz') }}</small>
                                    <b>{{ is_numeric($stock->sector_rank ?? null) ? '#'.number_format((float) $stock->sector_rank, 0, ',', '.') : '—' }}</b>
                                </div>
                            </div>
                            @php
                                $percentiles = $stock->global_percentiles ?? [];
                                $indexPercentiles = $stock->index_percentiles ?? [];
                                $sectorPercentiles = $stock->sector_percentiles ?? [];
                                $percentileRows = [
                                    [__('KI-Score'), $rankingScorePercent, 'score', '/100'],
                                    [__('Prognose 20T'), $return, 'return_20d', '%'],
                                    [__('Konfidenz'), $rankingConfidencePercent, 'confidence', '%'],
                                    [__('Profitfaktor'), $rankingProfitFactorAvailable ? $rankingProfitFactor : null, 'profit_factor', ''],
                                    [__('Hit-Rate'), $rankingHitRateAvailable ? $rankingHitRatePercent : null, 'hit_rate', '%'],
                                    [__('Risiko'), $rankingRiskPercent, 'risk', '%'],
                                    [__('Volatilität'), is_numeric($stock->annualized_volatility) ? (float) $stock->annualized_volatility * 100 : null, 'volatility', '%'],
                                    [__('Indikatoren'), $stock->indicator_strength_percent, 'indicators', '%'],
                                    [__('KGV'), $priceEarningsRatio, 'pe_ratio', ''],
                                    [__('Dividendenrendite'), $dividendYield, 'dividend_yield', '%'],
                                ];
                            @endphp
                            <div class="screener-percentile-profile screener-transparent-panel h-full min-h-0 overflow-hidden rounded-xl border sm:col-span-2">
                                <div class="screener-company-slider hidden h-full xl:block" x-data="{ companySlide: 0, slideCount: 3 }">
                                    <div class="flex items-center justify-between border-b border-cyan-300/15 px-3 py-2">
                                        <div>
                                            <p class="text-[9px] font-black uppercase tracking-[.12em] text-cyan-300" x-text="companySlide === 0 ? @js(__('Unternehmen')) : (companySlide === 2 ? @js(__('Fundamentaldaten')) : @js(__('Qualitätsprofil')))"></p>
                                            <p class="mt-0.5 text-[8px] font-bold text-[var(--ak-muted)]" x-show="companySlide === 1">{{ __('Aktuelle Modell- und Risikobewertung') }}</p>
                                            <p class="mt-0.5 text-[8px] font-bold text-[var(--ak-muted)]" x-show="companySlide === 2">{{ __('Die wichtigsten Unternehmenskennzahlen') }}</p>
                                        </div>
                                        <div class="flex items-center gap-1.5">
                                            <button type="button" @click="companySlide = (companySlide - 1 + slideCount) % slideCount" class="grid h-7 w-7 place-items-center rounded-lg border border-cyan-300/20 text-cyan-300 transition hover:bg-cyan-400/10" aria-label="{{ __('Vorherige Ansicht') }}"><x-heroicon-o-chevron-left class="h-3.5 w-3.5" /></button>
                                            <template x-for="dot in slideCount" :key="dot"><button type="button" @click="companySlide = dot - 1" class="h-1.5 w-1.5 rounded-full transition" :class="companySlide === dot - 1 ? 'bg-cyan-300 scale-125' : 'bg-slate-600'" :aria-label="`Slide ${dot}`"></button></template>
                                            <button type="button" @click="companySlide = (companySlide + 1) % slideCount" class="grid h-7 w-7 place-items-center rounded-lg border border-cyan-300/20 text-cyan-300 transition hover:bg-cyan-400/10" aria-label="{{ __('Nächste Ansicht') }}"><x-heroicon-o-chevron-right class="h-3.5 w-3.5" /></button>
                                        </div>
                                    </div>
                                    <div class="h-[calc(100%-3rem)] p-3">
                                        <div x-show="companySlide === 0" x-transition.opacity class="h-full">
                                            <p class="line-clamp-[9] text-[11px] leading-[1.65] text-[var(--ak-muted)]">{{ $businessSummary ?: __('Für dieses Unternehmen ist noch keine Beschreibung verfügbar.') }}</p>
                                        </div>
                                        <div x-cloak x-show="companySlide === 2" x-transition.opacity class="screener-fundamentals-slide grid h-full grid-cols-2 content-center gap-2.5">
                                            @foreach ([
                                                [__('KGV'), $priceEarningsRatio !== null ? number_format($priceEarningsRatio, 1, ',', '.') : '—'],
                                                [__('Dividendenrendite'), $dividendYield !== null ? number_format($dividendYield, 2, ',', '.').' %' : '—'],
                                                [__('Sektorplatz'), is_numeric($stock->sector_rank ?? null) ? '#'.number_format((float) $stock->sector_rank, 0, ',', '.') : '—'],
                                                [__('Prognose 20T'), $return !== null ? (($return > 0 ? '+' : '').number_format($return, 1, ',', '.').' %') : '—'],
                                            ] as [$fundamentalLabel, $fundamentalValue])
                                                <div>
                                                    <small>{{ $fundamentalLabel }}</small>
                                                    <b>{{ $fundamentalValue }}</b>
                                                </div>
                                            @endforeach
                                        </div>
                                        <div x-cloak x-show="companySlide === 1" x-transition.opacity class="grid h-full grid-cols-3 content-center gap-x-3 gap-y-4">
                                            @foreach ([
                                                [__('KI-Qualität'), $rankingScorePercent, $rankingScoreColor, \App\Support\QualityGrade::fromPercent($rankingScorePercent) ?? '—', false],
                                                [__('Konf.'), $rankingConfidencePercent, $rankingConfidenceColor, \App\Support\QualityGrade::fromPercent($rankingConfidencePercent) ?? '—', false],
                                                [__('Hit-Rate'), $rankingHitRatePercent, $rankingHitRateColor, $rankingHitRateAvailable ? \App\Support\QualityGrade::fromPercent($rankingHitRatePercent) : '—', false],
                                                [__('Profitfaktor'), $rankingProfitFactorPercent, $rankingProfitFactorColor, $rankingProfitFactorAvailable ? \App\Support\QualityGrade::fromPercent($rankingProfitFactorPercent) : '—', false],
                                                [__('Stabilität'), $rankingStabilityPercent, $rankingStabilityColor, $rankingStabilityAvailable ? \App\Support\QualityGrade::fromPercent($rankingStabilityPercent) : '—', false],
                                                [__('Risiko'), $rankingRiskPercent ?? 0, $riskDonutColor, \App\Support\QualityGrade::riskLevel($rankingRiskPercent) ?? '—', true],
                                            ] as [$sliderMetricLabel, $sliderMetricValue, $sliderMetricColor, $sliderMetricGrade, $sliderMetricRisk])
                                                <div class="screener-slider-quality-metric">
                                                    <div class="screener-metric-donut {{ $sliderMetricRisk ? 'screener-risk-donut' : '' }}" style="--donut-value: {{ number_format((float) $sliderMetricValue, 2, '.', '') }}%; --donut-color: {{ $sliderMetricColor }}"><span>{{ $sliderMetricGrade }}</span></div>
                                                    <small>{{ $sliderMetricLabel }}</small>
                                                </div>
                                            @endforeach
                                        </div>
                                    </div>
                                </div>
                                <div class="flex items-center justify-between border-b border-cyan-300/15 px-3 py-2">
                                    <p class="text-[9px] font-black uppercase tracking-[.12em] text-cyan-300">{{ __('Perzentilprofil') }}</p>
                                    <span class="text-[8px] font-bold uppercase tracking-[.08em] text-[var(--ak-muted)]">{{ __('Vergleichsgruppen') }}</span>
                                </div>
                                <table class="w-full table-fixed text-left text-[10px]">
                                    <thead class="text-[8px] font-black uppercase tracking-[.08em] text-[var(--ak-muted)]">
                                        <tr><th class="w-[31%] px-3 py-1.5">{{ __('Kennzahl') }}</th><th class="px-1 py-1.5 text-right">{{ __('Wert') }}</th><th class="px-1 py-1.5 text-right">{{ __('Global') }}</th><th class="px-1 py-1.5 text-right">{{ __('Index') }}</th><th class="px-3 py-1.5 text-right">{{ __('Sektor') }}</th></tr>
                                    </thead>
                                    <tbody>
                                        @foreach($percentileRows as [$percentileLabel, $percentileValue, $percentileKey, $percentileSuffix])
                                            <tr class="border-t border-cyan-300/10 odd:bg-slate-500/[.025]">
                                                <td class="truncate px-3 py-1.5 font-bold text-[var(--ak-text)]">{{ $percentileLabel }}</td>
                                                <td class="px-1 py-1.5 text-right font-black text-[var(--ak-text)]">{{ is_numeric($percentileValue) ? number_format((float) $percentileValue, $percentileSuffix === '' ? 2 : 1, ',', '.').$percentileSuffix : '—' }}</td>
                                                @foreach([$percentiles[$percentileKey] ?? null, $indexPercentiles[$percentileKey] ?? null, $sectorPercentiles[$percentileKey] ?? null] as $comparisonPercentile)
                                                    @php
                                                        $percentileColorValue = $comparisonPercentile;
                                                        $percentileTone = ! is_numeric($comparisonPercentile)
                                                            ? 'border-slate-400/20 bg-slate-400/[.05] text-[var(--ak-muted)]'
                                                            : match (true) {
                                                                (float) $percentileColorValue >= 80 => 'border-emerald-400/55 bg-emerald-400/[.24] text-emerald-300 shadow-[0_0_10px_rgba(52,211,153,.12)]',
                                                                (float) $percentileColorValue >= 60 => 'border-lime-400/45 bg-lime-400/[.17] text-lime-300',
                                                                (float) $percentileColorValue >= 40 => 'border-amber-400/45 bg-amber-400/[.16] text-amber-300',
                                                                (float) $percentileColorValue >= 20 => 'border-orange-400/45 bg-orange-400/[.17] text-orange-300',
                                                                default => 'border-rose-400/55 bg-rose-400/[.22] text-rose-300',
                                                            };
                                                        $percentileBand = ! is_numeric($comparisonPercentile) ? 'neutral' : match (true) {
                                                            (float) $percentileColorValue >= 75 => 'green',
                                                            (float) $percentileColorValue >= 50 => 'yellow',
                                                            (float) $percentileColorValue >= 25 => 'orange',
                                                            default => 'red',
                                                        };
                                                    @endphp
                                                    <td class="px-1 py-1.5 text-right last:pr-3"><span data-band="{{ $percentileBand }}" class="screener-percentile-badge inline-flex min-w-9 justify-center rounded-md border px-1 py-0.5 font-black {{ $percentileTone }}">{{ is_numeric($comparisonPercentile) ? 'P'.number_format((float) $comparisonPercentile, 0, ',', '.') : '—' }}</span></td>
                                                @endforeach
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                                <div class="border-t border-cyan-300/15 px-3 py-2.5">
                                    <div class="grid gap-2.5 sm:grid-cols-[minmax(0,1fr)_12rem] sm:items-start">
                                        <details class="group min-w-0">
                                            <summary class="cursor-pointer list-none">
                                                <span class="flex items-center justify-between gap-2"><span class="text-[8px] font-black uppercase tracking-[.12em] text-cyan-300">{{ __('Unternehmen') }}</span>@if($businessSummary)<span class="text-[8px] font-black text-cyan-300/70 group-open:hidden">{{ __('Mehr') }} ↓</span>@endif</span>
                                                <span class="mt-1 block max-h-16 overflow-hidden text-[10px] leading-4 text-[var(--ak-muted)] group-open:hidden">{{ $businessSummary ?: __('Für dieses Unternehmen ist noch keine Beschreibung verfügbar.') }}</span>
                                            </summary>
                                            @if($businessSummary)<p class="mt-1 text-[10px] leading-4 text-[var(--ak-muted)]">{{ $businessSummary }}</p><span class="mt-1 inline-block text-[8px] font-black text-cyan-300/70">{{ __('Weniger') }} ↑</span>@endif
                                        </details>
                                        <div class="grid grid-cols-2 gap-1.5">
                                            <div class="rounded-lg border border-cyan-300/15 bg-cyan-400/[.045] px-2 py-1.5 text-center">
                                                <small class="block text-[7px] font-black uppercase tracking-[.08em] text-[var(--ak-muted)]">{{ __('KGV') }}</small>
                                                <b class="mt-0.5 block text-[10px] font-black tabular-nums text-[var(--ak-text)]">{{ $priceEarningsRatio !== null ? number_format($priceEarningsRatio, 2, ',', '.') : '—' }}</b>
                                            </div>
                                            <div class="rounded-lg border border-emerald-400/15 bg-emerald-400/[.045] px-2 py-1.5 text-center">
                                                <small class="block text-[7px] font-black uppercase tracking-[.06em] text-[var(--ak-muted)]">{{ __('Div.-Rendite') }}</small>
                                                <b class="mt-0.5 block text-[10px] font-black tabular-nums {{ $dividendYield !== null && $dividendYield > 0 ? 'text-emerald-300' : 'text-[var(--ak-text)]' }}">{{ $dividendYield !== null ? number_format($dividendYield, 2, ',', '.').' %' : '—' }}</b>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
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
