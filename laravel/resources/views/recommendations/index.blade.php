<x-app-layout>
    <div class="flex h-[calc(100dvh-89px)] min-h-0 flex-col py-4 text-[var(--ak-text)]">
        <div class="mb-4 flex shrink-0 flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
            <div class="flex items-center gap-3">
                <div class="flex h-11 w-11 items-center justify-center rounded-2xl border border-amber-300/25 bg-amber-300/[.08] text-amber-300 shadow-[0_0_22px_rgba(245,158,11,.08)]">
                    <x-heroicon-o-sparkles class="h-6 w-6" />
                </div>
                <div>
                    <p class="text-[10px] font-black uppercase tracking-[.18em] text-teal-700">{{ __('Datenbasierte Auswahl') }}</p>
                    <h1 class="mt-1 text-2xl font-black tracking-tight">{{ __('Top 3') }}</h1>
                    <p class="mt-1 text-sm text-[var(--ak-muted)]">{{ __('Die drei aktuell stärksten Aktien mit bestandenem Quality Gate – gewichtet nach KI-Score, Modellqualität, Risiko und Renditepotenzial.') }}</p>
                </div>
            </div>

            <div class="flex flex-wrap items-center gap-x-3 gap-y-1 rounded-xl border border-[var(--ak-border)] bg-[var(--ak-card)] px-3 py-2 text-[9px] font-bold uppercase tracking-wide text-[var(--ak-muted)]">
                <span><b class="text-teal-700">40 %</b> {{ __('KI-Score') }}</span>
                <span><b class="text-teal-700">25 %</b> {{ __('Konfidenz') }}</span>
                <span><b class="text-teal-700">20 %</b> {{ __('Risiko') }}</span>
                <span><b class="text-teal-700">15 %</b> {{ __('Rendite') }}</span>
            </div>
        </div>

        @php
            $countryFlags = ['DE' => '🇩🇪', 'US' => '🇺🇸', 'JP' => '🇯🇵', 'CN' => '🇨🇳', 'GB' => '🇬🇧', 'FR' => '🇫🇷', 'CH' => '🇨🇭', 'NL' => '🇳🇱', 'AU' => '🇦🇺', 'CA' => '🇨🇦', 'BR' => '🇧🇷', 'ZA' => '🇿🇦'];
            $hasFilters = $country !== '' || $sector !== '' || $exchangeId > 0;
        @endphp

        <form method="GET" action="{{ route('recommendations.index') }}" class="mb-4 grid shrink-0 gap-2 rounded-2xl border border-[var(--ak-border)] bg-[var(--ak-card)] p-3 shadow-[var(--ak-shadow)] sm:grid-cols-2 lg:grid-cols-[repeat(3,minmax(0,1fr))_auto]">
            <label class="relative">
                <span class="pointer-events-none absolute left-3 top-1.5 z-10 text-[8px] font-black uppercase tracking-[.12em] text-[var(--ak-muted)]">{{ __('Land') }}</span>
                <select name="country" onchange="this.form.submit()" class="ak-input h-11 w-full pb-1 pt-4 text-xs font-bold">
                    <option value="">{{ __('Alle Länder') }}</option>
                    @foreach ($countries as $option)
                        <option value="{{ $option }}" @selected($country === $option)>{{ $countryFlags[$option] ?? '🌐' }} {{ $option }}</option>
                    @endforeach
                </select>
            </label>

            <label class="relative">
                <span class="pointer-events-none absolute left-3 top-1.5 z-10 text-[8px] font-black uppercase tracking-[.12em] text-[var(--ak-muted)]">{{ __('Börsenplatz') }}</span>
                <select name="exchange" onchange="this.form.submit()" class="ak-input h-11 w-full pb-1 pt-4 text-xs font-bold">
                    <option value="">{{ __('Alle Börsenplätze') }}</option>
                    @foreach ($exchanges as $option)
                        <option value="{{ $option->id }}" @selected($exchangeId === (int) $option->id)>{{ $countryFlags[$option->country] ?? '🌐' }} {{ $option->name }} · {{ $option->code }} ({{ $option->stocks_count }})</option>
                    @endforeach
                </select>
            </label>

            <label class="relative sm:col-span-2 lg:col-span-1">
                <span class="pointer-events-none absolute left-3 top-1.5 z-10 text-[8px] font-black uppercase tracking-[.12em] text-[var(--ak-muted)]">{{ __('Sektor') }}</span>
                <select name="sector" onchange="this.form.submit()" class="ak-input h-11 w-full pb-1 pt-4 text-xs font-bold">
                    <option value="">{{ __('Alle Sektoren') }}</option>
                    @foreach ($sectors as $option)
                        <option value="{{ $option }}" @selected($sector === $option)>{{ $option }}</option>
                    @endforeach
                </select>
            </label>

            <a href="{{ route('recommendations.index') }}" class="inline-flex h-11 items-center justify-center gap-2 rounded-xl border border-[var(--ak-border)] px-4 text-xs font-black text-[var(--ak-muted)] transition hover:border-teal-500/35 hover:bg-teal-500/10 hover:text-teal-700 {{ $hasFilters ? '' : 'pointer-events-none opacity-40' }}">
                <x-heroicon-o-arrow-path class="h-4 w-4" />{{ __('Zurücksetzen') }}
            </a>
        </form>

        <section class="grid min-h-0 flex-1 gap-4 lg:grid-cols-3">
            @forelse ($recommendations as $recommendation)
                @php
                    $rank = (int) $recommendation->selection_rank;
                    $signal = strtoupper((string) ($recommendation->personalized_signal ?: 'HOLD'));
                    $signalClass = match ($signal) {
                        'BUY' => 'border-[#2b8f7b] bg-[#197864] text-white',
                        'WATCH' => 'border-[#789545] bg-[#657f39] text-white',
                        'SELL' => 'border-[#bd5b6c] bg-[#a94759] text-white',
                        default => 'border-[#bd8737] bg-[#a97429] text-white',
                    };
                    $rankClass = match ($rank) {
                        1 => 'border-amber-300/45 bg-amber-300/15 text-amber-300',
                        2 => 'border-slate-300/30 bg-slate-300/10 text-slate-300',
                        default => 'border-orange-300/30 bg-orange-300/10 text-orange-300',
                    };
                    $recommendationWatchlistIds = $watchlistMemberships->get((int) $recommendation->instrument_id, collect());
                    $isWatched = $recommendationWatchlistIds->isNotEmpty();
                    $modelQualityColor = match (true) {
                        $recommendation->confidence_percent < 40 => '#e35f72',
                        $recommendation->confidence_percent < 60 => '#f28a45',
                        $recommendation->confidence_percent < 75 => '#e5b643',
                        $recommendation->confidence_percent < 88 => '#91c94b',
                        default => '#22c58b',
                    };
                    $riskColor = match (true) {
                        $recommendation->risk_percent < 10 => '#22c58b',
                        $recommendation->risk_percent < 20 => '#91c94b',
                        $recommendation->risk_percent < 30 => '#e5b643',
                        $recommendation->risk_percent < 40 => '#f28a45',
                        default => '#e35f72',
                    };
                    $exchangeTone = match (strtoupper((string) $recommendation->exchange_code)) {
                        'XETRA', 'XETR', 'FRA', 'FWB' => 'border-amber-400/25 bg-amber-400/10 text-amber-400',
                        'NASDAQ', 'NMS', 'NGM', 'NCM' => 'border-sky-400/25 bg-sky-400/10 text-sky-400',
                        'NYSE', 'NYQ', 'ASE' => 'border-blue-400/25 bg-blue-400/10 text-blue-400',
                        'LSE', 'LON' => 'border-violet-400/25 bg-violet-400/10 text-violet-400',
                        'SIX', 'SWX' => 'border-rose-400/25 bg-rose-400/10 text-rose-400',
                        'EURONEXT', 'PAR', 'AMS', 'BRU' => 'border-cyan-400/25 bg-cyan-400/10 text-cyan-400',
                        'TSX', 'TOR' => 'border-red-400/25 bg-red-400/10 text-red-400',
                        'ASX', 'ASX.AX' => 'border-orange-400/25 bg-orange-400/10 text-orange-400',
                        'TSE', 'TYO' => 'border-fuchsia-400/25 bg-fuchsia-400/10 text-fuchsia-400',
                        'JSE', 'JNB' => 'border-lime-400/25 bg-lime-400/10 text-lime-400',
                        default => 'border-teal-500/20 bg-teal-500/[.07] text-teal-600',
                    };
                    $modelTierCode = $recommendation->model_tier_code ?: 'unqualified';
                    $modelTierName = $recommendation->model_tier_name ?: __('Nicht qualifiziert');
                    $modelTierClass = match ($modelTierCode) {
                        'top' => 'ak-model-tier-top',
                        'strong' => 'ak-model-tier-strong',
                        'solid' => 'ak-model-tier-solid',
                        'test' => 'ak-model-tier-test',
                        default => 'ak-model-tier-unqualified',
                    };
                    $modelRankingPercent = is_numeric($recommendation->model_quality_score)
                        ? min(100, max(0, (float) $recommendation->model_quality_score * ((float) $recommendation->model_quality_score <= 1 ? 100 : 1)))
                        : null;
                @endphp

                <article class="flex min-w-0 flex-col overflow-hidden rounded-2xl border {{ $rank === 1 ? 'border-amber-400/35' : 'border-[var(--ak-border)]' }} bg-[var(--ak-card-strong)] shadow-[var(--ak-shadow)] backdrop-blur-xl">
                    <div class="flex h-[112px] shrink-0 items-start justify-between gap-3 border-b border-[var(--ak-border)] p-3">
                        <div class="flex min-w-0 items-center gap-3">
                            <div class="relative flex h-10 w-10 shrink-0 items-center justify-center overflow-hidden rounded-xl border border-[var(--ak-border)] bg-white">
                                <span class="text-[10px] font-black text-teal-700">{{ strtoupper(substr($recommendation->symbol, 0, 2)) }}</span>
                                <img src="{{ route('stocks.icon', $recommendation->instrument_id) }}" alt="" class="absolute inset-1 h-8 w-8 object-contain" loading="eager" onerror="this.remove()">
                            </div>
                            <div class="min-w-0">
                                <div class="flex items-center gap-2">
                                    <h2 class="truncate text-lg font-black text-teal-700">{{ $recommendation->symbol }}</h2>
                                    <span class="inline-flex h-7 items-center rounded-md border px-2 text-[9px] font-black {{ $signalClass }}">{{ $signal }}</span>
                                </div>
                                <p class="mt-0.5 truncate text-xs font-bold text-[var(--ak-text)]">{{ $recommendation->name }}</p>
                                <div class="mt-1 flex min-w-0 flex-wrap items-center gap-1.5 text-[9px] font-bold text-[var(--ak-muted)]">
                                    <span class="inline-flex items-center gap-1 rounded-md border border-[var(--ak-border)] bg-[var(--ak-surface-muted)] px-1.5 py-0.5" title="{{ __('Land') }}">
                                        <span>{{ $countryFlags[$recommendation->country] ?? '🌐' }}</span>
                                        {{ $recommendation->country ?: '—' }}
                                    </span>
                                    <span class="inline-flex min-w-0 items-center gap-1 rounded-md border px-1.5 py-0.5 {{ $exchangeTone }}" title="{{ __('Börsenplatz') }}">
                                        <x-heroicon-o-building-library class="h-3 w-3 shrink-0" />
                                        <span class="truncate">{{ $recommendation->exchange_code ?: __('Keine Exchange') }}@if ($recommendation->exchange_name && $recommendation->exchange_name !== $recommendation->exchange_code) · {{ $recommendation->exchange_name }}@endif</span>
                                    </span>
                                    <span class="inline-flex max-w-full items-center gap-1 truncate"><x-sector-icon :sector="$recommendation->sector" class="h-3 w-3 shrink-0 text-teal-500" /><span class="truncate">{{ $recommendation->sector ?: '—' }}</span></span>
                                </div>
                            </div>
                        </div>
                        <div class="flex shrink-0 items-center gap-2">
                            @if ($userWatchlists->count() === 1)
                                @php $singleWatchlist = $userWatchlists->first(); @endphp
                                <form method="POST" action="{{ route('watchlists.items.toggle', [$singleWatchlist->id, $recommendation->instrument_id]) }}">
                                    @csrf
                                    <input type="hidden" name="prediction_id" value="{{ $recommendation->prediction_id }}">
                                    <button type="submit" class="inline-flex h-9 w-9 items-center justify-center rounded-xl border border-[var(--ak-border)] bg-[var(--ak-surface-muted)] transition hover:border-amber-400/35 hover:bg-amber-300/10 {{ $isWatched ? 'text-amber-400' : 'text-[var(--ak-muted)] hover:text-amber-400' }}" title="{{ $isWatched ? __('Aus Watchlist entfernen') : __('Zur Watchlist hinzufügen') }}">
                                        @if ($isWatched)<x-heroicon-s-star class="h-5 w-5" />@else<x-heroicon-o-star class="h-5 w-5" />@endif
                                    </button>
                                </form>
                            @elseif ($userWatchlists->count() > 1)
                                <div x-data="{ open: false }" class="relative">
                                    <button type="button" @click="open = !open" @click.outside="open = false" class="inline-flex h-9 w-9 items-center justify-center rounded-xl border border-[var(--ak-border)] bg-[var(--ak-surface-muted)] transition hover:border-amber-400/35 hover:bg-amber-300/10 {{ $isWatched ? 'text-amber-400' : 'text-[var(--ak-muted)] hover:text-amber-400' }}" title="{{ __('Watchlist auswählen') }}">
                                        @if ($isWatched)<x-heroicon-s-star class="h-5 w-5" />@else<x-heroicon-o-star class="h-5 w-5" />@endif
                                    </button>
                                    <div x-cloak x-show="open" x-transition.origin.top.right class="absolute right-0 top-11 z-40 w-52 overflow-hidden rounded-xl border border-[var(--ak-border)] bg-[var(--ak-card-strong)] p-2 shadow-2xl">
                                        <p class="px-2 pb-2 text-[9px] font-black uppercase tracking-wide text-[var(--ak-muted)]">{{ __('Watchlist auswählen') }}</p>
                                        @foreach ($userWatchlists as $watchlist)
                                            @php $isInWatchlist = $recommendationWatchlistIds->contains((int) $watchlist->id); @endphp
                                            <form method="POST" action="{{ route('watchlists.items.toggle', [$watchlist->id, $recommendation->instrument_id]) }}">
                                                @csrf
                                                <input type="hidden" name="prediction_id" value="{{ $recommendation->prediction_id }}">
                                                <button type="submit" class="flex w-full items-center justify-between gap-2 rounded-lg px-2 py-2 text-left text-xs font-bold text-[var(--ak-text)] transition hover:bg-teal-500/10">
                                                    <span class="truncate">{{ $watchlist->name }}</span>
                                                    @if ($isInWatchlist)
                                                        <x-heroicon-s-star class="h-4 w-4 shrink-0 text-amber-400" />
                                                    @else
                                                        <x-heroicon-o-plus class="h-4 w-4 shrink-0 text-[var(--ak-muted)]" />
                                                    @endif
                                                </button>
                                            </form>
                                        @endforeach
                                    </div>
                                </div>
                            @else
                                <a href="{{ route('watchlists.index') }}" class="inline-flex h-9 w-9 items-center justify-center rounded-xl border border-[var(--ak-border)] bg-[var(--ak-surface-muted)] text-[var(--ak-muted)] transition hover:border-amber-400/35 hover:bg-amber-300/10 hover:text-amber-400" title="{{ __('Zuerst Watchlist erstellen') }}">
                                    <x-heroicon-o-star class="h-5 w-5" />
                                </a>
                            @endif
                            <span class="inline-flex h-9 min-w-9 items-center justify-center rounded-xl border text-sm font-black {{ $rankClass }}">#{{ $rank }}</span>
                        </div>
                    </div>

                    <div class="mt-1 grid h-[84px] shrink-0 grid-cols-3 gap-2 px-3 pb-3 pt-3">
                        <div class="min-h-0 overflow-hidden rounded-xl border border-emerald-400/20 bg-emerald-400/[.06] p-2 text-right">
                            <p class="flex items-center justify-end gap-1.5 text-[9px] font-black uppercase tracking-wide text-[var(--ak-muted)]">
                                <span data-live-time-symbol="{{ $recommendation->symbol }}">
                                    {{ $recommendation->current_quote_time
                                        ? \Illuminate\Support\Carbon::parse($recommendation->current_quote_time)->timezone('Europe/Berlin')->format('H:i:s')
                                        : __('Kurszeit') }}
                                </span>
                                <span class="inline-block h-1.5 w-1.5 animate-pulse rounded-full bg-emerald-400 shadow-[0_0_7px_rgba(52,211,153,.65)]" aria-label="{{ __('Live') }}"></span>
                            </p>
                            <p
                                data-live-symbol="{{ $recommendation->symbol }}"
                                data-live-currency="{{ $recommendation->currency ?: 'EUR' }}"
                                data-live-decimals="2"
                                class="mt-0.5 truncate text-lg font-black tabular-nums text-[var(--ak-text)]"
                            >{{ is_numeric($recommendation->current_price) ? number_format((float) $recommendation->current_price, 2, ',', '.').' '.($recommendation->currency ?: 'EUR') : '—' }}</p>
                        </div>
                        <div class="min-h-0 overflow-hidden rounded-xl bg-[var(--ak-surface-muted)] p-2 text-right">
                            <p class="text-[9px] font-black uppercase tracking-wide text-[var(--ak-muted)]">{{ __('Zielkurs 20 Tage') }}</p>
                            <p class="mt-0.5 truncate text-lg font-black tabular-nums text-[var(--ak-text)]">
                                {{ is_numeric($recommendation->predicted_price_20d) ? number_format((float) $recommendation->predicted_price_20d, 2, ',', '.').' '.($recommendation->currency ?: 'EUR') : '—' }}
                            </p>
                        </div>
                        <div class="min-h-0 overflow-hidden rounded-xl bg-[var(--ak-surface-muted)] p-2 text-right">
                            <p class="text-[9px] font-black uppercase tracking-wide text-[var(--ak-muted)]">{{ __('Rendite Prognose 20 Tage') }}</p>
                            <p class="mt-0.5 text-lg font-black tabular-nums {{ $recommendation->expected_return_20d >= 0 ? 'text-emerald-400' : 'text-rose-400' }}">{{ $recommendation->expected_return_20d >= 0 ? '+' : '' }}{{ number_format($recommendation->expected_return_20d, 2, ',', '.') }} %</p>
                        </div>
                    </div>

                    <div class="mx-3 mb-2 mt-1 flex min-h-[108px] flex-1 flex-col overflow-hidden rounded-xl border border-[var(--ak-border)] bg-[var(--ak-surface-muted)]">
                        <div class="flex shrink-0 items-center justify-between px-3 py-1.5">
                            <p class="text-[9px] font-black uppercase tracking-wide text-[var(--ak-muted)]">{{ __('Kursverlauf · 32 Tage') }}</p>
                            <span class="text-[9px] font-bold text-teal-700">{{ __('Tageskerzen') }}</span>
                        </div>
                        <div id="recommendation-chart-{{ $recommendation->instrument_id }}" class="relative min-h-[92px] w-full flex-1 border-t border-[var(--ak-border)] bg-[color-mix(in_srgb,var(--ak-card)_72%,transparent)]" aria-label="{{ __('Kurschart für :symbol', ['symbol' => $recommendation->symbol]) }}">
                            <span data-chart-placeholder class="absolute inset-0 flex items-center justify-center gap-2 text-[9px] font-bold text-[var(--ak-muted)]">
                                <span class="h-3 w-3 animate-spin rounded-full border-2 border-teal-500/25 border-t-teal-600"></span>
                                {{ __('Kursdaten werden geladen') }}
                            </span>
                        </div>
                    </div>

                    <div class="mx-3 mb-2 flex h-[52px] shrink-0 items-center justify-between gap-3 rounded-xl border border-[var(--ak-border)] bg-[var(--ak-surface-muted)] px-3 py-2">
                        <div class="min-w-0">
                            <p class="text-[9px] font-black uppercase tracking-wide text-[var(--ak-muted)]">{{ __('Modellranking') }}</p>
                            <p class="mt-1 truncate text-sm font-black text-[var(--ak-text)]">{{ $recommendation->model_alias ?: '—' }}</p>
                        </div>
                        <div class="flex shrink-0 items-center gap-2">
                            <span class="ak-model-tier {{ $modelTierClass }}">{{ $modelTierName }}</span>
                            @if ($modelRankingPercent !== null)
                                <span class="text-xs font-black tabular-nums text-[var(--ak-text)]">{{ number_format($modelRankingPercent, 0, ',', '.') }} %</span>
                            @endif
                        </div>
                    </div>

                    <div class="grid h-[68px] shrink-0 grid-cols-3 gap-2 px-3 pb-2">
                        <div class="h-full min-w-0 rounded-xl border border-[var(--ak-border)] bg-[var(--ak-surface-muted)] p-2 shadow-sm">
                            <div class="mb-1.5 flex items-end justify-between gap-1">
                                <span class="truncate text-[9px] font-black uppercase tracking-wide text-[var(--ak-muted)]">{{ __('KI-Score') }}</span>
                                <span class="shrink-0 text-[10px] font-black tabular-nums">{{ number_format($recommendation->score_10, 1, ',', '.') }}<small class="ml-0.5 text-[7px] text-[var(--ak-muted)]">/10</small></span>
                            </div>
                            <x-dashboard.score-stripes :percent="$recommendation->score_percent" />
                        </div>
                        <div class="flex h-full min-w-0 items-center justify-between gap-2 rounded-xl border border-[var(--ak-border)] bg-[var(--ak-surface-muted)] p-2 shadow-sm">
                            <span class="min-w-0 text-[9px] font-black uppercase leading-[1.15] tracking-wide text-[var(--ak-muted)]">
                                <span class="block">{{ __('Modell') }}</span>
                                <span class="block">{{ __('Qualität') }}</span>
                            </span>
                            <div class="ak-prediction-donut" style="--value: {{ $recommendation->confidence_percent }}%; --color: {{ $modelQualityColor }}" role="meter" aria-label="{{ __('Modellqualität') }}" aria-valuemin="0" aria-valuemax="100" aria-valuenow="{{ round($recommendation->confidence_percent) }}">
                                <span>{{ number_format($recommendation->confidence_percent, 0, ',', '.') }}<small>%</small></span>
                            </div>
                        </div>
                        <div class="flex h-full min-w-0 items-center justify-between gap-2 rounded-xl border border-[var(--ak-border)] bg-[var(--ak-surface-muted)] p-2 shadow-sm">
                            <span class="min-w-0 text-[9px] font-black uppercase tracking-wide text-[var(--ak-muted)]">{{ __('Risiko') }}</span>
                            <div class="ak-prediction-donut" style="--value: {{ $recommendation->risk_percent }}%; --color: {{ $riskColor }}" role="meter" aria-label="{{ __('Risiko') }}" aria-valuemin="0" aria-valuemax="100" aria-valuenow="{{ round($recommendation->risk_percent) }}">
                                <span>{{ number_format($recommendation->risk_percent, 0, ',', '.') }}<small>%</small></span>
                            </div>
                        </div>
                    </div>

                    <div class="mt-auto flex h-[48px] shrink-0 items-center justify-end border-t border-[var(--ak-border)] px-3 py-2">
                        <a href="{{ route('stocks.show', ['symbol' => $recommendation->symbol, 'prediction' => $recommendation->prediction_id, 'return_to' => request()->getRequestUri()]) }}" class="inline-flex h-8 shrink-0 items-center gap-2 rounded-lg bg-teal-700 px-3 text-xs font-black text-white transition hover:bg-teal-600">
                            {{ __('Details') }}<x-heroicon-o-arrow-right class="h-4 w-4" />
                        </a>
                    </div>
                </article>
            @empty
                <div class="col-span-full grid place-items-center rounded-2xl border border-[var(--ak-border)] bg-[var(--ak-card)] p-10 text-center">
                    <div>
                        <x-heroicon-o-sparkles class="mx-auto h-10 w-10 text-[var(--ak-muted)]" />
                        <h2 class="mt-4 font-black">{{ __('Noch keine Empfehlungen verfügbar') }}</h2>
                        <p class="mt-2 text-sm text-[var(--ak-muted)]">{{ __('Sobald vollständige Prognosen vorliegen, erscheinen hier die drei bestbewerteten Aktien.') }}</p>
                    </div>
                </div>
            @endforelse
        </section>

        <p class="mt-3 shrink-0 text-center text-[10px] text-[var(--ak-muted)]">{{ __('Die Bewertung dient der Recherche und stellt keine Anlageberatung dar.') }}</p>
    </div>

    @php
        $recommendationCharts = $recommendations->mapWithKeys(function ($recommendation) {
            return [
                (string) $recommendation->instrument_id => [
                    'symbol' => $recommendation->symbol,
                    'currency' => $recommendation->currency ?: 'EUR',
                    'candles' => $recommendation->candles,
                    'forecast_return' => $recommendation->expected_return_20d,
                    'signal_transition' => $recommendation->last_signal_transition,
                    'data_url' => route('stocks.chart-data', ['symbol' => $recommendation->symbol]),
                ],
            ];
        })->all();
    @endphp

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            if (!window.ApexCharts) return;

            const charts = @json($recommendationCharts);
            const light = document.documentElement.dataset.theme === 'light';
            const addTradingDays = (timestamp, tradingDays) => {
                const target = new Date(timestamp);
                let remaining = tradingDays;

                while (remaining > 0) {
                    target.setDate(target.getDate() + 1);
                    if (target.getDay() !== 0 && target.getDay() !== 6) remaining -= 1;
                }

                return target.getTime();
            };
            const normalizeCandles = candles => {
                const unique = new Map();

                (Array.isArray(candles) ? candles : []).forEach(candle => {
                    const timestamp = new Date(candle?.x).getTime();
                    const values = Array.isArray(candle?.y) ? candle.y.slice(0, 4).map(Number) : [];
                    if (!Number.isFinite(timestamp) || values.length !== 4 || values.some(value => !Number.isFinite(value))) return;
                    const tradingDay = new Date(timestamp).toISOString().slice(0, 10);
                    unique.set(tradingDay, { x: timestamp, y: values });
                });

                return [...unique.values()].sort((left, right) => left.x - right.x);
            };

            Object.entries(charts).forEach(async ([instrumentId, stock]) => {
                const element = document.querySelector(`#recommendation-chart-${instrumentId}`);
                if (!element) return;

                let candles = normalizeCandles(stock.candles);
                if (candles.length < 10 && stock.data_url) {
                    try {
                        const response = await fetch(stock.data_url, {
                            headers: { Accept: 'application/json' },
                            credentials: 'same-origin',
                        });
                        if (response.ok) {
                            const payload = await response.json();
                            const loadedCandles = normalizeCandles(payload.candles);
                            if (loadedCandles.length > candles.length) candles = loadedCandles;
                        }
                    } catch (error) {
                        console.warn(`Chartdaten für ${stock.symbol} konnten nicht geladen werden.`, error);
                    }
                }

                const placeholder = element.querySelector('[data-chart-placeholder]');
                if (candles.length === 0) {
                    if (placeholder) {
                        placeholder.innerHTML = @json(__('Keine Kursdaten verfügbar'));
                    }
                    return;
                }
                placeholder?.remove();

                const lastCandle = candles[candles.length - 1];
                const lastTimestamp = lastCandle.x;
                const lastClose = lastCandle.y[3];
                const forecastReturn = Number(stock.forecast_return);
                const forecastTarget = Number.isFinite(forecastReturn)
                    ? lastClose * (1 + (forecastReturn / 100))
                    : null;
                const targetTimestamp = addTradingDays(lastTimestamp, 20);
                const positiveForecast = Number.isFinite(forecastTarget) && forecastTarget >= lastClose;
                const forecastColor = positiveForecast ? '#14b8a6' : '#e56b75';
                const values = candles
                    .flatMap(candle => candle.y);
                if (Number.isFinite(forecastTarget)) values.push(forecastTarget);
                const minimum = Math.min(...values);
                const maximum = Math.max(...values);
                const padding = Math.max((maximum - minimum) * 0.08, maximum * 0.005);
                const yMin = minimum - padding;
                const yMax = maximum + padding;
                const xMin = candles[0].x;
                const xMax = targetTimestamp;

                element.__aktienkiChart?.destroy?.();
                element.replaceChildren();
                const miniChart = new ApexCharts(element, {
                    chart: {
                        type: 'candlestick',
                        height: element.clientHeight || 180,
                        background: 'transparent',
                        toolbar: { show: false },
                        zoom: { enabled: false, allowMouseWheelZoom: false },
                        selection: { enabled: false },
                        pan: { enabled: false },
                        animations: { enabled: false },
                        parentHeightOffset: 0,
                    },
                    series: [{
                        name: stock.symbol,
                        data: candles,
                    }],
                    plotOptions: {
                        candlestick: {
                            colors: { upward: '#20c9a0', downward: '#ee6678' },
                            wick: { useFillColor: false },
                        },
                    },
                    stroke: { width: 1 },
                    fill: { opacity: 1 },
                    dataLabels: { enabled: false },
                    states: {
                        hover: { filter: { type: 'none' } },
                        active: { filter: { type: 'none' } },
                    },
                    grid: {
                        borderColor: light ? 'rgba(51,65,85,.11)' : 'rgba(148,163,184,.10)',
                        strokeDashArray: 4,
                        padding: { left: 4, right: 4, top: 4, bottom: 10 },
                    },
                    xaxis: {
                        type: 'datetime',
                        min: xMin,
                        max: xMax,
                        labels: { show: false },
                        axisBorder: { show: false },
                        axisTicks: { show: false },
                        crosshairs: { show: false },
                        tooltip: { enabled: false },
                    },
                    yaxis: {
                        opposite: true,
                        min: yMin,
                        max: yMax,
                        forceNiceScale: false,
                        decimalsInFloat: 2,
                        tickAmount: 3,
                        labels: {
                            minWidth: 42,
                            maxWidth: 50,
                            formatter: value => Number(value).toFixed(2),
                            style: { colors: [light ? '#64748b' : '#94a3b8'], fontSize: '8px' },
                        },
                    },
                    tooltip: {
                        theme: light ? 'light' : 'dark',
                        x: { format: 'dd.MM.yyyy' },
                        y: {
                            formatter: value => Number.isFinite(Number(value))
                                ? `${Number(value).toFixed(2)} ${stock.currency || 'USD'}`
                                : '—',
                        },
                    },
                    theme: { mode: light ? 'light' : 'dark' },
                });
                element.__aktienkiChart = miniChart;

                miniChart.render().then(() => {
                    if (!Number.isFinite(forecastTarget)) return;

                    const svg = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
                    const width = element.clientWidth;
                    const height = element.clientHeight;
                    const left = 10;
                    const right = 56;
                    const top = 10;
                    const bottom = 20;
                    const plotWidth = Math.max(1, width - left - right);
                    const plotHeight = Math.max(1, height - top - bottom);
                    const toX = timestamp => left + ((timestamp - xMin) / (xMax - xMin)) * plotWidth;
                    const toY = price => top + plotHeight - ((price - yMin) / (yMax - yMin)) * plotHeight;
                    const patternId = `recommendation-forecast-${instrumentId}`;

                    svg.setAttribute('viewBox', `0 0 ${width} ${height}`);
                    svg.setAttribute('aria-hidden', 'true');
                    svg.classList.add('pointer-events-none', 'absolute', 'inset-0', 'z-10', 'h-full', 'w-full');
                    svg.innerHTML = `
                        <defs>
                            <pattern id="${patternId}" width="7" height="7" patternUnits="userSpaceOnUse" patternTransform="rotate(35)">
                                <line x1="0" y1="0" x2="0" y2="7" stroke="${forecastColor}" stroke-width="1" stroke-opacity=".48"></line>
                            </pattern>
                        </defs>
                        <polygon
                            points="${toX(lastTimestamp)},${toY(lastClose)} ${toX(targetTimestamp)},${toY(Math.max(lastClose, forecastTarget))} ${toX(targetTimestamp)},${toY(Math.min(lastClose, forecastTarget))}"
                            fill="url(#${patternId})"
                            stroke="${forecastColor}"
                            stroke-width="1.3"
                            stroke-dasharray="5 5"
                            stroke-opacity=".78"
                            stroke-linejoin="round"
                            vector-effect="non-scaling-stroke"
                        ></polygon>
                    `;
                    const transition = stock.signal_transition;
                    const transitionTimestamp = Number(transition?.x);
                    if (Number.isFinite(transitionTimestamp) && transitionTimestamp >= xMin && transitionTimestamp <= xMax) {
                        const transitionX = toX(transitionTimestamp);
                        const transitionColor = '#f59e0b';
                        const transitionLine = document.createElementNS('http://www.w3.org/2000/svg', 'line');
                        transitionLine.setAttribute('x1', transitionX);
                        transitionLine.setAttribute('x2', transitionX);
                        transitionLine.setAttribute('y1', top);
                        transitionLine.setAttribute('y2', top + plotHeight);
                        transitionLine.setAttribute('stroke', transitionColor);
                        transitionLine.setAttribute('stroke-width', '1.2');
                        transitionLine.setAttribute('stroke-dasharray', '3 4');
                        transitionLine.setAttribute('stroke-opacity', '.72');
                        svg.appendChild(transitionLine);

                        const transitionText = `${transition.from} → ${transition.to}`;
                        const badgeWidth = Math.max(48, transitionText.length * 4.8 + 10);
                        const badgeOnLeft = transitionX + 7 + badgeWidth > left + plotWidth;
                        const badgeX = badgeOnLeft ? transitionX - badgeWidth - 7 : transitionX + 7;
                        const badgeY = top + 2;
                        const transitionBadge = document.createElementNS('http://www.w3.org/2000/svg', 'rect');
                        transitionBadge.setAttribute('x', Math.max(left, badgeX));
                        transitionBadge.setAttribute('y', badgeY);
                        transitionBadge.setAttribute('width', badgeWidth);
                        transitionBadge.setAttribute('height', '14');
                        transitionBadge.setAttribute('rx', '4');
                        transitionBadge.setAttribute('fill', transitionColor);
                        transitionBadge.setAttribute('fill-opacity', '.16');
                        transitionBadge.setAttribute('stroke', transitionColor);
                        transitionBadge.setAttribute('stroke-opacity', '.55');
                        svg.appendChild(transitionBadge);

                        const transitionLabel = document.createElementNS('http://www.w3.org/2000/svg', 'text');
                        transitionLabel.setAttribute('x', Math.max(left, badgeX) + badgeWidth / 2);
                        transitionLabel.setAttribute('y', badgeY + 10);
                        transitionLabel.setAttribute('fill', transitionColor);
                        transitionLabel.setAttribute('font-size', '7');
                        transitionLabel.setAttribute('font-weight', '800');
                        transitionLabel.setAttribute('text-anchor', 'middle');
                        transitionLabel.textContent = transitionText;
                        svg.appendChild(transitionLabel);
                    }
                    element.appendChild(svg);
                });
            });
        });
    </script>
</x-app-layout>
